<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageSettings;
use App\Filament\Pages\ModuleSettings;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SettingsDomainCompletenessTest extends TestCase
{
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::firstOrCreate(
            ['email' => 'admin@laijau.com'],
            [
                'name' => 'Laijau Super Admin',
                'password' => bcrypt('password'),
                'role' => 'super_admin',
                'is_active' => true,
            ]
        );

        if (class_exists(Role::class)) {
            $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'admin']);
            if (!$this->adminUser->hasRole('Super Admin', 'admin')) {
                $this->adminUser->assignRole($role);
            }
        }
    }

    public function test_general_settings_page_renders_with_full_configuration(): void
    {
        Livewire::actingAs($this->adminUser, 'admin')
            ->test(ManageSettings::class)
            ->assertSuccessful()
            ->assertSee('Store Operations & System Settings')
            ->assertSee('Store Identity', false)
            ->assertSee('Nepal Payments', false)
            ->assertSee('Logistics & Shipping', false)
            ->assertSee('Nepal Tax & VAT Engine', false)
            ->assertSee('Transactional Mail & SMTP', false)
            ->assertSee('Concierge & Socials', false)
            ->assertSee('Maintenance & System', false);
    }

    public function test_module_settings_page_renders_with_control_plane(): void
    {
        Livewire::actingAs($this->adminUser, 'admin')
            ->test(ModuleSettings::class)
            ->assertSuccessful()
            ->assertSee('Module Settings')
            ->assertSee('Control Plane')
            ->assertSee('Business Modules')
            ->assertSee('Mutation Audit Trail');
    }

    public function test_system_users_resource_table_renders_with_staff_management(): void
    {
        Livewire::actingAs($this->adminUser, 'admin')
            ->test(ListUsers::class)
            ->assertSuccessful()
            ->assertSee('Staff Name')
            ->assertSee('System Role')
            ->assertSee('Security Roles')
            ->assertSee('Active');
    }

    public function test_user_activation_and_deactivation_controls_access(): void
    {
        $panel = Filament::getPanel('admin');

        $staff = User::firstOrCreate(
            ['email' => 'test.staff.ops@laijau.com'],
            [
                'name' => 'Ops Staff Member',
                'password' => bcrypt('password'),
                'role' => 'store_manager',
                'is_active' => true,
            ]
        );

        // When active: permitted to access panel
        $this->assertTrue($staff->canAccessPanel($panel));

        // When deactivated: access immediately revoked
        $staff->update(['is_active' => false]);
        $this->assertFalse($staff->canAccessPanel($panel));

        // When reactivated: access restored
        $staff->update(['is_active' => true]);
        $this->assertTrue($staff->canAccessPanel($panel));

        $staff->delete();
    }

    public function test_all_3_settings_domain_routes_return_http_200(): void
    {
        $routes = [
            '/intadmin/manage-settings',
            '/intadmin/module-settings',
            '/intadmin/users',
        ];

        foreach ($routes as $url) {
            $response = $this->actingAs($this->adminUser, 'admin')->get($url);
            $this->assertEquals(200, $response->getStatusCode(), "Failed asserting route {$url} returned 200.");
        }
    }
}
