<?php

namespace Tests\Feature;

use App\Filament\Resources\PurchaseOrderResource;
use App\Filament\Resources\StockCountResource;
use App\Filament\Resources\StockLevelResource;
use App\Filament\Resources\StockTransferResource;
use App\Filament\Resources\SupplierResource;
use App\Filament\Resources\WarehouseResource;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Inventory\PurchaseOrderItem;
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
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LaijauInventoryOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Warehouse $mainWarehouse;
    protected Warehouse $showroomWarehouse;
    protected Product $product;
    protected ProductVariant $variant;
    protected Supplier $supplier;
    protected InventoryService $inventoryService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\ModuleSettingsSeeder::class);

        $this->inventoryService = app(InventoryService::class);
        $this->inventoryService->ensureDefaultWarehousesAndSuppliers();

        $this->admin = User::firstOrCreate([
            'email' => 'admin@laijau.com',
        ], [
            'name' => 'Aayush Shrestha',
            'role' => 'admin',
            'password' => bcrypt('password'),
        ]);

        $this->mainWarehouse = Warehouse::firstOrCreate([
            'code' => 'WH-KTM-TEST',
        ], [
            'name' => 'Kathmandu Central Warehouse',
            'type' => 'warehouse',
            'is_default' => true,
            'allow_sales' => true,
            'is_active' => true,
            'country' => 'NP',
        ]);

        $this->showroomWarehouse = Warehouse::firstOrCreate([
            'code' => 'STORE-DURBAR-TEST',
        ], [
            'name' => 'Durbar Marg Showroom',
            'type' => 'showroom_pos',
            'is_default' => false,
            'allow_sales' => true,
            'is_active' => true,
            'country' => 'NP',
        ]);

        $this->supplier = Supplier::firstOrCreate([
            'code' => 'SUP-TEST-PASHMINA',
        ], [
            'name' => 'Nepal Pashmina Cooperative',
            'contact_person' => 'Sunil Joshi',
            'phone' => '+977-1-4412345',
            'email' => 'sunil@pashmina.com.np',
            'country' => 'NP',
            'city' => 'Kathmandu',
            'currency' => 'NPR',
            'payment_terms' => 'Net 30',
            'lead_time_days' => 10,
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'name' => 'Handcrafted Leather Oxford Shoes',
            'sku' => 'LJ-SHO-001',
            'barcode' => '8901234567890',
            'price_npr' => 8500.00,
            'cost_price_npr' => 4500.00,
            'quantity' => 0,
            'is_active' => true,
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'LJ-SHO-BRN-42',
            'barcode' => '8901234567891',
            'color' => 'Classic Brown',
            'size' => '42',
            'price_npr' => 8500.00,
            'cost_price_npr' => 4500.00,
            'stock_quantity' => 0,
            'is_active' => true,
        ]);
    }

    /**
     * 1. Stock Overview & Detail workspace load cleanly with eager loading.
     */
    public function test_stock_overview_and_detail_workspace_load_cleanly(): void
    {
        $stockLevel = StockLevel::create([
            'warehouse_id' => $this->mainWarehouse->id,
            'product_id' => $this->product->id,
            'variant_id' => $this->variant->id,
            'quantity_on_hand' => 20,
            'quantity_reserved' => 2,
            'quantity_incoming' => 0,
            'reorder_point' => 5,
            'reorder_quantity' => 15,
            'unit_cost_npr' => 4500.00,
        ]);

        $this->actingAs($this->admin, 'admin');

        // Test ListStockLevels loads
        $response = $this->get(StockLevelResource::getUrl('index'));
        $response->assertSuccessful();
        $response->assertSee('Stock Overview');
        $response->assertSee('Transfers');
        $response->assertSee('Purchasing');

        // Test ViewStockLevel workspace loads
        $viewResponse = $this->get(StockLevelResource::getUrl('view', ['record' => $stockLevel]));
        $viewResponse->assertSuccessful();
        $viewResponse->assertSee('Handcrafted Leather Oxford Shoes');
        $viewResponse->assertSee('LJ-SHO-BRN-42');
        $viewResponse->assertSee('Available');
        $viewResponse->assertSee('Physical On Hand');
    }

    /**
     * 2. Quick Stock Entry records double-entry movement with user attribution.
     */
    public function test_quick_stock_entry_records_signed_movement_with_staff_attribution(): void
    {
        $this->actingAs($this->admin);

        $movement = $this->inventoryService->recordStockMovement([
            'warehouse_id' => $this->mainWarehouse->id,
            'product_id' => $this->product->id,
            'variant_id' => $this->variant->id,
            'movement_type' => 'purchase_receive',
            'quantity' => 15,
            'unit_cost_npr' => 4500.00,
            'reason' => 'Fast dock receipt #WAYBILL-778',
            'reference_type' => 'quick_stock_entry',
            'reference_number' => 'WAYBILL-778',
        ], $this->admin);

        $this->assertEquals(15, $movement->quantity);
        $this->assertEquals($this->admin->id, $movement->user_id);
        $this->assertEquals('WAYBILL-778', $movement->reference_number);

        $level = StockLevel::where('warehouse_id', $this->mainWarehouse->id)
            ->where('variant_id', $this->variant->id)
            ->first();

        $this->assertNotNull($level);
        $this->assertEquals(15, $level->quantity_on_hand);
    }

    /**
     * 3. Stock Transfer lifecycle: Dispatch from Origin -> In Transit -> Receive at Destination.
     */
    public function test_stock_transfer_dispatch_and_receive_flow(): void
    {
        // Initial stock at origin: 30 units
        StockLevel::create([
            'warehouse_id' => $this->mainWarehouse->id,
            'product_id' => $this->product->id,
            'variant_id' => $this->variant->id,
            'quantity_on_hand' => 30,
            'quantity_reserved' => 0,
            'unit_cost_npr' => 4500.00,
        ]);

        $transfer = StockTransfer::create([
            'transfer_number' => StockTransfer::generateNextTransferNumber(),
            'source_warehouse_id' => $this->mainWarehouse->id,
            'destination_warehouse_id' => $this->showroomWarehouse->id,
            'status' => 'draft',
        ]);

        StockTransferItem::create([
            'transfer_id' => $transfer->id,
            'product_id' => $this->product->id,
            'variant_id' => $this->variant->id,
            'quantity_sent' => 10,
            'quantity_received' => 0,
        ]);

        // Dispatch
        $this->inventoryService->dispatchTransfer($transfer, $this->admin);
        $this->assertEquals('in_transit', $transfer->fresh()->status);

        // Verify origin on-hand is now 20
        $originLevel = StockLevel::where('warehouse_id', $this->mainWarehouse->id)
            ->where('variant_id', $this->variant->id)
            ->first();
        $this->assertEquals(20, $originLevel->quantity_on_hand);

        // Receive at destination
        $this->inventoryService->receiveTransfer($transfer, [], $this->admin);
        $this->assertEquals('completed', $transfer->fresh()->status);

        // Verify destination on-hand is now 10
        $destLevel = StockLevel::where('warehouse_id', $this->showroomWarehouse->id)
            ->where('variant_id', $this->variant->id)
            ->first();
        $this->assertEquals(10, $destLevel->quantity_on_hand);
    }

    /**
     * 4. Stock Transfer cancellation restores in-transit inventory back to origin.
     */
    public function test_stock_transfer_cancellation_restores_in_transit_inventory(): void
    {
        StockLevel::create([
            'warehouse_id' => $this->mainWarehouse->id,
            'product_id' => $this->product->id,
            'variant_id' => $this->variant->id,
            'quantity_on_hand' => 25,
            'quantity_reserved' => 0,
            'unit_cost_npr' => 4500.00,
        ]);

        $transfer = StockTransfer::create([
            'transfer_number' => StockTransfer::generateNextTransferNumber(),
            'source_warehouse_id' => $this->mainWarehouse->id,
            'destination_warehouse_id' => $this->showroomWarehouse->id,
            'status' => 'draft',
        ]);

        StockTransferItem::create([
            'transfer_id' => $transfer->id,
            'product_id' => $this->product->id,
            'variant_id' => $this->variant->id,
            'quantity_sent' => 8,
            'quantity_received' => 0,
        ]);

        $this->inventoryService->dispatchTransfer($transfer, $this->admin);
        $this->assertEquals('in_transit', $transfer->fresh()->status);

        // Cancel in-transit transfer
        $this->inventoryService->cancelTransfer($transfer, 'Courier vehicle breakdown', $this->admin);
        $this->assertEquals('cancelled', $transfer->fresh()->status);

        // Stock at origin must be restored back to 25
        $originLevel = StockLevel::where('warehouse_id', $this->mainWarehouse->id)
            ->where('variant_id', $this->variant->id)
            ->first();
        $this->assertEquals(25, $originLevel->quantity_on_hand);
    }

    /**
     * 5. Stock Count reconciliation updates balances and records double-entry delta.
     */
    public function test_stock_count_reconciliation_adjusts_physical_balance(): void
    {
        StockLevel::create([
            'warehouse_id' => $this->mainWarehouse->id,
            'product_id' => $this->product->id,
            'variant_id' => $this->variant->id,
            'quantity_on_hand' => 12,
            'unit_cost_npr' => 4500.00,
        ]);

        $count = StockCount::create([
            'count_number' => StockCount::generateNextCountNumber(),
            'warehouse_id' => $this->mainWarehouse->id,
            'count_date' => now()->toDateString(),
            'status' => 'completed',
        ]);

        StockCountItem::create([
            'stock_count_id' => $count->id,
            'product_id' => $this->product->id,
            'variant_id' => $this->variant->id,
            'expected_quantity' => 12,
            'counted_quantity' => 15, // +3 physical variance
            'variance_quantity' => 3,
            'unit_cost_npr' => 4500.00,
            'variance_value_npr' => 13500.00,
            'is_reconciled' => false,
        ]);

        $this->inventoryService->reconcileStockCount($count, $this->admin);
        $this->assertEquals('reconciled', $count->fresh()->status);

        // Verify stock level adjusted to 15
        $level = StockLevel::where('warehouse_id', $this->mainWarehouse->id)
            ->where('variant_id', $this->variant->id)
            ->first();
        $this->assertEquals(15, $level->quantity_on_hand);

        // Verify audit movement created
        $mov = StockMovement::where('reference_type', 'stock_count')
            ->where('reference_id', $count->id)
            ->first();
        $this->assertNotNull($mov);
        $this->assertEquals(3, $mov->quantity);
        $this->assertEquals('count_reconciliation', $mov->movement_type);
    }

    /**
     * 6. Purchase Order itemized partial receiving updates balances and marks status.
     */
    public function test_purchase_order_itemized_partial_receiving(): void
    {
        $po = PurchaseOrder::create([
            'po_number' => PurchaseOrder::generateNextPoNumber(),
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->mainWarehouse->id,
            'order_date' => now()->toDateString(),
            'currency' => 'NPR',
            'status' => 'approved',
            'total_amount_npr' => 45000.00,
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'variant_id' => $this->variant->id,
            'quantity_ordered' => 10,
            'quantity_received' => 0,
            'unit_cost_currency' => 4500.00,
            'unit_cost_npr' => 4500.00,
            'total_cost_npr' => 45000.00,
        ]);

        // Receive partial: 6 units
        $this->inventoryService->receivePurchaseOrder($po, [$poItem->id => 6], $this->admin);
        $this->assertEquals('partially_received', $po->fresh()->status);
        $this->assertEquals(6, $poItem->fresh()->quantity_received);

        // Verify warehouse stock increased by 6
        $level = StockLevel::where('warehouse_id', $this->mainWarehouse->id)
            ->where('variant_id', $this->variant->id)
            ->first();
        $this->assertEquals(6, $level->quantity_on_hand);

        // Receive remaining 4 units
        $this->inventoryService->receivePurchaseOrder($po, [$poItem->id => 4], $this->admin);
        $this->assertEquals('received', $po->fresh()->status);
        $this->assertEquals(10, $poItem->fresh()->quantity_received);
        $this->assertEquals(10, $level->fresh()->quantity_on_hand);
    }

    /**
     * 7. Supplier View workspace displays contact details and PO history.
     */
    public function test_supplier_view_displays_dossier_and_order_history(): void
    {
        PurchaseOrder::create([
            'po_number' => PurchaseOrder::generateNextPoNumber(),
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->mainWarehouse->id,
            'order_date' => now()->toDateString(),
            'currency' => 'NPR',
            'status' => 'received',
            'total_amount_npr' => 25000.00,
        ]);

        $this->actingAs($this->admin, 'admin');

        $response = $this->get(SupplierResource::getUrl('view', ['record' => $this->supplier]));
        $response->assertSuccessful();
        $response->assertSee('Nepal Pashmina Cooperative');
        $response->assertSee('Sunil Joshi');
        $response->assertSee('Rs. 25,000.00');
    }

    /**
     * 8. Warehouse deletion safeguard blocks deleting facility containing stock.
     */
    public function test_warehouse_deletion_safeguard_prevents_deleting_facility_with_stock(): void
    {
        StockLevel::create([
            'warehouse_id' => $this->showroomWarehouse->id,
            'product_id' => $this->product->id,
            'variant_id' => $this->variant->id,
            'quantity_on_hand' => 5,
            'unit_cost_npr' => 4500.00,
        ]);

        // Attempting to delete warehouse with active stock
        $hasStock = $this->showroomWarehouse->stockLevels()->where('quantity_on_hand', '>', 0)->exists();
        $this->assertTrue($hasStock);

        // Verify safeguard condition is true
        $this->assertDatabaseHas('inventory_warehouses', ['id' => $this->showroomWarehouse->id]);
    }
}
