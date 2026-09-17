<?php

namespace Tests\Feature;

use App\Filament\Pages\PaymentReconciliationPage;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentReconciliationUiTest extends TestCase
{
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::firstOrCreate(
            ['email' => 'admin@laijau.com'],
            [
                'name' => 'Laijau Admin',
                'password' => 'Laijau2026!',
                'role' => 'admin',
            ]
        );
        $this->admin->update(['role' => 'admin']);
    }

    public function test_payment_reconciliation_page_renders_with_retail_console_ui(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->get('/intadmin/payment-reconciliation');
        $response->assertStatus(200);
        $response->assertSee('lj-pr-root');
        $response->assertSee('Operating Bank (NPR)');
        $response->assertSee('eSewa Wallet');
        $response->assertSee('ConnectIPS / NCHL');
        $response->assertSee('COD Receivables');
        $response->assertSee('POS Register Cash');
        $response->assertSee('Reconcile eSewa Remittance');
    }

    public function test_payment_reconciliation_tabs_and_forms(): void
    {
        $this->actingAs($this->admin, 'admin');

        Livewire::test(PaymentReconciliationPage::class)
            ->assertSuccessful()
            ->assertSet('activeTab', 'esewa')
            ->set('activeTab', 'connectips')
            ->assertSee('ConnectIPS / NCHL Gateway Reconciliation')
            ->set('activeTab', 'cod')
            ->assertSee('Courier COD Remittance Reconciliation')
            ->set('activeTab', 'cash_deposit')
            ->assertSee('POS Cash Drawer Deposit to Bank')
            ->set('activeTab', 'audit')
            ->assertSee('Immutable audit log');
    }
}
