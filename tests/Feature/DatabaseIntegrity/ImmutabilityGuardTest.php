<?php

declare(strict_types=1);

namespace Tests\Feature\DatabaseIntegrity;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountingInvoice;
use App\Models\Accounting\JournalEntry;
use App\Models\Hrm\PayrollRun;
use App\Services\Accounting\AccountingService;
use App\Services\Hrm\HrmService;
use Database\Seeders\ModuleSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImmutabilityGuardTest extends TestCase
{
    use RefreshDatabase;

    protected AccountingService $accountingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModuleSettingsSeeder::class);
        $this->accountingService = app(AccountingService::class);
    }

    /**
     * Bogføringsloven: A posted journal entry cannot be deleted.
     */
    public function test_posted_journal_entry_cannot_be_deleted(): void
    {
        $this->accountingService->ensureDefaultChartOfAccounts();
        $bank = Account::where('account_number', '1120')->first() ?: Account::where('account_number', '2410')->firstOrFail();
        $sales = Account::where('account_number', '4120')->first() ?: Account::where('account_number', '1010')->firstOrFail();

        $entry = $this->accountingService->postJournalEntry([
            'voucher_date' => date('Y-m-d'),
            'description' => 'Legal binding voucher',
        ], [
            ['account_id' => $bank->id, 'debit' => 250.00, 'credit' => 0.00],
            ['account_id' => $sales->id, 'debit' => 0.00, 'credit' => 250.00],
        ]);

        $this->assertEquals('posted', $entry->status);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/(Statutory Compliance Violation|immutable and cannot be deleted|Bogføringsloven)/');

        $entry->delete();
    }

    /**
     * A draft journal entry (not posted) can be deleted if needed.
     */
    public function test_draft_journal_entry_can_be_deleted(): void
    {
        $period = $this->accountingService->resolvePeriodForDate(date('Y-m-d'));

        $draft = JournalEntry::create([
            'entry_number' => JournalEntry::generateNextEntryNumber(),
            'voucher_date' => date('Y-m-d'),
            'accounting_period_id' => $period->id,
            'entry_type' => 'manual',
            'description' => 'Unposted draft voucher',
            'currency' => 'NPR',
            'total_debit' => 0,
            'total_credit' => 0,
            'status' => 'draft',
        ]);

        $draftId = $draft->id;
        $draft->delete();

        $this->assertDatabaseMissing('accounting_journal_entries', ['id' => $draftId]);
    }

    /**
     * Posted and paid sales invoices cannot be deleted.
     */
    public function test_posted_invoice_cannot_be_deleted(): void
    {
        $invoice = AccountingInvoice::create([
            'invoice_number' => AccountingInvoice::generateNextInvoiceNumber('sales_invoice'),
            'type' => 'sales_invoice',
            'contact_name' => 'Kirsten Holm',
            'contact_email' => 'kirsten@example.com',
            'issue_date' => date('Y-m-d'),
            'due_date' => date('Y-m-d', strtotime('+14 days')),
            'currency' => 'NPR',
            'payment_status' => 'paid',
            'subtotal' => 500.00,
            'vat_amount' => 65.00,
            'total_amount' => 565.00,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/(legally binding under the Nepal VAT Act|Bogførte og betalte)/');

        $invoice->delete();
    }

    /**
     * Approved and paid payroll runs cannot be deleted.
     */
    public function test_approved_payroll_run_cannot_be_deleted(): void
    {
        $run = PayrollRun::create([
            'run_number' => 'LON-2026-09-001',
            'name' => 'September 2026 Regular Payroll',
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'pay_date' => '2026-09-30',
            'status' => 'approved',
            'total_gross_salary_npr' => 45000.00,
            'total_net_payout_npr' => 28000.00,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/(statutory records and cannot be deleted|Godkendte eller udbetalte)/');

        $run->delete();
    }
}
