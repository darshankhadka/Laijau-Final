<?php

namespace App\Services\Logistics;

use App\Models\LogisticsEvent;
use App\Models\Order;
use App\Services\Logistics\Contracts\LogisticsProviderInterface;
use App\Services\Logistics\Providers\NcmProvider;
use App\Services\Logistics\Providers\PathaoProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LogisticsService
{
    /**
     * Resolve the logistics provider instance
     */
    public function resolveProvider(?string $provider = null): LogisticsProviderInterface
    {
        $name = strtolower(trim((string) $provider));

        if (empty($name)) {
            $name = config('services.logistics.default', 'ncm');
        }

        if (str_contains($name, 'pathao')) {
            return app(PathaoProvider::class);
        }

        // Default to NCM
        return app(NcmProvider::class);
    }

    /**
     * Create a shipment with the chosen courier provider
     */
    public function createShipment(Order $order, ?string $provider = null, array $options = []): array
    {
        // Enforce idempotency: prevent accidental duplicate shipment creation if already dispatched
        if (!empty($order->courier_order_id) && empty($options['force'])) {
            return [
                'success' => true,
                'already_exists' => true,
                'external_order_id' => $order->courier_order_id,
                'tracking_number' => $order->tracking_number,
                'tracking_url' => $order->tracking_url,
                'status' => $order->courier_status,
                'message' => 'Shipment already exists for order ' . $order->order_number,
                'error' => null,
            ];
        }

        // Strict Courier Dispatch Guards
        if ($order->payment_method === 'esewa' && $order->payment_status !== Order::PAYMENT_STATUS_PAID) {
            return [
                'success' => false,
                'error' => 'Cannot dispatch courier for unverified eSewa payment. Order payment must be verified first.',
            ];
        }

        if ($order->payment_method === 'connectips' && $order->payment_status !== Order::PAYMENT_STATUS_PAID) {
            return [
                'success' => false,
                'error' => 'Cannot dispatch courier for unpaid ConnectIPS order. Payment must be confirmed first.',
            ];
        }

        if ($order->payment_method === 'cod' && !$order->is_inside_valley) {
            return [
                'success' => false,
                'error' => 'Cannot dispatch courier for Cash on Delivery outside Kathmandu Valley.',
            ];
        }

        $providerInstance = $this->resolveProvider($provider ?: $order->carrier ?: $order->courier_name);

        $result = $providerInstance->createShipment($order, $options);

        if (!$result['success']) {
            Log::warning("Logistics shipment creation failed for order {$order->order_number}: " . ($result['error'] ?? 'Unknown error'));
            return $result;
        }

        DB::transaction(function () use ($order, $providerInstance, $result) {
            $order->carrier = $providerInstance->getDisplayName();
            $order->courier_name = $providerInstance->getDisplayName();
            $order->courier_order_id = $result['external_order_id'];
            $order->tracking_number = $result['tracking_number'];
            $order->tracking_url = $result['tracking_url'] ?: Order::resolveTrackingUrl($providerInstance->getProviderName(), $result['tracking_number']);
            $order->courier_status = $result['status'] ?: 'Created';
            $order->courier_last_sync_at = now();

            // Progress status if currently in early preparation states
            $earlyStatuses = [
                Order::STATUS_PENDING,
                Order::STATUS_CUSTOMER_CONFIRMED,
                Order::STATUS_PAYMENT_PENDING,
                Order::STATUS_PAYMENT_VERIFIED,
                Order::STATUS_PROCESSING,
                Order::STATUS_PACKING,
            ];

            if (in_array($order->status, $earlyStatuses)) {
                $order->status = Order::STATUS_READY_FOR_DELIVERY;
            }

            $order->save();

            LogisticsEvent::create([
                'order_id' => $order->id,
                'provider' => $providerInstance->getProviderName(),
                'external_order_id' => $result['external_order_id'],
                'event' => 'shipment_created',
                'status' => $result['status'],
                'payload' => $result['raw_response'],
                'processing_status' => 'processed',
                'idempotency_key' => $providerInstance->getProviderName() . '_create_' . $result['external_order_id'],
                'received_at' => now(),
                'processed_at' => now(),
            ]);
        });

        return array_merge($result, [
            'order_status' => $order->fresh()->status,
        ]);
    }

    /**
     * Poll courier API to refresh latest status
     */
    public function syncShipmentStatus(Order $order): array
    {
        if (empty($order->courier_order_id)) {
            return [
                'success' => false,
                'error' => "Order {$order->order_number} does not have an active courier consignment ID.",
            ];
        }

        $providerInstance = $this->resolveProvider($order->carrier ?: $order->courier_name);
        $result = $providerInstance->getShipmentStatus($order->courier_order_id);

        if (!$result['success']) {
            return $result;
        }

        DB::transaction(function () use ($order, $providerInstance, $result) {
            $order->courier_status = $result['status'] ?: $order->courier_status;
            if (!empty($result['comments'])) {
                $order->courier_comments = $result['comments'];
            }
            $order->courier_last_sync_at = now();

            $mappedStatus = $providerInstance->mapExternalStatusToLaijau((string) $result['status']);
            if ($mappedStatus) {
                $this->applyStatusTransition($order, $mappedStatus);
            }

            $order->save();

            LogisticsEvent::create([
                'order_id' => $order->id,
                'provider' => $providerInstance->getProviderName(),
                'external_order_id' => $order->courier_order_id,
                'event' => 'status_sync',
                'status' => $result['status'],
                'payload' => $result['raw_response'],
                'processing_status' => 'processed',
                'received_at' => now(),
                'processed_at' => now(),
            ]);
        });

        return [
            'success' => true,
            'courier_status' => $order->fresh()->courier_status,
            'order_status' => $order->fresh()->status,
            'comments' => $order->fresh()->courier_comments,
        ];
    }

    /**
     * Add comment to courier shipment
     */
    public function addComment(Order $order, string $comment): array
    {
        if (empty($order->courier_order_id)) {
            return ['success' => false, 'error' => 'No courier shipment exists for this order.'];
        }

        $providerInstance = $this->resolveProvider($order->carrier ?: $order->courier_name);
        $result = $providerInstance->addComment($order->courier_order_id, $comment);

        if ($result['success']) {
            $existingComments = $order->courier_comments ? $order->courier_comments . "\n" : '';
            $order->courier_comments = $existingComments . '[' . now()->format('Y-m-d H:i') . '] ' . $comment;
            $order->save();
        }

        return $result;
    }

    /**
     * Process incoming courier webhook payload idempotently
     */
    public function processWebhook(string $providerName, array $payload, array $headers = []): array
    {
        $providerInstance = $this->resolveProvider($providerName);
        $events = $providerInstance->parseWebhookPayload($payload, $headers);

        $processedCount = 0;
        $skippedCount = 0;
        $errors = [];

        foreach ($events as $event) {
            $idempotencyKey = $event['idempotency_key'];

            // 1. Check idempotency: skip if already successfully processed
            $existingEvent = LogisticsEvent::where('idempotency_key', $idempotencyKey)->first();
            if ($existingEvent && $existingEvent->processing_status === 'processed') {
                $skippedCount++;
                continue;
            }

            // 2. Handle test webhook ping
            if (!empty($event['is_test'])) {
                LogisticsEvent::create([
                    'order_id' => null,
                    'provider' => $providerInstance->getProviderName(),
                    'external_order_id' => $event['external_order_id'],
                    'event' => $event['event'] ?? 'test',
                    'status' => $event['status'] ?? 'test',
                    'payload' => $event['raw'],
                    'processing_status' => 'processed',
                    'idempotency_key' => $idempotencyKey,
                    'received_at' => now(),
                    'processed_at' => now(),
                ]);
                $processedCount++;
                continue;
            }

            // 3. Match Order by external order id or tracking number
            $order = Order::where('courier_order_id', $event['external_order_id'])
                ->orWhere('tracking_number', $event['external_order_id'])
                ->first();

            try {
                DB::transaction(function () use ($order, $providerInstance, $event, $idempotencyKey) {
                    if ($order) {
                        $order->courier_status = $event['status'] ?: $order->courier_status;
                        $order->courier_last_sync_at = now();

                        $mappedStatus = $providerInstance->mapExternalStatusToLaijau((string) $event['status'], (string) $event['event']);
                        if ($mappedStatus) {
                            $this->applyStatusTransition($order, $mappedStatus);
                        }

                        $order->save();
                    }

                    LogisticsEvent::create([
                        'order_id' => $order?->id,
                        'provider' => $providerInstance->getProviderName(),
                        'external_order_id' => $event['external_order_id'],
                        'event' => $event['event'],
                        'status' => $event['status'],
                        'payload' => $event['raw'],
                        'processing_status' => 'processed',
                        'idempotency_key' => $idempotencyKey,
                        'received_at' => now(),
                        'processed_at' => now(),
                    ]);
                });

                $processedCount++;
            } catch (\Throwable $e) {
                Log::error("Failed to process {$providerName} webhook event for {$event['external_order_id']}: " . $e->getMessage());
                $errors[] = $e->getMessage();

                LogisticsEvent::create([
                    'order_id' => $order?->id,
                    'provider' => $providerInstance->getProviderName(),
                    'external_order_id' => $event['external_order_id'],
                    'event' => $event['event'],
                    'status' => $event['status'],
                    'payload' => $event['raw'],
                    'processing_status' => 'failed',
                    'error' => $e->getMessage(),
                    'idempotency_key' => $idempotencyKey,
                    'received_at' => now(),
                    'processed_at' => now(),
                ]);
            }
        }

        return [
            'success' => empty($errors),
            'processed' => $processedCount,
            'skipped_duplicates' => $skippedCount,
            'errors' => $errors,
        ];
    }

    /**
     * Safely apply status transition to order without regressing forward states
     */
    protected function applyStatusTransition(Order $order, string $newStatus): void
    {
        $statusHierarchy = [
            Order::STATUS_PENDING => 10,
            Order::STATUS_CUSTOMER_CONFIRMED => 20,
            Order::STATUS_PAYMENT_PENDING => 30,
            Order::STATUS_PAYMENT_VERIFIED => 40,
            Order::STATUS_PROCESSING => 50,
            Order::STATUS_PACKING => 60,
            Order::STATUS_READY_FOR_DELIVERY => 70,
            Order::STATUS_HANDED_TO_COURIER => 80,
            Order::STATUS_IN_TRANSIT => 90,
            Order::STATUS_DELIVERED => 100,
        ];

        // Terminal states can always override
        if (in_array($newStatus, [Order::STATUS_CANCELLED, Order::STATUS_RETURNED, Order::STATUS_FAILED_DELIVERY])) {
            $order->status = $newStatus;
            if ($newStatus === Order::STATUS_CANCELLED) {
                $order->cancelled_at = now();
            }
            return;
        }

        // Delivery completed: mark delivered and settle COD payment if unpaid
        if ($newStatus === Order::STATUS_DELIVERED) {
            $order->status = Order::STATUS_DELIVERED;
            $order->delivered_at = now();

            if (strtolower((string) $order->payment_method) === 'cod' && $order->payment_status !== Order::PAYMENT_STATUS_PAID) {
                $order->payment_status = Order::PAYMENT_STATUS_PAID;
                $order->payment_verified_at = now();
            }
            return;
        }

        $currentRank = $statusHierarchy[$order->status] ?? 0;
        $newRank = $statusHierarchy[$newStatus] ?? 0;

        // Prevent regressing order status (e.g. don't go from In Transit back to Ready for Delivery)
        if ($newRank >= $currentRank) {
            $order->status = $newStatus;
        }
    }

    /**
     * Automatic intelligent courier recommendation based on destination and serviceability.
     */
    public function recommendCourier(Order $order): string
    {
        $isValley = (bool) ($order->is_inside_valley ?? false);
        $city = strtolower(trim((string) ($order->shipping_city ?? $order->city ?? '')));
        $valleyCities = ['kathmandu', 'ktm', 'lalitpur', 'bhaktapur', 'patan', 'thimi', 'kirtipur'];

        foreach ($valleyCities as $vCity) {
            if (str_contains($city, $vCity)) {
                $isValley = true;
                break;
            }
        }

        if ($isValley) {
            return 'pathao';
        }

        return 'ncm';
    }

    /**
     * Settle a Delivered COD order and auto-generate accounting reconciliation.
     */
    public function settleCodOrder(Order $order, array $settlementData = [], ?\App\Models\User $user = null): array
    {
        if (strtolower((string) $order->payment_method) !== 'cod') {
            return ['success' => false, 'error' => 'Order is not a Cash on Delivery (COD) order.'];
        }

        return DB::transaction(function () use ($order, $settlementData, $user) {
            $totalAmount = (float) ($order->total_amount_npr ?: $order->total_amount);
            $courierFee = (float) ($settlementData['courier_fee'] ?? 150.00);
            $netRemitted = max(0, $totalAmount - $courierFee);
            $batchRef = $settlementData['batch_reference'] ?? ('COD-REMIT-' . date('Ymd') . '-' . $order->order_number);
            $remitDate = $settlementData['date'] ?? date('Y-m-d');

            $order->payment_status = Order::PAYMENT_STATUS_PAID;
            $order->payment_verified_at = now();
            $order->payment_notes = ($order->payment_notes ? $order->payment_notes . "\n" : "") . "[COD Settled] Remitted: Rs. {$netRemitted}, Fee: Rs. {$courierFee}, Ref: {$batchRef}";
            $order->save();

            $accountingService = app(\App\Services\Accounting\AccountingService::class);
            $journalEntry = null;
            try {
                if (method_exists($accountingService, 'reconcileCodSettlement')) {
                    $journalEntry = $accountingService->reconcileCodSettlement(
                        remittedAmount: $netRemitted,
                        courierFee: $courierFee,
                        courierName: (string) ($order->carrier ?: $order->courier_name ?: 'Nepal Courier'),
                        batchRef: $batchRef,
                        date: $remitDate,
                        orderId: $order->id
                    );
                }
            } catch (\Throwable $e) {
                Log::warning("COD settlement accounting posting notice: " . $e->getMessage());
            }

            return [
                'success' => true,
                'order' => $order->fresh(),
                'net_remitted' => $netRemitted,
                'courier_fee' => $courierFee,
                'journal_entry' => $journalEntry,
            ];
        });
    }
}
