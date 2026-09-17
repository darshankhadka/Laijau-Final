<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class FilamentAdminTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'admin']);

        $this->admin = User::firstOrCreate(
            ['email' => 'admin@laijau.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('Dars@@9861'),
                'role' => 'admin',
            ]
        );

        if (!$this->admin->hasRole('Super Admin', 'admin')) {
            $this->admin->assignRole($role);
        }
    }

    public function test_admin_can_access_filament_dashboard()
    {
        $response = $this->actingAs($this->admin, 'admin')->get('/intadmin');
        $response->assertStatus(200);
    }

    public function test_admin_can_render_all_core_filament_resources_and_pages()
    {
        $endpoints = [
            '/intadmin',
            '/intadmin/point-of-sale',
            '/intadmin/products',
            '/intadmin/products/create',
            '/intadmin/categories',
            '/intadmin/collections',
            '/intadmin/collections/create',
            '/intadmin/attributes-sizes',
            '/intadmin/orders',
            '/intadmin/inventory-dashboard',
            '/intadmin/stock-levels',
            '/intadmin/stock-movements',
            '/intadmin/warehouses',
            '/intadmin/suppliers',
            '/intadmin/purchase-orders',
            '/intadmin/stock-transfers',
            '/intadmin/stock-adjustments',
            '/intadmin/stock-counts',
            '/intadmin/inventory-valuation-page',
            '/intadmin/crm-kanban',
            '/intadmin/crm-leads',
            '/intadmin/customers',
            '/intadmin/users',
            '/intadmin/coupons',
            '/intadmin/coupons/create',
            '/intadmin/shipping-methods',
            '/intadmin/contact-messages',
            '/intadmin/manage-settings',
            '/intadmin/hrm-dashboard',
            '/intadmin/employees',
            '/intadmin/employees/create',
            '/intadmin/departments',
            '/intadmin/departments/create',
            '/intadmin/leave-requests',
            '/intadmin/leave-requests/create',
            '/intadmin/timesheets',
            '/intadmin/timesheets/create',
            '/intadmin/payroll-runs',
            '/intadmin/payroll-runs/create',
            '/intadmin/expense-claims',
            '/intadmin/expense-claims/create',
            '/intadmin/recruitments',
            '/intadmin/recruitments/create',
        ];

        foreach ($endpoints as $url) {
            $res = $this->actingAs($this->admin, 'admin')->get($url);
            if ($res->status() !== 200) {
                dump("Error on {$url}: status " . $res->status());
                if ($res->exception) {
                    dump("Exception: " . $res->exception->getMessage());
                }
            }
            $this->assertEquals(200, $res->status(), "Failed rendering {$url}");
        }
    }

    public function test_admin_can_create_product_with_variants_and_npr_pricing()
    {
        $category = Category::firstOrCreate(
            ['slug' => 'test-footwear-category'],
            ['name' => 'Test Footwear Category', 'is_active' => true]
        );

        $product = Product::create([
            'name' => 'Hand-woven Raw Silk Dupatta',
            'slug' => 'hand-woven-raw-silk-dupatta-' . uniqid(),
            'sku' => 'TEST-DUP-' . rand(1000, 9999),
            'price_npr' => 1350.00,
            'compare_at_price_npr' => 1550.00,
            'quantity' => 15,
            'track_quantity' => true,
            'requires_custom_measurements' => true,
            'custom_measurement_attributes' => [
                ['name' => 'length', 'label' => 'Length (cm)', 'type' => 'number', 'required' => true],
            ],
            'is_active' => true,
            'is_published' => true,
            'is_featured' => true,
        ]);

        $product->categories()->attach($category->id);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => $product->sku . '-GOLD',
            'size' => 'Standard (2.5m)',
            'color' => 'Royal Gold',
            'color_hex' => '#D4AF37',
            'stock_quantity' => 8,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'is_featured' => 1]);
        $this->assertDatabaseHas('product_variants', ['id' => $variant->id, 'stock_quantity' => 8]);

        // Verify public API returns the product with NPR pricing
        $apiResponse = $this->getJson('/api/products/' . $product->slug . '?currency=npr');
        $apiResponse->assertStatus(200)
            ->assertJsonPath('data.is_featured', true);
        $this->assertEquals(1350.00, (float)$apiResponse->json('data.price_npr'));
    }

    public function test_coupon_validation_rules_enforce_min_spend_and_active_status()
    {
        $coupon = Coupon::create([
            'code' => 'TESTMIN100_' . uniqid(),
            'type' => 'fixed',
            'value' => 200.00,
            'min_spend' => 1000.00,
            'is_active' => true,
        ]);

        $this->assertTrue($coupon->isValid(1500.00));
        $this->assertFalse($coupon->isValid(500.00)); // Fails min spend

        $coupon->update(['is_active' => false]);
        $this->assertFalse($coupon->isValid(2000.00)); // Fails because inactive
    }

    public function test_order_can_be_dispatched_with_tracking_info()
    {
        $order = Order::create([
            'order_number' => 'NA-TEST-' . rand(1000, 9999),
            'first_name' => 'Aarav',
            'last_name' => 'Sharma',
            'email' => 'aarav@example.com',
            'shipping_address' => 'Lazimpat, Ward 2',
            'shipping_city' => 'Kathmandu',
            'shipping_postal_code' => '44600',
            'shipping_country' => 'NP',
            'subtotal' => 3000.00,
            'total_amount' => 3000.00,
            'currency' => 'npr',
            'status' => Order::STATUS_PAID,
            'payment_status' => 'paid',
        ]);

        $trackingUrl = Order::resolveTrackingUrl('Nepal Can Move (NCM)', 'NCM-123456789');

        $order->update([
            'status' => Order::STATUS_SHIPPED,
            'carrier' => 'Nepal Can Move (NCM)',
            'tracking_number' => 'NCM-123456789',
            'tracking_url' => $trackingUrl,
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => Order::STATUS_SHIPPED,
            'carrier' => 'Nepal Can Move (NCM)',
            'tracking_number' => 'NCM-123456789',
        ]);

        $this->assertStringContainsString('nepalcanmove.com', $order->tracking_url);
    }

    public function test_order_spec_sheet_renders_for_authenticated_admin()
    {
        $product = Product::first();
        if (!$product) {
            $product = Product::create([
                'name' => 'Classic Leather Shoes',
                'slug' => 'classic-leather-shoes-' . uniqid(),
                'sku' => 'TEST-SHOE',
                'price_npr' => 1850.00,
                'stock' => 10,
                'is_active' => true,
            ]);
        }

        $order = Order::create([
            'order_number' => 'NA-SPEC-' . rand(1000, 9999),
            'first_name' => 'Sunita',
            'last_name' => 'Shrestha',
            'email' => 'sunita@example.com',
            'shipping_address' => 'Lakeside, Ward 6',
            'shipping_city' => 'Pokhara',
            'shipping_postal_code' => '33700',
            'shipping_country' => 'NP',
            'subtotal' => 1850.00,
            'total_amount' => 1850.00,
            'currency' => 'npr',
            'status' => Order::STATUS_PROCESSING,
            'customer_notes' => 'Standard retail packaging please.',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => 'Classic Leather Shoes',
            'sku' => 'TEST-SHOE',
            'selected_size' => '42',
            'selected_color' => 'Brown',
            'unit_price' => 1850.00,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($this->admin, 'admin')->get(route('order.spec_sheet', $order));
        $response->assertStatus(200);
        $response->assertSee('Specification Sheet');
        $response->assertSee('Sunita Shrestha');
        $response->assertSee('Standard retail packaging please');
    }

    public function test_admin_settings_nepal_gateway_credentials()
    {
        $this->actingAs($this->admin, 'admin');
        $response = $this->get('/intadmin/manage-settings');
        $this->assertTrue(in_array($response->status(), [200, 302]));
    }

    public function test_crm_and_accounts_suite_complete()
    {
        // 1. Test CRM Leads & Kanban
        $lead = \App\Models\CrmLead::create([
            'title' => 'Winter Leather Boots Inquiry',
            'contact_name' => 'Amisha Patel',
            'phone' => '+977 9841223344',
            'email' => 'amisha@example.com',
            'stage' => \App\Models\CrmLead::STAGE_NEW_INQUIRY,
            'estimated_value' => 50000.00,
            'currency' => 'npr',
            'source' => 'whatsapp',
        ]);

        $resKanban = $this->actingAs($this->admin, 'admin')->get('/intadmin/crm-kanban');
        $resKanban->assertStatus(200);

        $resLeadsIndex = $this->actingAs($this->admin, 'admin')->get('/intadmin/crm-leads');
        $resLeadsIndex->assertStatus(200);

        // WhatsApp direct link generator
        $this->assertNotNull($lead->whats_app_url);
        $this->assertStringContainsString('Winter+Leather+Boots', $lead->whats_app_url);

        // Test Kanban Page Livewire Component
        \Livewire\Livewire::actingAs($this->admin, 'admin')
            ->test(\App\Filament\Pages\CrmKanban::class)
            ->assertSuccessful()
            ->call('moveStage', $lead->id, \App\Models\CrmLead::STAGE_CONTACTED)
            ->assertHasNoErrors();

        $this->assertEquals(\App\Models\CrmLead::STAGE_CONTACTED, $lead->fresh()->stage);

        // Test Lead Creation via Kanban modal
        \Livewire\Livewire::actingAs($this->admin, 'admin')
            ->test(\App\Filament\Pages\CrmKanban::class)
            ->call('openNewLeadModal')
            ->set('newTitle', 'Cashmere Shawl Inquiry')
            ->set('newContactName', 'Bikash Thapa')
            ->set('newPhone', '+977 9851050607')
            ->set('newEstimatedValue', 12000.00)
            ->set('newCurrency', 'npr')
            ->call('saveNewLead')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('crm_leads', [
            'title' => 'Cashmere Shawl Inquiry',
            'contact_name' => 'Bikash Thapa',
        ]);

        // 2. Test Customer Resource CRUD & Isolation
        $customer = User::create([
            'name' => 'Manish Adhikari',
            'email' => 'manish_' . uniqid() . '@example.com',
            'phone' => '+977 9801234567',
            'password' => Hash::make('Secret123!'),
            'role' => 'customer',
            'saved_measurements' => [
                'bust' => '90',
                'waist' => '72',
                'neckline' => '36',
            ],
            'gdpr_consent' => true,
            'gdpr_consented_at' => now(),
        ]);

        $this->assertEquals('customer', $customer->role);
        $this->assertEquals('90', $customer->saved_measurements['bust']);

        $resCustomerIndex = $this->actingAs($this->admin, 'admin')->get('/intadmin/customers');
        $resCustomerIndex->assertStatus(200);

        $resCustomerCreate = $this->actingAs($this->admin, 'admin')->get('/intadmin/customers/create');
        $resCustomerCreate->assertStatus(200);

        $resCustomerEdit = $this->actingAs($this->admin, 'admin')->get('/intadmin/customers/' . $customer->id . '/edit');
        $resCustomerEdit->assertStatus(200);

        // 3. Test Internal Accounts / User Resource
        $staffUser = User::create([
            'name' => 'Sita Sharma (Sales Associate)',
            'email' => 'sita_' . uniqid() . '@laijau.com',
            'phone' => '+977 9811223344',
            'password' => Hash::make('Sales2026!'),
            'role' => 'sales_rep',
        ]);

        $resUsersIndex = $this->actingAs($this->admin, 'admin')->get('/intadmin/users');
        $resUsersIndex->assertStatus(200);

        $resUsersCreate = $this->actingAs($this->admin, 'admin')->get('/intadmin/users/create');
        $resUsersCreate->assertStatus(200);

        $resUsersEdit = $this->actingAs($this->admin, 'admin')->get('/intadmin/users/' . $staffUser->id . '/edit');
        $resUsersEdit->assertStatus(200);

        // 4. Test Communication Messages
        $contactMessage = \App\Models\ContactMessage::create([
            'name' => 'Pooja KC',
            'email' => 'pooja@example.com',
            'subject' => 'Leather Boot Sizing & Fit Consultation',
            'message' => 'Hello, I am looking for size advice for an upcoming purchase in Kathmandu.',
            'status' => \App\Models\ContactMessage::STATUS_NEW,
        ]);

        $resMessagesIndex = $this->actingAs($this->admin, 'admin')->get('/intadmin/contact-messages');
        $resMessagesIndex->assertStatus(200);

        $badge = \App\Filament\Resources\ContactMessageResource::getNavigationBadge();
        $this->assertNotNull($badge);

        // Public Contact API Submission
        $apiRes = $this->postJson('/api/contact', [
            'name' => 'Binita Rai',
            'email' => 'binita@example.com',
            'subject' => 'Pashmina Wrap Care Advice',
            'message' => 'Could you provide advice on storing 100% Chyangra Pashmina wraps during summer months?',
        ]);
        $apiRes->assertStatus(201);
        $this->assertDatabaseHas('contact_messages', [
            'email' => 'binita@example.com',
            'status' => 'new',
        ]);

        // 5. Test Configuration: Settings & Shipping Methods
        $resSettings = $this->actingAs($this->admin, 'admin')->get('/intadmin/manage-settings');
        $resSettings->assertStatus(200);

        $resShipping = $this->actingAs($this->admin, 'admin')->get('/intadmin/shipping-methods');
        $resShipping->assertStatus(200);

        $shippingMethod = \App\Models\ShippingMethod::firstOrCreate(
            ['code' => 'test_express_courier'],
            [
                'name' => 'Test Express Courier',
                'zone' => 'kathmandu_valley',
                'carrier' => 'Pathao Express',
                'price_npr' => 150.00,
                'free_shipping_threshold_npr' => 3000.00,
                'estimated_delivery' => 'Same day express',
                'is_active' => true,
                'sort_order' => 5,
            ]
        );

        $this->assertDatabaseHas('shipping_methods', ['code' => 'test_express_courier']);
        $rates = \App\Models\ShippingMethod::getRateForCountry('NP', 400.0, 'NPR', 'test_express_courier', 'Kathmandu');
        $this->assertEquals(150.00, $rates['cost']);
        $this->assertFalse($rates['is_free']);

        $freeRates = \App\Models\ShippingMethod::getRateForCountry('NP', 3500.0, 'NPR', 'test_express_courier', 'Kathmandu');
        $this->assertEquals(0.00, $freeRates['cost']);
        $this->assertTrue($freeRates['is_free']);
    }
}
