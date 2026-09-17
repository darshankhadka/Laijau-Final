<?php

namespace Tests\Feature;

use App\Filament\Pages\InventoryDashboard;
use App\Filament\Pages\InventoryValuationPage;
use App\Filament\Resources\PurchaseOrderResource;
use App\Filament\Resources\StockAdjustmentResource;
use App\Filament\Resources\StockCountResource;
use App\Filament\Resources\StockLevelResource;
use App\Filament\Resources\StockMovementResource;
use App\Filament\Resources\StockTransferResource;
use App\Filament\Resources\SupplierResource;
use App\Filament\Resources\WarehouseResource;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
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
use App\Models\OfflineSale;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use App\Services\Inventory\InventoryService;
use App\Services\OfflineSaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnterpriseInventorySystemTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected InventoryService $inventoryService;
    protected Warehouse $warehouseKtm;
    protected Warehouse $warehouseShowroom;
    protected Supplier $supplierPashmina;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed default chart of accounts
        app(AccountingService::class)->ensureDefaultChartOfAccounts();

        $this->inventoryService = app(InventoryService::class);
        $this->inventoryService->ensureDefaultWarehousesAndSuppliers();

        $this->warehouseKtm = Warehouse::where('code', InventoryService::DEFAULT_WH_CODE)->first();
        $this->warehouseShowroom = Warehouse::where('code', InventoryService::SHOWROOM_WH_CODE)->first();
        $this->supplierPashmina = Supplier::where('code', 'SUP-PASHMINA-KTM')->first();

        $this->admin = User::factory()->create([
            'email' => 'inventory-director@laijau.com',
            'role' => 'admin',
        ]);
    }

    /**
     * Test 1: Warehouses & Suppliers are initialized with proper codes, types and defaults.
     */
    public function test_default_warehouses_and_suppliers_are_seeded(): void
    {
        $this->assertNotNull($this->warehouseKtm);
        $this->assertTrue((bool)$this->warehouseKtm->is_default);
        $this->assertEquals('warehouse', $this->warehouseKtm->type);

        $this->assertNotNull($this->warehouseShowroom);
        $this->assertEquals('showroom_pos', $this->warehouseShowroom->type);

        $this->assertNotNull($this->supplierPashmina);
        $this->assertEquals('SUP-PASHMINA-KTM', $this->supplierPashmina->code);
    }

    /**
     * Test 2: Double-Entry Stock Movement Ledger records signed deltas and balances accurately.
     */
    public function test_stock_movement_ledger_tracks_quantities_and_balances(): void
    {
        $product = Product::create([
            'name' => 'Changthangi Raw Cashmere Stole',
            'slug' => 'changthangi-raw-cashmere-stole',
            'sku' => 'PAS-RAW-001',
            'price_npr' => 2400.00,
            'cost_price_npr' => 800.00,
            'quantity' => 0,
            'is_active' => true,
        ]);

        // 1. Initial Opening Stock (+10)
        $movement1 = $this->inventoryService->recordStockMovement([
            'warehouse_id' => $this->warehouseKtm->id,
            'product_id' => $product->id,
            'movement_type' => 'opening_stock',
            'quantity' => 10,
            'unit_cost_npr' => 800.00,
            'reason' => 'Initial stock intake',
        ], $this->admin);

        $this->assertEquals(0, $movement1->quantity_before);
        $this->assertEquals(10, $movement1->quantity_after);
        $this->assertEquals(8000.00, (float)$movement1->total_cost_npr);

        // Verify StockLevel and Product quantity synced
        $level = StockLevel::where('warehouse_id', $this->warehouseKtm->id)
            ->where('product_id', $product->id)
            ->first();

        $this->assertEquals(10, $level->quantity_on_hand);
        $product->refresh();
        $this->assertEquals(10, $product->quantity);

        // 2. Deduction (-2)
        $movement2 = $this->inventoryService->recordStockMovement([
            'warehouse_id' => $this->warehouseKtm->id,
            'product_id' => $product->id,
            'movement_type' => 'sale_order',
            'quantity' => -2,
            'reason' => 'Order fulfillment',
        ], $this->admin);

        $this->assertEquals(10, $movement2->quantity_before);
        $this->assertEquals(8, $movement2->quantity_after);

        $level->refresh();
        $this->assertEquals(8, $level->quantity_on_hand);
        $product->refresh();
        $this->assertEquals(8, $product->quantity);
    }

    /**
     * Test 3: Multi-Location Variant stock synchronization.
     */
    public function test_multi_location_variant_and_size_stock_synchronization(): void
    {
        $product = Product::create([
            'name' => 'Classic Leather Oxford Shoes',
            'slug' => 'classic-leather-oxford-shoes',
            'sku' => 'SHO-OXF-001',
            'price_npr' => 4500.00,
            'cost_price_npr' => 1500.00,
            'quantity' => 5,
            'is_active' => true,
        ]);

        $var41 = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SHO-OXF-001-41',
            'size' => '41',
            'price_npr' => 4500.00,
            'stock_quantity' => 2,
            'is_active' => true,
        ]);

        $var42 = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SHO-OXF-001-42',
            'size' => '42',
            'price_npr' => 4500.00,
            'stock_quantity' => 3,
            'is_active' => true,
        ]);

        // Stock in KTM Main Warehouse for Size 41
        $this->inventoryService->recordStockMovement([
            'warehouse_id' => $this->warehouseKtm->id,
            'product_id' => $product->id,
            'variant_id' => $var41->id,
            'movement_type' => 'opening_stock',
            'quantity' => 2,
            'unit_cost_npr' => 1500.00,
            'reason' => 'Opening stock size 41',
        ]);

        // Stock in KTM Main Warehouse for Size 42
        $this->inventoryService->recordStockMovement([
            'warehouse_id' => $this->warehouseKtm->id,
            'product_id' => $product->id,
            'variant_id' => $var42->id,
            'movement_type' => 'opening_stock',
            'quantity' => 3,
            'unit_cost_npr' => 1500.00,
            'reason' => 'Opening stock size 42',
        ]);

        $this->assertEquals(2, $this->inventoryService->getAvailableStock($product->id, $var41->id));
        $this->assertEquals(3, $this->inventoryService->getAvailableStock($product->id, $var42->id));
    }

    /**
     * Test 4: E-commerce Order fulfillment via InventoryService deducts stock and records COGS voucher.
     */
    public function test_order_fulfillment_deducts_stock_and_posts_cogs(): void
    {
        $product = Product::create([
            'name' => 'Midnight Indigo Silk Wrap',
            'sku' => 'SHW-IND-001',
            'price_npr' => 1800.00,
            'cost_price_npr' => 600.00,
            'quantity' => 10,
            'is_active' => true,
        ]);

        $this->inventoryService->recordStockMovement([
            'warehouse_id' => $this->warehouseKtm->id,
            'product_id' => $product->id,
            'movement_type' => 'opening_stock',
            'quantity' => 10,
            'unit_cost_npr' => 600.00,
            'reason' => 'Opening stock',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-2026-TEST-001',
            'first_name' => 'Rajan',
            'last_name' => 'Shrestha',
            'email' => 'rajan@laijau.com',
            'shipping_address' => 'Putalisadak 12',
            'shipping_city' => 'Kathmandu',
            'shipping_postal_code' => '44600',
            'shipping_country' => 'NP',
            'subtotal' => 1800.00,
            'total_amount' => 1800.00,
            'currency' => 'npr',
            'status' => 'processing',
            'payment_status' => 'paid',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => 1800.00,
            'total_price' => 3600.00,
            'quantity' => 2,
        ]);

        $this->inventoryService->fulfillOrderStock($order, $this->admin);

        $product->refresh();
        $this->assertEquals(8, $product->quantity);

        $level = StockLevel::where('warehouse_id', $this->warehouseKtm->id)
            ->where('product_id', $product->id)
            ->first();
        $this->assertEquals(8, $level->quantity_on_hand);

        // Check stock movement
        $movement = StockMovement::where('reference_type', 'order')
            ->where('reference_id', $order->id)
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals(-2, $movement->quantity);
        $this->assertEquals('sale_order', $movement->movement_type);

        // Check COGS Journal Entry
        $cogsVoucher = JournalEntry::where('reference_type', 'order_cogs')
            ->where('reference_id', $order->id)
            ->first();

        $this->assertNotNull($cogsVoucher);
        $this->assertEquals(1200.00, (float)$cogsVoucher->total_debit);
    }

    /**
     * Test 5: Customer return restores physical stock and creates return movement.
     */
    public function test_order_return_restores_inventory(): void
    {
        $product = Product::create([
            'name' => 'Ivory Pashmina Scarf',
            'sku' => 'PAS-IVR-001',
            'price_npr' => 1200.00,
            'quantity' => 5,
            'is_active' => true,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-RET-001',
            'first_name' => 'Suman',
            'last_name' => 'Gurung',
            'email' => 'suman@laijau.com',
            'shipping_address' => 'New Road 25',
            'shipping_country' => 'NP',
            'subtotal' => 1200.00,
            'total_amount' => 1200.00,
            'currency' => 'npr',
            'status' => 'completed',
        ]);

        $this->inventoryService->restoreOrderReturn($order, [
            ['product_id' => $product->id, 'quantity' => 1],
        ], 'Customer exchanged size', $this->admin);

        $movement = StockMovement::where('reference_type', 'order_return')
            ->where('reference_id', $order->id)
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals(1, $movement->quantity);
        $this->assertEquals('return_customer', $movement->movement_type);
    }

    /**
     * Test 6: Offline Sales / POS deducts showroom stock and void restores it.
     */
    public function test_offline_pos_sale_and_void_restoration(): void
    {
        $product = Product::create([
            'name' => 'Kashmiri Hand-Embroidered Shawl',
            'sku' => 'SHW-KSH-001',
            'price_npr' => 3000.00,
            'cost_price_npr' => 1000.00,
            'quantity' => 6,
            'is_active' => true,
        ]);

        // Initialize showroom stock
        $this->inventoryService->recordStockMovement([
            'warehouse_id' => $this->warehouseShowroom->id,
            'product_id' => $product->id,
            'movement_type' => 'opening_stock',
            'quantity' => 6,
            'unit_cost_npr' => 1000.00,
            'reason' => 'Showroom display initial stock',
        ]);

        $posService = app(OfflineSaleService::class);
        $sale = $posService->createSale([
            'customer_name' => 'Walk-in Guest',
            'currency' => 'npr',
            'payment_method' => 'esewa',
            'sales_channel' => 'physical',
        ], [
            ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 3000.00],
        ], $this->admin);

        $level = StockLevel::where('warehouse_id', $this->warehouseShowroom->id)
            ->where('product_id', $product->id)
            ->first();
        $this->assertEquals(5, $level->quantity_on_hand);

        // Void POS Sale
        $posService->voidSale($sale, 'Customer refund', $this->admin, true);

        $level->refresh();
        $this->assertEquals(6, $level->quantity_on_hand);
    }

    /**
     * Test 7: Purchase Order Goods Receiving increases physical stock and logs accounting voucher.
     */
    public function test_purchase_order_goods_receiving_workflow(): void
    {
        $product = Product::create([
            'name' => 'Artisanal Himalayan Cashmere Cardigan',
            'sku' => 'APP-CARD-001',
            'price_npr' => 2800.00,
            'quantity' => 0,
            'is_active' => true,
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-2026-TEST-99',
            'supplier_id' => $this->supplierPashmina->id,
            'warehouse_id' => $this->warehouseKtm->id,
            'order_date' => now()->toDateString(),
            'currency' => 'npr',
            'exchange_rate_to_npr' => 1.000000,
            'total_amount_npr' => 10000.00,
            'status' => 'ordered',
        ]);

        $item = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity_ordered' => 10,
            'quantity_received' => 0,
            'unit_cost_currency' => 1000.00,
            'unit_cost_npr' => 1000.00,
            'total_cost_npr' => 10000.00,
        ]);

        $this->inventoryService->receivePurchaseOrder($po, [$item->id => 10], $this->admin);

        $po->refresh();
        $this->assertEquals('received', $po->status);

        $level = StockLevel::where('warehouse_id', $this->warehouseKtm->id)
            ->where('product_id', $product->id)
            ->first();
        $this->assertEquals(10, $level->quantity_on_hand);

        $product->refresh();
        $this->assertEquals(10, $product->quantity);

        // Check stock movement
        $mov = StockMovement::where('reference_type', 'purchase_order')
            ->where('reference_id', $po->id)
            ->first();
        $this->assertNotNull($mov);
        $this->assertEquals(10, $mov->quantity);
        $this->assertEquals('purchase_receive', $mov->movement_type);

        // Check Vendor Invoice Journal Entry (Account 2210 & 2710)
        $this->assertNotNull($po->journal_entry_id);
    }

    /**
     * Test 8: Inter-Warehouse Stock Transfer dispatch and receiving.
     */
    public function test_inter_warehouse_stock_transfer(): void
    {
        $product = Product::create([
            'name' => 'Festive Silk Dupatta',
            'sku' => 'ACC-DUP-001',
            'price_npr' => 900.00,
            'cost_price_npr' => 300.00,
            'quantity' => 10,
            'is_active' => true,
        ]);

        // Stock in KTM Main WH
        $this->inventoryService->recordStockMovement([
            'warehouse_id' => $this->warehouseKtm->id,
            'product_id' => $product->id,
            'movement_type' => 'opening_stock',
            'quantity' => 10,
            'unit_cost_npr' => 300.00,
            'reason' => 'Opening stock',
        ]);

        $transfer = StockTransfer::create([
            'transfer_number' => 'TRF-TEST-001',
            'source_warehouse_id' => $this->warehouseKtm->id,
            'destination_warehouse_id' => $this->warehouseShowroom->id,
            'status' => 'draft',
        ]);

        $transferItem = StockTransferItem::create([
            'transfer_id' => $transfer->id,
            'product_id' => $product->id,
            'quantity_sent' => 4,
            'quantity_received' => 0,
        ]);

        // 1. Dispatch
        $this->inventoryService->dispatchTransfer($transfer, $this->admin);
        $transfer->refresh();
        $this->assertEquals('in_transit', $transfer->status);

        $sourceLevel = StockLevel::where('warehouse_id', $this->warehouseKtm->id)->where('product_id', $product->id)->first();
        $this->assertEquals(6, $sourceLevel->quantity_on_hand);

        // 2. Receive
        $this->inventoryService->receiveTransfer($transfer, [$transferItem->id => 4], $this->admin);
        $transfer->refresh();
        $this->assertEquals('completed', $transfer->status);

        $destLevel = StockLevel::where('warehouse_id', $this->warehouseShowroom->id)->where('product_id', $product->id)->first();
        $this->assertEquals(4, $destLevel->quantity_on_hand);
    }

    /**
     * Test 9: Stock Adjustment applies delta and auto-posts shrinkage/gain accounting journal entry.
     */
    public function test_stock_adjustment_applies_delta_and_posts_accounting(): void
    {
        $product = Product::create([
            'name' => 'Brocade Evening Clutch',
            'sku' => 'BAG-BROC-001',
            'price_npr' => 1500.00,
            'cost_price_npr' => 500.00,
            'quantity' => 5,
            'is_active' => true,
        ]);

        $this->inventoryService->recordStockMovement([
            'warehouse_id' => $this->warehouseKtm->id,
            'product_id' => $product->id,
            'movement_type' => 'opening_stock',
            'quantity' => 5,
            'unit_cost_npr' => 500.00,
            'reason' => 'Opening stock',
        ]);

        $adjustment = StockAdjustment::create([
            'adjustment_number' => 'ADJ-TEST-001',
            'warehouse_id' => $this->warehouseKtm->id,
            'product_id' => $product->id,
            'type' => 'damage',
            'quantity' => -1,
            'unit_cost_npr' => 500.00,
            'total_value_npr' => 500.00,
            'reason' => 'Stained fabric during showcase',
            'status' => 'pending',
        ]);

        $this->inventoryService->applyAdjustment($adjustment, $this->admin);

        $adjustment->refresh();
        $this->assertEquals('approved', $adjustment->status);
        $this->assertNotNull($adjustment->journal_entry_id);

        $level = StockLevel::where('warehouse_id', $this->warehouseKtm->id)->where('product_id', $product->id)->first();
        $this->assertEquals(4, $level->quantity_on_hand);
    }

    /**
     * Test 10: Stock Count audit reconciles physical variance and adjusts ledger.
     */
    public function test_stock_count_audit_reconciles_variance(): void
    {
        $product = Product::create([
            'name' => 'Pure Pashmina Muffler',
            'sku' => 'MUF-PAS-001',
            'price_npr' => 800.00,
            'cost_price_npr' => 250.00,
            'quantity' => 10,
            'is_active' => true,
        ]);

        $this->inventoryService->recordStockMovement([
            'warehouse_id' => $this->warehouseKtm->id,
            'product_id' => $product->id,
            'movement_type' => 'opening_stock',
            'quantity' => 10,
            'unit_cost_npr' => 250.00,
            'reason' => 'Opening stock',
        ]);

        $count = StockCount::create([
            'count_number' => 'CNT-TEST-001',
            'warehouse_id' => $this->warehouseKtm->id,
            'count_date' => now()->toDateString(),
            'status' => 'in_progress',
        ]);

        StockCountItem::create([
            'stock_count_id' => $count->id,
            'product_id' => $product->id,
            'expected_quantity' => 10,
            'counted_quantity' => 12, // +2 variance (found)
            'variance_quantity' => 2,
            'unit_cost_npr' => 250.00,
            'variance_value_npr' => 500.00,
        ]);

        $this->inventoryService->reconcileStockCount($count, $this->admin);

        $count->refresh();
        $this->assertEquals('reconciled', $count->status);

        $level = StockLevel::where('warehouse_id', $this->warehouseKtm->id)->where('product_id', $product->id)->first();
        $this->assertEquals(12, $level->quantity_on_hand);

        $product->refresh();
        $this->assertEquals(12, $product->quantity);
    }

    /**
     * Test 11: Inventory Valuation and KPIs return correct calculations.
     */
    public function test_valuation_summary_and_warehouse_breakdowns(): void
    {
        $summary = $this->inventoryService->getValuationSummary();
        $this->assertArrayHasKey('total_valuation_npr', $summary);
        $this->assertArrayHasKey('total_units_on_hand', $summary);
        $this->assertArrayHasKey('total_warehouses', $summary);

        $breakdown = $this->inventoryService->getWarehouseBreakdown();
        $this->assertIsArray($breakdown);
        $this->assertNotEmpty($breakdown);
    }

    /**
     * Test 12: Stock Movement Ledger immutability prevents editing and deleting records.
     */
    public function test_stock_movement_ledger_is_immutable(): void
    {
        $product = Product::create([
            'name' => 'Cashmere Shawl Immutable',
            'sku' => 'IMM-CSH-001',
            'price_npr' => 1500.00,
            'cost_price_npr' => 500.00,
            'quantity' => 5,
            'is_active' => true,
        ]);

        $movement = $this->inventoryService->recordStockMovement([
            'warehouse_id' => $this->warehouseKtm->id,
            'product_id' => $product->id,
            'movement_type' => 'opening_stock',
            'quantity' => 5,
            'unit_cost_npr' => 500.00,
            'reason' => 'Opening stock',
        ], $this->admin);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('immutable');
        $movement->update(['reason' => 'Hacked reason']);
    }

    /**
     * Test 13: Stock Movement deletion is blocked by immutability guard.
     */
    public function test_stock_movement_deletion_is_blocked(): void
    {
        $product = Product::create([
            'name' => 'Cashmere Shawl No Delete',
            'sku' => 'NODEL-CSH-001',
            'price_npr' => 1500.00,
            'cost_price_npr' => 500.00,
            'quantity' => 5,
            'is_active' => true,
        ]);

        $movement = $this->inventoryService->recordStockMovement([
            'warehouse_id' => $this->warehouseKtm->id,
            'product_id' => $product->id,
            'movement_type' => 'opening_stock',
            'quantity' => 5,
            'unit_cost_npr' => 500.00,
            'reason' => 'Opening stock',
        ], $this->admin);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('immutable');
        $movement->delete();
    }

    /**
     * Test 14: Stock Reservation creation reduces available stock and release restores it.
     */
    public function test_inventory_reservation_creation_and_release(): void
    {
        $product = Product::create([
            'name' => 'Gold Zari Brocade Shawl',
            'sku' => 'ZAR-BRO-001',
            'price_npr' => 3500.00,
            'cost_price_npr' => 1200.00,
            'quantity' => 4,
            'is_active' => true,
        ]);

        $this->inventoryService->recordStockMovement([
            'warehouse_id' => $this->warehouseKtm->id,
            'product_id' => $product->id,
            'movement_type' => 'opening_stock',
            'quantity' => 4,
            'unit_cost_npr' => 1200.00,
            'reason' => 'Opening stock',
        ]);

        $level = StockLevel::where('warehouse_id', $this->warehouseKtm->id)
            ->where('product_id', $product->id)
            ->first();

        $this->assertEquals(4, $level->available_quantity);

        // Reserve 2 units
        $reservation = $this->inventoryService->createReservation([
            'warehouse_id' => $this->warehouseKtm->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'reservation_type' => 'checkout',
            'cart_token' => 'cart_token_abc_123',
            'expires_in_minutes' => 15,
        ], $this->admin);

        $level->refresh();
        $this->assertEquals(2, $level->reserved_quantity);
        $this->assertEquals(2, $level->available_quantity);
        $this->assertEquals('active', $reservation->status);

        // Release reservation
        $this->inventoryService->releaseReservation($reservation, 'Checkout expired');

        $level->refresh();
        $reservation->refresh();
        $this->assertEquals(0, $level->reserved_quantity);
        $this->assertEquals(4, $level->available_quantity);
        $this->assertEquals('released', $reservation->status);
    }

    /**
     * Test 15: Stock Reservation conversion to order fulfillment moves reserved stock to sold.
     */
    public function test_inventory_reservation_conversion_to_fulfillment(): void
    {
        $product = Product::create([
            'name' => 'Ruby Raw Cashmere Shawl',
            'sku' => 'RUB-RAW-001',
            'price_npr' => 2200.00,
            'cost_price_npr' => 700.00,
            'quantity' => 3,
            'is_active' => true,
        ]);

        $this->inventoryService->recordStockMovement([
            'warehouse_id' => $this->warehouseKtm->id,
            'product_id' => $product->id,
            'movement_type' => 'opening_stock',
            'quantity' => 3,
            'unit_cost_npr' => 700.00,
            'reason' => 'Opening stock',
        ]);

        $reservation = $this->inventoryService->createReservation([
            'warehouse_id' => $this->warehouseKtm->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'reservation_type' => 'order',
        ], $this->admin);

        $order = Order::create([
            'order_number' => 'ORD-RESV-CONVERT-001',
            'first_name' => 'Pooja',
            'last_name' => 'Sharma',
            'email' => 'pooja@laijau.com',
            'shipping_address' => 'Baneshwor 10',
            'shipping_country' => 'NP',
            'subtotal' => 2200.00,
            'total_amount' => 2200.00,
            'currency' => 'npr',
            'status' => 'processing',
        ]);

        $this->inventoryService->convertReservationToFulfillment($reservation, $order, $this->admin);

        $reservation->refresh();
        $this->assertEquals('converted', $reservation->status);

        $level = StockLevel::where('warehouse_id', $this->warehouseKtm->id)
            ->where('product_id', $product->id)
            ->first();

        $this->assertEquals(2, $level->quantity_on_hand);
        $this->assertEquals(0, $level->reserved_quantity);
        $this->assertEquals(2, $level->available_quantity);
    }

    /**
     * Test 16: Sweep expired reservations automatically restores available stock.
     */
    public function test_cleanup_expired_reservations(): void
    {
        $product = Product::create([
            'name' => 'Silver Trim Pashmina',
            'sku' => 'SLV-PAS-001',
            'price_npr' => 1600.00,
            'quantity' => 5,
            'is_active' => true,
        ]);

        $this->inventoryService->recordStockMovement([
            'warehouse_id' => $this->warehouseKtm->id,
            'product_id' => $product->id,
            'movement_type' => 'opening_stock',
            'quantity' => 5,
            'unit_cost_npr' => 500.00,
        ]);

        $reservation = $this->inventoryService->createReservation([
            'warehouse_id' => $this->warehouseKtm->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'reservation_type' => 'cart',
            'expires_in_minutes' => 10,
        ]);

        // Fast-forward expiration
        $reservation->update(['expires_at' => now()->subMinutes(5)]);

        $releasedCount = $this->inventoryService->cleanupExpiredReservations();
        $this->assertGreaterThanOrEqual(1, $releasedCount);

        $reservation->refresh();
        $this->assertEquals('expired', $reservation->status);

        $level = StockLevel::where('warehouse_id', $this->warehouseKtm->id)->where('product_id', $product->id)->first();
        $this->assertEquals(0, $level->reserved_quantity);
        $this->assertEquals(5, $level->available_quantity);
    }

    /**
     * Test 17: Concurrency & stock safety rejects overselling when stock is insufficient.
     */
    public function test_overselling_prevention(): void
    {
        $product = Product::create([
            'name' => 'Single Exclusive Item',
            'sku' => 'EXC-001',
            'price_npr' => 5000.00,
            'quantity' => 1,
            'is_active' => true,
        ]);

        $this->inventoryService->recordStockMovement([
            'warehouse_id' => $this->warehouseKtm->id,
            'product_id' => $product->id,
            'movement_type' => 'opening_stock',
            'quantity' => 1,
            'unit_cost_npr' => 2000.00,
        ]);

        // Attempting to reserve 2 when only 1 exists
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Insufficient available stock');

        $this->inventoryService->createReservation([
            'warehouse_id' => $this->warehouseKtm->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
    }

    /**
     * Test 18: Purchase Order submit, approve, partial receive, and cancellation workflow.
     */
    public function test_purchase_order_lifecycle_and_partial_receiving(): void
    {
        $product = Product::create([
            'name' => 'Cotton Fabric Roll',
            'sku' => 'FAB-COT-001',
            'price_npr' => 800.00,
            'quantity' => 0,
            'is_active' => true,
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-LIFECYCLE-001',
            'supplier_id' => $this->supplierPashmina->id,
            'warehouse_id' => $this->warehouseKtm->id,
            'order_date' => now()->toDateString(),
            'currency' => 'npr',
            'exchange_rate_to_npr' => 1.0,
            'total_amount_npr' => 5000.00,
            'status' => 'draft',
        ]);

        $item = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity_ordered' => 10,
            'quantity_received' => 0,
            'unit_cost_currency' => 500.00,
            'unit_cost_npr' => 500.00,
            'total_cost_npr' => 5000.00,
        ]);

        // 1. Submit PO
        $this->inventoryService->submitPurchaseOrder($po, $this->admin);
        $po->refresh();
        $this->assertEquals('submitted', $po->status);

        // 2. Approve PO
        $this->inventoryService->approvePurchaseOrder($po, $this->admin);
        $po->refresh();
        $this->assertEquals('approved', $po->status);

        // 3. Partial Receiving: 4 of 10
        $this->inventoryService->receivePurchaseOrder($po, [$item->id => 4], $this->admin);
        $po->refresh();
        $this->assertEquals('partially_received', $po->status);

        $level = StockLevel::where('warehouse_id', $this->warehouseKtm->id)->where('product_id', $product->id)->first();
        $this->assertEquals(4, $level->quantity_on_hand);

        // 4. Remaining Receiving: 6 of 10
        $this->inventoryService->receivePurchaseOrder($po, [$item->id => 6], $this->admin);
        $po->refresh();
        $this->assertEquals('received', $po->status);

        $level->refresh();
        $this->assertEquals(10, $level->quantity_on_hand);
    }

    /**
     * Test 19: Transfer cancellation restores in-transit stock back to the source warehouse.
     */
    public function test_transfer_cancellation_restores_in_transit_stock(): void
    {
        $product = Product::create([
            'name' => 'Yak Wool Blanket',
            'sku' => 'YAK-BLN-001',
            'price_npr' => 2000.00,
            'cost_price_npr' => 800.00,
            'quantity' => 10,
            'is_active' => true,
        ]);

        $this->inventoryService->recordStockMovement([
            'warehouse_id' => $this->warehouseKtm->id,
            'product_id' => $product->id,
            'movement_type' => 'opening_stock',
            'quantity' => 10,
            'unit_cost_npr' => 800.00,
        ]);

        $transfer = StockTransfer::create([
            'transfer_number' => 'TRF-CANCEL-001',
            'source_warehouse_id' => $this->warehouseKtm->id,
            'destination_warehouse_id' => $this->warehouseShowroom->id,
            'status' => 'draft',
        ]);

        StockTransferItem::create([
            'transfer_id' => $transfer->id,
            'product_id' => $product->id,
            'quantity_sent' => 5,
            'quantity_received' => 0,
        ]);

        // Dispatch
        $this->inventoryService->dispatchTransfer($transfer, $this->admin);
        $transfer->refresh();
        $this->assertEquals('in_transit', $transfer->status);

        $sourceLevel = StockLevel::where('warehouse_id', $this->warehouseKtm->id)->where('product_id', $product->id)->first();
        $this->assertEquals(5, $sourceLevel->quantity_on_hand);

        // Cancel transfer in transit -> restores 5 to source
        $this->inventoryService->cancelTransfer($transfer, 'Courier lost in storm / shipment recalled', $this->admin);
        $transfer->refresh();
        $this->assertEquals('cancelled', $transfer->status);

        $sourceLevel->refresh();
        $this->assertEquals(10, $sourceLevel->quantity_on_hand);
    }

    /**
     * Test 20: Customer return with damaged disposition isolates damaged stock and posts GL 1230 write-off.
     */
    public function test_customer_return_with_damaged_disposition_and_gl_write_off(): void
    {
        $product = Product::create([
            'name' => 'Damaged Return Item Test',
            'sku' => 'DAM-RET-001',
            'price_npr' => 2500.00,
            'cost_price_npr' => 1000.00,
            'quantity' => 5,
            'is_active' => true,
        ]);

        $this->inventoryService->recordStockMovement([
            'warehouse_id' => $this->warehouseKtm->id,
            'product_id' => $product->id,
            'movement_type' => 'opening_stock',
            'quantity' => 5,
            'unit_cost_npr' => 1000.00,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-DAMAGED-RET-001',
            'first_name' => 'Anish',
            'last_name' => 'Karki',
            'email' => 'anish@laijau.com',
            'shipping_address' => 'Jhamsikhel 5',
            'shipping_country' => 'NP',
            'subtotal' => 2500.00,
            'total_amount' => 2500.00,
            'currency' => 'npr',
            'status' => 'completed',
        ]);

        $this->inventoryService->processCustomerReturn(
            $order,
            [['product_id' => $product->id, 'quantity' => 1]],
            'damaged',
            'Customer snagged delicate silk weave',
            $this->admin
        );

        $movement = StockMovement::where('reference_type', 'order_return_damaged')
            ->where('reference_id', $order->id)
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals('damage', $movement->movement_type);

        $level = StockLevel::where('warehouse_id', $this->warehouseKtm->id)->where('product_id', $product->id)->first();
        $this->assertEquals(1, $level->damaged_quantity);
        $this->assertEquals(5, $level->quantity_on_hand);
        $this->assertEquals(4, $level->available_quantity); // available = 5 on_hand - 1 damaged

        // Check GL Write-off journal entry (Dr 1230 / Cr 2210)
        $writeOffVoucher = JournalEntry::where('reference_type', 'damaged_return')
            ->where('reference_id', $order->id)
            ->first();

        $this->assertNotNull($writeOffVoucher);
        $this->assertEquals(1000.00, (float)$writeOffVoucher->total_debit);
    }

    /**
     * Test 21: Accounting Reconciliation against nepalese GL Account 2210 (Inventory Asset).
     */
    public function test_accounting_gl_2210_reconciliation(): void
    {
        $recon = $this->inventoryService->getAccountingReconciliation();

        $this->assertArrayHasKey('operational_valuation_npr', $recon);
        $this->assertArrayHasKey('gl_inventory_balance_npr', $recon);
        $this->assertArrayHasKey('variance_npr', $recon);
        $this->assertArrayHasKey('status', $recon);
        $this->assertArrayHasKey('reconciled_at', $recon);
    }

    /**
     * Test 22: Reorder Intelligence & PO Generation from low stock recommendations.
     */
    public function test_reorder_intelligence_and_po_generation(): void
    {
        $product = Product::create([
            'name' => 'Hand-spun Wool Throw',
            'sku' => 'THR-WOL-001',
            'price_npr' => 1400.00,
            'cost_price_npr' => 450.00,
            'quantity' => 1,
            'is_active' => true,
        ]);

        $level = StockLevel::create([
            'warehouse_id' => $this->warehouseKtm->id,
            'product_id' => $product->id,
            'quantity_on_hand' => 1,
            'reorder_point' => 5,
            'safety_stock' => 2,
            'unit_cost_npr' => 450.00,
        ]);

        $report = $this->inventoryService->getReorderIntelligenceReport();
        $this->assertArrayHasKey('items', $report);
        $this->assertArrayHasKey('summary', $report);

        $matchingItem = collect($report['items'])->firstWhere('sku', 'THR-WOL-001');
        $this->assertNotNull($matchingItem);
        $this->assertTrue(in_array($matchingItem['status'], ['REORDER REQUIRED', 'LOW STOCK']));

        // Generate PO from recommendations
        $po = $this->inventoryService->generatePurchaseOrderFromRecommendations(
            $this->supplierPashmina->id,
            $this->warehouseKtm->id,
            [$product->id],
            $this->admin
        );

        $this->assertNotNull($po);
        $this->assertEquals('draft', $po->status);
        $this->assertEquals(1, $po->items()->count());
    }

    /**
     * Test 23: Filament Admin Inventory Resources, Dashboard, Valuation, and Reports render.
     */
    public function test_filament_admin_inventory_pages_render(): void
    {
        $this->actingAs($this->admin, 'admin');

        // Dashboard
        $this->get(InventoryDashboard::getUrl())->assertStatus(200);

        // Valuation Page
        $this->get(InventoryValuationPage::getUrl())->assertStatus(200);

        // Reports Page
        $this->get(\App\Filament\Pages\InventoryReportsPage::getUrl())->assertStatus(200);

        // Resources
        $this->get(StockLevelResource::getUrl('index'))->assertStatus(200);
        $this->get(StockMovementResource::getUrl('index'))->assertStatus(200);
        $this->get(\App\Filament\Resources\StockReservationResource::getUrl('index'))->assertStatus(200);
        $this->get(WarehouseResource::getUrl('index'))->assertStatus(200);
        $this->get(SupplierResource::getUrl('index'))->assertStatus(200);
        $this->get(PurchaseOrderResource::getUrl('index'))->assertStatus(200);
        $this->get(StockTransferResource::getUrl('index'))->assertStatus(200);
        $this->get(StockAdjustmentResource::getUrl('index'))->assertStatus(200);
        $this->get(StockCountResource::getUrl('index'))->assertStatus(200);
    }
}
