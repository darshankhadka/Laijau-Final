<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Accounting\AccountingInvoice;
use App\Models\Accounting\AccountingInvoiceItem;
use App\Models\OfflineSale;
use App\Models\OfflineSaleItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PosPaymentItemsAndColorSanitizationTest extends TestCase
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
     * Test that no products in the catalog have raw -bl, -Bl, or BL shorthand as names/SKUs.
     */
    public function test_no_products_have_raw_bl_shorthand(): void
    {
        $rawCount = Product::whereIn('name', ['-bl', '-Bl', '-BL', 'BL', 'bl'])
            ->orWhereIn('sku', ['-bl', '-Bl', '-BL', 'BL', 'bl'])
            ->count();

        $this->assertSame(0, $rawCount, 'Catalog products should not have raw -bl or BL names/SKUs.');
    }

    /**
     * Test that all POS sales have valid payment methods.
     */
    public function test_all_sales_have_valid_payment_methods(): void
    {
        $validMethods = ['cash', 'fonepay', 'esewa', 'khalti', 'card', 'split', 'bank_transfer', 'digital'];
        $invalidCount = OfflineSale::whereNotIn('payment_method', $validMethods)->count();

        $this->assertSame(0, $invalidCount, 'All POS sales must use valid supported payment methods.');
    }

    /**
     * Test that the General Ledger is strictly balanced with 0.0000 NPR variance.
     */
    public function test_general_ledger_strictly_balanced(): void
    {
        $totDebit = (float)DB::table('accounting_journal_entry_lines')->sum('debit');
        $totCredit = (float)DB::table('accounting_journal_entry_lines')->sum('credit');
        $variance = abs($totDebit - $totCredit);

        $this->assertGreaterThan(0.0, $totDebit, 'General ledger debits must be greater than zero.');
        $this->assertLessThan(0.0001, $variance, "GL variance must be exactly 0.0000 NPR (Variance: {$variance})");
    }

    /**
     * Test offline receipt view rendering.
     */
    public function test_offline_receipt_renders_clean_payment_and_variant(): void
    {
        $sale = OfflineSale::with('items')->whereHas('items')->first();
        $this->assertNotNull($sale);

        $html = view('offline-receipt', ['sale' => $sale])->render();

        $this->assertStringContainsString('Payment Method:', $html);
        $this->assertStringContainsString('LAIJAU', $html);
        $this->assertStringNotContainsString('SKU: -Bl', $html);
        $this->assertStringNotContainsString('SKU: -bl', $html);
    }
}

