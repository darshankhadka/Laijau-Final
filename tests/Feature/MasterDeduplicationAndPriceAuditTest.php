<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Inventory\Supplier;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MasterDeduplicationAndPriceAuditTest extends TestCase
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
     * Test 1: Zero duplicate SKUs across products and variants.
     */
    public function test_zero_duplicate_skus_across_products_and_variants(): void
    {
        $dupProductSkus = DB::table('products')
            ->select('sku', DB::raw('count(*) as c'))
            ->groupBy('sku')
            ->having('c', '>', 1)
            ->count();
        $this->assertEquals(0, $dupProductSkus, 'There must be strictly zero duplicate product SKUs.');

        $dupVariantSkus = DB::table('product_variants')
            ->select('sku', DB::raw('count(*) as c'))
            ->groupBy('sku')
            ->having('c', '>', 1)
            ->count();
        $this->assertEquals(0, $dupVariantSkus, 'There must be strictly zero duplicate variant SKUs.');
    }

    /**
     * Test 2: Published product names are completely distinct and disambiguated.
     */
    public function test_zero_duplicate_published_product_names(): void
    {
        $dupPublishedNames = DB::table('products')
            ->where('is_published', true)
            ->select('name', DB::raw('count(*) as c'))
            ->groupBy('name')
            ->having('c', '>', 1)
            ->count();
        $this->assertEquals(0, $dupPublishedNames, 'All published storefront products must have distinct titles.');
    }

    /**
     * Test 3: Zero unpopulated variants and zero duplicate variant (product, color, size) combos.
     */
    public function test_zero_unpopulated_or_duplicate_variants(): void
    {
        $nullVariants = DB::table('product_variants')
            ->whereNull('color')
            ->whereNull('size')
            ->count();
        $this->assertEquals(0, $nullVariants, 'There must be zero unpopulated variants with null color and size.');

        $dupCombos = DB::table('product_variants')
            ->select('product_id', 'color', 'size', DB::raw('count(*) as c'))
            ->groupBy('product_id', 'color', 'size')
            ->having('c', '>', 1)
            ->count();
        $this->assertEquals(0, $dupCombos, 'There must be zero duplicate variant combinations for any product.');
    }

    /**
     * Test 4: Zero duplicate customer phones or emails.
     */
    public function test_zero_duplicate_customers(): void
    {
        $dupPhones = DB::table('users')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->select('phone', DB::raw('count(*) as c'))
            ->groupBy('phone')
            ->having('c', '>', 1)
            ->count();
        $this->assertEquals(0, $dupPhones, 'Customer phone numbers must be unique.');

        $dupEmails = DB::table('users')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->select('email', DB::raw('count(*) as c'))
            ->groupBy('email')
            ->having('c', '>', 1)
            ->count();
        $this->assertEquals(0, $dupEmails, 'Customer emails must be unique.');
    }

    /**
     * Test 5: Zero duplicate supplier codes, names, or PANs.
     */
    public function test_zero_duplicate_suppliers(): void
    {
        $dupCodes = DB::table('inventory_suppliers')
            ->select('code', DB::raw('count(*) as c'))
            ->groupBy('code')
            ->having('c', '>', 1)
            ->count();
        $this->assertEquals(0, $dupCodes, 'Supplier codes must be unique.');

        $dupNames = DB::table('inventory_suppliers')
            ->select('name', DB::raw('count(*) as c'))
            ->groupBy('name')
            ->having('c', '>', 1)
            ->count();
        $this->assertEquals(0, $dupNames, 'Supplier names must be unique.');
    }

    /**
     * Test 6: Zero aberrant product prices and realistic commercial margins.
     */
    public function test_zero_aberrant_product_prices_and_margins(): void
    {
        $inflatedProducts = DB::table('products')->where('price', '>', 50000)->count();
        $this->assertEquals(0, $inflatedProducts, 'There must be zero products with inflated prices > 50,000 NPR.');

        $zeroCostProducts = DB::table('products')->where('cost_price', '<=', 0)->count();
        $this->assertEquals(0, $zeroCostProducts, 'Every product must have a positive, verified cost price.');

        $excessiveMarginProducts = DB::table('products')
            ->where('is_published', true)
            ->whereRaw('((price - cost_price) / price) > 0.65')
            ->count();
        $this->assertEquals(0, $excessiveMarginProducts, 'There must be zero published products with fake >65% margins.');
    }

    /**
     * Test 7: Double-Entry General Ledger equilibrium is strictly preserved (0.0000 NPR variance).
     */
    public function test_general_ledger_double_entry_equilibrium(): void
    {
        $totDebit = (float)DB::table('accounting_journal_entry_lines')->sum('debit');
        $totCredit = (float)DB::table('accounting_journal_entry_lines')->sum('credit');
        $variance = round(abs($totDebit - $totCredit), 4);

        $this->assertEqualsWithDelta(0.0000, $variance, 0.0001, 'General Ledger Debits must equal Credits with 0.0000 NPR variance.');

        $unbalanced = DB::table('accounting_journal_entries')->where('is_balanced', 0)->count();
        $this->assertEquals(0, $unbalanced, 'All journal entries must be marked as balanced.');
    }
}
