<?php

namespace App\Filament\Pages;

use App\Models\LogisticsEvent;
use App\Models\Order;
use App\Services\Logistics\LogisticsService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\WithPagination;

class FulfillmentHubPage extends Page
{
    use WithPagination;

    protected string $view = 'filament.pages.fulfillment-hub-page';
    protected Width | string | null $maxWidth = 'full';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-truck';
    protected static string | \UnitEnum | null $navigationGroup = 'Fulfillment';
    protected static ?string $navigationLabel = 'Fulfillment Hub';
    protected static ?int $navigationSort = 10;
    protected static ?string $title = 'Logistics & Fulfillment Hub';
    protected static ?string $slug = 'fulfillment-hub';

    // Active Queue Tab
    // 'ready_pick' | 'packing' | 'ready_dispatch' | 'in_transit' | 'exceptions' | 'cod_settlements'
    public string $activeTab = 'ready_pick';

    // Search & Filter State
    public string $searchQuery = '';
    public ?string $zoneFilter = null;       // 'kathmandu_valley' | 'outside_valley'
    public ?string $paymentFilter = null;    // 'cod' | 'prepaid'
    public ?string $courierFilter = null;    // 'pathao' | 'ncm' | 'in_house' | 'manual'

    // Picking Modal State
    public ?int $pickingOrderId = null;
    public array $pickedChecklist = [];

    // Packing Modal State
    public ?int $packingOrderId = null;
    public string $packingNotes = '';
    public string $packageWeight = '0.5 kg';
    public int $packageBoxCount = 1;

    // Dispatch Modal State
    public ?int $dispatchingOrderId = null;
    public string $selectedCourier = 'pathao';
    public string $manualTrackingNumber = '';
    public string $manualCourierName = 'Pathao Courier';
    public string $dispatchNotes = '';

    // Exception Modal State
    public ?int $exceptionOrderId = null;
    public string $exceptionReason = 'customer_unreachable';
    public string $exceptionNotes = '';
    public string $exceptionAction = 'retry'; // 'retry' | 'fail' | 'return_stock'

    // COD Settlement Modal State
    public ?int $settlingOrderId = null;
    public float $courierFee = 150.00;
    public string $settlementBatchRef = '';
    public string $settlementDate = '';

    // Dispatch Manifest Modal
    public bool $showManifestModal = false;
    public string $manifestCourier = 'all';

