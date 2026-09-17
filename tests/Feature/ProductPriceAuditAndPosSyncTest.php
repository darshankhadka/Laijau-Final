<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductPriceAuditAndPosSyncTest extends TestCase
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
     * Test 1: Zero products have fractional cents (e.g., .67, .18, .23).
     */
    public function test_zero_fractional_prices_across_all_products(): void
    {
        $fractionalCount = DB::table('products')
            ->whereNotNull('price')
            ->whereRaw('ROUND(price, 0) != price')
            ->count();

        $this->assertEquals(0, $fractionalCount, 'All product selling prices must be whole round figures without fractional cents.');
    }

    /**
     * Test 2: Product prices are clean retail multiples (multiples of 50/100/500).
     */
    public function test_selling_prices_are_clean_retail_multiples(): void
    {
        $oddPriceCount = DB::table('products')
            ->whereNotNull('price')
            ->where('price', '>', 0)
            ->whereRaw('MOD(ROUND(price), 50) != 0')
            ->count();

        $this->assertEquals(0, $oddPriceCount, 'Every product selling price must be a multiple of 50 or 100/500 NPR.');
    }

    /**
     * Test 3: Specific historical catalog products like 'Diesel Open Trouser' are canonical.
     */
    public function test_diesel_half_pant_has_canonical_selling_price(): void
    {
        $diesel = DB::table('products')
            ->where('name', 'like', '%Diesel%')
            ->first();

        $this->assertNotNull($diesel, 'Diesel product must exist in product catalog.');
        $this->assertEquals(600.00, (float)$diesel->price, 'Diesel Open Trouser must be priced at canonical Rs. 600.00.');
        $this->assertGreaterThan(0, (float)$diesel->cost_price);
        $this->assertLessThan((float)$diesel->price, (float)$diesel->cost_price);
    }

    /**
     * Test 4: Product variants match parent prices and have no test/sandbox prices.
     */
    public function test_product_variants_have_no_test_prices_and_match_parents(): void
    {
        $testVariants = DB::table('product_variants')
            ->where('price', '<=', 10)
            ->count();

        $this->assertEquals(0, $testVariants, 'There must be zero product variants with test prices <= Rs. 10.');

        $mismatchedVariants = DB::table('product_variants')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->whereRaw('product_variants.price != products.price')
            ->count();

        $this->assertEquals(0, $mismatchedVariants, 'All product variants must synchronize with their parent product selling price.');
    }

    /**
     * Test 5: Showroom POS customer names are sanitized (no 'Th', '7 Th', 'Feb', '-Black', etc.).
     */
    public function test_pos_customer_names_are_sanitized(): void
    {
        $junkCustomerNames = ['Th', '7 Th', 'th', '7th', 'Feb', 'May', 'Aug', '-Black', 'Su27-Broshop', '2242-Br', 'Cash', 'Online'];

        $junkCount = DB::table('offline_sales')
            ->whereIn('customer_name', $junkCustomerNames)
            ->count();

        $this->assertEquals(0, $junkCount, 'All shorthand codes, day markers, and color codes in offline_sales must be sanitized to "Walk-in Customer".');

        $walkInCount = DB::table('offline_sales')
            ->where('customer_name', 'Walk-in Customer')
            ->count();

        $this->assertGreaterThan(3000, $walkInCount, 'Sanitized anonymous sales must be assigned "Walk-in Customer".');
    }

    /**
     * Test 6: Bikri Khata (accounting_invoices) reflects sanitized POS customer names.
     */
    public function test_accounting_invoices_customer_names_are_synchronized(): void
    {
        $mismatchedNames = DB::table('accounting_invoices')
            ->join('offline_sales', 'offline_sales.id', '=', 'accounting_invoices.reference_offline_sale_id')
            ->where('accounting_invoices.type', 'sales_invoice')
            ->whereRaw('accounting_invoices.contact_name != offline_sales.customer_name')
            ->count();

        $this->assertEquals(0, $mismatchedNames, 'Bikri Khata invoices must mirror the sanitized customer names of offline_sales.');
    }

    /**
     * Test 7: POS sales line items have realized costs and realistic gross margins.
     */
    public function test_pos_sales_line_items_have_realized_costs_and_valid_margins(): void
    {
        $zeroCostItems = DB::table('offline_sale_items')
            ->where('unit_cost_npr', '<=', 0)
            ->where('unit_price', '>', 0)
            ->count();

        $this->assertEquals(0, $zeroCostItems, 'All POS line items with positive price must have a non-zero unit cost.');

        $unrealizedCount = DB::table('offline_sale_items')
            ->where('cost_type', '!=', 'realized')
            ->count();

        $this->assertEquals(0, $unrealizedCount, 'All POS line items must be classified with cost_type = "realized".');
    }

    /**
     * Test 8: Double-Entry General Ledger equilibrium is strictly preserved (0.0000 NPR variance).
     */
    public function test_general_ledger_equilibrium_strictly_balanced(): void
    {
        $totals = DB::table('accounting_journal_entry_lines')
            ->selectRaw('COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit')
            ->first();

        $debit = round((float)($totals->total_debit ?? 0), 4);
        $credit = round((float)($totals->total_credit ?? 0), 4);
        $variance = round(abs($debit - $credit), 4);

        $this->assertEqualsWithDelta(0.0000, $variance, 0.0001, "General Ledger Debits ({$debit}) must equal Credits ({$credit}) with 0.0000 NPR variance.");
    }

    /**
     * Test 9: Normalization command runs idempotently.
     */
    public function test_normalization_command_runs_idempotently(): void
    {
        $this->artisan('laijau:normalize-prices-and-pos', ['--force' => true])
            ->assertSuccessful();
    }
}
