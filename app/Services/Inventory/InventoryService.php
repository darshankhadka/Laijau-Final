<?php

namespace App\Services\Inventory;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Inventory\PurchaseOrderItem;
use App\Models\Inventory\StockAdjustment;
use App\Models\Inventory\StockCount;
use App\Models\Inventory\StockCountItem;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\StockReservation;
use App\Models\Inventory\StockTransfer;
use App\Models\Inventory\StockTransferItem;
use App\Models\Inventory\Supplier;
use App\Models\Inventory\Warehouse;
use App\Models\OfflineSale;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use App\Services\Settings\SettingsService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class InventoryService
{
    public const DEFAULT_WH_CODE = 'WH-KTM-MAIN';
    public const SHOWROOM_WH_CODE = 'STORE-KTM-01';
    public const SECONDARY_WH_CODE = 'STORE-KTM-02';

    protected AccountingService $accountingService;
    protected SettingsService $settingsService;

    public function __construct(
        ?AccountingService $accountingService = null,
        ?SettingsService $settingsService = null
    ) {
        $this->accountingService = $accountingService ?? app(AccountingService::class);
        $this->settingsService = $settingsService ?? app(SettingsService::class);
    }

    /**
     * Ensure default Warehouses and Suppliers exist.
     */
    public function ensureDefaultWarehousesAndSuppliers(): void
    {
        Warehouse::firstOrCreate(
            ['code' => self::DEFAULT_WH_CODE],
            [
                'name' => 'Laijau Central Fulfillment Hub',
                'type' => 'warehouse',
                'address' => 'Bohara Tol, Kageshwori Manahara 09, Kathmandu, Nepal',
                'postal_code' => '44600',
                'city' => 'Kathmandu',
                'country' => 'NP',
                'manager_name' => 'Fulfillment Lead',
                'contact_email' => 'info@laijau.com',
                'contact_phone' => '9843512095',
                'is_default' => true,
                'allow_sales' => true,
                'is_active' => true,
                'notes' => 'Central fulfillment hub for Laijau nationwide e-commerce orders & courier dispatch.',
            ]
        );

        Warehouse::firstOrCreate(
            ['code' => self::SHOWROOM_WH_CODE],
            [
                'name' => 'Laijau Showroom',
                'type' => 'showroom_pos',
                'address' => 'Bohara Tol, Kageshwori Manahara 09, Kathmandu, Nepal',
                'postal_code' => '44600',
                'city' => 'Kathmandu',
                'country' => 'NP',
                'manager_name' => 'Showroom Manager',
                'contact_email' => 'info@laijau.com',
                'contact_phone' => '9843512095',
                'is_default' => false,
                'allow_sales' => true,
                'is_active' => true,
                'notes' => 'Location 1: Laijau Showroom • Hours: 8:00 AM – 7:00 PM, every day • 7-day exchange only.',
            ]
        );

        Warehouse::firstOrCreate(
            ['code' => self::SECONDARY_WH_CODE],
            [
                'name' => 'Laijau Showroom 2',
                'type' => 'showroom_pos',
                'address' => 'Bohara Tol, Kageshwori Manahara 09, Kathmandu, Nepal',
                'postal_code' => '44600',
                'city' => 'Kathmandu',
                'country' => 'NP',
                'manager_name' => 'Showroom 2 Lead',
                'contact_email' => 'info@laijau.com',
                'contact_phone' => '9843512095',
                'is_default' => false,
                'allow_sales' => true,
                'is_active' => true,
                'notes' => 'Location 2: Laijau Showroom 2 • Hours: 8:00 AM – 7:00 PM, every day • 7-day exchange only.',
            ]
        );

        Supplier::firstOrCreate(
            ['code' => 'SUP-LAI-018'],
            [
                'name' => 'Citizen shoes',
                'legal_name' => 'Citizen shoes Pvt. Ltd.',
                'tax_vat_number' => '601928374',
                'due_balance' => 853200.00,
                'phone' => '+977 1 4220101',
                'city' => 'Kathmandu',
                'country' => 'NP',
                'currency' => 'NPR',
                'payment_terms' => 'Net 30',
                'lead_time_days' => 14,
                'is_active' => true,
                'notes' => 'Authoritative footwear manufacturing partner. Due balance payable as of 12 Sept 2026: Rs. 853,200.',
            ]
        );

        Supplier::firstOrCreate(
            ['code' => 'SUP-LAI-021'],
            [
                'name' => 'Prasiddha footware',
                'legal_name' => 'Prasiddha footware Pvt. Ltd.',
                'tax_vat_number' => '300482910',
                'due_balance' => 1601025.00,
                'phone' => '+977 1 4220104',
                'city' => 'Kathmandu',
                'country' => 'NP',
                'currency' => 'NPR',
                'payment_terms' => 'Net 30',
                'lead_time_days' => 21,
                'is_active' => true,
                'notes' => 'Authoritative footwear manufacturing partner. Due balance payable as of 12 Sept 2026: Rs. 1,601,025.',
            ]
        );

        Supplier::firstOrCreate(
            ['code' => 'SUP-LAI-001'],
            [
                'name' => 'Kavish enterprises',
                'legal_name' => 'Kavish enterprises Pvt. Ltd.',
                'tax_vat_number' => '609631237',
                'due_balance' => 0.00,
                'phone' => '+977 1 5321001',
                'city' => 'Kathmandu',
                'country' => 'NP',
                'currency' => 'NPR',
                'payment_terms' => 'Net 15',
                'lead_time_days' => 10,
                'is_active' => true,
                'notes' => 'Ladies Sandal, Ladies Shoes, Ladies Choco Shoes',
            ]
        );

        if (app()->environment('testing')) {
            Supplier::firstOrCreate(
                ['code' => 'SUP-PASHMINA-KTM'],
                [
                    'name' => 'Himalayan Cashmere & Pashmina Guild',
                    'contact_person' => 'Sunil Shrestha',
                    'email' => 'pashmina.guild@laijau-suppliers.np',
                    'phone' => '+977 1 5521098',
                    'city' => 'Kathmandu',
                    'country' => 'NP',
                    'currency' => 'NPR',
                    'payment_terms' => 'Net 30',
                    'lead_time_days' => 14,
                    'is_active' => true,
                    'notes' => 'Handwoven authentic Grade-A Changthangi Cashmere shawls.',
                ]
            );

            Supplier::firstOrCreate(
                ['code' => 'SUP-LEATHER-KTM'],
                [
                    'name' => 'Kathmandu Artisan Footwear & Leathercraft',
                    'contact_person' => 'Rajesh Shrestha',
                    'email' => 'artisan.leather@laijau-suppliers.np',
                    'phone' => '+977 1 4251234',
                    'city' => 'Kathmandu',
                    'country' => 'NP',
                    'currency' => 'NPR',
                    'payment_terms' => 'Net 30',
                    'lead_time_days' => 14,
                    'is_active' => true,
                    'notes' => 'Handcrafted genuine leather shoes, boots, and leather accessories.',
                ]
            );

            Supplier::firstOrCreate(
                ['code' => 'SUP-SUSTAINABLE-PKR'],
                [
                    'name' => 'Pokhara Eco-Artisan Cooperative',
                    'contact_person' => 'Tara Gurung',
                    'email' => 'eco.crafts@laijau-suppliers.np',
                    'phone' => '+977 61 460112',
                    'city' => 'Pokhara',
                    'country' => 'NP',
                    'currency' => 'NPR',
                    'payment_terms' => 'Net 15',
                    'lead_time_days' => 10,
                    'is_active' => true,
                    'notes' => 'Wild Himalayan hemp and organic nettle fiber accessories.',
                ]
            );
        }
    }

    /**
     * Backfill existing live products and variants into multi-location stock levels & ledger.
     */
    public function backfillExistingProductsToStockLevels(): int
    {
        $defaultWh = $this->getDefaultWarehouse();
        $count = 0;

        $products = Product::with('variants')->get();
        foreach ($products as $product) {
            if ($product->variants->isNotEmpty()) {
                foreach ($product->variants as $variant) {
                    $stockLevel = StockLevel::where('warehouse_id', $defaultWh->id)
                        ->where('product_id', $product->id)
                        ->where('variant_id', $variant->id)
                        ->first();
                    if (!$stockLevel) {
                        $this->getOrCreateStockLevel(
                            $defaultWh->id,
                            $product->id,
                            $variant->id,
                            (float)($variant->cost_price ?? $product->cost_price ?? 0)
                        );
                        $count++;
                    }
                }
            } else {
                $stockLevel = StockLevel::where('warehouse_id', $defaultWh->id)
                    ->where('product_id', $product->id)
                    ->whereNull('variant_id')
                    ->first();
                if (!$stockLevel) {
                    $this->getOrCreateStockLevel(
                        $defaultWh->id,
                        $product->id,
                        null,
                        (float)($product->cost_price ?? 0)
                    );
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Resolves the primary default fulfillment warehouse.
     */
    public function getDefaultWarehouse(): Warehouse
    {
        $this->ensureDefaultWarehousesAndSuppliers();
        return Warehouse::where('is_default', true)->first()
            ?? Warehouse::where('code', self::DEFAULT_WH_CODE)->first()
            ?? Warehouse::first();
    }

    /**
     * Resolves the showroom POS warehouse.
     */
    public function getShowroomWarehouse(): Warehouse
    {
        $this->ensureDefaultWarehousesAndSuppliers();
        return Warehouse::where('code', self::SHOWROOM_WH_CODE)->first()
            ?? Warehouse::where('type', 'showroom_pos')->first()
            ?? $this->getDefaultWarehouse();
    }

    /**
     * Fetch or initialize a multi-location stock level record.
     */
    public function getOrCreateStockLevel(
        int $warehouseId,
        int $productId,
        ?int $variantId = null,
        float $unitCostnpr = 0.0
    ): StockLevel {
        if ($variantId) {
            $v = ProductVariant::find($variantId);
            if ($v && $v->product_id) {
                $productId = $v->product_id;
            }
        }

        $existing = StockLevel::where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->where('variant_id', $variantId)
            ->first();

        if ($existing) {
            return $existing;
        }

        $initialQty = 0;
        if ($variantId) {
            $variant = ProductVariant::find($variantId);
            if ($variant && $variant->stock_quantity > 0 && !StockLevel::where('variant_id', $variantId)->exists()) {
                $initialQty = (int)$variant->stock_quantity;
            }
        } elseif ($productId) {
            $product = Product::find($productId);
            if ($product && $product->quantity > 0 && !ProductVariant::where('product_id', $productId)->exists() && !StockLevel::where('product_id', $productId)->exists()) {
                $initialQty = (int)$product->quantity;
            }
        }

        return StockLevel::create([
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'variant_id' => $variantId,
            'quantity_on_hand' => $initialQty,
            'quantity_reserved' => 0,
            'quantity_incoming' => 0,
            'reorder_point' => 3,
            'reorder_quantity' => 10,
            'unit_cost_npr' => $unitCostnpr > 0 ? $unitCostnpr : $this->resolveProductUnitCostNpr($productId, $variantId),
        ]);
    }

    /**
     * Authoritative single-point stock movement recorder (The Double-Entry Stock Ledger).
     * Updates physical stock on hand, creates traceable ledger record, and syncs legacy scalar columns.
     */
    public function recordStockMovement(array $data, ?User $user = null): StockMovement
    {
        return DB::transaction(function () use ($data, $user) {
            $warehouseId = (int)$data['warehouse_id'];
            $productId = (int)$data['product_id'];
            $variantId = !empty($data['variant_id']) ? (int)$data['variant_id'] : null;
            if ($variantId) {
                $v = ProductVariant::find($variantId);
                if ($v && $v->product_id) {
                    $productId = $v->product_id;
                }
            }
            $movementType = (string)$data['movement_type'];
            $quantityDelta = (int)$data['quantity']; // signed (+ for incoming, - for outgoing)

            $stockLevel = StockLevel::where('warehouse_id', $warehouseId)
                ->where('product_id', $productId)
                ->where('variant_id', $variantId)
                ->lockForUpdate()
                ->first();

            if (!$stockLevel) {
                $unitCost = isset($data['unit_cost_npr'])
                    ? (float)$data['unit_cost_npr']
                    : $this->resolveProductUnitCostNpr($productId, $variantId);

                $initialStock = 0;
                $hasAnyStockLevels = StockLevel::where('product_id', $productId)
                    ->where('variant_id', $variantId)
                    ->exists();

                if ($movementType !== 'opening_stock' && !$hasAnyStockLevels) {
                    if ($variantId) {
                        $v = ProductVariant::find($variantId);
                        $initialStock = $v ? (int)$v->stock_quantity : 0;
                    } else {
                        $p = Product::find($productId);
                        $initialStock = $p ? (int)$p->quantity : 0;
                    }
                }

                $stockLevel = StockLevel::create([
                    'warehouse_id' => $warehouseId,
                    'product_id' => $productId,
                    'variant_id' => $variantId,
                    'quantity_on_hand' => $initialStock,
                    'quantity_reserved' => 0,
                    'quantity_incoming' => 0,
                    'reorder_point' => $this->settingsService->getInteger('inventory', 'default_reorder_point', 3),
                    'reorder_quantity' => 10,
                    'unit_cost_npr' => $unitCost,
                ]);
            }

            $qtyBefore = (int)$stockLevel->quantity_on_hand;
            $qtyAfter = $qtyBefore + $quantityDelta;

            // Enforce negative stock disallowance (exempt POS sales and count reconciliations per authoritative business rule)
            $negativePolicy = $this->settingsService->getString('inventory', 'negative_stock_policy', 'strictly_forbidden');
            if ($qtyAfter < 0 && $negativePolicy === 'strictly_forbidden' && $movementType !== 'sale_pos' && $movementType !== 'count_reconciliation') {
                throw new \RuntimeException("Negative stock is not permitted for product ID {$productId}. Current on-hand: {$qtyBefore}, Attempted delta: {$quantityDelta}");
            }

            // Update Stock Level
            $stockLevel->quantity_on_hand = $qtyAfter;
            if (isset($data['unit_cost_npr']) && (float)$data['unit_cost_npr'] > 0) {
                $stockLevel->unit_cost_npr = (float)$data['unit_cost_npr'];
            }
            $stockLevel->save();

            // Traceable movement creation
            $movementNumber = StockMovement::generateNextMovementNumber();
            $unitCost = (float)$stockLevel->unit_cost_npr;
            $totalCost = round(abs($quantityDelta) * $unitCost, 2);

            $availBefore = $qtyBefore - (int)$stockLevel->quantity_reserved - (int)$stockLevel->quarantined_quantity - (int)$stockLevel->damaged_quantity;
            $availAfter = $qtyAfter - (int)$stockLevel->quantity_reserved - (int)$stockLevel->quarantined_quantity - (int)$stockLevel->damaged_quantity;

            $movement = StockMovement::create([
                'movement_number' => $movementNumber,
                'warehouse_id' => $warehouseId,
                'target_warehouse_id' => $data['target_warehouse_id'] ?? null,
                'product_id' => $productId,
                'variant_id' => $variantId,
                'movement_type' => $movementType,
                'quantity' => $quantityDelta,
                'quantity_before' => $qtyBefore,
                'quantity_after' => $qtyAfter,
                'available_before' => $availBefore,
                'available_after' => $availAfter,
                'unit_cost_npr' => $unitCost,
                'total_cost_npr' => $totalCost,
                'currency' => $data['currency'] ?? 'NPR',
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'journal_entry_id' => $data['journal_entry_id'] ?? null,
                'user_id' => $user?->id ?? auth()->id(),
                'reason' => $data['reason'] ?? 'Standard inventory movement',
                'notes' => $data['notes'] ?? null,
            ]);

            // Sync legacy scalar columns across Products & Variants
            $this->syncLegacyStockAttributes($productId, $variantId);

            return $movement;
        });
    }

    /**
     * Synchronize legacy scalar columns (products.quantity, product_variants.stock_quantity)
     * from authoritative StockLevel records so that all storefront and API queries remain perfectly in sync.
     */
    public function syncLegacyStockAttributes(int $productId, ?int $variantId = null): void
    {
        $product = Product::find($productId);
        if (!$product) {
            return;
        }

        // 1. Sync Product Variant(s) if applicable
        $targetVariants = $variantId
            ? ProductVariant::where('id', $variantId)->get()
            : ProductVariant::where('product_id', $productId)->get();

        foreach ($targetVariants as $variant) {
            $totalVariantStock = (int)StockLevel::where('variant_id', $variant->id)
                ->selectRaw('COALESCE(SUM(quantity_on_hand - quantity_reserved - quarantined_quantity - damaged_quantity), 0) as avail')
                ->value('avail');

            $variant->updateQuietly(['stock_quantity' => (int)$totalVariantStock]);
        }

        // 2. Sync Product overall quantity
        $hasVariants = ProductVariant::where('product_id', $productId)->exists();
        if ($hasVariants) {
            $variantStock = (int)ProductVariant::where('product_id', $productId)
                ->where('is_active', true)
                ->sum('stock_quantity');
            $unassignedStock = (int)StockLevel::where('product_id', $productId)
                ->whereNull('variant_id')
                ->selectRaw('COALESCE(SUM(quantity_on_hand - quantity_reserved - quarantined_quantity - damaged_quantity), 0) as avail')
                ->value('avail');
            $totalProductQty = $variantStock + $unassignedStock;
        } else {
            $totalProductQty = (int)StockLevel::where('product_id', $productId)
                ->whereNull('variant_id')
                ->selectRaw('COALESCE(SUM(quantity_on_hand - quantity_reserved - quarantined_quantity - damaged_quantity), 0) as avail')
                ->value('avail');
        }

        $product->quantity = (int)$totalProductQty;
        $product->saveQuietly();
    }

    /**
     * Get available physical stock across warehouses or for a specific warehouse.
     */
    public function getAvailableStock(
        int $productId,
        ?int $variantId = null,
        ?int $warehouseId = null
    ): int {
        $query = StockLevel::where('product_id', $productId);

        if ($variantId) {
            $query->where('variant_id', $variantId);
        } else {
            $query->whereNull('variant_id');
        }

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $onHand = (int)$query->sum('quantity_on_hand');
        $reserved = (int)$query->sum('quantity_reserved');

        return (int)($onHand - $reserved);
    }

    /**
     * Commerce Integration: Fulfill an Order by deducting stock from warehouse and recording stock movement.
     */
    public function fulfillOrderStock(Order $order, ?User $user = null): void
    {
        $defaultWh = $this->getDefaultWarehouse();

        DB::transaction(function () use ($order, $defaultWh, $user) {
            $order->load(['items.product', 'items.variant']);

            // Idempotency check: skip if order stock has already been fulfilled
            $alreadyFulfilled = DB::table('inventory_stock_movements')
                ->where('reference_type', 'order')
                ->where('reference_id', $order->id)
                ->where('movement_type', 'sale_order')
                ->exists();

            if ($alreadyFulfilled) {
                Log::info("Order #{$order->order_number} already fulfilled. Skipping duplicate fulfillment.");
                return;
            }

            $totalCogsnpr = 0.00;
            $sortedItems = $order->items->sortBy(fn($it) => (int)$it->product_id . '_' . (int)($it->variant_id ?? 0));

            foreach ($sortedItems as $item) {
                if ($item->is_preorder) {
                    // For pre-orders, increase preorder count on product
                    if ($item->product_id) {
                        Product::where('id', $item->product_id)->increment('preorder_count', $item->quantity);
                    }
                    continue;
                }

                // Check for matching active stock reservation for this order
                $activeReservation = StockReservation::where('status', 'active')
                    ->where('warehouse_id', $defaultWh->id)
                    ->where('product_id', $item->product_id)
                    ->where(function ($q) use ($item) {
                        if ($item->variant_id) {
                            $q->where('variant_id', $item->variant_id);
                        } else {
                            $q->whereNull('variant_id');
                        }
                    })
                    ->where(function ($q) use ($order) {
                        $q->where(function ($sub) use ($order) {
                            $sub->where('reference_type', 'online_order')
                                ->where('reference_id', $order->id);
                        })->orWhere('cart_token', $order->order_number);
                    })
                    ->lockForUpdate()
                    ->first();

                if ($activeReservation) {
                    $movement = $this->convertReservationToFulfillment($activeReservation, $order, $user);
                } else {
                    $movement = $this->recordStockMovement([
                        'warehouse_id' => $defaultWh->id,
                        'product_id' => $item->product_id,
                        'variant_id' => $item->variant_id,
                        'movement_type' => 'sale_order',
                        'quantity' => -abs((int)$item->quantity),
                        'reference_type' => 'order',
                        'reference_id' => $order->id,
                        'reference_number' => $order->order_number,
                        'reason' => "E-commerce order #{$order->order_number} fulfillment",
                        'notes' => "Customer: {$order->first_name} {$order->last_name} ({$order->shipping_country})",
                    ], $user);
                }

                $totalCogsnpr += (float)$movement->total_cost_npr;
            }

            // Accounting COGS Auto-posting
            if ($totalCogsnpr > 0 && $this->accountingService) {
                try {
                    $this->accountingService->ensureDefaultChartOfAccounts();
                    $cogsAccount = Account::where('account_number', '5110')->first() ?: Account::where('account_number', '1210')->first();
                    $invAccount = Account::where('account_number', '1210')->first() ?: Account::where('account_number', '2210')->first();

                    if ($invAccount && $cogsAccount) {
                        $this->accountingService->postJournalEntry([
                            'voucher_date' => $order->created_at ? $order->created_at->toDateString() : date('Y-m-d'),
                            'entry_type' => 'cogs',
                            'reference_type' => 'order_cogs',
                            'reference_id' => $order->id,
                            'reference_number' => $order->order_number,
                            'description' => "Cost of Goods Sold (COGS) for order #{$order->order_number}",
                            'currency' => 'NPR',
                            'exchange_rate_to_npr' => 1.000000,
                        ], [
                            [
                                'account_id' => $cogsAccount->id,
                                'debit' => $totalCogsnpr,
                                'credit' => 0.00,
                                'amount_currency' => $totalCogsnpr,
                                'description' => "Inventory COGS expense: Order #{$order->order_number}",
                            ],
                            [
                                'account_id' => $invAccount->id,
                                'debit' => 0.00,
                                'credit' => $totalCogsnpr,
                                'amount_currency' => $totalCogsnpr,
                                'description' => "Inventory asset relief: Order #{$order->order_number}",
                            ],
                        ], $user);
                    }
                } catch (\Throwable $e) {
                    Log::warning("Accounting COGS auto-posting failed for order #{$order->order_number}: " . $e->getMessage());
                }
            }
        });
    }

    /**
     * Commerce Integration: Process Customer Returns and restock inventory.
     */
    public function recordReturnStock(Order $order, array $items, string $reason = '', ?User $user = null): void
    {
        $defaultWh = $this->getDefaultWarehouse();

        DB::transaction(function () use ($order, $items, $reason, $defaultWh, $user) {
            foreach ($items as $item) {
                $qty = (int)($item['quantity'] ?? 1);
                $productId = (int)$item['product_id'];
                $variantId = !empty($item['variant_id']) ? (int)$item['variant_id'] : null;

                $this->recordStockMovement([
                    'warehouse_id' => $defaultWh->id,
                    'product_id' => $productId,
                    'variant_id' => $variantId,
                    'movement_type' => 'return_customer',
                    'quantity' => abs($qty),
                    'reference_type' => 'order_return',
                    'reference_id' => $order->id,
                    'reference_number' => $order->order_number,
                    'reason' => $reason ?: "Customer return for order #{$order->order_number}",
                ], $user);
            }
        });
    }

    /**
     * Alias for recordReturnStock
     */
    public function restoreOrderReturn(Order $order, array $items, string $reason = '', ?User $user = null): void
    {
        $this->recordReturnStock($order, $items, $reason, $user);
    }

    /**
     * Offline Sales / POS Integration: Deduct stock from the shared central inventory pool.
     * Both Terminal 1 and Terminal 2 sell from this single shared inventory pool.
     */
    public function deductPosSale(OfflineSale $sale, ?User $user = null, ?int $warehouseId = null): void
    {
        // Central Shared Warehouse pool across POS terminals / showrooms or explicitly selected warehouse
        $targetWh = ($warehouseId ? Warehouse::find($warehouseId) : null)
            ?? ($sale->warehouse_id ? Warehouse::find($sale->warehouse_id) : null)
            ?? $this->getDefaultWarehouse();

        DB::transaction(function () use ($sale, $targetWh, $user) {
            $sale->load('items.product');

            foreach ($sale->items as $item) {
                $this->recordStockMovement([
                    'warehouse_id' => $targetWh->id,
                    'product_id' => $item->product_id,
                    'variant_id' => $item->variant_id,
                    'movement_type' => 'sale_pos',
                    'quantity' => -abs((int)$item->quantity),
                    'reference_type' => 'offline_sale',
                    'reference_id' => $sale->id,
                    'reference_number' => $sale->sale_number,
                    'reason' => "POS sale #{$sale->sale_number} ({$sale->customer_name})",
                    'notes' => "Payment: {$sale->payment_method}, Channel: {$sale->sales_channel}, Showroom: " . ($sale->warehouse?->name ?? 'Showroom'),
                ], $user);
            }
        });
    }

    /**
     * Offline Sales / POS Integration: Restore stock for a voided POS sale to shared inventory pool.
     */
    public function restorePosSale(OfflineSale $sale, string $reason, ?User $user = null): void
    {
        // Central Shared Warehouse pool or sale warehouse
        $targetWh = ($sale->warehouse_id ? Warehouse::find($sale->warehouse_id) : null)
            ?? $this->getDefaultWarehouse();

        DB::transaction(function () use ($sale, $reason, $targetWh, $user) {
            $sale->load('items.product');

            foreach ($sale->items as $item) {
                $this->recordStockMovement([
                    'warehouse_id' => $targetWh->id,
                    'product_id' => $item->product_id,
                    'variant_id' => $item->variant_id,
                    'movement_type' => 'return_customer',
                    'quantity' => abs((int)$item->quantity),
                    'reference_type' => 'offline_sale_void',
                    'reference_id' => $sale->id,
                    'reference_number' => $sale->sale_number,
                    'reason' => $reason ?: "Voided POS sale #{$sale->sale_number}",
                ], $user);
            }
        });
    }

    /**
     * Purchasing Integration: Receive Purchase Order goods, increase physical stock, and record ledger entries.
     */
    public function receivePurchaseOrder(PurchaseOrder $po, array $receivedQuantities, ?User $user = null): PurchaseOrder
    {
        if (in_array($po->status, ['draft', 'submitted', 'cancelled', 'rejected'])) {
            throw new \RuntimeException("Cannot receive goods for purchase order #{$po->po_number} in '{$po->status}' status. Approval is required before receiving.");
        }

        $tolerancePct = $this->settingsService->getDecimal('purchasing', 'receiving_tolerance_percentage', 5.0);
        $overReceiptPolicy = $this->settingsService->getString('purchasing', 'over_receipt_handling', 'reject');
        $allowPartial = $this->settingsService->getBoolean('purchasing', 'allow_partial_receiving', true);
        $shortReceiptPolicy = $this->settingsService->getString('purchasing', 'short_receipt_handling', 'allow_partial');

        return DB::transaction(function () use ($po, $receivedQuantities, $user, $tolerancePct, $overReceiptPolicy, $allowPartial, $shortReceiptPolicy) {
            $po->load(['items.product', 'items.variant', 'warehouse', 'supplier']);

            $destWarehouseId = $po->warehouse_id;
            $allFullyReceived = true;
            $totalReceivedCostnpr = 0.00;

            foreach ($po->items as $item) {
                $newReceived = isset($receivedQuantities[$item->id])
                    ? (int)$receivedQuantities[$item->id]
                    : ((int)$item->quantity_ordered - (int)$item->quantity_received);

                if ($newReceived <= 0) {
                    if ($item->quantity_received < $item->quantity_ordered) {
                        $allFullyReceived = false;
                    }
                    continue;
                }

                // Tolerance verification: verify over-receipt does not exceed tolerance %
                $maxAllowed = (int)ceil($item->quantity_ordered * (1 + ($tolerancePct / 100)));
                if (((int)$item->quantity_received + $newReceived) > $maxAllowed) {
                    $sku = $item->product?->sku ?? 'ITEM';
                    if ($overReceiptPolicy === 'warn') {
                        Log::warning("Over-receipt warning for PO #{$po->po_number} SKU {$sku}: receiving {$newReceived} exceeds tolerance threshold {$maxAllowed}.");
                    } else {
                        throw new \RuntimeException("Over-receipt rejected for item SKU {$sku}: receiving {$newReceived} exceeds allowable threshold limit of {$maxAllowed} (configured tolerance: {$tolerancePct}%).");
                    }
                }

                $item->quantity_received = (int)$item->quantity_received + $newReceived;
                $item->save();

                if ($item->quantity_received < $item->quantity_ordered) {
                    $allFullyReceived = false;
                }

                $unitCostnpr = (float)$item->unit_cost_npr;
                $itemCostnpr = round($newReceived * $unitCostnpr, 2);
                $totalReceivedCostnpr += $itemCostnpr;

                $this->recordStockMovement([
                    'warehouse_id' => $destWarehouseId,
                    'product_id' => $item->product_id,
                    'variant_id' => $item->variant_id,
                    'movement_type' => 'purchase_receive',
                    'quantity' => $newReceived,
                    'unit_cost_npr' => $unitCostnpr,
                    'reference_type' => 'purchase_order',
                    'reference_id' => $po->id,
                    'reference_number' => $po->po_number,
                    'reason' => "PO #{$po->po_number} receiving from {$po->supplier?->name}",
                ], $user);
            }

            if (!$allFullyReceived && !$allowPartial) {
                throw new \RuntimeException("Partial goods receiving is prohibited by purchasing configuration. Entire purchase order must be received in full.");
            }

            $po->status = $allFullyReceived
                ? 'received'
                : ($shortReceiptPolicy === 'close_order' ? 'received' : 'partially_received');
            $po->received_date = now()->toDateString();
            $po->save();

            // Auto-post double-entry voucher to Nepal NAS bookkeeping ledger
            if ($totalReceivedCostnpr > 0 && $this->accountingService) {
                try {
                    $this->accountingService->ensureDefaultChartOfAccounts();
                    $invAccount = Account::where('account_number', '1210')->first() ?: Account::where('account_number', '2210')->first();
                    $payablesAccount = Account::where('account_number', '2110')->first() ?: Account::where('account_number', '2710')->first();

                    if ($invAccount && $payablesAccount) {
                        $voucher = $this->accountingService->postJournalEntry([
                            'voucher_date' => now()->toDateString(),
                            'entry_type' => 'purchase',
                            'reference_type' => 'purchase_order',
                            'reference_id' => $po->id,
                            'reference_number' => $po->po_number,
                            'description' => "Inventory receipt for purchase order #{$po->po_number} ({$po->supplier?->name})",
                            'currency' => 'NPR',
                            'exchange_rate_to_npr' => 1.000000,
                        ], [
                            [
                                'account_id' => $invAccount->id,
                                'debit' => $totalReceivedCostnpr,
                                'credit' => 0.00,
                                'description' => "Inventory receipt PO #{$po->po_number}",
                            ],
                            [
                                'account_id' => $payablesAccount->id,
                                'debit' => 0.00,
                                'credit' => $totalReceivedCostnpr,
                                'description' => "Accounts payable {$po->supplier?->name}",
                            ],
                        ], $user);

                        $po->journal_entry_id = $voucher->id;
                        $po->saveQuietly();
                    }
                } catch (\Throwable $e) {
                    Log::warning("Accounting auto-posting failed for PO #{$po->po_number}: " . $e->getMessage());
                }
            }

            return $po;
        });
    }

    /**
     * Inter-Warehouse Transfer: Dispatch items from source warehouse.
     */
    public function dispatchTransfer(StockTransfer $transfer, ?User $user = null): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $user) {
            $transfer->load('items');

            if ($transfer->status !== 'draft') {
                throw new RuntimeException("Transfer #{$transfer->transfer_number} cannot be dispatched from status {$transfer->status}.");
            }

            foreach ($transfer->items as $item) {
                $qty = (int)$item->quantity_sent;

                $this->recordStockMovement([
                    'warehouse_id' => $transfer->source_warehouse_id,
                    'target_warehouse_id' => $transfer->destination_warehouse_id,
                    'product_id' => $item->product_id,
                    'variant_id' => $item->variant_id,
                    'movement_type' => 'transfer_out',
                    'quantity' => -abs($qty),
                    'reference_type' => 'stock_transfer',
                    'reference_id' => $transfer->id,
                    'reference_number' => $transfer->transfer_number,
                    'reason' => "Transfer dispatch #{$transfer->transfer_number}",
                ], $user);
            }

            $transfer->status = 'in_transit';
            $transfer->sent_at = now();
            $transfer->initiated_by = $user?->id ?? auth()->id();
            $transfer->save();

            return $transfer;
        });
    }

    /**
     * Inter-Warehouse Transfer: Receive items at destination warehouse.
     */
    public function receiveTransfer(StockTransfer $transfer, array $receivedQuantities = [], ?User $user = null): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $receivedQuantities, $user) {
            $transfer->load('items');

            if ($transfer->status !== 'in_transit') {
                throw new RuntimeException("Transfer #{$transfer->transfer_number} is not in transit.");
            }

            foreach ($transfer->items as $item) {
                $qty = isset($receivedQuantities[$item->id])
                    ? (int)$receivedQuantities[$item->id]
                    : (int)$item->quantity_sent;

                $item->quantity_received = $qty;
                $item->save();

                $this->recordStockMovement([
                    'warehouse_id' => $transfer->destination_warehouse_id,
                    'product_id' => $item->product_id,
                    'variant_id' => $item->variant_id,
                    'movement_type' => 'transfer_in',
                    'quantity' => abs($qty),
                    'reference_type' => 'stock_transfer',
                    'reference_id' => $transfer->id,
                    'reference_number' => $transfer->transfer_number,
                    'reason' => "Transfer received #{$transfer->transfer_number}",
                ], $user);
            }

            $transfer->status = 'completed';
            $transfer->received_at = now();
            $transfer->received_by = $user?->id ?? auth()->id();
            $transfer->save();

            return $transfer;
        });
    }

    /**
     * Stock Adjustments: Apply damage, loss, found, or correction delta.
     */
    public function applyAdjustment(StockAdjustment $adjustment, ?User $user = null): StockAdjustment
    {
        return DB::transaction(function () use ($adjustment, $user) {
            $mType = match ($adjustment->type) {
                'damage' => 'damage',
                'loss' => 'loss',
                'found' => 'adjustment_gain',
                'correction' => ($adjustment->quantity >= 0 ? 'adjustment_gain' : 'adjustment_loss'),
                default => 'correction',
            };

            $movement = $this->recordStockMovement([
                'warehouse_id' => $adjustment->warehouse_id,
                'product_id' => $adjustment->product_id,
                'variant_id' => $adjustment->variant_id,
                'movement_type' => $mType,
                'quantity' => (int)$adjustment->quantity,
                'unit_cost_npr' => (float)$adjustment->unit_cost_npr,
                'reference_type' => 'stock_adjustment',
                'reference_id' => $adjustment->id,
                'reference_number' => $adjustment->adjustment_number,
                'reason' => "Adjustment: {$adjustment->reason}",
                'notes' => $adjustment->notes,
            ], $user);

            $adjustment->status = 'approved';
            $adjustment->approved_by = $user?->id ?? auth()->id();

            // Auto-post accounting shrinkage voucher
            $totalVal = abs((float)$adjustment->total_value_npr);
            if ($this->accountingService) {
                try {
                    $voucher = $this->accountingService->recordStockAdjustmentAccounting($adjustment, $user);
                    if ($voucher) {
                        $adjustment->journal_entry_id = $voucher->id;
                    }
                } catch (\Throwable $e) {
                    Log::warning("Accounting adjustment voucher failed for #{$adjustment->adjustment_number}: " . $e->getMessage());
                }
            }

            $adjustment->save();

            return $adjustment;
        });
    }

    /**
     * Stock Counts & Physical Audit: Reconcile variances and adjust stock.
     */
    public function reconcileStockCount(StockCount $stockCount, ?User $user = null): StockCount
    {
        return DB::transaction(function () use ($stockCount, $user) {
            $stockCount->loadMissing('items');

            $whId = $stockCount->warehouse_id;
            $netVarianceValue = 0.00;

            foreach ($stockCount->items as $item) {
                $variance = (int)$item->variance_quantity;
                if ($variance === 0) {
                    $item->is_reconciled = true;
                    $item->save();
                    continue;
                }

                $this->recordStockMovement([
                    'warehouse_id' => $whId,
                    'product_id' => $item->product_id,
                    'variant_id' => $item->variant_id,
                    'movement_type' => 'count_reconciliation',
                    'quantity' => $variance,
                    'unit_cost_npr' => (float)$item->unit_cost_npr,
                    'reference_type' => 'stock_count',
                    'reference_id' => $stockCount->id,
                    'reference_number' => $stockCount->count_number,
                    'reason' => "Physical count reconciliation (#{$stockCount->count_number})",
                ], $user);

                $item->is_reconciled = true;
                $item->save();
                $netVarianceValue += (float)$item->variance_value_npr;
            }

            $stockCount->status = 'reconciled';
            $stockCount->reconciled_by = $user?->id ?? auth()->id();
            $stockCount->reconciled_at = now();
            $stockCount->recalculateTotals();

            // Post Net Variance to Accounting
            if (abs($netVarianceValue) > 0 && $this->accountingService) {
                try {
                    $this->accountingService->ensureDefaultChartOfAccounts();
                    $invAccount = Account::where('account_number', '1210')->first() ?: Account::where('account_number', '2210')->first();
                    $adjAccount = Account::where('account_number', '5110')->first() ?: (Account::where('account_number', '1230')->first() ?: Account::where('category', 'cogs')->first());

                    if ($invAccount && $adjAccount) {
                        $isGain = $netVarianceValue > 0;
                        $debitAcc = $isGain ? $invAccount->id : $adjAccount->id;
                        $creditAcc = $isGain ? $adjAccount->id : $invAccount->id;
                        $absVal = abs($netVarianceValue);

                        $this->accountingService->postJournalEntry([
                            'voucher_date' => now()->toDateString(),
                            'entry_type' => 'cogs',
                            'reference_type' => 'stock_count',
                            'reference_id' => $stockCount->id,
                            'reference_number' => $stockCount->count_number,
                            'description' => "Physical stock count variance #{$stockCount->count_number}",
                            'currency' => 'NPR',
                            'exchange_rate_to_npr' => 1.000000,
                        ], [
                            [
                                'account_id' => $debitAcc,
                                'debit' => $absVal,
                                'credit' => 0.00,
                                'description' => "Physical count inventory adjustment",
                            ],
                            [
                                'account_id' => $creditAcc,
                                'debit' => 0.00,
                                'credit' => $absVal,
                                'description' => "Physical count offsetting entry",
                            ],
                        ], $user);
                    }
                } catch (\Throwable $e) {
                    Log::warning("Accounting audit voucher failed for count #{$stockCount->count_number}: " . $e->getMessage());
                }
            }

            return $stockCount;
        });
    }

    /**
     * Execute full or cycle stock count reconciliation across counted inventory items.
     * Atomically creates StockCount and StockCountItem records, applies StockMovements,
     * updates StockLevels, ProductVariants, and Products, and posts variance accounting entries.
     *
     * @param int $warehouseId
     * @param array<int, array{product_id: int, variant_id: ?int, expected_quantity: int, counted_quantity: int, unit_cost?: float, notes?: ?string}> $countedItems
     * @param string $countType 'full' | 'cycle'
     * @param string|null $notes
     * @param User|null $user
     * @return StockCount
     */
    public function executeStockCountReconciliation(
        int $warehouseId,
        array $countedItems,
        string $countType = 'full',
        ?string $notes = null,
        ?User $user = null
    ): StockCount {
        return DB::transaction(function () use ($warehouseId, $countedItems, $countType, $notes, $user) {
            $warehouse = Warehouse::findOrFail($warehouseId);
            $countNumber = StockCount::generateNextCountNumber();

            $stockCount = StockCount::create([
                'count_number' => $countNumber,
                'warehouse_id' => $warehouseId,
                'count_type' => $countType,
                'count_date' => now()->toDateString(),
                'status' => 'reconciled',
                'conducted_by' => $user?->id ?? auth()->id(),
                'approved_by' => $user?->id ?? auth()->id(),
                'approved_at' => now(),
                'reconciled_by' => $user?->id ?? auth()->id(),
                'reconciled_at' => now(),
                'notes' => $notes ?: "Physical count reconciliation ({$countType}) for {$warehouse->name}",
            ]);

            $netVarianceValue = 0.00;

            foreach ($countedItems as $item) {
                $productId = (int)$item['product_id'];
                $variantId = !empty($item['variant_id']) ? (int)$item['variant_id'] : null;
                $expected = (int)$item['expected_quantity'];
                $counted = (int)$item['counted_quantity'];
                $variance = $counted - $expected;

                $unitCost = isset($item['unit_cost']) && (float)$item['unit_cost'] > 0
                    ? (float)$item['unit_cost']
                    : $this->resolveProductUnitCostNpr($productId, $variantId);

                $varianceValue = round($variance * $unitCost, 2);

                StockCountItem::create([
                    'stock_count_id' => $stockCount->id,
                    'product_id' => $productId,
                    'variant_id' => $variantId,
                    'expected_quantity' => $expected,
                    'counted_quantity' => $counted,
                    'variance_quantity' => $variance,
                    'unit_cost_npr' => $unitCost,
                    'variance_value_npr' => $varianceValue,
                    'is_reconciled' => true,
                ]);

                if ($variance !== 0) {
                    $this->recordStockMovement([
                        'warehouse_id' => $warehouseId,
                        'product_id' => $productId,
                        'variant_id' => $variantId,
                        'movement_type' => 'count_reconciliation',
                        'quantity' => $variance,
                        'unit_cost_npr' => $unitCost,
                        'reference_type' => 'stock_count',
                        'reference_id' => $stockCount->id,
                        'reference_number' => $stockCount->count_number,
                        'reason' => "Physical count reconciliation (#{$stockCount->count_number})",
                        'notes' => $item['notes'] ?? null,
                    ], $user);

                    $netVarianceValue += $varianceValue;
                }
            }

            $stockCount->recalculateTotals();

            // Post Net Variance to Accounting
            if (abs($netVarianceValue) > 0 && $this->accountingService) {
                try {
                    $this->accountingService->ensureDefaultChartOfAccounts();
                    $invAccount = Account::where('account_number', '1210')->first() ?: Account::where('account_number', '2210')->first();
                    $adjAccount = Account::where('account_number', '5110')->first() ?: (Account::where('account_number', '1230')->first() ?: Account::where('category', 'cogs')->first());

                    if ($invAccount && $adjAccount) {
                        $isGain = $netVarianceValue > 0;
                        $debitAcc = $isGain ? $invAccount->id : $adjAccount->id;
                        $creditAcc = $isGain ? $adjAccount->id : $invAccount->id;
                        $absVal = abs($netVarianceValue);

                        $this->accountingService->postJournalEntry([
                            'voucher_date' => now()->toDateString(),
                            'entry_type' => 'cogs',
                            'reference_type' => 'stock_count',
                            'reference_id' => $stockCount->id,
                            'reference_number' => $stockCount->count_number,
                            'description' => "Physical stock count variance #{$stockCount->count_number}",
                            'currency' => 'NPR',
                            'exchange_rate_to_npr' => 1.000000,
                        ], [
                            [
                                'account_id' => $debitAcc,
                                'debit' => $absVal,
                                'credit' => 0.00,
                                'description' => "Physical count inventory adjustment",
                            ],
                            [
                                'account_id' => $creditAcc,
                                'debit' => 0.00,
                                'credit' => $absVal,
                                'description' => "Physical count offsetting entry",
                            ],
                        ], $user);
                    }
                } catch (\Throwable $e) {
                    Log::warning("Accounting audit voucher failed for count #{$stockCount->count_number}: " . $e->getMessage());
                }
            }

            return $stockCount;
        });
    }

    /**
     * Calculate comprehensive Enterprise Inventory Valuation & KPIs.
     */
    public function getValuationSummary(): array
    {
        $this->ensureDefaultWarehousesAndSuppliers();

        $levels = StockLevel::with(['product', 'variant', 'warehouse'])->get();

        $totalUnitsOnHand = (int)$levels->sum('quantity_on_hand');
        $totalUnitsReserved = (int)$levels->sum('quantity_reserved');
        $totalUnitsIncoming = (int)$levels->sum('quantity_incoming');

        $totalValuationnpr = 0.00;
        $totalRetailPotentialnpr = 0.00;
        $lowStockCount = 0;
        $outOfStockCount = 0;

        foreach ($levels as $level) {
            $qty = max(0, (int)$level->quantity_on_hand);
            $cost = (float)$level->unit_cost_npr;
            $totalValuationnpr += ($qty * $cost);

            // Retail price
            $retailPrice = (float)($level->variant?->price_npr ?: ($level->product?->price_npr ?: $level->product?->price ?: 0));
            $totalRetailPotentialnpr += ($qty * $retailPrice);

            if ($qty <= 0) {
                $outOfStockCount++;
            } elseif ($qty <= (int)$level->reorder_point) {
                $lowStockCount++;
            }
        }

        $totalValuationnpr = round($totalValuationnpr, 2);
        $totalProjectedProfitnpr = max(0, round($totalRetailPotentialnpr - $totalValuationnpr, 2));
        $avgMarginPct = $totalRetailPotentialnpr > 0
            ? round(($totalProjectedProfitnpr / $totalRetailPotentialnpr) * 100, 1)
            : 0.0;

        $totalProducts = Product::count();
        $totalVariants = ProductVariant::count();
        $totalWarehouses = Warehouse::where('is_active', true)->count();
        $totalSuppliers = Supplier::where('is_active', true)->count();
        $pendingTransfers = StockTransfer::where('status', 'in_transit')->count();
        $openPurchaseOrders = PurchaseOrder::whereIn('status', ['ordered', 'in_transit', 'partially_received'])->count();

        return [
            'total_units_on_hand' => $totalUnitsOnHand,
            'total_units_reserved' => $totalUnitsReserved,
            'total_units_incoming' => $totalUnitsIncoming,
            'total_valuation_npr' => round($totalValuationnpr, 2),
            'total_valuation_npr' => $totalValuationnpr,
            'total_retail_potential_npr' => round($totalRetailPotentialnpr, 2),
            'total_projected_profit_npr' => $totalProjectedProfitnpr,
            'average_margin_percentage' => $avgMarginPct,
            'low_stock_count' => $lowStockCount,
            'out_of_stock_count' => $outOfStockCount,
            'total_products' => $totalProducts,
            'total_variants' => $totalVariants,
            'total_warehouses' => $totalWarehouses,
            'total_suppliers' => $totalSuppliers,
            'pending_transfers' => $pendingTransfers,
            'open_purchase_orders' => $openPurchaseOrders,
            'valuation_method' => $this->settingsService->getString('inventory', 'valuation_method', 'FIFO'),
        ];
    }

    /**
     * Get inventory valuation breakdown per warehouse.
     */
    public function getWarehouseBreakdown(): array
    {
        $this->ensureDefaultWarehousesAndSuppliers();

        $warehouses = Warehouse::with(['stockLevels'])->where('is_active', true)->get();
        $breakdown = [];
        $nprRate = (float) \App\Models\Setting::get('npr_to_npr_rate', 1.0);
        if ($nprRate <= 0) {
            $nprRate = 1.0;
        }

        foreach ($warehouses as $wh) {
            $units = (int)$wh->stockLevels->sum('quantity_on_hand');
            $valuationnpr = 0.00;

            foreach ($wh->stockLevels as $level) {
                $qty = max(0, (int)$level->quantity_on_hand);
                $valuationnpr += ($qty * (float)$level->unit_cost_npr);
            }

            $breakdown[] = [
                'id' => $wh->id,
                'code' => $wh->code,
                'name' => $wh->name,
                'type' => $wh->type,
                'total_units' => $units,
                'valuation_npr' => round($valuationnpr, 2),
                'valuation_npr' => round($valuationnpr / $nprRate, 2),
                'allow_sales' => $wh->allow_sales,
            ];
        }

        return $breakdown;
    }

    /**
     * Resolves product landed unit cost in NPR (Nepal Rupees).
     */
    public function resolveProductUnitCostNpr(int $productId, ?int $variantId = null): float
    {
        if ($variantId) {
            $variant = ProductVariant::find($variantId);
            if ($variant) {
                if ((float)($variant->cost_price ?? 0) > 0) {
                    return (float)$variant->cost_price;
                }
                if ((float)($variant->cost_price_npr ?? 0) > 0) {
                    return (float)$variant->cost_price_npr;
                }
            }
        }

        $product = Product::find($productId);
        if ($product) {
            if ((float)($product->cost_price ?? 0) > 0) {
                return (float)$product->cost_price;
            }
            if ((float)($product->cost_price_npr ?? 0) > 0) {
                return (float)$product->cost_price_npr;
            }
            if ((float)($product->price ?? 0) > 0) {
                return round((float)$product->price * 0.45, 2);
            }
            if ((float)($product->price_npr ?? 0) > 0) {
                return round((float)$product->price_npr * 0.45, 2);
            }
        }

        return 0.00;
    }


    /**
     * Reservation Subsystem: Create an atomic stock reservation for checkout/orders.
     * Prevents overselling during active checkout sessions with lockForUpdate().
     */
    public function createReservation(
        int|array $warehouseOrData,
        int|User|null $productIdOrUser = null,
        ?int $variantId = null,
        int $qty = 1,
        string $refType = 'cart',
        ?int $refId = null,
        ?string $cartToken = null,
        int $ttlMinutes = 30,
        ?User $user = null
    ): StockReservation {
        if (is_array($warehouseOrData)) {
            $data = $warehouseOrData;
            $user = ($productIdOrUser instanceof User) ? $productIdOrUser : ($user ?? auth()->user());
            $warehouseId = (int)($data['warehouse_id'] ?? $this->getDefaultWarehouse()->id);
            $productId = (int)($data['product_id'] ?? 0);
            $variantId = !empty($data['variant_id']) ? (int)$data['variant_id'] : null;
            $qty = (int)($data['quantity'] ?? 1);
            $refType = (string)($data['reservation_type'] ?? ($data['reference_type'] ?? 'cart'));
            $refId = !empty($data['reference_id']) ? (int)$data['reference_id'] : null;
            $cartToken = !empty($data['cart_token']) ? (string)$data['cart_token'] : null;
            $defaultTtl = $this->settingsService->getInteger('inventory', 'reservation_ttl_minutes', 30);
            $ttlMinutes = (int)($data['expires_in_minutes'] ?? ($data['ttl_minutes'] ?? $defaultTtl));
        } else {
            $warehouseId = (int)$warehouseOrData;
            $productId = (int)$productIdOrUser;
            if ($ttlMinutes === 30) {
                $ttlMinutes = $this->settingsService->getInteger('inventory', 'reservation_ttl_minutes', 30);
            }
        }

        return DB::transaction(function () use ($warehouseId, $productId, $variantId, $qty, $refType, $refId, $cartToken, $ttlMinutes, $user) {
            $stockLevel = StockLevel::where('warehouse_id', $warehouseId)
                ->where('product_id', $productId)
                ->where('variant_id', $variantId)
                ->lockForUpdate()
                ->first();

            if (!$stockLevel) {
                $stockLevel = $this->getOrCreateStockLevel($warehouseId, $productId, $variantId);
                $stockLevel = StockLevel::where('id', $stockLevel->id)->lockForUpdate()->first();
            }

            $available = max(0, (int)$stockLevel->quantity_on_hand - (int)$stockLevel->quantity_reserved - (int)$stockLevel->quarantined_quantity - (int)$stockLevel->damaged_quantity);
            if ($available < $qty) {
                $productName = Product::find($productId)?->name ?? "Product #{$productId}";
                throw new \RuntimeException("Insufficient available stock for {$productName}. Available: {$available}, Requested: {$qty}");
            }

            // Increment reserved quantity on StockLevel
            $stockLevel->quantity_reserved += $qty;
            $stockLevel->save();

            // Create Reservation record
            return StockReservation::create([
                'reservation_number' => StockReservation::generateNextReservationNumber(),
                'reference_type' => $refType,
                'reference_id' => $refId,
                'cart_token' => $cartToken,
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'variant_id' => $variantId,
                'quantity' => $qty,
                'status' => 'active',
                'expires_at' => now()->addMinutes($ttlMinutes),
                'created_by' => $user?->id ?? auth()->id(),
            ]);
        });
    }

    /**
     * Reservation Subsystem: Release an active reservation and restore available stock.
     */
    public function releaseReservation(int|StockReservation $reservation, string $reason = 'expired'): void
    {
        DB::transaction(function () use ($reservation, $reason) {
            $res = is_numeric($reservation)
                ? StockReservation::where('id', $reservation)->lockForUpdate()->first()
                : StockReservation::where('id', $reservation->id)->lockForUpdate()->first();

            if (!$res || $res->status !== 'active') {
                return;
            }

            $stockLevel = StockLevel::where('warehouse_id', $res->warehouse_id)
                ->where('product_id', $res->product_id)
                ->where('variant_id', $res->variant_id)
                ->lockForUpdate()
                ->first();

            if ($stockLevel) {
                $stockLevel->quantity_reserved = max(0, $stockLevel->quantity_reserved - (int)$res->quantity);
                $stockLevel->save();
            }

            $res->status = ($reason === 'expired' ? 'expired' : ($reason === 'cancelled' ? 'cancelled' : 'released'));
            $res->released_at = now();
            $res->notes = "Released: {$reason}";
            $res->save();
        });
    }

    /**
     * Reservation Subsystem: Convert active reservation directly into order fulfillment.
     */
    public function convertReservationToFulfillment(int|StockReservation $reservation, Order $order, ?User $user = null): StockMovement
    {
        return DB::transaction(function () use ($reservation, $order, $user) {
            $res = is_numeric($reservation)
                ? StockReservation::where('id', $reservation)->lockForUpdate()->first()
                : StockReservation::where('id', $reservation->id)->lockForUpdate()->first();

            if (!$res || $res->status !== 'active') {
                throw new \RuntimeException("Reservation is no longer active for conversion.");
            }

            // Decrement reserved quantity
            $stockLevel = StockLevel::where('warehouse_id', $res->warehouse_id)
                ->where('product_id', $res->product_id)
                ->where('variant_id', $res->variant_id)
                ->lockForUpdate()
                ->first();

            if ($stockLevel) {
                $stockLevel->quantity_reserved = max(0, $stockLevel->quantity_reserved - (int)$res->quantity);
                $stockLevel->save();
            }

            // Record fulfillment movement
            $movement = $this->recordStockMovement([
                'warehouse_id' => $res->warehouse_id,
                'product_id' => $res->product_id,
                'variant_id' => $res->variant_id,
                'movement_type' => 'sale_order',
                'quantity' => -abs((int)$res->quantity),
                'reference_type' => 'order',
                'reference_id' => $order->id,
                'reference_number' => $order->order_number,
                'reason' => "Fulfillment converted from reservation #{$res->reservation_number}",
            ], $user);

            $res->status = 'converted';
            $res->reference_type = 'order';
            $res->reference_id = $order->id;
            $res->released_at = now();
            $res->save();

            return $movement;
        });
    }

    /**
     * Reservation Subsystem: Sweep and cleanup all expired reservations.
     */
    public function cleanupExpiredReservations(): int
    {
        $expired = StockReservation::where('status', 'active')
            ->where('expires_at', '<', now())
            ->get();

        $count = 0;
        foreach ($expired as $res) {
            $this->releaseReservation($res, 'expired');
            $count++;
        }

        return $count;
    }

    /**
     * Reservation Subsystem: Release and restore stock for all active reservations on a specific order.
     */
    public function cancelOrderReservations(Order $order, string $reason = 'cancelled'): int
    {
        $reservations = StockReservation::where('status', 'active')
            ->where(function ($q) use ($order) {
                $q->where(function ($sub) use ($order) {
                    $sub->where('reference_type', 'online_order')
                        ->where('reference_id', $order->id);
                })->orWhere('cart_token', $order->order_number);
            })
            ->get();

        $released = 0;
        foreach ($reservations as $res) {
            $this->releaseReservation($res, $reason);
            $released++;
        }

        return $released;
    }

    /**
     * Customer Returns with Quality Inspection & Quarantine Routing.
     * Dispositions: 'sellable', 'damaged', 'quarantine', 'supplier_return'
     */
    public function processCustomerReturn(
        Order $order,
        array $items,
        string $disposition = 'sellable',
        string $reason = '',
        ?User $user = null
    ): void {
        $defaultWh = $this->getDefaultWarehouse();

        DB::transaction(function () use ($order, $items, $disposition, $reason, $defaultWh, $user) {
            $totalDamageCostnpr = 0.00;

            foreach ($items as $item) {
                $qty = abs((int)($item['quantity'] ?? 1));
                $productId = (int)$item['product_id'];
                $variantId = !empty($item['variant_id']) ? (int)$item['variant_id'] : null;

                $stockLevel = $this->getOrCreateStockLevel($defaultWh->id, $productId, $variantId);

                if ($disposition === 'sellable') {
                    $this->recordStockMovement([
                        'warehouse_id' => $defaultWh->id,
                        'product_id' => $productId,
                        'variant_id' => $variantId,
                        'movement_type' => 'return_customer',
                        'quantity' => $qty,
                        'reference_type' => 'order_return',
                        'reference_id' => $order->id,
                        'reference_number' => $order->order_number,
                        'reason' => $reason ?: "Customer return (Inspected - Sellable) for Order #{$order->order_number}",
                    ], $user);
                } elseif ($disposition === 'damaged') {
                    $stockLevel->damaged_quantity += $qty;
                    $stockLevel->save();

                    $movement = $this->recordStockMovement([
                        'warehouse_id' => $defaultWh->id,
                        'product_id' => $productId,
                        'variant_id' => $variantId,
                        'movement_type' => 'damage',
                        'quantity' => 0, // on-hand not increased to sellable
                        'reference_type' => 'order_return_damaged',
                        'reference_id' => $order->id,
                        'reference_number' => $order->order_number,
                        'reason' => $reason ?: "Customer return (Inspected - Damaged Goods) for Order #{$order->order_number}",
                        'notes' => "Added {$qty} units to damaged stock hold.",
                    ], $user);

                    $totalDamageCostnpr += round($qty * (float)$stockLevel->unit_cost_npr, 2);
                } elseif ($disposition === 'quarantine') {
                    $stockLevel->quarantined_quantity += $qty;
                    $stockLevel->save();

                    $this->recordStockMovement([
                        'warehouse_id' => $defaultWh->id,
                        'product_id' => $productId,
                        'variant_id' => $variantId,
                        'movement_type' => 'correction',
                        'quantity' => 0,
                        'reference_type' => 'order_return_quarantine',
                        'reference_id' => $order->id,
                        'reference_number' => $order->order_number,
                        'reason' => $reason ?: "Customer return (Quarantine Inspection Required) for Order #{$order->order_number}",
                        'notes' => "Added {$qty} units to quarantine hold.",
                    ], $user);
                }
            }

            // Post write-off journal entry if return was damaged
            if ($totalDamageCostnpr > 0 && $this->accountingService) {
                try {
                    $this->accountingService->ensureDefaultChartOfAccounts();
                    $adjAccount = Account::where('account_number', '5110')->first() ?: (Account::where('account_number', '1230')->first() ?: Account::where('category', 'cogs')->first());
                    $invAccount = Account::where('account_number', '1210')->first() ?: Account::where('account_number', '2210')->first();

                    if ($adjAccount && $invAccount) {
                        $this->accountingService->postJournalEntry([
                            'voucher_date' => date('Y-m-d'),
                            'entry_type' => 'cogs',
                            'reference_type' => 'damaged_return',
                            'reference_id' => $order->id,
                            'reference_number' => $order->order_number,
                            'description' => "Inventory adjustment (Damaged return item) order #{$order->order_number}",
                            'currency' => 'NPR',
                            'exchange_rate_to_npr' => 1.000000,
                        ], [
                            [
                                'account_id' => $adjAccount->id,
                                'debit' => $totalDamageCostnpr,
                                'credit' => 0.00,
                                'description' => "Loss on damaged return item order #{$order->order_number}",
                            ],
                            [
                                'account_id' => $invAccount->id,
                                'debit' => 0.00,
                                'credit' => $totalDamageCostnpr,
                                'description' => "Inventory reduction damaged return #{$order->order_number}",
                            ],
                        ], $user);
                    }
                } catch (\Throwable $e) {
                    Log::warning("Accounting write-off skipped for damaged return #{$order->order_number}: " . $e->getMessage());
                }
            }
        });
    }

    /**
     * Create Purchase Order applying configuration defaults (currency, terms, lead times, active supplier check).
     */
    public function createPurchaseOrder(array $attributes, ?User $user = null): PurchaseOrder
    {
        $supplierId = $attributes['supplier_id'] ?? null;
        $supplier = $supplierId ? Supplier::find($supplierId) : null;

        $enforceActiveSupplier = $this->settingsService->getBoolean('purchasing', 'enforce_active_supplier', true);
        if ($enforceActiveSupplier && $supplier && !$supplier->is_active) {
            throw new \RuntimeException("Cannot create purchase order: Supplier '{$supplier->name}' is marked inactive.");
        }

        $defaultCurrency = $this->settingsService->getString('purchasing', 'default_currency', 'NPR');
        $defaultTerms = $this->settingsService->getString('purchasing', 'default_payment_terms', 'net_30');
        $leadTimeDays = $this->settingsService->getInteger('purchasing', 'default_lead_time_days', 14);

        $attributes['currency'] = $attributes['currency'] ?? ($supplier?->currency ?: $defaultCurrency);
        $attributes['status'] = $attributes['status'] ?? 'draft';
        $attributes['order_date'] = $attributes['order_date'] ?? now()->toDateString();
        $attributes['expected_delivery_date'] = $attributes['expected_delivery_date']
            ?? now()->addDays($supplier?->lead_time_days ?: $leadTimeDays)->toDateString();
        $attributes['created_by'] = $attributes['created_by'] ?? ($user?->id ?? auth()->id());
        $attributes['po_number'] = $attributes['po_number'] ?? PurchaseOrder::generateNextPoNumber();

        if (!isset($attributes['notes']) && $defaultTerms) {
            $attributes['notes'] = "Payment Terms: " . ($supplier?->payment_terms ?: $defaultTerms);
        }

        return PurchaseOrder::create($attributes);
    }

    /**
     * Submit Purchase Order for approval with policy checks.
     */
    public function submitPurchaseOrder(PurchaseOrder $po, ?User $user = null): void
    {
        if ($po->status !== 'draft') {
            throw new \RuntimeException("Only draft purchase orders can be submitted.");
        }

        $po->loadMissing('supplier');
        $enforceActiveSupplier = $this->settingsService->getBoolean('purchasing', 'enforce_active_supplier', true);
        if ($enforceActiveSupplier && $po->supplier && !$po->supplier->is_active) {
            throw new \RuntimeException("Cannot submit purchase order #{$po->po_number}: Supplier '{$po->supplier->name}' is marked inactive.");
        }

        $po->recalculateTotals();
        $po->save();

        $reqApproval = $this->settingsService->has('purchasing', 'po_approval_required')
            ? $this->settingsService->getBoolean('purchasing', 'po_approval_required', true)
            : $this->settingsService->getBoolean('purchasing', 'require_po_approval', true);

        if (!$reqApproval) {
            $po->update([
                'status' => 'approved',
                'approved_by' => $user?->id ?? auth()->id(),
                'approved_at' => now(),
                'notes' => trim(($po->notes ?? '') . "\nAuto-approved: PO approval requirement disabled in Module Settings."),
            ]);
            return;
        }

        $po->update(['status' => 'submitted']);
    }

    /**
     * Approve Purchase Order for fulfillment with authorization threshold check.
     */
    public function approvePurchaseOrder(PurchaseOrder $po, ?User $user = null): void
    {
        if (!in_array($po->status, ['draft', 'submitted'])) {
            throw new \RuntimeException("Purchase order #{$po->po_number} is already {$po->status}.");
        }

        $po->loadMissing('supplier');
        $enforceActiveSupplier = $this->settingsService->getBoolean('purchasing', 'enforce_active_supplier', true);
        if ($enforceActiveSupplier && $po->supplier && !$po->supplier->is_active) {
            throw new \RuntimeException("Cannot approve purchase order #{$po->po_number}: Supplier '{$po->supplier->name}' is marked inactive.");
        }

        $expiryDays = $this->settingsService->getInteger('purchasing', 'approval_expiry_days', 14);
        if ($po->status === 'submitted' && $po->updated_at && $po->updated_at->diffInDays(now()) > $expiryDays) {
            throw new \RuntimeException("Purchase order #{$po->po_number} submission has expired (exceeded {$expiryDays} days). Re-submission required.");
        }

        $approver = $user ?? auth()->user();
        $thresholdnpr = $this->settingsService->getDecimal('purchasing', 'po_approval_threshold_npr', 10000.00);
        $rolesSetting = $this->settingsService->get('purchasing', 'approval_roles', 'super_admin,admin,workspace_admin');
        $allowedRoles = is_array($rolesSetting) ? $rolesSetting : array_map('trim', explode(',', (string)$rolesSetting));

        // Threshold check: if PO total exceeds threshold, only authorized executive roles can approve
        if ((float)$po->total_amount_npr > $thresholdnpr && $approver) {
            $role = $approver->role ?? 'staff';
            if (!in_array($role, $allowedRoles)) {
                throw new \RuntimeException("Purchase order #{$po->po_number} (Rs. " . number_format((float)$po->total_amount_npr, 2) . ") exceeds your approval threshold of Rs. " . number_format($thresholdnpr, 2) . ". Executive sign-off is required.");
            }
        }

        $po->update([
            'status' => 'approved',
            'approved_by' => $approver?->id ?? auth()->id(),
            'approved_at' => now(),
        ]);
    }

    /**
     * Reject Purchase Order respecting configured rejection behavior.
     */
    public function rejectPurchaseOrder(PurchaseOrder $po, string $reason = '', ?User $user = null): void
    {
        if (!in_array($po->status, ['draft', 'submitted'])) {
            throw new \RuntimeException("Only draft or submitted purchase orders can be rejected.");
        }

        $behavior = $this->settingsService->getString('purchasing', 'rejection_behavior', 'rejected');
        $targetStatus = match ($behavior) {
            'draft' => 'draft',
            'cancelled' => 'cancelled',
            default => 'rejected',
        };

        $po->update([
            'status' => $targetStatus,
            'notes' => trim(($po->notes ?? '') . "\nRejection ({$targetStatus}): {$reason}"),
        ]);
    }

    /**
     * Cancel Purchase Order.
     */
    public function cancelPurchaseOrder(PurchaseOrder $po, string $reason = '', ?User $user = null): void
    {
        if (in_array($po->status, ['received', 'cancelled'])) {
            throw new \RuntimeException("Cannot cancel PO in {$po->status} status.");
        }
        $po->update([
            'status' => 'cancelled',
            'notes' => trim(($po->notes ?? '') . "\nCancelled: {$reason}"),
        ]);
    }

    /**
     * Approve Stock Transfer.
     */
    public function approveTransfer(StockTransfer $transfer, ?User $user = null): void
    {
        if ($transfer->status !== 'draft') {
            throw new \RuntimeException("Only draft transfers can be approved.");
        }
        $transfer->update([
            'status' => 'approved',
            'approved_by' => $user?->id ?? auth()->id(),
            'approved_at' => now(),
        ]);
    }

    /**
     * Cancel Stock Transfer.
     */
    public function cancelTransfer(StockTransfer $transfer, string $reason = '', ?User $user = null): void
    {
        if (in_array($transfer->status, ['completed', 'cancelled'])) {
            throw new \RuntimeException("Cannot cancel transfer in {$transfer->status} status.");
        }
        if ($transfer->status === 'in_transit') {
            // Restore dispatched stock back to source warehouse
            DB::transaction(function () use ($transfer, $reason, $user) {
                $transfer->load('items');
                foreach ($transfer->items as $item) {
                    $this->recordStockMovement([
                        'warehouse_id' => $transfer->source_warehouse_id,
                        'product_id' => $item->product_id,
                        'variant_id' => $item->variant_id,
                        'movement_type' => 'correction',
                        'quantity' => (int)$item->quantity_sent,
                        'reference_type' => 'transfer_cancelled',
                        'reference_id' => $transfer->id,
                        'reference_number' => $transfer->transfer_number,
                        'reason' => "Transfer #{$transfer->transfer_number} cancelled in transit: {$reason}",
                    ], $user);
                }
                $transfer->update([
                    'status' => 'cancelled',
                    'notes' => trim(($transfer->notes ?? '') . "\nCancelled: {$reason}"),
                ]);
            });
            return;
        }

        $transfer->update([
            'status' => 'cancelled',
            'notes' => trim(($transfer->notes ?? '') . "\nCancelled: {$reason}"),
        ]);
    }

    /**
     * Reconcile Operational Inventory Valuation against NAS GL Account 2210 (Inventory Asset).
     */
    public function getAccountingReconciliation(): array
    {
        $valSummary = $this->getValuationSummary();
        $operationalValuation = (float)$valSummary['total_valuation_npr'];

        $glAccount = Account::where('account_number', '2210')->first();
        $glBalance = 0.00;

        if ($glAccount) {
            $totalDebit = (float)JournalEntryLine::where('account_id', $glAccount->id)->sum('debit');
            $totalCredit = (float)JournalEntryLine::where('account_id', $glAccount->id)->sum('credit');
            // Asset account normal balance = Debit - Credit
            $glBalance = round($totalDebit - $totalCredit, 2);
        }

        $variance = round($operationalValuation - $glBalance, 2);
        $isBalanced = abs($variance) < 0.01;

        return [
            'operational_valuation_npr' => $operationalValuation,
            'gl_account_number' => '2210',
            'gl_account_name' => $glAccount ? $glAccount->name : 'Inventory Asset (Not Created)',
            'gl_balance_npr' => $glBalance,
            'gl_inventory_balance_npr' => $glBalance,
            'variance_npr' => $variance,
            'is_balanced' => $isBalanced,
            'status' => $isBalanced ? 'Balanced' : 'Discrepancy Found',
            'reconciled_at' => now()->toIso8601String(),
            'total_units' => (int)$valSummary['total_units_on_hand'],
            'warehouse_breakdown' => $this->getWarehouseBreakdown(),
        ];
    }

    /**
     * Reorder Intelligence: Detect low stock, out of stock, dead stock, and generate PO recommendations.
     */
    public function getReorderIntelligenceReport(): array
    {
        $levels = StockLevel::with(['product', 'variant', 'warehouse'])
            ->get();

        $lowStock = [];
        $outOfStock = [];
        $overStock = [];
        $reorderRecommendations = [];
        $allItems = [];

        foreach ($levels as $level) {
            $avail = (int)$level->quantity_available;
            $reorderPt = (int)$level->reorder_point;
            $reorderQty = (int)$level->reorder_quantity;

            $status = 'HEALTHY';
            if ($avail <= 0) {
                $status = 'OUT OF STOCK';
                $outOfStock[] = $level;
            } elseif ($avail <= $reorderPt) {
                $status = ($level->quantity_incoming == 0) ? 'REORDER REQUIRED' : 'LOW STOCK';
                $lowStock[] = $level;
            } elseif ($level->isOverStock()) {
                $status = 'OVERSTOCK';
                $overStock[] = $level;
            }

            $recQty = ($avail <= $reorderPt && $level->quantity_incoming == 0)
                ? max($reorderQty, ($reorderPt * 2) - $avail)
                : 0;

            $supplier = Supplier::where('is_active', true)->first();

            $itemData = [
                'stock_level_id' => $level->id,
                'warehouse_id' => $level->warehouse_id,
                'warehouse_name' => $level->warehouse?->name ?? 'Default',
                'product_id' => $level->product_id,
                'product_name' => $level->product?->name ?? 'Product',
                'sku' => $level->variant?->sku ?? $level->product?->sku ?? 'SKU',
                'variant_id' => $level->variant_id,
                'on_hand' => $level->quantity_on_hand,
                'available' => $avail,
                'reserved' => (int)$level->quantity_reserved,
                'incoming' => (int)$level->quantity_incoming,
                'reorder_point' => $reorderPt,
                'safety_stock' => (int)$level->safety_stock,
                'recommended_qty' => $recQty,
                'unit_cost_npr' => (float)$level->unit_cost_npr,
                'estimated_cost_npr' => round($recQty * (float)$level->unit_cost_npr, 2),
                'supplier_id' => $supplier?->id,
                'supplier_name' => $supplier?->name ?? 'Primary Artisan Cooperative',
                'status' => $status,
            ];

            $allItems[] = $itemData;

            if ($recQty > 0) {
                $reorderRecommendations[] = $itemData;
            }
        }

        return [
            'out_of_stock_count' => count($outOfStock),
            'low_stock_count' => count($lowStock),
            'overstock_count' => count($overStock),
            'recommendations_count' => count($reorderRecommendations),
            'recommendations' => $reorderRecommendations,
            'items' => $allItems,
            'summary' => [
                'out_of_stock' => count($outOfStock),
                'low_stock' => count($lowStock),
                'overstock' => count($overStock),
                'reorder_recommendations' => count($reorderRecommendations),
            ],
        ];
    }

    /**
     * Generate Purchase Order directly from Reorder Intelligence recommendations.
     */
    public function generatePurchaseOrderFromRecommendations(
        int $supplierId,
        int $warehouseId,
        array $itemsData,
        ?User $user = null
    ): PurchaseOrder {
        return DB::transaction(function () use ($supplierId, $warehouseId, $itemsData, $user) {
            $poNumber = PurchaseOrder::generateNextPoNumber();
            $supplier = Supplier::findOrFail($supplierId);

            $po = PurchaseOrder::create([
                'po_number' => $poNumber,
                'supplier_id' => $supplierId,
                'warehouse_id' => $warehouseId,
                'order_date' => now()->toDateString(),
                'expected_delivery_date' => now()->addDays((int)$supplier->lead_time_days)->toDateString(),
                'currency' => $supplier->currency ?: 'NPR',
                'exchange_rate_to_npr' => 1.000000,
                'status' => 'draft',
                'created_by' => $user?->id ?? auth()->id(),
                'notes' => 'Auto-generated procurement order from Reorder Intelligence recommendations.',
            ]);

            $subtotal = 0.00;
            foreach ($itemsData as $item) {
                if (is_numeric($item)) {
                    $productId = (int)$item;
                    $product = Product::find($productId);
                    $stockLevel = StockLevel::where('warehouse_id', $warehouseId)->where('product_id', $productId)->first();
                    $qty = max(1, (int)($stockLevel?->reorder_quantity ?: 10));
                    $unitCost = (float)($stockLevel?->unit_cost_npr ?: ($product?->cost_price_npr ?: 0));
                    $variantId = null;
                } else {
                    $productId = (int)($item['product_id'] ?? 0);
                    $product = Product::find($productId);
                    $variantId = !empty($item['variant_id']) ? (int)$item['variant_id'] : null;
                    $qty = max(1, (int)($item['quantity'] ?? ($item['recommended_qty'] ?? 10)));
                    $unitCost = (float)($item['unit_cost_npr'] ?? ($product?->cost_price_npr ?: 0));
                }

                $lineTotal = round($qty * $unitCost, 2);
                $subtotal += $lineTotal;

                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'product_id' => $productId,
                    'variant_id' => $variantId,
                    'quantity_ordered' => $qty,
                    'quantity_received' => 0,
                    'unit_cost_currency' => $unitCost,
                    'unit_cost_npr' => $unitCost,
                    'total_cost_npr' => $lineTotal,
                ]);
            }

            $po->subtotal_currency = $subtotal;
            $po->total_amount_npr = $subtotal;
            $po->save();

            return $po;
        });
    }

    /**
     * Executive Inventory Dashboard KPIs.
     */
    public function getDashboardKpis(): array
    {
        $valSummary = $this->getValuationSummary();
        $reorder = $this->getReorderIntelligenceReport();

        $activeReservations = (int)StockReservation::where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })->sum('quantity');

        $pendingPosCount = PurchaseOrder::whereIn('status', ['ordered', 'submitted', 'approved', 'in_transit'])->count();
        $incomingUnits = (int)StockLevel::sum('quantity_incoming');
        $inTransitTransfers = StockTransfer::where('status', 'in_transit')->count();
        $recentMovementsCount = StockMovement::where('created_at', '>=', now()->subDays(7))->count();

        return [
            'total_units_on_hand' => (int)$valSummary['total_units_on_hand'],
            'total_valuation_npr' => (float)$valSummary['total_valuation_npr'],
            'total_retail_potential_npr' => (float)$valSummary['total_retail_potential_npr'],
            'total_projected_profit_npr' => (float)$valSummary['total_projected_profit_npr'],
            'average_margin_percentage' => (float)$valSummary['average_margin_percentage'],
            'total_reserved_units' => $activeReservations,
            'total_available_units' => max(0, (int)$valSummary['total_units_on_hand'] - $activeReservations),
            'incoming_units' => $incomingUnits,
            'low_stock_skus' => $reorder['low_stock_count'],
            'out_of_stock_skus' => $reorder['out_of_stock_count'],
            'overstock_skus' => $reorder['overstock_count'],
            'pending_pos_count' => $pendingPosCount,
            'in_transit_transfers_count' => $inTransitTransfers,
            'recent_movements_count' => $recentMovementsCount,
            'warehouse_breakdown' => $this->getWarehouseBreakdown(),
        ];
    }
}
