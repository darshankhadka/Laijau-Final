<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Accounting\AccountingInvoice;
use App\Models\Accounting\AccountingInvoiceItem;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Inventory\PurchaseOrderItem;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\Supplier;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthoritativePurchaseImportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql']);
        config(['database.connections.mysql.database' => 'laijau_staging']);
        config(['database.connections.mysql.host' => '127.0.0.1']);
        config(['database.connections.mysql.username' => 'darshan']);
        config(['database.connections.mysql.password' => 'Dars@@9861']);
        DB::purge('mysql');
        DB::reconnect('mysql');
    }

    /**
     * Test 1: All 103 delivery batches and 635 purchase items are fully imported.
     */
    public function test_authoritative_purchase_orders_and_items_are_fully_imported(): void
    {
        $poCount = PurchaseOrder::where('po_number', 'LIKE', 'PO-2026-%')->count();
        $this->assertEquals(103, $poCount, 'Exactly 103 purchase order delivery batches must be recorded.');

        $receivedCount = PurchaseOrder::where('po_number', 'LIKE', 'PO-2026-%')
            ->where('status', 'received')
            ->count();
        $this->assertEquals(103, $receivedCount, 'All imported purchase orders must be in received status.');

        $itemCount = PurchaseOrderItem::whereHas('purchaseOrder', function ($q) {
            $q->where('po_number', 'LIKE', 'PO-2026-%');
        })->count();
        $this->assertEquals(635, $itemCount, 'All 635 purchase line items from real PDFs must be imported.');

        $totalPcs = (int)PurchaseOrderItem::whereHas('purchaseOrder', function ($q) {
            $q->where('po_number', 'LIKE', 'PO-2026-%');
        })->sum('quantity_received');
        $this->assertEquals(13476, $totalPcs, 'Total purchased units must be exactly 13,476 pieces.');

        $totalVal = (float)PurchaseOrder::where('po_number', 'LIKE', 'PO-2026-%')->sum('total_amount_npr');
        $this->assertEqualsWithDelta(10593350.00, $totalVal, 5000.00, 'Total procurement value must be approximately NPR 10,593,350.00.');
    }

    /**
     * Test 2: Purchase items strictly link to valid products, variants, and suppliers.
     */
    public function test_purchase_items_link_to_valid_products_and_suppliers(): void
    {
        $orphanItems = PurchaseOrderItem::whereDoesntHave('product')->count();
        $this->assertEquals(0, $orphanItems, 'Zero orphan purchase order items allowed: all must link to valid products.');

        $orphanPOs = PurchaseOrder::whereDoesntHave('supplier')->count();
        $this->assertEquals(0, $orphanPOs, 'Zero orphan purchase orders allowed: all must link to valid suppliers.');

        $zeroQtyCount = PurchaseOrderItem::where('quantity_received', '<=', 0)->count();
        $this->assertEquals(0, $zeroQtyCount, 'All purchase items must have positive pieces quantity.');

        $zeroCostCount = PurchaseOrderItem::where('unit_cost_npr', '<=', 0)->count();
        $this->assertEquals(0, $zeroCostCount, 'All purchase items must have positive unit cost rate.');
    }

    /**
     * Test 3: Statutory Kharid Khata bills registered with complete item breakdown.
     */
    public function test_statutory_kharid_khata_bills_registered_in_accounting(): void
    {
        $poBillsCount = AccountingInvoice::where('type', 'supplier_bill')
            ->where('invoice_number', 'LIKE', 'BILL-2026-%')
            ->count();
        $this->assertEquals(103, $poBillsCount, 'Exactly 103 Kharid Khata purchase bills must be registered.');

        $totalSupplierBills = AccountingInvoice::where('type', 'supplier_bill')->count();
        $this->assertGreaterThanOrEqual(110, $totalSupplierBills, 'Total supplier bills must be at least 110 (103 PO bills + opening bills).');

        $orphanBillItems = AccountingInvoiceItem::whereDoesntHave('invoice')->count();
        $this->assertEquals(0, $orphanBillItems, 'Zero orphan invoice items allowed.');
    }

    /**
     * Test 4: Inventory stock movements and warehouse levels reflect purchases.
     */
    public function test_stock_movements_and_levels_reflect_purchases(): void
    {
        $movementCount = StockMovement::where('movement_type', 'purchase_receive')
            ->where('movement_number', 'LIKE', 'SM-PO-%')
            ->count();
        $this->assertEquals(635, $movementCount, 'Exactly 635 purchase stock movements must be recorded.');

        $totalMovementQty = (int)StockMovement::where('movement_type', 'purchase_receive')
            ->where('movement_number', 'LIKE', 'SM-PO-%')
            ->sum('quantity');
        $this->assertEquals(13476, $totalMovementQty, 'Stock movements must total exactly 13,476 pieces.');
    }

    /**
     * Test 5: General Ledger double-entry equilibrium is strictly preserved (0.0000 NPR variance).
     */
    public function test_general_ledger_equilibrium_is_strictly_preserved(): void
    {
        $totalDebits = (float)DB::table('accounting_journal_entry_lines')->sum('debit');
        $totalCredits = (float)DB::table('accounting_journal_entry_lines')->sum('credit');
        $variance = abs($totalDebits - $totalCredits);

        $this->assertGreaterThan(50000000.0, $totalDebits, 'GL debits must reflect full transaction volume.');
        $this->assertEquals(0.0000, round($variance, 4), 'General Ledger variance must be strictly 0.0000 NPR (Debits === Credits).');
    }

    /**
     * Test 6: Catalog photo authorization, realistic margins, and sales sync remain intact.
     */
    public function test_catalog_photo_authorization_and_realistic_margins_remain_intact(): void
    {
        // 532 published products with real thumbnails
        $publishedCount = Product::where('is_published', true)->count();
        $this->assertEquals(532, $publishedCount, 'Only the 532 products with verified physical photos must be published.');

        // Zero products with fake excessive margin (>65%)
        $highMarginProducts = Product::where('price', '>', 0)
            ->whereRaw('(price - cost_price) / price > 0.65')
            ->count();
        $this->assertEquals(0, $highMarginProducts, 'Zero products may show fake excessive profit margin (>65%).');

        // Zero offline sales with zero cost
        $zeroCostSales = DB::table('offline_sales')
            ->where('total_amount', '>', 0)
            ->where('total_cost_npr', '<=', 0)
            ->count();
        $this->assertEquals(0, $zeroCostSales, 'Zero POS sales should have 0 cost.');
    }
}
