<?php

namespace Tests\Feature;

use App\Filament\Pages\AccountingDashboard;
use App\Filament\Pages\BankReconciliationPage;
use App\Filament\Pages\VatReportingPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AccountingLivewireUiTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        app(\App\Services\Accounting\AccountingService::class)->seedDefaultChartOfAccounts();

        $this->adminUser = User::where('email', 'admin@laijau.com')->first() ?? User::factory()->create([
            'email' => 'admin@laijau.com',
            'role' => 'admin',
        ]);
        $this->actingAs($this->adminUser, 'admin');
    }

    public function test_accounting_dashboard_renders_all_tabs_with_styles(): void
    {
        $component = Livewire::test(AccountingDashboard::class);

        // Tab 1: PnL
        $component->assertSee('Financial Overview & Reporting', false)
            ->assertSee('na-header-banner')
            ->assertSee('na-grid-8')
            ->assertSee('Net Revenue')
            ->assertSee('na-wf-row');

        // Tab 2: Balance Sheet
        $component->call('setTab', 'balance')
            ->assertSee('Balance Sheet in Equilibrium')
            ->assertSee('ASSETS (सम्पत्ति)')
            ->assertSee('LIABILITIES & EQUITY (दायित्व तथा पूँजी)', false);

        // Tab 3: Trial Balance
        $component->call('setTab', 'trial_balance')
            ->assertSee('Trial Balance (वासलात / सन्तुलन परीक्षण)')
            ->assertSee('na-table')
            ->assertSee('TOTALS (Verification Control)');

        // Tab 4: General Ledger
        $component->call('setTab', 'ledger')
            ->assertSee('General Ledger (खाता / मुख्य पुस्तिका)')
            ->assertSee('na-table');

        // Tab 5: Periods
        $component->call('setTab', 'periods')
            ->assertSee('Accounting Periods & Year-End Closing', false)
            ->assertSee('Execute Year-End Closing');
    }

    public function test_bank_reconciliation_renders_with_virtual_cards_and_pipeline(): void
    {
        $component = Livewire::test(BankReconciliationPage::class);

        $component->assertSee('Bank Reconciliation & Settlement Clearing', false)
            ->assertSee('na-header-banner')
            ->assertSee('na-grid-4')
            ->assertSee('Liquid Capital (Bank + Cash)', false)
            ->assertSee('nordic-debit-card')
            ->assertSee('debit-card-npr')
            ->assertSee('debit-card-cash')
            ->assertSee('Operating Bank Accounts + Showroom Drawer')
            ->assertSee('Authoritative balance in Nepalese Rupees (NPR)');
    }

    public function test_vat_reporting_renders_all_tabs_with_styles(): void
    {
        $component = Livewire::test(VatReportingPage::class);

        // Tab 1: Nepal IRD VAT Return
        $component->assertSee('Nepal Inland Revenue Department (IRD) VAT Return', false)
            ->assertSee('अनुसूची १०')
            ->assertSee('lj-vat-hero')
            ->assertSee('Fiscal Year')
            ->assertSee('Tax Period')
            ->assertSee('Filing Status');
    }
}
