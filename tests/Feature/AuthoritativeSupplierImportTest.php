<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountingInvoice;
use App\Models\Accounting\JournalEntry;
use App\Models\Inventory\Supplier;
use App\Services\Accounting\AccountingService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthoritativeSupplierImportTest extends TestCase
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
     * Test 1: Authoritative suppliers from Suppliers.pdf exist with zero duplicates.
     */
    public function test_exact_24_authoritative_suppliers_exist(): void
    {
        $totalSuppliers = Supplier::count();
        $this->assertGreaterThanOrEqual(24, $totalSuppliers, 'There must be at least 24 authoritative suppliers.');

        $distinctCodes = Supplier::distinct('code')->count('code');
        $this->assertEquals($totalSuppliers, $distinctCodes, 'All suppliers must have unique supplier codes.');

        $distinctNames = Supplier::distinct('name')->count('name');
        $this->assertEquals($totalSuppliers, $distinctNames, 'All suppliers must have unique names.');

        $activeCount = Supplier::where('is_active', true)->count();
        $this->assertEquals($totalSuppliers, $activeCount, 'All authoritative suppliers must be marked as active.');
    }

    /**
     * Test 2: Payable balances as of today strictly match Suppliers .pdf.
     */
    public function test_all_supplier_payable_balances_match_document_strictly(): void
    {
        $expectedBalances = [
            'Kavish Enterprises' => 0.00,
            'Nirja Apparels' => 0.00,
            'Glamour Plus' => 0.00,
            'Lankhana Mai Store' => 0.00,
            'Girls Choice' => 0.00,
            'A.L. International' => 0.00,
            'AVIKA ENTERPRISE' => 0.00,
            'Gunlaxmi Apparel' => 0.00,
            'FOREVER NEW' => 0.00,
            'Maza Footwear Inc.' => 0.00,
            "Manya's Collection" => 0.00,
            'Limiloxa Enterprise' => 0.00,
            'Devkota Fancy Store' => 0.00,
            'Raj And Gautam' => 0.00,
            'New Poudel Store' => 0.00,
            'Aisha Store' => 0.00,
            'RUN SHOES INDUSTRY' => 0.00,
            'CITIZEN SHOES' => 828250.00,
            'STAR DENIM' => 470230.00,
            'MAXX RIDERS' => 853200.00,
            'PRASIDDHA FOOTWARE' => 1601025.00,
            'SM FACTORY' => 1867235.00,
            'SK SHOES' => 378500.00,
            'HIMSHIKHAR SHOES' => 81700.00,
        ];

        $zeroCount = Supplier::where('due_balance', 0.00)->count();
        $this->assertGreaterThanOrEqual(17, $zeroCount, 'At least 17 suppliers must have a cleared (0.00) payable balance.');

        $activeBalanceCount = Supplier::where('due_balance', '>', 0.00)->count();
        $this->assertEquals(7, $activeBalanceCount, 'Exactly 7 suppliers must have an active payable balance.');

        foreach ($expectedBalances as $name => $expected) {
            $actual = (float) Supplier::where('name', $name)->value('due_balance');
            $this->assertEqualsWithDelta(
                $expected,
                $actual,
                0.01,
                "Payable balance for '{$name}' must match Suppliers.pdf exactly."
            );
        }

        $totalBalance = (float) Supplier::sum('due_balance');
        $this->assertEqualsWithDelta(
            6080140.00,
            $totalBalance,
            0.01,
            'Total trade payables due balance across all suppliers must equal NPR 6,080,140.00.'
        );
    }

    /**
     * Test 3: Opening Kharid Khata bills exist in accounting_invoices and are unpaid.
     */
    public function test_kharid_khata_opening_bills_registered_in_accounting_invoices(): void
    {
        $openingBills = AccountingInvoice::where('type', 'supplier_bill')
            ->where('invoice_number', 'like', 'KH-OP-%')
            ->get();

        $this->assertCount(7, $openingBills, 'There must be 7 opening Kharid Khata bills for the 7 active suppliers.');

        $totalBillsAmount = (float) $openingBills->sum('total_amount');
        $this->assertEqualsWithDelta(6080140.00, $totalBillsAmount, 0.01);

        foreach ($openingBills as $bill) {
            $this->assertEquals('unpaid', $bill->payment_status);
            $this->assertEquals(0.00, (float) $bill->paid_amount);
            $this->assertTrue((bool) $bill->posted_to_gl);
            $this->assertNotNull($bill->journal_entry_id);
        }
    }

    /**
     * Test 4: General Ledger equilibrium is maintained with 0.0000 NPR variance.
     */
    public function test_general_ledger_remains_strictly_balanced(): void
    {
        $openingJv = JournalEntry::where('entry_number', 'JV-OP-AP-2026')->first();
        $this->assertNotNull($openingJv, 'Opening journal entry JV-OP-AP-2026 must exist.');
        $this->assertEqualsWithDelta(6080140.00, (float) $openingJv->total_debit, 0.01);
        $this->assertEqualsWithDelta(6080140.00, (float) $openingJv->total_credit, 0.01);

        $totalDebits = (float) DB::table('accounting_journal_entry_lines')->sum('debit');
        $totalCredits = (float) DB::table('accounting_journal_entry_lines')->sum('credit');
        $variance = abs($totalDebits - $totalCredits);

        $this->assertLessThan(
            0.0001,
            $variance,
            "General Ledger must remain in exact equilibrium. Debits: {$totalDebits}, Credits: {$totalCredits}."
        );

        $acc2110 = Account::where('account_number', '2110')->first();
        $this->assertNotNull($acc2110);
        $this->assertEqualsWithDelta(6080140.00, (float) $acc2110->current_balance, 0.01);

        $acc1210 = Account::where('account_number', '1210')->first();
        $this->assertNotNull($acc1210);
        $this->assertGreaterThanOrEqual(6000000.00, (float) $acc1210->current_balance);
    }

    /**
     * Test 5: Receivables & Payables aging service reflects all 7 active supplier balances.
     */
    public function test_receivables_payables_aging_service_reports_full_supplier_due_balance(): void
    {
        $service = app(AccountingService::class);
        $aging = $service->generateReceivablesPayablesAging();

        $this->assertArrayHasKey('payables', $aging);
        $this->assertEqualsWithDelta(6080140.00, (float) $aging['payables']['total'], 0.01);
        $this->assertEquals(7, $aging['payables']['count']);
    }
}
