<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Spatie\Permission\Models\Role;

class AuthenticationIsolationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'admin']);
        Role::firstOrCreate(['name' => 'Customer', 'guard_name' => 'web']);
    }

    public function test_admin_session_does_not_authenticate_customer()
    {
        $admin = User::factory()->create(['email' => 'admin_test@laijau.com']);
        $superAdminRole = Role::where('name', 'Super Admin')->first();
        $admin->assignRole($superAdminRole);

        // Log in the admin using the Filament 'admin' guard
        $this->actingAs($admin, 'admin');

        // The admin should be able to access Filament dashboard
        $this->get('/intadmin')->assertStatus(200);

        // When the admin visits the customer storefront /account, they should NOT be authenticated as a customer
        $response = $this->get('/account');
        $response->assertStatus(200); // 200 because it shows the guest view

        // Assert that the storefront is showing the Guest/Sign In view and NOT the admin's profile
        $response->assertSee('this.$store.store.user = null;', false);
        $response->assertDontSee($admin->email);
    }

    public function test_customer_cannot_access_admin()
    {
        $customer = User::factory()->create(['email' => 'customer_test@laijau.com']);
        $customerRole = Role::where('name', 'Customer')->first();
        $customer->assignRole($customerRole);

        // Log in the customer using the storefront 'web' guard
        $this->actingAs($customer, 'web');

        // Customer can access their account (their email will be in the hydration script)
        $this->get('/account')->assertSee($customer->email);

        // Customer MUST NOT be able to access the admin panel
        // Filament redirects unauthenticated (on the admin guard) users to login
        $this->get('/intadmin')->assertRedirect('/intadmin/login');
        $this->get('/admin')->assertRedirect('/');
    }

    public function test_customer_login_works_properly()
    {
        $customer = User::factory()->create([
            'email' => 'login_test@laijau.com',
            'password' => bcrypt('password123')
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'login_test@laijau.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['access_token', 'user']);
    }

    public function test_customer_logout_works_properly()
    {
        $customer = User::factory()->create();
        $this->actingAs($customer, 'sanctum');

        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(200);
    }
}
