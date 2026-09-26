<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Pos\PosSession;
use App\Models\Hardware\PosStation;
use Livewire\Livewire;
use Tests\TestCase;

class PosShiftReportAndCustomerSelectionTest extends TestCase
{
    use \Illuminate\Foundation\Testing\DatabaseTransactions;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::firstOrCreate(
            ['email' => 'admin@laijau.com'],
            ['name' => 'Admin User', 'password' => bcrypt('password'), 'role' => 'admin']
        );
    }

    public function test_pos_dashboard_opens_shift_report_without_error(): void
    {
        $station = PosStation::firstOrCreate(
            ['id' => 1],
            ['name' => 'Terminal 1', 'code' => 'pos_terminal_1', 'warehouse_id' => 43, 'is_active' => true]
        );

        $today = app(\App\Services\Pos\PosSessionService::class)->getNepalToday();
        PosSession::updateOrCreate(
            ['pos_station_id' => $station->id, 'business_date' => $today],
            [
                'warehouse_id' => 43,
                'terminal_code' => $station->code,
                'terminal_name' => $station->name,
                'showroom_name' => 'Laijau Showroom',
                'status' => 'open',
                'opening_balance' => 1500.00,
                'opened_by_user_id' => $this->admin->id,
                'opened_by_name' => $this->admin->name,
                'opened_at' => now(),
                'expected_cash' => 1500.00,
            ]
        );

        // Ensure PosDashboard Livewire component handles openModal('shift_report') without 500 error
        Livewire::actingAs($this->admin, 'admin')
            ->test(\App\Filament\Pages\PosDashboard::class)
            ->assertSuccessful()
            ->call('openModal', 'shift_report')
            ->assertSet('activeModal', 'shift_report')
            ->assertSee('POS REGISTER SHIFT REPORT')
            ->assertSee('Print Thermal (80mm)')
            ->assertSee('Standard (A4 / PDF)')
            ->call('closeModal')
            ->assertSet('activeModal', null);
    }

    public function test_existing_customer_selection_and_search_in_pos(): void
    {
        // Create an existing customer
        $customer = User::create([
            'name' => 'Suman Shakya',
            'email' => 'suman.' . uniqid() . '@example.com',
            'phone' => '9801234567',
            'password' => bcrypt('secret123'),
            'role' => 'customer',
        ]);

        $test = Livewire::actingAs($this->admin, 'admin')
            ->test(\App\Filament\Pages\OfflineSales::class)
            ->call('openCustomerModal')
            ->assertSet('activeModal', 'customer_modal')
            ->set('customerSearchQuery', 'Suman')
            ->assertSee('Suman Shakya')
            ->call('selectCustomer', $customer->id)
            ->assertSet('selectedUserId', $customer->id)
            ->assertSet('customerName', 'Suman Shakya')
            ->assertSet('customerPhone', '9801234567')
            ->assertSet('activeModal', null);
    }

    public function test_existing_customer_phone_search_with_or_without_prefix(): void
    {
        $customer = User::create([
            'name' => 'Binod Chaudhary',
            'email' => 'binod.' . uniqid() . '@example.com',
            'phone' => '+977-9851020304',
            'password' => bcrypt('secret123'),
            'role' => 'customer',
        ]);

        // Search with only last 10 digits
        $test = Livewire::actingAs($this->admin, 'admin')
            ->test(\App\Filament\Pages\OfflineSales::class)
            ->call('openCustomerModal')
            ->set('customerSearchQuery', '9851020304')
            ->assertSee('Binod Chaudhary')
            ->call('selectCustomer', $customer->id)
            ->assertSet('selectedUserId', $customer->id)
            ->assertSet('customerName', 'Binod Chaudhary');
    }
}
