<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminPanelAuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Test genuine administrator can access the Filament panel.
     */
    public function test_genuine_admin_can_access_panel(): void
    {
        $panel = Filament::getPanel('admin');

        $admin = new User([
            'name' => 'Certified Admin',
            'email' => 'admin_test@corporate.com',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->assertTrue($admin->canAccessPanel($panel));
        $this->assertTrue($admin->isSuperAdmin());
    }

    /**
     * Test normal customer cannot access the Filament panel.
     */
    public function test_customer_cannot_access_panel(): void
    {
        $panel = Filament::getPanel('admin');

        $customer = new User([
            'name' => 'Normal Customer',
            'email' => 'shopper@example.com',
            'role' => 'customer',
            'is_active' => true,
        ]);

        $this->assertFalse($customer->canAccessPanel($panel));
        $this->assertFalse($customer->isSuperAdmin());
    }

    /**
     * Test arbitrary Gmail address cannot gain access without proper role.
     */
    public function test_arbitrary_email_cannot_access_panel_without_role(): void
    {
        $panel = Filament::getPanel('admin');

        // Test with the previously whitelisted backdoor emails
        $backdoorEmailUser1 = new User([
            'name' => 'Attacker With Whitelisted Email',
            'email' => 'nepazon9@gmail.com',
            'role' => 'customer',
            'is_active' => true,
        ]);

        $backdoorEmailUser2 = new User([
            'name' => 'Customer With Admin Email',
            'email' => 'admin@laijau.com',
            'role' => 'customer',
            'is_active' => true,
        ]);

        $this->assertFalse($backdoorEmailUser1->canAccessPanel($panel));
        $this->assertFalse($backdoorEmailUser1->isSuperAdmin());

        $this->assertFalse($backdoorEmailUser2->canAccessPanel($panel));
        $this->assertFalse($backdoorEmailUser2->isSuperAdmin());
    }

    /**
     * Test deactivated administrator is denied panel access.
     */
    public function test_deactivated_admin_cannot_access_panel(): void
    {
        $panel = Filament::getPanel('admin');

        $deactivatedAdmin = new User([
            'name' => 'Former Admin',
            'email' => 'former_admin@corporate.com',
            'role' => 'admin',
            'is_active' => false,
        ]);

        $this->assertFalse($deactivatedAdmin->canAccessPanel($panel));
    }
}
