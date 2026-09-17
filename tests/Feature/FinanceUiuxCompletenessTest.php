<?php

namespace Tests\Feature;

use App\Filament\Pages\AccountingDashboard;
use App\Filament\Pages\BankReconciliationPage;
use App\Filament\Pages\PaymentReconciliationPage;
use App\Filament\Pages\ReceivablesPayablesPage;
use App\Filament\Pages\SalesPurchaseReconciliationPage;
use App\Filament\Pages\VatReportingPage;
use App\Filament\Resources\AccountResource\Pages\ListAccounts;
use App\Filament\Resources\BikriKhataResource\Pages\ListBikriKhataEntries;
use App\Filament\Resources\ExpenseClaimResource\Pages\ListExpenseClaims;
use App\Filament\Resources\FiscalYearResource\Pages\ListFiscalYears;
use App\Filament\Resources\JournalEntryResource\Pages\ListJournalEntries;
use App\Filament\Resources\KharidKhataResource\Pages\ListKharidKhataEntries;
use App\Filament\Resources\TdsRecordResource\Pages\ListTdsRecords;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FinanceUiuxCompletenessTest extends TestCase
{
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql']);
        config(['database.connections.mysql.database' => 'LAIJAU']);
        \Illuminate\Support\Facades\DB::purge('mysql');

        $this->adminUser = User::firstOrCreate(
            ['email' => 'admin@laijau.com'],
            [
                'name' => 'Laijau Super Admin',
                'password' => bcrypt('password'),
                'role' => 'admin',
            ]
        );

        // Assign super_admin role if Spatie Permission is configured
        if (class_exists(\Spatie\Permission\Models\Role::class)) {
            $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'admin']);
            if (!$this->adminUser->hasRole('super_admin', 'admin')) {
                $this->adminUser->assignRole($role);
            }
        }
    }

    public function test_accounting_dashboard_renders_with_full_ui(): void
    {
        Livewire::actingAs($this->adminUser, 'admin')
            ->test(AccountingDashboard::class)
            ->assertSuccessful()
            ->assertSee('Financial Overview')
            ->assertSee('Net Revenue')
            ->assertSee('EBITDA');
    }

    public function test_sales_purchase_reconciliation_renders_with_full_ui(): void
    {
        Livewire::actingAs($this->adminUser, 'admin')
            ->test(SalesPurchaseReconciliationPage::class)
            ->assertSuccessful()
            ->assertSee('General Ledger Cross-Reconciliation Center')
            ->assertSee('Storefront Orders')
            ->assertSee('Unposted Invoices');
    }

    public function test_receivables_payables_renders_with_full_ui(): void
    {
        Livewire::actingAs($this->adminUser, 'admin')
            ->test(ReceivablesPayablesPage::class)
            ->assertSuccessful()
            ->assertSee('Accounts Receivable (Customer Invoices)')
            ->assertSee('Accounts Payable (Supplier Bills)');
    }

    public function test_vat_reporting_page_renders_with_full_ui(): void
    {
        Livewire::actingAs($this->adminUser, 'admin')
            ->test(VatReportingPage::class)
            ->assertSuccessful()
            ->assertSee('Nepal Inland Revenue Department (IRD) VAT Return')
            ->assertSee('Section A')
            ->assertSee('Section B')
            ->assertSee('Section C');
    }

    public function test_payment_reconciliation_page_renders_with_full_ui(): void
    {
        Livewire::actingAs($this->adminUser, 'admin')
            ->test(PaymentReconciliationPage::class)
            ->assertSuccessful()
            ->assertSee('Operating Bank (NPR)')
            ->assertSee('eSewa')
            ->assertSee('ConnectIPS');
    }

    public function test_bank_reconciliation_page_renders_with_full_ui(): void
    {
        Livewire::actingAs($this->adminUser, 'admin')
            ->test(BankReconciliationPage::class)
            ->assertSuccessful()
            ->assertSee('Bank Reconciliation');
    }

    public function test_bikri_khata_resource_table_renders(): void
    {
        Livewire::actingAs($this->adminUser, 'admin')
            ->test(ListBikriKhataEntries::class)
            ->assertSuccessful();
    }

    public function test_kharid_khata_resource_table_renders(): void
    {
        Livewire::actingAs($this->adminUser, 'admin')
            ->test(ListKharidKhataEntries::class)
            ->assertSuccessful();
    }

    public function test_kharid_khata_inspect_action(): void
    {
        $entry = \App\Models\Accounting\KharidKhataEntry::first();
        Livewire::actingAs($this->adminUser, 'admin')
            ->test(ListKharidKhataEntries::class)
            ->mountTableAction('view', $entry)
            ->assertSee($entry->invoice_number);
    }

    public function test_tds_record_resource_table_renders(): void
    {
        Livewire::actingAs($this->adminUser, 'admin')
            ->test(ListTdsRecords::class)
            ->assertSuccessful();
    }

    public function test_expense_claim_resource_table_renders(): void
    {
        Livewire::actingAs($this->adminUser, 'admin')
            ->test(ListExpenseClaims::class)
            ->assertSuccessful();
    }

    public function test_journal_entry_resource_table_renders(): void
    {
        Livewire::actingAs($this->adminUser, 'admin')
            ->test(ListJournalEntries::class)
            ->assertSuccessful();
    }

    public function test_account_resource_table_renders(): void
    {
        Livewire::actingAs($this->adminUser, 'admin')
            ->test(ListAccounts::class)
            ->assertSuccessful();
    }

    public function test_fiscal_year_resource_table_renders(): void
    {
        Livewire::actingAs($this->adminUser, 'admin')
            ->test(ListFiscalYears::class)
            ->assertSuccessful();
    }

    public function test_all_13_finance_routes_return_http_200(): void
    {
        $routes = [
            '/intadmin/accounting',
            '/intadmin/bikri-khata',
            '/intadmin/kharid-khata',
            '/intadmin/sales-purchase-reconciliation',
            '/intadmin/receivables-payables',
            '/intadmin/payment-reconciliation',
            '/intadmin/expense-claims',
            '/intadmin/journal-entries',
            '/intadmin/bank-reconciliation',
            '/intadmin/vat-reporting',
            '/intadmin/tds-records',
            '/intadmin/accounts',
            '/intadmin/fiscal-years',
        ];

        foreach ($routes as $url) {
            $response = $this->actingAs($this->adminUser, 'admin')->get($url);
            $this->assertEquals(200, $response->getStatusCode(), "Failed asserting route {$url} returned 200.");
        }
    }

    public function test_balance_sheet_is_in_exact_equilibrium_zero_difference(): void
    {
        $service = app(\App\Services\Accounting\AccountingService::class);
        $bs = $service->generateBalanceSheet();

        $this->assertTrue($bs['is_balanced'], 'Balance sheet should be balanced.');
        $this->assertEquals(0.00, $bs['difference'], 'Balance sheet variance should be exactly NPR 0.00.');
        $this->assertEquals(
            $bs['assets']['total_assets'],
            $bs['liabilities_and_equity']['total_liabilities_and_equity'],
            'Total Assets must equal Total Liabilities and Equity.'
        );
    }

    public function test_commerce_reconciliation_has_zero_unposted(): void
    {
        $service = app(\App\Services\Accounting\AccountingService::class);
        $recon = $service->reconcileCommerceVsAccounting();

        $this->assertEquals(0, $recon['orders']['unposted'], 'Unposted active orders must be 0.');
        $this->assertEquals(0, $recon['pos']['unposted'], 'Unposted POS sales must be 0.');
        $this->assertEquals(1642, $recon['orders']['total'], 'Active orders must be exactly 1,642.');
        $this->assertEquals(1642, $recon['orders']['posted_invoices'], 'Posted order invoices must be 1,642.');
        $this->assertEquals(308, $recon['orders']['cancelled'], 'Cancelled orders must be cleanly isolated as 308.');
    }

    public function test_supplier_payables_strictly_matches_pdf_source_of_truth(): void
    {
        $service = app(\App\Services\Accounting\AccountingService::class);
        $aging = $service->generateReceivablesPayablesAging();

        $this->assertEquals(6, $aging['payables']['count'], 'There must be exactly 6 supplier payable records.');
        $this->assertEquals(4212905.00, $aging['payables']['total'], 'Total supplier payables must strictly equal NPR 4,212,905.00.');
    }
}
