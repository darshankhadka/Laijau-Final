<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerAccountTest extends TestCase
{
    use DatabaseTransactions;

    protected Role $customerRole;
    protected Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customerRole = Role::firstOrCreate(['name' => 'Customer', 'guard_name' => 'web']);
        $this->adminRole = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'admin']);



        $product = Product::firstOrCreate(
            ['slug' => 'test-luxury-kaftan'],
            [
                'name' => 'Test Luxury Kaftan',
                'description' => 'Test Luxury Kaftan Description',
                'price' => 890.00,
                'price_npr' => 890.00,
                'quantity' => 20,
                'is_published' => true,
                'is_active' => true,
            ]
        );

        ProductVariant::firstOrCreate(
            ['product_id' => $product->id, 'size' => 'M', 'color' => 'Navy'],
            [
                'sku' => 'TLK-NV-M',
                'price_npr' => 890.00,
                'stock_quantity' => 20,
                'is_active' => true,
            ]
        );

        ShippingMethod::firstOrCreate(
            ['code' => 'inside_valley_standard'],
            [
                'name' => 'Kathmandu Valley Standard Delivery',
                'zone' => 'kathmandu_valley',
                'price_npr' => 100.00,
                'free_shipping_threshold_npr' => 3000.00,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );
    }

    /**
     * 1. guest_account_page
     */
    public function test_guest_account_page(): void
    {
        $response = $this->get('/account');

        $response->assertStatus(200);
        $this->assertFalse(auth('web')->check());
        $response->assertSee('window.__LAIJAU_AUTH_USER__ = null;', false);
        $response->assertSee('Sign In', false);
        $response->assertSee('Create Account', false);
        $response->assertSee('Continue with Google', false);
    }

    /**
     * 2. customer_registration
     */
    public function test_customer_registration(): void
    {
        $payload = [
            'name' => 'Laijau Customer',
            'email' => 'new_customer@laijau.com',
            'password' => 'Laijau#2026',
            'password_confirmation' => 'Laijau#2026',
        ];

        // Web registration
        $response = $this->post('/register', $payload);
        $response->assertRedirect('/account');

        $this->assertTrue(auth('web')->check());
        $user = auth('web')->user();
        $this->assertEquals('new_customer@laijau.com', $user->email);
        $this->assertTrue(Hash::check('Laijau#2026', $user->password));

        // Subsequent visit to /account confirms customer is authenticated
        $accountPage = $this->get('/account');
        $accountPage->assertStatus(200);
        $accountPage->assertSee('new_customer@laijau.com');
    }

    /**
     * 3. duplicate_registration
     */
    public function test_duplicate_registration(): void
    {
        User::factory()->create([
            'email' => 'duplicate@laijau.com',
            'password' => Hash::make('secret1234'),
        ]);

        $initialCount = User::count();

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Duplicate Attempt',
            'email' => 'duplicate@laijau.com',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
        $this->assertEquals($initialCount, User::count());
    }

    /**
     * 4. customer_login
     */
    public function test_customer_login(): void
    {
        $customer = User::factory()->create([
            'email' => 'valid_login@laijau.com',
            'password' => Hash::make('P@ssword2026!'),
        ]);
        $customer->assignRole($this->customerRole);

        $response = $this->post('/login', [
            'email' => 'valid_login@laijau.com',
            'password' => 'P@ssword2026!',
        ]);

        $response->assertRedirect('/account');
        $this->assertTrue(auth('web')->check());
        $this->assertEquals($customer->id, auth('web')->id());
    }

    /**
     * 5. invalid_login
     */
    public function test_invalid_login(): void
    {
        User::factory()->create([
            'email' => 'valid_login@laijau.com',
            'password' => Hash::make('CorrectPassword#2026'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'valid_login@laijau.com',
            'password' => 'CorrectPassword#2026',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
        $this->assertTrue(auth('web')->check());
    }

    /**
     * 5. invalid_credentials_login
     */
    public function test_invalid_credentials_login(): void
    {
        User::factory()->create([
            'email' => 'invalid_try@laijau.com',
            'password' => Hash::make('RightPass123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'invalid_try@laijau.com',
            'password' => 'WrongPass999',
        ]);

        $response->assertStatus(401);
        $this->assertFalse(auth('web')->check());
    }

    /**
     * 6. customer_logout
     */
    public function test_customer_logout(): void
    {
        /** @var User $customer */
        $customer = User::factory()->create(['email' => 'logout_test@laijau.com']);
        $this->actingAs($customer, 'web');

        $this->assertTrue(auth('web')->check());

        $response = $this->post('/logout');
        $response->assertRedirect('/account');

        $this->assertFalse(auth('web')->check());
    }

    /**
     * 7. persistent_session
     */
    public function test_persistent_session(): void
    {
        /** @var User $customer */
        $customer = User::factory()->create(['email' => 'persistent@laijau.com']);
        $customer->assignRole($this->customerRole);

        $this->actingAs($customer, 'web');

        $this->get('/products')->assertStatus(200);
        $this->assertTrue(auth('web')->check());

        $this->get('/cart')->assertStatus(200);
        $this->assertTrue(auth('web')->check());

        $this->get('/checkout')->assertStatus(200);
        $this->assertTrue(auth('web')->check());

        $accountRes = $this->get('/account');
        $accountRes->assertStatus(200);
        $accountRes->assertSee('persistent@laijau.com');
        $this->assertTrue(auth('web')->check());
    }

    /**
     * 8. session_expiration
     */
    public function test_session_expiration(): void
    {
        /** @var User $customer */
        $customer = User::factory()->create(['email' => 'expired_test@laijau.com']);
        $this->actingAs($customer, 'web');

        $this->get('/account')->assertSee('expired_test@laijau.com');

        // Session expiration simulation
        $this->flushSession();
        Auth::logout();

        $response = $this->get('/account');
        $response->assertStatus(200);
        $response->assertSee('window.__LAIJAU_AUTH_USER__ = null;', false);
        $this->assertFalse(auth('web')->check());
    }

    /**
     * 8b. google_redirect_canonical_uri
     */
    public function test_google_redirect_generates_canonical_uri(): void
    {
        config([
            'services.google.client_id' => 'test-client-id.apps.googleusercontent.com',
            'services.google.client_secret' => 'test-secret',
            'services.google.redirect' => 'https://laijau.com/auth/google/callback',
        ]);

        $response = $this->get('/auth/google/redirect');
        $response->assertRedirect();
        $targetUrl = $response->getTargetUrl();

        $this->assertStringContainsString('https://accounts.google.com/o/oauth2/auth', $targetUrl);
        $this->assertStringContainsString('redirect_uri=' . urlencode('https://laijau.com/auth/google/callback'), $targetUrl);

        // Test JSON request
        $jsonResponse = $this->getJson('/auth/google/redirect');
        $jsonResponse->assertStatus(200);
        $jsonResponse->assertJson(['success' => true]);
        $this->assertStringContainsString('redirect_uri=' . urlencode('https://laijau.com/auth/google/callback'), $jsonResponse->json('url'));
    }

    /**
     * 9. google_new_customer
     */
    public function test_google_new_customer(): void
    {
        $abstractUser = Mockery::mock('Laravel\Socialite\Two\User');
        $abstractUser->shouldReceive('getId')->andReturn('google_sub_99999');
        $abstractUser->shouldReceive('getEmail')->andReturn('google_fresh_customer@laijau.com');
        $abstractUser->shouldReceive('getName')->andReturn('Google New Customer');
        $abstractUser->shouldReceive('getAvatar')->andReturn('https://lh3.googleusercontent.com/avatar_new.jpg');

        $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('stateless')->andReturn($provider);
        $provider->shouldReceive('user')->andReturn($abstractUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get('/auth/google/callback');
        $response->assertRedirect();

        $this->assertTrue(auth('web')->check());
        $user = auth('web')->user();
        $this->assertEquals('google_fresh_customer@laijau.com', $user->email);
        $this->assertEquals('google_sub_99999', $user->google_id);
    }

    /**
     * 10. google_existing_customer
     */
    public function test_google_existing_customer(): void
    {
        $existing = User::factory()->create([
            'email' => 'existing_google@laijau.com',
            'google_id' => 'google_sub_existing_111',
        ]);
        $existing->assignRole($this->customerRole);

        $initialCount = User::count();

        $abstractUser = Mockery::mock('Laravel\Socialite\Two\User');
        $abstractUser->shouldReceive('getId')->andReturn('google_sub_existing_111');
        $abstractUser->shouldReceive('getEmail')->andReturn('existing_google@laijau.com');
        $abstractUser->shouldReceive('getName')->andReturn('Existing Google User');
        $abstractUser->shouldReceive('getAvatar')->andReturn('https://lh3.googleusercontent.com/avatar_exist.jpg');

        $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('stateless')->andReturn($provider);
        $provider->shouldReceive('user')->andReturn($abstractUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get('/auth/google/callback');
        $response->assertRedirect();

        $this->assertEquals($initialCount, User::count());
        $this->assertTrue(auth('web')->check());
        $this->assertEquals($existing->id, auth('web')->id());
    }

    /**
     * 11. google_existing_email
     */
    public function test_google_existing_email(): void
    {
        $existing = User::factory()->create([
            'email' => 'manual_registered@laijau.com',
            'google_id' => null,
        ]);
        $existing->assignRole($this->customerRole);

        $initialCount = User::count();

        $abstractUser = Mockery::mock('Laravel\Socialite\Two\User');
        $abstractUser->shouldReceive('getId')->andReturn('google_sub_linked_888');
        $abstractUser->shouldReceive('getEmail')->andReturn('manual_registered@laijau.com');
        $abstractUser->shouldReceive('getName')->andReturn('Linked User');
        $abstractUser->shouldReceive('getAvatar')->andReturn(null);

        $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('stateless')->andReturn($provider);
        $provider->shouldReceive('user')->andReturn($abstractUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get('/auth/google/callback');
        $response->assertRedirect();

        $this->assertEquals($initialCount, User::count());
        $existing->refresh();
        $this->assertEquals('google_sub_linked_888', $existing->google_id);
        $this->assertTrue(auth('web')->check());
        $this->assertEquals($existing->id, auth('web')->id());
    }

    /**
     * 12. google_failure
     */
    public function test_google_failure(): void
    {
        $response = $this->get('/auth/google/callback?error=access_denied');

        $response->assertRedirect('/account?error=google_auth_cancelled');
        $this->assertFalse(auth('web')->check());
    }

    /**
     * 13. admin_customer_isolation
     */
    public function test_admin_customer_isolation(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create(['email' => 'admin_strictly@laijau.com']);
        $admin->assignRole($this->adminRole);

        $this->actingAs($admin, 'admin');

        $response = $this->get('/account');
        $response->assertStatus(200);

        // Storefront treats them strictly as a guest
        $this->assertFalse(auth('web')->check());
        $response->assertSee('window.__LAIJAU_AUTH_USER__ = null;', false);
        $response->assertDontSee('admin_strictly@laijau.com');
    }

    /**
     * 14. customer_admin_isolation
     */
    public function test_customer_admin_isolation(): void
    {
        /** @var User $customer */
        $customer = User::factory()->create(['email' => 'customer_strictly@laijau.com']);
        $customer->assignRole($this->customerRole);

        $this->actingAs($customer, 'web');

        // Customer cannot access Filament admin panel
        $response = $this->get('/intadmin');
        $response->assertRedirect('/intadmin/login');
        $this->get('/admin')->assertRedirect('/');
    }

    /**
     * 15. customer_profile
     */
    public function test_customer_profile(): void
    {
        /** @var User $customer */
        $customer = User::factory()->create([
            'email' => 'profile_customer@laijau.com',
            'name' => 'Initial Name',
            'phone' => '9841223344',
        ]);
        $customer->assignRole($this->customerRole);

        $this->actingAs($customer, 'web');

        $response = $this->postJson('/api/user/profile', [
            'name' => 'Updated Couture Client',
            'phone' => '9841998877',
            'address' => 'Bhotahiti 10',
            'city' => 'Kathmandu',
            'postal_code' => '44600',
            'country' => 'Nepal',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $customer->refresh();
        $this->assertEquals('Updated Couture Client', $customer->name);
        $this->assertEquals('9841998877', $customer->phone);
        $this->assertEquals('Bhotahiti 10', $customer->address);
    }

    /**
     * 16. customer_measurements
     */
    public function test_customer_measurements(): void
    {
        /** @var User $customer */
        $customer = User::factory()->create(['email' => 'measurements@laijau.com']);
        $customer->assignRole($this->customerRole);

        $this->actingAs($customer, 'web');

        $measurements = [
            'bust' => 88.5,
            'waist' => 68.0,
            'hip' => 94.0,
            'shoulder' => 38.0,
            'height' => 172.0,
        ];

        $response = $this->postJson('/api/user/profile', [
            'saved_measurements' => $measurements,
        ]);

        $response->assertStatus(200);

        $customer->refresh();
        $this->assertEquals($measurements, $customer->saved_measurements);

        // Verification through profile endpoint
        $profileRes = $this->getJson('/api/user');
        $profileRes->assertStatus(200)
            ->assertJson([
                'saved_measurements' => $measurements,
            ]);
    }

    /**
     * 17. customer_orders
     */
    public function test_customer_orders(): void
    {
        /** @var User $customerA */
        $customerA = User::factory()->create(['email' => 'cust_a@laijau.com']);
        /** @var User $customerB */
        $customerB = User::factory()->create(['email' => 'cust_b@laijau.com']);

        $orderA = Order::create([
            'order_number' => 'LAI-ORDER-A1',
            'user_id' => $customerA->id,
            'first_name' => 'Customer',
            'last_name' => 'A',
            'shipping_address' => 'Bhotahiti 1',
            'email' => $customerA->email,
            'currency' => 'npr',
            'subtotal' => 3000.00,
            'total_amount' => 3000.00,
            'status' => Order::STATUS_PROCESSING,
            'payment_status' => 'paid',
            'shipping_country' => 'NP',
        ]);

        $orderB = Order::create([
            'order_number' => 'LAI-ORDER-B1',
            'user_id' => $customerB->id,
            'first_name' => 'Customer',
            'last_name' => 'B',
            'shipping_address' => 'Jhamsikhel 2',
            'email' => $customerB->email,
            'currency' => 'npr',
            'subtotal' => 5000.00,
            'total_amount' => 5000.00,
            'status' => Order::STATUS_PROCESSING,
            'payment_status' => 'paid',
            'shipping_country' => 'NP',
        ]);

        $this->actingAs($customerA, 'web');

        $response = $this->getJson('/api/user/orders');
        $response->assertStatus(200);

        $orderNumbers = collect($response->json())->pluck('order_number')->toArray();
        $this->assertContains('LAI-ORDER-A1', $orderNumbers);
        $this->assertNotContains('LAI-ORDER-B1', $orderNumbers);
    }

    /**
     * 18. customer_order_authorization
     */
    public function test_customer_order_authorization(): void
    {
        /** @var User $customerA */
        $customerA = User::factory()->create(['email' => 'cust_a_auth@laijau.com']);
        /** @var User $customerB */
        $customerB = User::factory()->create(['email' => 'cust_b_auth@laijau.com']);

        $orderB = Order::create([
            'order_number' => 'LAI-ORDER-SECURE-B',
            'user_id' => $customerB->id,
            'first_name' => 'Customer',
            'last_name' => 'B',
            'shipping_address' => 'Pulchowk 3',
            'email' => $customerB->email,
            'currency' => 'npr',
            'subtotal' => 4500.00,
            'total_amount' => 4500.00,
            'status' => Order::STATUS_PROCESSING,
            'payment_status' => 'paid',
            'shipping_country' => 'NP',
        ]);

        // Customer A attempts to view Customer B's order
        $this->actingAs($customerA, 'web');

        $response = $this->getJson("/api/user/orders/{$orderB->order_number}");
        $response->assertStatus(404);

        // Also test order lookup endpoint
        $lookupRes = $this->getJson("/api/orders/lookup/{$orderB->order_number}");
        $lookupRes->assertStatus(403);
    }

    /**
     * 19. customer_receipt_authorization
     */
    public function test_customer_receipt_authorization(): void
    {
        /** @var User $customerA */
        $customerA = User::factory()->create(['email' => 'receipt_a@laijau.com']);
        /** @var User $customerB */
        $customerB = User::factory()->create(['email' => 'receipt_b@laijau.com']);

        $orderA = Order::create([
            'order_number' => 'LAI-RECEIPT-A',
            'user_id' => $customerA->id,
            'first_name' => 'Customer',
            'last_name' => 'A',
            'shipping_address' => 'Lazimpat 4',
            'email' => $customerA->email,
            'currency' => 'npr',
            'subtotal' => 2000.00,
            'total_amount' => 2000.00,
            'status' => Order::STATUS_PROCESSING,
            'payment_status' => 'paid',
            'shipping_country' => 'NP',
        ]);

        // 1. Customer B attempts to view Customer A's receipt -> 403
        $this->actingAs($customerB, 'web');
        $this->get("/orders/{$orderA->id}/receipt")->assertStatus(403);

        // 2. Unauthenticated guest attempts to view receipt without session -> 403
        Auth::logout();
        $this->get("/orders/{$orderA->id}/receipt")->assertStatus(403);

        // 3. Customer A views their own receipt -> 200
        $this->actingAs($customerA, 'web');
        $this->get("/orders/{$orderA->id}/receipt")->assertStatus(200);
    }

    private function getTestProductAndVariant(): array
    {
        $variant = ProductVariant::where('is_active', true)
            ->where('stock_quantity', '>=', 5)
            ->whereHas('product', function ($q) {
                $q->where('is_published', true)->where('is_active', true);
            })
            ->first();

        if ($variant && $variant->product) {
            $product = $variant->product;
            $product->update([
                'availability_status' => 'available',
                'is_published' => true,
                'is_active' => true,
            ]);
            return [$product, $variant];
        }

        $product = Product::where('is_published', true)
            ->where('is_active', true)
            ->where('quantity', '>=', 5)
            ->first();

        if ($product) {
            $product->update(['availability_status' => 'available']);
            $variant = $product->variants()->where('is_active', true)->first();
            if ($variant) {
                $variant->update(['stock_quantity' => 20]);
                return [$product, $variant];
            }
        }

        $product = Product::create([
            'name' => 'Test In-Stock Kaftan ' . uniqid(),
            'slug' => 'test-in-stock-kaftan-' . uniqid(),
            'price' => 890.00,
            'quantity' => 20,
            'availability_status' => 'available',
            'is_published' => true,
            'is_active' => true,
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'size' => 'M',
            'color' => 'Navy',
            'sku' => 'TEST-STOCK-' . uniqid(),
            'price' => 890.00,
            'stock_quantity' => 20,
            'is_active' => true,
        ]);

        return [$product, $variant];
    }

    /**
     * 20. guest_checkout
     */
    public function test_guest_checkout(): void
    {
        [$product, $variant] = $this->getTestProductAndVariant();

        $response = $this->postJson('/api/checkout', [
            'customer' => [
                'first_name' => 'Guest',
                'last_name' => 'Shopper',
                'email' => 'guest_checkout_test@example.com',
                'phone' => '9841234567',
                'province' => 'Bagmati Province',
                'district' => 'Kathmandu',
                'municipality' => 'Kathmandu Metropolitan City',
                'ward' => '10',
                'tole' => 'Baneshwor',
                'address' => 'Strøget 12',
                'country' => 'NP',
            ],
            'items' => [
                [
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'quantity' => 1,
                    'selected_size' => $variant->size,
                    'selected_color' => $variant->color,
                ]
            ],
            'currency' => 'npr',
            'payment_method' => 'cod',
        ]);

        $response->assertSuccessful()
            ->assertJsonStructure(['order_id', 'order_number', 'total_amount']);

        $order = Order::find($response->json('order_id'));
        $this->assertNull($order->user_id);
        $this->assertEquals('guest_checkout_test@example.com', $order->email);
    }

    /**
     * 21. authenticated_checkout
     */
    public function test_authenticated_checkout(): void
    {
        /** @var User $customer */
        $customer = User::factory()->create(['email' => 'auth_checkout@laijau.com']);
        $customer->assignRole($this->customerRole);

        [$product, $variant] = $this->getTestProductAndVariant();

        $this->actingAs($customer, 'web');

        // Client attempts to pass a tampered user_id = 99999
        $response = $this->postJson('/api/checkout', [
            'user_id' => 99999,
            'customer' => [
                'first_name' => 'Auth',
                'last_name' => 'Customer',
                'email' => 'auth_checkout@laijau.com',
                'phone' => '9841234567',
                'province' => 'Bagmati Province',
                'district' => 'Kathmandu',
                'municipality' => 'Kathmandu Metropolitan City',
                'ward' => '10',
                'tole' => 'Baneshwor',
                'address' => 'New Baneshwor 12',
                'country' => 'NP',
            ],
            'items' => [
                [
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'quantity' => 1,
                    'selected_size' => $variant->size,
                    'selected_color' => $variant->color,
                ]
            ],
            'currency' => 'npr',
            'payment_method' => 'cod',
        ]);

        $response->assertSuccessful();
        $order = Order::find($response->json('order_id'));

        // Server authoritative user_id binding: ignores 99999 and uses $customer->id
        $this->assertEquals($customer->id, $order->user_id);
    }

    /**
     * 22. guest_to_login_cart_persistence
     */
    public function test_guest_to_login_cart_persistence(): void
    {
        [$product, $variant] = $this->getTestProductAndVariant();

        // Customer logs in with existing bag items in payload
        $customer = User::factory()->create([
            'email' => 'cart_persist@laijau.com',
            'password' => Hash::make('Laijau#Cart2026'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'cart_persist@laijau.com',
            'password' => 'Laijau#Cart2026',
        ]);

        $response->assertStatus(200);
        $this->assertTrue(auth('web')->check());

        // Cart validation remains accessible and intact
        $cartVal = $this->postJson('/api/cart/validate', [
            'shipping_country' => 'NP',
            'district' => 'Kathmandu',
            'currency' => 'npr',
            'items' => [
                [
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'quantity' => 1,
                ]
            ]
        ]);

        $cartVal->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /**
     * 23. logout_clears_customer_state
     */
    public function test_logout_clears_customer_state(): void
    {
        /** @var User $customer */
        $customer = User::factory()->create(['email' => 'clear_state@laijau.com']);
        $this->actingAs($customer, 'web');

        $this->assertTrue(auth('web')->check());

        $this->postJson('/api/auth/logout')->assertStatus(200);

        $this->assertFalse(auth('web')->check());

        $account = $this->get('/account');
        $account->assertStatus(200);
        $account->assertSee('window.__LAIJAU_AUTH_USER__ = null;', false);
    }

    /**
     * 24. stale_localstorage_does_not_authenticate
     */
    public function test_stale_localstorage_does_not_authenticate(): void
    {
        // When there is no server session or token, server state is strictly guest
        $this->assertFalse(auth('web')->check());

        $account = $this->get('/account');
        $account->assertStatus(200);
        $account->assertSee('window.__LAIJAU_AUTH_USER__ = null;', false);

        // Protected customer endpoints reject unauthenticated requests
        $apiResponse = $this->getJson('/api/user');
        $apiResponse->assertStatus(401);
    }

    /**
     * 25. customer_cannot_modify_another_profile
     */
    public function test_customer_cannot_modify_another_profile(): void
    {
        /** @var User $customerA */
        $customerA = User::factory()->create([
            'email' => 'customer_a_profile@laijau.com',
            'name' => 'Original Customer A',
        ]);
        /** @var User $customerB */
        $customerB = User::factory()->create([
            'email' => 'customer_b_profile@laijau.com',
            'name' => 'Original Customer B',
            'phone' => '9841111111',
            'address' => 'Secret Address B',
        ]);

        $this->actingAs($customerA, 'web');

        // Customer A submits update, even if attempting to pass user_id = Customer B
        $response = $this->postJson('/api/user/profile', [
            'user_id' => $customerB->id,
            'name' => 'Hacked Name',
            'phone' => '9841999999',
            'address' => 'Hacked Address',
        ]);

        $response->assertStatus(200);

        // Customer A was updated
        $customerA->refresh();
        $this->assertEquals('Hacked Name', $customerA->name);

        // Customer B remains completely untouched
        $customerB->refresh();
        $this->assertEquals('Original Customer B', $customerB->name);
        $this->assertEquals('9841111111', $customerB->phone);
        $this->assertEquals('Secret Address B', $customerB->address);
    }

    /**
     * 26. checkout_price_manipulation_defense
     */
    public function test_checkout_price_manipulation_defense(): void
    {
        [$product, $variant] = $this->getTestProductAndVariant();

        // Customer attempts to submit checkout with forged 0.01 unit price or 0 total
        $response = $this->postJson('/api/checkout', [
            'total_amount' => 0.01,
            'unit_price' => 0.01,
            'customer' => [
                'first_name' => 'Price',
                'last_name' => 'Tester',
                'email' => 'price_manipulation@example.com',
                'phone' => '9841234567',
                'province' => 'Bagmati Province',
                'district' => 'Kathmandu',
                'municipality' => 'Kathmandu Metropolitan City',
                'ward' => '10',
                'tole' => 'Baneshwor',
                'address' => 'New Baneshwor 10',
                'country' => 'NP',
            ],
            'items' => [
                [
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'quantity' => 1,
                    'selected_size' => $variant->size,
                    'selected_color' => $variant->color,
                    'unit_price' => 0.01,
                    'total_price' => 0.01,
                ]
            ],
            'currency' => 'npr',
            'payment_method' => 'cod',
        ]);

        $response->assertSuccessful();
        $order = Order::find($response->json('order_id'));

        // Server authoritative calculation ignores the 0.01 forged price
        $expectedPrice = (float)($variant->price ?: $variant->price_npr);
        $this->assertEquals($expectedPrice, (float)$order->items->first()->unit_price);
    }

    /**
     * 27. google_account_conflict_defense
     */
    public function test_google_account_conflict_defense(): void
    {
        $userA = User::factory()->create([
            'email' => 'usera@laijau.com',
            'google_id' => 'google_collision_id',
        ]);
        $userB = User::factory()->create([
            'email' => 'userb@laijau.com',
            'google_id' => null,
        ]);

        // Socialite returns Google ID of userA but email of userB (account collision)
        $abstractUser = Mockery::mock('Laravel\Socialite\Two\User');
        $abstractUser->shouldReceive('getId')->andReturn('google_collision_id');
        $abstractUser->shouldReceive('getEmail')->andReturn('userb@laijau.com');
        $abstractUser->shouldReceive('getName')->andReturn('Collision Test');
        $abstractUser->shouldReceive('getAvatar')->andReturn(null);

        $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('stateless')->andReturn($provider);
        $provider->shouldReceive('user')->andReturn($abstractUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get('/auth/google/callback');

        // Must reject collision with error redirect and not authenticate
        $response->assertRedirect('/account?error=account_conflict');
        $this->assertFalse(auth('web')->check());
    }

    /**
     * 28. session_fixation_defense
     */
    public function test_session_fixation_defense(): void
    {
        $customer = User::factory()->create([
            'email' => 'fixation@laijau.com',
            'password' => Hash::make('Laijau#Fixation2026'),
        ]);

        // Start initial session
        $this->get('/account');
        $initialSessionId = session()->getId();

        $response = $this->post('/login', [
            'email' => 'fixation@laijau.com',
            'password' => 'Laijau#Fixation2026',
        ]);

        $response->assertRedirect('/account');
        $newSessionId = session()->getId();

        // Session ID MUST be regenerated after authentication
        $this->assertNotEquals($initialSessionId, $newSessionId);
    }

    /**
     * 29. unauthorized_api_rejection
     */
    public function test_unauthorized_api_rejection(): void
    {
        Auth::logout();

        $response = $this->getJson('/api/user/orders');
        $response->assertStatus(401);

        $profileRes = $this->postJson('/api/user/profile', ['name' => 'Unauth Name']);
        $profileRes->assertStatus(401);
    }
}
