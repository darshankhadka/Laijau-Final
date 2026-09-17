<?php

namespace Database\Seeders;

use App\Models\Inventory\PurchaseOrder;
use App\Models\Inventory\PurchaseOrderItem;
use App\Models\Inventory\StockAdjustment;
use App\Models\Inventory\StockCount;
use App\Models\Inventory\StockCountItem;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\StockTransfer;
use App\Models\Inventory\StockTransferItem;
use App\Models\Inventory\Supplier;
use App\Models\Inventory\Warehouse;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $inventoryService = app(InventoryService::class);

        // 1. Seed default Warehouses and Suppliers
        $inventoryService->ensureDefaultWarehousesAndSuppliers();

        // 2. Backfill existing live products and variants into multi-location stock levels & ledger
        $inventoryService->backfillExistingProductsToStockLevels();

        $admin = User::first();
        $mainWarehouse = Warehouse::where('code', InventoryService::DEFAULT_WH_CODE)->first();
        $showroomWarehouse = Warehouse::where('code', InventoryService::SHOWROOM_WH_CODE)->first();
        $supplierPashmina = Supplier::where('code', 'SUP-PASHMINA-KTM')->first();
        $supplierSilk = Supplier::where('code', 'SUP-SILK-VARANASI')->first();

        $sampleProduct = Product::with('variants')->first();
        $sampleVariant = $sampleProduct?->variants->first();

        // 3. Seed Sample Purchase Order & Inbound Goods Receiving
        if ($supplierPashmina && $mainWarehouse && $sampleProduct) {
            $poNumber = 'PO-2026-09-001';
            $po = PurchaseOrder::firstOrCreate(
                ['po_number' => $poNumber],
                [
                    'supplier_id' => $supplierPashmina->id,
                    'warehouse_id' => $mainWarehouse->id,
                    'order_date' => now()->subDays(10)->toDateString(),
                    'expected_delivery_date' => now()->subDays(2)->toDateString(),
                    'received_date' => now()->toDateString(),
                    'currency' => 'npr',
                    'exchange_rate_to_npr' => 1.000000,
                    'subtotal_currency' => 7500.00,
                    'shipping_cost_npr' => 450.00,
                    'customs_duty_npr' => 300.00,
                    'total_amount_npr' => 8250.00,
                    'status' => 'received',
                    'created_by' => $admin?->id,
                    'notes' => 'Autumn collection cashmere restocking from Patan artisan guild.',
                ]
            );

            PurchaseOrderItem::firstOrCreate(
                [
                    'purchase_order_id' => $po->id,
                    'product_id' => $sampleProduct->id,
                    'variant_id' => $sampleVariant?->id,
                ],
                [
                    'quantity_ordered' => 15,
                    'quantity_received' => 15,
                    'unit_cost_currency' => 500.00,
                    'unit_cost_npr' => 500.00,
                    'total_cost_npr' => 7500.00,
                ]
            );
        }

        // 4. Seed Sample Inter-Warehouse Stock Transfer
        if ($mainWarehouse && $showroomWarehouse && $sampleProduct) {
            $transferNumber = 'TRF-2026-09-001';
            $transfer = StockTransfer::firstOrCreate(
                ['transfer_number' => $transferNumber],
                [
                    'source_warehouse_id' => $mainWarehouse->id,
                    'destination_warehouse_id' => $showroomWarehouse->id,
                    'status' => 'completed',
                    'initiated_by' => $admin?->id,
                    'received_by' => $admin?->id,
                    'sent_at' => now()->subDays(3),
                    'received_at' => now()->subDays(2),
                    'tracking_reference' => 'INTERNAL-VAN-04',
                    'notes' => 'Weekly replenishment for Kathmandu Showroom storefront display.',
                ]
            );

            StockTransferItem::firstOrCreate(
                [
                    'transfer_id' => $transfer->id,
                    'product_id' => $sampleProduct->id,
                    'variant_id' => $sampleVariant?->id,
                ],
                [
                    'quantity_sent' => 3,
                    'quantity_received' => 3,
                ]
            );
        }

        // 5. Seed Sample Physical Audit Stock Count
        if ($mainWarehouse && $sampleProduct) {
            $countNumber = 'CNT-2026-09-001';
            $count = StockCount::firstOrCreate(
                ['count_number' => $countNumber],
                [
                    'warehouse_id' => $mainWarehouse->id,
                    'count_date' => now()->toDateString(),
                    'status' => 'reconciled',
                    'total_expected_items' => 20,
                    'total_counted_items' => 20,
                    'total_variance_items' => 0,
                    'total_variance_value_npr' => 0.00,
                    'conducted_by' => $admin?->id,
                    'reconciled_by' => $admin?->id,
                    'reconciled_at' => now(),
                    'notes' => 'Monthly spot audit for cashmere & silk racks. 100% accuracy verified.',
                ]
            );

            StockCountItem::firstOrCreate(
                [
                    'stock_count_id' => $count->id,
                    'product_id' => $sampleProduct->id,
                    'variant_id' => $sampleVariant?->id,
                ],
                [
                    'expected_quantity' => 20,
                    'counted_quantity' => 20,
                    'variance_quantity' => 0,
                    'unit_cost_npr' => 500.00,
                    'variance_value_npr' => 0.00,
                    'is_reconciled' => true,
                ]
            );
        }
    }
}
