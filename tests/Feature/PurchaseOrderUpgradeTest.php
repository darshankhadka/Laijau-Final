<?php

namespace Tests\Feature;

use App\Models\Inventory\PurchaseOrder;
use App\Models\Inventory\PurchaseOrderItem;
use App\Models\Inventory\Supplier;
use App\Models\Inventory\Warehouse;
use App\Models\Product;
use App\Models\User;
use Tests\TestCase;

class PurchaseOrderUpgradeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql']);
        config(['database.connections.mysql.database' => 'laijau_staging']);
        config(['database.connections.mysql.host' => '127.0.0.1']);
        config(['database.connections.mysql.username' => 'darshan']);
        config(['database.connections.mysql.password' => 'Dars@@9861']);
        \Illuminate\Support\Facades\DB::purge('mysql');
        \Illuminate\Support\Facades\DB::reconnect('mysql');
    }

    public function test_purchase_order_item_lifecycle_auto_calculates_line_and_po_totals(): void
    {
        $supplier = Supplier::first() ?? Supplier::create([
            'name' => 'Test Artisan Guild',
            'email' => 'artisan@example.com',
            'is_active' => true,
        ]);

        $warehouse = Warehouse::first() ?? Warehouse::create([
            'name' => 'Test KTM Warehouse',
            'code' => 'WH-TEST',
            'is_active' => true,
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-' . uniqid(),
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'order_date' => now()->toDateString(),
            'status' => 'draft',
            'shipping_cost_npr' => 500.00,
            'customs_duty_npr' => 200.00,
        ]);

        $product = Product::first();

        $item = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity_ordered' => 10,
            'unit_cost_currency' => 1200.00,
            'unit_cost_npr' => 1200.00,
        ]);

        // Assert item total_cost_npr was auto-calculated by model booted hook
        $this->assertEquals(12000.00, (float)$item->fresh()->total_cost_npr);

        // Assert PO total was auto-recalculated (12000 + 500 shipping + 200 customs = 12700)
        $this->assertEquals(12700.00, (float)$po->fresh()->total_amount_npr);

        // Clean up
        $item->delete();
        $po->delete();
    }

    public function test_purchase_order_printable_voucher_renders_with_authorized_user(): void
    {
        $admin = User::where('role', 'super_admin')->first() ?? User::where('role', 'admin')->first() ?? User::first();
        $po = PurchaseOrder::with(['items', 'supplier', 'warehouse'])->latest()->first();

        $this->assertNotNull($po, 'At least one PO should exist in the database');

        $response = $this->actingAs($admin)->get("/intadmin/purchase-orders/{$po->id}/print");
        $response->assertStatus(200);
        $response->assertSee($po->po_number);
        $response->assertSee('LAIJAU ENTERPRISES');
        $response->assertSee('Purchase Order');
    }

    public function test_purchase_order_inspect_modal_renders_properly(): void
    {
        $po = PurchaseOrder::with(['items.product', 'supplier', 'warehouse'])->latest()->first();
        $this->assertNotNull($po);

        $html = view('filament.components.purchase-order-inspect-modal', ['record' => $po])->render();
        $this->assertStringContainsString($po->po_number, $html);
        $this->assertStringContainsString('Total Landed Value', $html);
        $this->assertStringContainsString('Print PO Voucher', $html);
    }
}
