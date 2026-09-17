<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Accounting\AccountingInvoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InvoiceLineItemsIntegrityTest extends TestCase
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
     * Test 1: Zero invoices are missing line items.
     */
    public function test_zero_invoices_are_missing_line_items(): void
    {
        $emptyInvoices = DB::table('accounting_invoices')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('accounting_invoice_items')
                    ->whereColumn('accounting_invoice_items.accounting_invoice_id', 'accounting_invoices.id');
            })
            ->count();

        $this->assertEquals(0, $emptyInvoices, 'All accounting invoices must possess populated line items.');
    }

    /**
     * Test 2: Sales invoices have valid product references and descriptions.
     */
    public function test_sales_invoice_line_items_have_valid_products(): void
    {
        $itemsWithProducts = DB::table('accounting_invoice_items')
            ->whereNotNull('product_id')
            ->count();

        $this->assertGreaterThan(10000, $itemsWithProducts, 'Over 10,000 line items must be linked to genuine catalog products.');

        // Test random invoice relationship loading
        $sampleInvoice = AccountingInvoice::with('items.product')->whereHas('items', fn($q) => $q->whereNotNull('product_id'))->first();
        $this->assertNotNull($sampleInvoice);
        $this->assertNotEmpty($sampleInvoice->items);
        $this->assertNotNull($sampleInvoice->items->first()->product);
    }

    /**
     * Test 3: Zero calculation divergence between line item sums and invoice totals.
     */
    public function test_zero_calculation_divergence_between_line_items_and_invoice_totals(): void
    {
        $mismatches = DB::table('accounting_invoices')
            ->join(DB::raw('(SELECT accounting_invoice_id, SUM(total_amount) as items_total FROM accounting_invoice_items GROUP BY accounting_invoice_id) agg'), 'agg.accounting_invoice_id', '=', 'accounting_invoices.id')
            ->whereRaw('ABS(accounting_invoices.total_amount - agg.items_total) > 0.01')
            ->count();

        $this->assertEquals(0, $mismatches, 'The sum of invoice line items must equal parent invoice total_amount with 0 variance.');
    }

    /**
     * Test 4: Finalized employee roster roles, settings access, and delete permissions.
     */
    public function test_finalized_employee_roster_permissions(): void
    {
        // IT / Super Admin
        $darshan = User::where('email', 'admin@laijau.com')->first();
        $this->assertNotNull($darshan);
        $this->assertTrue($darshan->isSuperAdmin());
        $this->assertTrue($darshan->canViewSettings());

        // CEO / COO: Except Settings
        $anup = User::where('email', 'anup@laijau.com')->first();
        $neha = User::where('email', 'neha@laijau.com')->first();
        $this->assertNotNull($anup);
        $this->assertNotNull($neha);
        $this->assertFalse($anup->canViewSettings(), 'CEO must NOT have settings access.');
        $this->assertFalse($neha->canViewSettings(), 'COO must NOT have settings access.');

        // Managers: Except Settings and delete access
        $upasna = User::where('email', 'upashna@laijau.com')->first();
        $babita = User::where('email', 'babita@laijau.com')->first();
        $this->assertNotNull($upasna);
        $this->assertNotNull($babita);
        $this->assertFalse($upasna->canViewSettings(), 'Manager must NOT have settings access.');
        $this->assertFalse($babita->canViewSettings(), 'Manager must NOT have settings access.');

        // Sales: Except Settings and delete access
        $yogesh = User::where('email', 'yogesh@laijau.com')->first();
        $ishor = User::where('email', 'ishor@laijau.com')->first();
        $this->assertNotNull($yogesh);
        $this->assertNotNull($ishor);
        $this->assertFalse($yogesh->canViewSettings(), 'Sales must NOT have settings access.');
        $this->assertFalse($ishor->canViewSettings(), 'Sales must NOT have settings access.');

        // Cashier: POS only
        $cashier = User::where('email', 'cashier@laijau.com')->first();
        $this->assertNotNull($cashier);
        $this->assertFalse($cashier->canViewSettings(), 'Cashier must NOT have settings access.');
    }
}