    public function mount(): void
    {
        if (request()->query('tab')) {
            $this->activeTab = request()->query('tab');
        }
        $this->settlementDate = date('Y-m-d');
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function updatedSearchQuery(): void
    {
        $this->resetPage();
    }

    public function updatedZoneFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPaymentFilter(): void
    {
        $this->resetPage();
    }

    public function updatedCourierFilter(): void
    {
        $this->resetPage();
    }

    // ==========================================
    // 1. PICKING WORKFLOW
    // ==========================================

    public function openPickingModal(int $orderId): void
    {
        $this->pickingOrderId = $orderId;
        $order = Order::with('items')->find($orderId);
        $this->pickedChecklist = [];
        if ($order) {
            foreach ($order->items as $item) {
                $this->pickedChecklist[$item->id] = true;
            }
        }
    }

    public function closePickingModal(): void
    {
        $this->pickingOrderId = null;
        $this->pickedChecklist = [];
    }

    public function confirmPicking(int $orderId): void
    {
        $order = Order::find($orderId);
        if (!$order) return;

        DB::transaction(function () use ($order) {
            $order->status = Order::STATUS_PACKING;
            $userName = auth()->user()?->name ?? 'Staff';
            $note = "[Picked & Verified] All items verified by {$userName} at " . now()->format('Y-m-d H:i');
            $order->internal_notes = ($order->internal_notes ? $order->internal_notes . "\n" : "") . $note;
            $order->save();

            LogisticsEvent::create([
                'order_id' => $order->id,
                'provider' => 'internal',
                'external_order_id' => (string) $order->order_number,
                'event' => 'order_picked',
                'status' => 'Picked',
                'payload' => [
                    'picked_by' => $userName,
                    'timestamp' => now()->toIso8601String(),
                ],
                'processing_status' => 'processed',
                'idempotency_key' => 'pick_' . $order->id . '_' . time(),
                'received_at' => now(),
                'processed_at' => now(),
            ]);
        });

        Notification::make()
            ->title('Picking Completed')
            ->body("Order #{$order->order_number} marked as Picked & moved to Packing Queue.")
            ->success()
            ->send();

        $this->closePickingModal();
    }

    // ==========================================
    // 2. PACKING WORKFLOW
    // ==========================================

    public function openPackingModal(int $orderId): void
    {
        $this->packingOrderId = $orderId;
        $this->packingNotes = '';
        $this->packageWeight = '0.5 kg';
        $this->packageBoxCount = 1;
    }

    public function closePackingModal(): void
    {
        $this->packingOrderId = null;
    }

    public function confirmPacking(int $orderId): void
    {
        $order = Order::find($orderId);
        if (!$order) return;

        DB::transaction(function () use ($order) {
            $order->status = Order::STATUS_READY_FOR_DELIVERY;
            $userName = auth()->user()?->name ?? 'Staff';
            $note = "[Packed & Boxed] Packed by {$userName} (Boxes: {$this->packageBoxCount}, Weight: {$this->packageWeight})" . ($this->packingNotes ? " Notes: {$this->packingNotes}" : "");
            $order->internal_notes = ($order->internal_notes ? $order->internal_notes . "\n" : "") . $note;
            $order->save();

            LogisticsEvent::create([
                'order_id' => $order->id,
                'provider' => 'internal',
                'external_order_id' => (string) $order->order_number,
                'event' => 'order_packed',
                'status' => 'Packed',
                'payload' => [
                    'packed_by' => $userName,
                    'boxes' => $this->packageBoxCount,
                    'weight' => $this->packageWeight,
                    'notes' => $this->packingNotes,
                ],
                'processing_status' => 'processed',
                'idempotency_key' => 'pack_' . $order->id . '_' . time(),
                'received_at' => now(),
                'processed_at' => now(),
            ]);
        });

        Notification::make()
            ->title('Order Packed & Sealed')
            ->body("Order #{$order->order_number} marked Ready for Courier Dispatch.")
            ->success()
            ->send();

        $this->closePackingModal();
    }

    // ==========================================
    // 3. DISPATCH WORKFLOW
    // ==========================================

    public function openDispatchModal(int $orderId): void
    {
        $this->dispatchingOrderId = $orderId;
        $order = Order::find($orderId);
        if ($order) {
            $logistics = app(LogisticsService::class);
            $recommended = $logistics->recommendCourier($order);
            $this->selectedCourier = $recommended;
            $this->manualCourierName = $recommended === 'pathao' ? 'Pathao Courier' : 'Nepal Can Move (NCM)';
            $this->manualTrackingNumber = $order->tracking_number ?: '';
            $this->dispatchNotes = '';
        }
    }

    public function closeDispatchModal(): void
    {
        $this->dispatchingOrderId = null;
    }

    public function confirmDispatch(?int $orderId = null): void
    {
        $orderId = $orderId ?: $this->dispatchingOrderId;
        $order = Order::find($orderId);
        if (!$order) return;

        // Strict Dispatch Guards
        if ($order->payment_method === 'esewa' && $order->payment_status !== Order::PAYMENT_STATUS_PAID) {
            Notification::make()
                ->title('Dispatch Blocked')
                ->body('Cannot dispatch courier for unverified eSewa payment. Payment must be verified first.')
                ->danger()
                ->send();
            return;
        }

        if ($order->payment_method === 'connectips' && $order->payment_status !== Order::PAYMENT_STATUS_PAID) {
            Notification::make()
                ->title('Dispatch Blocked')
                ->body('Cannot dispatch courier for unpaid ConnectIPS order. Payment must be verified first.')
                ->danger()
                ->send();
            return;
        }

        if ($order->payment_method === 'cod' && !$order->is_inside_valley) {
            Notification::make()
                ->title('Dispatch Blocked')
                ->body('Cash on Delivery (COD) is restricted strictly to Kathmandu Valley locations.')
                ->danger()
                ->send();
            return;
        }

        // Automated API Courier Dispatch
        if (in_array($this->selectedCourier, ['pathao', 'ncm'], true) && empty($this->manualTrackingNumber)) {
            $logistics = app(LogisticsService::class);
            $result = $logistics->createShipment($order, $this->selectedCourier);

            if ($result['success']) {
                $order->refresh();
                $order->status = Order::STATUS_HANDED_TO_COURIER;
                $order->courier_pickup_date = now();
                $order->save();

                Notification::make()
                    ->title('Shipment Dispatched')
                    ->body("Order #{$order->order_number} dispatched via " . strtoupper($this->selectedCourier) . ". Tracking: " . ($result['tracking_number'] ?? $order->tracking_number))
                    ->success()
                    ->send();
                $this->closeDispatchModal();
                return;
            } else {
                Log::warning("Courier API notice for order #{$order->order_number}: " . ($result['error'] ?? 'API booking failed'));
                Notification::make()
                    ->title('Courier API Notice')
                    ->body($result['error'] ?? 'Could not create courier booking via API. Please verify recipient details/phone or enter tracking number manually.')
                    ->danger()
                    ->persistent()
                    ->send();
                return;
            }
        }

        // Manual Courier or In-House Rider Dispatch
        DB::transaction(function () use ($order) {
            $carrierName = match ($this->selectedCourier) {
                'pathao' => 'Pathao Courier',
                'ncm' => 'Nepal Can Move (NCM)',
                'in_house' => 'Laijau In-House Rider',
                default => $this->manualCourierName ?: 'Nepal Courier',
            };

            $trackingNum = !empty($this->manualTrackingNumber)
                ? trim($this->manualTrackingNumber)
                : ('LJ-' . strtoupper($this->selectedCourier) . '-' . $order->order_number);

            $order->carrier = $carrierName;
            $order->courier_name = $carrierName;
            $order->tracking_number = $trackingNum;
            $order->courier_order_id = $trackingNum;
            $order->courier_status = 'Dispatched';
            $order->courier_pickup_date = now();
            $order->status = Order::STATUS_HANDED_TO_COURIER;
            if ($this->dispatchNotes) {
                $order->delivery_notes = ($order->delivery_notes ? $order->delivery_notes . "\n" : "") . "[Dispatch] " . $this->dispatchNotes;
            }
            $order->save();

            LogisticsEvent::create([
                'order_id' => $order->id,
                'provider' => $this->selectedCourier,
                'external_order_id' => $trackingNum,
                'event' => 'shipment_dispatched',
                'status' => 'Dispatched',
                'payload' => [
                    'carrier' => $carrierName,
                    'tracking' => $trackingNum,
                    'notes' => $this->dispatchNotes,
                    'dispatched_by' => auth()->user()?->name ?? 'Staff',
                ],
                'processing_status' => 'processed',
                'idempotency_key' => 'dispatch_' . $order->id . '_' . time(),
                'received_at' => now(),
                'processed_at' => now(),
            ]);
        });

        Notification::make()
            ->title('Shipment Dispatched')
            ->body("Order #{$order->order_number} marked Dispatched. Courier: {$order->carrier}. AWB: {$order->tracking_number}")
            ->success()
            ->send();

        $this->closeDispatchModal();
    }

    public function bulkDispatchReady(): void
    {
        $orders = $this->getReadyDispatchQuery()->get();
        if ($orders->isEmpty()) {
            Notification::make()->title('No orders currently ready for dispatch')->info()->send();
            return;
        }

        $logistics = app(LogisticsService::class);
        $success = 0;
        $failed = 0;

        foreach ($orders as $order) {
            // Check guards
            if ($order->payment_method === 'esewa' && $order->payment_status !== Order::PAYMENT_STATUS_PAID) {
                $failed++;
                continue;
            }
            if ($order->payment_method === 'cod' && !$order->is_inside_valley) {
                $failed++;
                continue;
            }

            $provider = $logistics->recommendCourier($order);
            $res = $logistics->createShipment($order, $provider);

            if ($res['success']) {
                $order->refresh();
                $order->status = Order::STATUS_HANDED_TO_COURIER;
                $order->courier_pickup_date = now();
                $order->save();
                $success++;
            } else {
                // Manual fallback assignment
                $order->carrier = $provider === 'pathao' ? 'Pathao Courier' : 'Nepal Can Move (NCM)';
                $order->courier_name = $order->carrier;
                $order->tracking_number = 'LJ-' . strtoupper($provider) . '-' . $order->order_number;
                $order->courier_order_id = $order->tracking_number;
                $order->courier_status = 'Dispatched';
                $order->courier_pickup_date = now();
                $order->status = Order::STATUS_HANDED_TO_COURIER;
                $order->save();
                $success++;
            }
        }

        Notification::make()
            ->title('Bulk Courier Dispatch Completed')
            ->body("{$success} orders dispatched successfully. " . ($failed > 0 ? "{$failed} orders held back due to payment/COD guards." : ''))
            ->success()
            ->send();
    }

    // ==========================================
    // 4. ACTIVE SHIPMENTS & DELIVERY
    // ==========================================

    public function syncShipment(int $orderId): void
    {
        $order = Order::find($orderId);
        if (!$order) return;

        $logistics = app(LogisticsService::class);
        $result = $logistics->syncShipmentStatus($order);

        if ($result['success']) {
            Notification::make()
                ->title('Shipment Synced')
                ->body("Order #{$order->order_number} status updated to: " . ($result['courier_status'] ?? 'Updated'))
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Sync Notice')
                ->body($result['error'] ?? 'No remote updates available from courier API.')
                ->info()
                ->send();
        }
    }

    public function markDelivered(int $orderId): void
    {
        $order = Order::find($orderId);
        if (!$order) return;

        DB::transaction(function () use ($order) {
            $order->status = Order::STATUS_DELIVERED;
            $order->delivered_at = now();
            $order->actual_delivery_date = now();
            $order->courier_status = 'Delivered';

            // Auto-settle prepaid, keep COD pending remittance
            if (strtolower((string) $order->payment_method) !== 'cod') {
                $order->payment_status = Order::PAYMENT_STATUS_PAID;
                $order->payment_verified_at = now();
            }

            $order->save();

            LogisticsEvent::create([
                'order_id' => $order->id,
                'provider' => $order->carrier ?: 'courier',
                'external_order_id' => $order->tracking_number ?: (string) $order->id,
                'event' => 'delivered',
                'status' => 'Delivered',
                'payload' => [
                    'delivered_at' => now()->toIso8601String(),
                    'marked_by' => auth()->user()?->name ?? 'Staff',
                ],
                'processing_status' => 'processed',
                'idempotency_key' => 'deliv_' . $order->id . '_' . time(),
                'received_at' => now(),
                'processed_at' => now(),
            ]);
        });

        Notification::make()
            ->title('Order Delivered')
            ->body("Order #{$order->order_number} marked as Delivered.")
            ->success()
            ->send();
    }

    // ==========================================
    // 5. EXCEPTIONS & RETURNS
    // ==========================================

    public function openExceptionModal(int $orderId): void
    {
        $this->exceptionOrderId = $orderId;
        $this->exceptionReason = 'customer_unreachable';
        $this->exceptionNotes = '';
        $this->exceptionAction = 'retry';
    }

    public function closeExceptionModal(): void
    {
        $this->exceptionOrderId = null;
    }

    public function confirmException(?int $orderId = null): void
    {
        $orderId = $orderId ?: $this->exceptionOrderId;
        $order = Order::find($orderId);
        if (!$order) return;

        DB::transaction(function () use ($order) {
            $userName = auth()->user()?->name ?? 'Staff';
            $note = "[Exception: " . strtoupper(str_replace('_', ' ', $this->exceptionReason)) . "] " . ($this->exceptionNotes ? $this->exceptionNotes . " " : "") . "(Logged by {$userName})";
            $order->courier_comments = ($order->courier_comments ? $order->courier_comments . "\n" : "") . $note;

            if ($this->exceptionAction === 'fail') {
                $order->status = Order::STATUS_FAILED_DELIVERY;
            } elseif ($this->exceptionAction === 'return_stock') {
                $order->status = Order::STATUS_RETURNED;
                // Safely restock lines via InventoryService
                try {
                    $restoreLines = [];
                    foreach ($order->items as $item) {
                        $restoreLines[] = [
                            'product_id' => $item->product_id,
                            'variant_id' => $item->variant_id,
                            'quantity' => $item->quantity,
                            'condition' => 'good',
                        ];
                    }
                    app(\App\Services\Inventory\InventoryService::class)->restoreOrderReturn($order, $restoreLines, "Return from failed delivery: {$this->exceptionReason}", auth()->user());
                } catch (\Throwable $e) {
                    Log::warning("Inventory restock notice on return for #{$order->order_number}: " . $e->getMessage());
                }
            } else {
                // Reschedule: re-Queue to ready for delivery
                $order->status = Order::STATUS_READY_FOR_DELIVERY;
            }

            $order->save();

            LogisticsEvent::create([
                'order_id' => $order->id,
                'provider' => $order->carrier ?: 'courier',
                'external_order_id' => $order->tracking_number ?: (string) $order->id,
                'event' => 'delivery_exception',
                'status' => $this->exceptionReason,
                'payload' => [
                    'reason' => $this->exceptionReason,
                    'notes' => $this->exceptionNotes,
                    'action' => $this->exceptionAction,
                    'logged_by' => $userName,
                ],
                'processing_status' => 'processed',
                'idempotency_key' => 'exc_' . $order->id . '_' . time(),
                'received_at' => now(),
                'processed_at' => now(),
            ]);
        });

        Notification::make()
            ->title('Exception Logged')
            ->body("Order #{$order->order_number} updated with exception: " . str_replace('_', ' ', $this->exceptionReason))
            ->warning()
            ->send();

        $this->closeExceptionModal();
    }

    // ==========================================
    // 6. COD REMITTANCE & SETTLEMENTS
    // ==========================================

    public function openCodModal(int $orderId): void
    {
        $this->settlingOrderId = $orderId;
        $order = Order::find($orderId);
        $this->courierFee = 150.00;
        $this->settlementBatchRef = 'COD-REMIT-' . date('Ymd') . '-' . ($order?->order_number ?? '');
        $this->settlementDate = date('Y-m-d');
    }

    public function closeCodModal(): void
    {
        $this->settlingOrderId = null;
    }

    public function confirmCodSettlement(): void
    {
        if (!$this->settlingOrderId) return;

        $order = Order::find($this->settlingOrderId);
        if (!$order) return;

        $logistics = app(LogisticsService::class);
        $result = $logistics->settleCodOrder($order, [
            'courier_fee' => $this->courierFee,
            'batch_reference' => $this->settlementBatchRef,
            'date' => $this->settlementDate ?: date('Y-m-d'),
        ], auth()->user());

        if ($result['success']) {
            Notification::make()
                ->title('COD Settled & Reconciled')
                ->body("Order #{$order->order_number} remittance of Rs. " . number_format($result['net_remitted'], 2) . " verified and posted.")
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Settlement Failed')
                ->body($result['error'] ?? 'Unable to settle COD order.')
                ->danger()
                ->send();
        }

        $this->closeCodModal();
    }

    // ==========================================
    // 7. DISPATCH MANIFEST
    // ==========================================

    public function openManifestModal(): void
    {
        $this->showManifestModal = true;
    }

    public function closeManifestModal(): void
    {
        $this->showManifestModal = false;
    }

    public function getManifestOrdersProperty()
    {
        $query = Order::with(['items.product', 'user'])
            ->whereIn('status', [
                Order::STATUS_READY_FOR_DELIVERY,
                Order::STATUS_HANDED_TO_COURIER,
                Order::STATUS_IN_TRANSIT,
            ]);

        if ($this->manifestCourier !== 'all') {
            $query->where(function ($q) {
                $q->where('carrier', 'like', "%{$this->manifestCourier}%")
                    ->orWhere('courier_name', 'like', "%{$this->manifestCourier}%");
            });
        }

        return $query->orderBy('created_at', 'asc')->get();
    }

    // ==========================================
    // 8. DATABASE QUERIES & METRICS
    // ==========================================

    protected function applyFilters($query)
    {
        if (!empty($this->searchQuery)) {
            $q = trim($this->searchQuery);
            $query->where(function ($sub) use ($q) {
                $sub->where('order_number', 'like', "%{$q}%")
                    ->orWhere('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('tracking_number', 'like', "%{$q}%")
                    ->orWhere('courier_order_id', 'like', "%{$q}%");
            });
        }

        if ($this->zoneFilter) {
            if ($this->zoneFilter === 'kathmandu_valley') {
                $query->where('is_inside_valley', true);
            } elseif ($this->zoneFilter === 'outside_valley') {
                $query->where('is_inside_valley', false);
            }
        }

        if ($this->paymentFilter) {
            if ($this->paymentFilter === 'cod') {
                $query->where('payment_method', 'cod');
            } elseif ($this->paymentFilter === 'prepaid') {
                $query->where('payment_method', '!=', 'cod');
            }
        }

        if ($this->courierFilter) {
            $query->where(function ($sub) {
                $sub->where('carrier', 'like', "%{$this->courierFilter}%")
                    ->orWhere('courier_name', 'like', "%{$this->courierFilter}%");
            });
        }

        return $query;
    }

    public function getReadyToPickQuery()
    {
        return $this->applyFilters(
            Order::with(['items.product', 'items.variant', 'user'])
                ->whereIn('status', [
                    Order::STATUS_CUSTOMER_CONFIRMED,
                    Order::STATUS_PAYMENT_VERIFIED,
                    Order::STATUS_PROCESSING,
                ])
                ->where('status', '!=', Order::STATUS_CANCELLED)
                ->oldest()
        );
    }

    public function getPackingQuery()
    {
        return $this->applyFilters(
            Order::with(['items.product', 'items.variant', 'user'])
                ->where('status', Order::STATUS_PACKING)
                ->oldest()
        );
    }

    public function getReadyDispatchQuery()
    {
        return $this->applyFilters(
            Order::with(['items.product', 'items.variant', 'user'])
                ->where('status', Order::STATUS_READY_FOR_DELIVERY)
                ->oldest()
        );
    }

    public function getInTransitQuery()
    {
        return $this->applyFilters(
            Order::with(['items.product', 'items.variant', 'user'])
                ->whereIn('status', [
                    Order::STATUS_HANDED_TO_COURIER,
                    Order::STATUS_IN_TRANSIT,
                    'out_for_delivery',
                ])
                ->latest('courier_pickup_date')
        );
    }

    public function getExceptionsQuery()
    {
        return $this->applyFilters(
            Order::with(['items.product', 'items.variant', 'user'])
                ->where(function ($q) {
                    $q->whereIn('status', [
                        Order::STATUS_FAILED_DELIVERY,
                        Order::STATUS_RETURNED,
                        Order::STATUS_RETURN_REQUESTED,
                    ])->orWhere(function ($sub) {
                        $sub->whereNotNull('courier_comments')
                            ->where(function ($commentSub) {
                                $commentSub->where('courier_comments', 'like', '%fail%')
                                    ->orWhere('courier_comments', 'like', '%exception%')
                                    ->orWhere('courier_comments', 'like', '%unreachable%')
                                    ->orWhere('courier_comments', 'like', '%refused%');
                            });
                    });
                })
                ->latest()
        );
    }

    public function getCodSettlementsQuery()
    {
        return $this->applyFilters(
            Order::with(['items.product', 'user'])
                ->where('payment_method', 'cod')
                ->where(function ($q) {
                    $q->where('status', Order::STATUS_DELIVERED)
                        ->orWhere('courier_status', 'like', '%deliver%');
                })
                ->where('payment_status', '!=', Order::PAYMENT_STATUS_PAID)
                ->latest('delivered_at')
        );
    }

    public function getPaginatedOrdersProperty(): LengthAwarePaginator
    {
        $query = match ($this->activeTab) {
            'ready_pick' => $this->getReadyToPickQuery(),
            'packing' => $this->getPackingQuery(),
            'ready_dispatch' => $this->getReadyDispatchQuery(),
            'in_transit' => $this->getInTransitQuery(),
            'exceptions' => $this->getExceptionsQuery(),
            'cod_settlements' => $this->getCodSettlementsQuery(),
            default => $this->getReadyToPickQuery(),
        };

        return $query->paginate(15);
    }

    public function getMetricsProperty(): array
    {
        $readyPickCount = Order::whereIn('status', [
            Order::STATUS_CUSTOMER_CONFIRMED,
            Order::STATUS_PAYMENT_VERIFIED,
            Order::STATUS_PROCESSING,
        ])->where('status', '!=', Order::STATUS_CANCELLED)->count();

        $packingCount = Order::where('status', Order::STATUS_PACKING)->count();

        $readyDispatchCount = Order::where('status', Order::STATUS_READY_FOR_DELIVERY)->count();

        $dispatchedTodayCount = Order::whereIn('status', [
            Order::STATUS_HANDED_TO_COURIER,
            Order::STATUS_IN_TRANSIT,
            'out_for_delivery',
            Order::STATUS_DELIVERED,
        ])->where(function ($q) {
            $q->whereDate('courier_pickup_date', today())
                ->orWhereDate('updated_at', today());
        })->whereNotNull('courier_pickup_date')->count();

        $inTransitCount = Order::whereIn('status', [
            Order::STATUS_HANDED_TO_COURIER,
            Order::STATUS_IN_TRANSIT,
            'out_for_delivery',
        ])->count();

        $deliveredTodayCount = Order::where('status', Order::STATUS_DELIVERED)
            ->where(function ($q) {
                $q->whereDate('delivered_at', today())
                    ->orWhereDate('updated_at', today());
            })->count();

        $exceptionsCount = Order::whereIn('status', [
            Order::STATUS_FAILED_DELIVERY,
            Order::STATUS_RETURNED,
            Order::STATUS_RETURN_REQUESTED,
        ])->orWhere(function ($q) {
            $q->whereNotNull('courier_comments')
                ->where(function ($sub) {
                    $sub->where('courier_comments', 'like', '%fail%')
                        ->orWhere('courier_comments', 'like', '%exception%')
                        ->orWhere('courier_comments', 'like', '%unreachable%')
                        ->orWhere('courier_comments', 'like', '%refused%');
                });
        })->count();

        $codPendingQuery = Order::where('payment_method', 'cod')
            ->where(function ($q) {
                $q->where('status', Order::STATUS_DELIVERED)
                    ->orWhere('courier_status', 'like', '%deliver%');
            })
            ->where('payment_status', '!=', Order::PAYMENT_STATUS_PAID);

        $codCount = $codPendingQuery->count();
        $codTotalAmount = (float) $codPendingQuery->sum('total_amount');

        return [
            'ready_pick' => $readyPickCount,
            'packing' => $packingCount,
            'ready_dispatch' => $readyDispatchCount,
            'dispatched_today' => $dispatchedTodayCount,
            'in_transit' => $inTransitCount,
            'delivered_today' => $deliveredTodayCount,
            'exceptions' => $exceptionsCount,
            'cod_count' => $codCount,
            'cod_amount' => $codTotalAmount,
            'ncm_total_shipments' => \App\Models\Shipment::where('provider', \App\Models\Shipment::PROVIDER_NCM)->count(),
            'ncm_delivered' => \App\Models\Shipment::where('provider', \App\Models\Shipment::PROVIDER_NCM)->where('normalized_status', \App\Models\Shipment::STATUS_DELIVERED)->count(),
            'ncm_out_for_delivery' => \App\Models\Shipment::where('provider', \App\Models\Shipment::PROVIDER_NCM)->where('normalized_status', \App\Models\Shipment::STATUS_OUT_FOR_DELIVERY)->count(),
            'ncm_dispatched' => \App\Models\Shipment::where('provider', \App\Models\Shipment::PROVIDER_NCM)->where('normalized_status', \App\Models\Shipment::STATUS_DISPATCHED)->count(),
            'ncm_arrived' => \App\Models\Shipment::where('provider', \App\Models\Shipment::PROVIDER_NCM)->where('normalized_status', \App\Models\Shipment::STATUS_ARRIVED)->count(),
            'ncm_pickup_pending' => \App\Models\Shipment::where('provider', \App\Models\Shipment::PROVIDER_NCM)->where('normalized_status', \App\Models\Shipment::STATUS_PICKUP_PENDING)->count(),
            'ncm_vendor_returns' => \App\Models\Shipment::where('provider', \App\Models\Shipment::PROVIDER_NCM)->where('vendor_return', true)->count(),
            'ncm_unmatched_ambiguous' => \App\Models\Shipment::where('provider', \App\Models\Shipment::PROVIDER_NCM)->whereIn('match_status', [\App\Models\Shipment::MATCH_STATUS_UNMATCHED, \App\Models\Shipment::MATCH_STATUS_AMBIGUOUS])->count(),
            'ncm_cod_amount' => (float)\App\Models\Shipment::where('provider', \App\Models\Shipment::PROVIDER_NCM)->sum('cod_amount'),
            'ncm_delivery_charge' => (float)\App\Models\Shipment::where('provider', \App\Models\Shipment::PROVIDER_NCM)->sum('delivery_charge'),
        ];
    }
}
