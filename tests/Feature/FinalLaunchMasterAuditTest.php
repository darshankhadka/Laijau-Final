<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Helpers\NepaliDateConverter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinalLaunchMasterAuditTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql']);
        config(['database.connections.mysql.database' => 'LAIJAU']);
        DB::purge('mysql');
    }

    /**
     * Test 1: Native Nepali Bikram Sambat Date Converter precision
     */
    public function test_nepali_date_converter_accuracy(): void
    {
        // 2082/08/05 BS -> 2025-11-20 AD (Minimum Clothes purchase date)
        $this->assertEquals('2025-11-20', NepaliDateConverter::convertDateString('2082/08/05'));

        // 9/19/2082 BS -> 2026-01-02 AD (Minimum Shoe purchase date)
        $this->assertEquals('2026-01-02', NepaliDateConverter::convertDateString('9/19/2082'));

        // 5/14/2083 BS -> 2026-08-29 AD (Maximum Shoe purchase date)
        $this->assertEquals('2026-08-29', NepaliDateConverter::convertDateString('5/14/2083'));

        // 2083/05/01 BS -> 2026-08-16 AD (Maximum Clothes purchase date)
        $this->assertEquals('2026-08-16', NepaliDateConverter::convertDateString('2083/05/01'));

        // bsToAd integer method
        $this->assertEquals('2025-11-20', NepaliDateConverter::bsToAd(2082, 8, 5));
    }

    /**
     * Test 2: Procurement Purchase Orders Gregorian AD Date Range
     */
    public function test_purchase_orders_ad_date_range(): void
    {
        $minDate = DB::table('inventory_purchase_orders')->min('order_date');
        $maxDate = DB::table('inventory_purchase_orders')->max('order_date');

        $this->assertGreaterThanOrEqual('2025-11-20', substr($minDate, 0, 10));
        $this->assertLessThanOrEqual('2026-09-12', substr($maxDate, 0, 10));

        // Zero purchase orders in 2082 or 2083 BS string format
        $bsCount = DB::table('inventory_purchase_orders')->where('order_date', 'like', '208%')->count();
        $this->assertEquals(0, $bsCount);
    }

    /**
     * Test 3: Supplier Due Balance Hard Invariant (NPR 4,212,905.00)
     */
    public function test_supplier_due_balance_invariant(): void
    {
        $dueBalances = [
            'Citizen shoes' => 853200.00,
            'Star denim' => 470230.00,
            'Maxx Rider' => 828250.00,
            'Prasiddha footware' => 1601025.00,
            'SK shoes' => 378500.00,
            'Himshikhar shoes' => 81700.00,
        ];

        $totalExpected = 4212905.00;
        $totalActual = (float) DB::table('inventory_suppliers')->sum('due_balance');

        $this->assertEqualsWithDelta($totalExpected, $totalActual, 0.01, 'Total supplier due balance must equal NPR 4,212,905.00');

        foreach ($dueBalances as $supplierName => $expectedBal) {
            $actualBal = (float) DB::table('inventory_suppliers')->where('name', $supplierName)->value('due_balance');
            $this->assertEqualsWithDelta($expectedBal, $actualBal, 0.01, "Supplier {$supplierName} balance must match");
        }
    }

    /**
     * Test 4: Physical Inventory Hard Invariant (2,721 pcs)
     */
    public function test_physical_inventory_invariant(): void
    {
        $totalStock = (int) DB::table('inventory_stock_levels')->sum('quantity_on_hand');
        $shoesStock = (int) DB::table('inventory_stock_levels')
            ->join('products', 'products.id', '=', 'inventory_stock_levels.product_id')
            ->where('products.type', 'footwear')
            ->sum('inventory_stock_levels.quantity_on_hand');
        $clothesStock = (int) DB::table('inventory_stock_levels')
            ->join('products', 'products.id', '=', 'inventory_stock_levels.product_id')
            ->where('products.type', '!=', 'footwear')
            ->sum('inventory_stock_levels.quantity_on_hand');

        $this->assertEquals(736, $shoesStock, 'Footwear physical stock must reconcile to 736 pcs');
        $this->assertEquals(1985, $clothesStock, 'Apparel physical stock must reconcile to 1,985 pcs');
        $this->assertEquals(2721, $totalStock, 'Total physical inventory must reconcile to 2,721 pcs');
    }

    /**
     * Test 5: General Ledger Double-Entry Balance (Variance NPR 0.0000)
     */
    public function test_general_ledger_variance_invariant(): void
    {
        $totalDebit = (float) DB::table('accounting_journal_entry_lines')->sum('debit');
        $totalCredit = (float) DB::table('accounting_journal_entry_lines')->sum('credit');
        $variance = abs($totalDebit - $totalCredit);

        $this->assertGreaterThan(0, $totalDebit);
        $this->assertLessThan(0.0001, $variance, 'General Ledger debit and credit variance must be NPR 0.0000');
    }

    /**
     * Test 6: September 2026 Showroom Sales Finalization (161 Sales)
     */
    public function test_september_sales_finalization(): void
    {
        $sepSales = DB::table('offline_sales')->where('sale_number', 'LIKE', 'OFF-2026-09-%')->get();
        $this->assertCount(161, $sepSales, 'Must have exactly 161 September showroom sales');

        $totalRev = (float) $sepSales->sum('total_amount');
        $this->assertEqualsWithDelta(309199.00, $totalRev, 0.01, 'September revenue must equal NPR 309,199.00');

        $cash = (float) $sepSales->sum('cash_received');
        $this->assertEqualsWithDelta(85899.00, $cash, 0.01, 'September cash must equal NPR 85,899.00');

        // Zero legacy mock POS records
        $mockCount = DB::table('offline_sales')->where('sale_number', 'LIKE', 'OFF-0000%')->count();
        $this->assertEquals(0, $mockCount, 'Legacy mock POS records must be zero');
    }

    /**
     * Test 7: Purge of Test & Demo Products
     */
    public function test_zero_test_products_in_database(): void
    {
        $testPrefixes = ['TEST-%', 'COD-FLOW-%', 'CIPS-%', 'ESEWA-%', 'ECOM-%', 'POS-FLOW-%', 'PREPAID-%'];
        $testCount = DB::table('products')->where(function ($q) use ($testPrefixes) {
            foreach ($testPrefixes as $p) {
                $q->orWhere('name', 'LIKE', $p)->orWhere('sku', 'LIKE', $p);
            }
        })->count();

        $this->assertEquals(0, $testCount, 'Zero test/demo products must exist in database');

        // Zero mock LJ-00xx or LJ-TEST orders
        $mockOrders = DB::table('orders')->where(function ($q) {
            $q->where('order_number', 'LIKE', 'LJ-TEST-%')
              ->orWhere('order_number', 'LIKE', 'ORD-TEST%')
              ->orWhere('order_number', 'LIKE', 'ORD-SEC-%')
              ->orWhere('order_number', 'LIKE', 'LJ-00%')
              ->orWhere('order_number', 'LIKE', 'LJ-01%');
        })->count();

        $this->assertEquals(0, $mockOrders, 'Zero mock orders must exist in database');
    }

    /**
     * Test 8: Storefront 500+ Published Products Preserved
     */
    public function test_storefront_published_products_preserved(): void
    {
        $publishedCount = DB::table('products')->where('is_published', 1)->count();
        $this->assertGreaterThanOrEqual(500, $publishedCount, 'Storefront published catalog must be >= 500 products');

        // All published products must have price > 0
        $zeroPrice = DB::table('products')->where('is_published', 1)->where(function ($q) {
            $q->whereNull('price')->orWhere('price', '<=', 0);
        })->count();
        $this->assertEquals(0, $zeroPrice, 'Storefront products must have price > 0');
    }

    /**
     * Test 9: Complete Statutory Final Production Audit Pass
     */
    public function test_final_production_audit_command_pass(): void
    {
        $exitCode = Artisan::call('laijau:final-audit');
        $output = Artisan::output();

        $this->assertEquals(0, $exitCode, 'FinalProductionAuditCommand must exit with 0');
        $this->assertStringContainsString('FINAL CERTIFICATION = PASSED', $output);
    }
}
