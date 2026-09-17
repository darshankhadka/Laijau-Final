<?php

namespace Tests\Feature;

use App\Models\OfflineSale;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Customer\CustomerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminOrdersAndCustomersTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected User $customer;
    protected Order $order;
    protected CustomerService $customerService;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'admin']);

        $this->admin = User::firstOrCreate(
            ['email' => 'admin@laijau.com'],
            [
                'name' => 'Laijau Administrator',
                'password' => Hash::make('password123'),
                'role' => 'admin',
            ]
        );

        if (!$this->admin->hasRole('Super Admin', 'admin')) {
            $this->admin->assignRole($role);
        }

        $this->customer = User::firstOrCreate(
            ['email' => 'suman.test@laijau.com'],
            [
                'name' => 'Suman Shrestha',
                'phone' => '9841234567',
                'role' => 'customer',
                'password' => Hash::make('secret123'),
                'district' => 'Kathmandu',
                'municipality' => 'Kathmandu Metropolitan',
                'ward' => '3',
                'tole' => 'Lazimpat',
                'address' => 'Lazimpat, Ward 3, Kathmandu',
            ]
        );

        $product = Product::firstOrCreate(
            ['slug' => 'pashmina-shawl-test'],
            [
                'name' => 'Royal Pashmina Shawl',
                'sku' => 'PAS-001',
                'price' => 4500.00,
                'quantity' => 10,
                'is_published' => true,
                'is_active' => true,
            ]
        );

        $this->order = Order::firstOrCreate(
            ['order_number' => 'LJ-1001'],
            [
                'channel' => Order::CHANNEL_ONLINE,
                'guest_access_token' => 'guest_token_1001',
                'user_id' => $this->customer->id,
                'first_name' => 'Suman',
                'last_name' => 'Shrestha',
                'phone' => '9841234567',
                'email' => 'suman.test@laijau.com',
                'province' => 'Bagmati',
                'district' => 'Kathmandu',
                'municipality' => 'Kathmandu Metropolitan',
                'ward' => '3',
                'tole' => 'Lazimpat',
                'landmark' => 'Near Embassy',
                'is_inside_valley' => true,
                'shipping_address' => 'Lazimpat, Ward 3, Kathmandu',
                'shipping_fee' => 0.00,
                'subtotal' => 4500.00,
                'total_amount' => 4500.00,
                'currency' => 'NPR',
                'status' => Order::STATUS_PROCESSING,
                'payment_method' => 'cod',
                'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            ]
        );

        OrderItem::firstOrCreate(
            ['order_id' => $this->order->id, 'product_id' => $product->id],
            [
                'product_name' => $product->name,
                'sku' => 'PAS-001-RED',
                'quantity' => 1,
                'unit_price' => 4500.00,
                'selected_color' => 'Red',
                'selected_size' => 'Free Size',
            ]
        );

        $this->customerService = app(CustomerService::class);
    }

    public function test_admin_orders_list_page_renders_successfully(): void
    {
        $response = $this->actingAs($this->admin, 'admin')->get('/intadmin/orders');
        $response->assertStatus(200);
        $response->assertSee('Order');
    }

    public function test_admin_customers_list_page_renders_successfully(): void
    {
        $response = $this->actingAs($this->admin, 'admin')->get('/intadmin/customers');
        $response->assertStatus(200);
        $response->assertSee('Customer');
    }

    public function test_order_detail_workspace_renders_with_items_and_timeline(): void
    {
        $response = $this->actingAs($this->admin, 'admin')->get("/intadmin/orders/{$this->order->id}");
        $response->assertStatus(200);
        $response->assertSee($this->order->order_number);
        $response->assertSee('Order Items');
        $response->assertSee('Operations Timeline');
        $response->assertSee('Financials');
        $response->assertSee('Payment');
    }

    public function test_customer_detail_dossier_renders_with_kpi_cards_and_history(): void
    {
        $response = $this->actingAs($this->admin, 'admin')->get("/intadmin/customers/{$this->customer->id}");
        $response->assertStatus(200);
        $response->assertSee($this->customer->name);
        $response->assertSee('Total Orders');
        $response->assertSee('Lifetime Spend');
        $response->assertSee('Sales History');
    }

    public function test_printable_tax_invoice_route_renders_compliantly(): void
    {
        $response = $this->actingAs($this->admin, 'admin')->get("/intadmin/orders/{$this->order->id}/invoice");
        $response->assertStatus(200);
        $response->assertSee('Retail Tax Invoice');
        $response->assertSee('604335148'); // VAT No.
        $response->assertSee($this->order->order_number);
    }

    public function test_printable_packing_slip_route_renders_compliantly(): void
    {
        $response = $this->actingAs($this->admin, 'admin')->get("/intadmin/orders/{$this->order->id}/packing-slip");
        $response->assertStatus(200);
        $response->assertSee('WAREHOUSE PACKING SLIP');
        $response->assertSee($this->order->order_number);
    }

    public function test_unauthenticated_user_cannot_access_invoices(): void
    {
        $response = $this->get("/intadmin/orders/{$this->order->id}/invoice");
        $response->assertStatus(403);
    }

    public function test_order_customer_bidirectional_resolution(): void
    {
        $customer = $this->order->resolveCustomer();
        $this->assertNotNull($customer, 'Order should resolve a valid customer.');
        $this->assertEquals('customer', $customer->role);
        $this->assertEquals($this->customer->id, $customer->id);

        $metrics = $this->customerService->getCustomerMetrics($customer);
        $this->assertGreaterThanOrEqual(1, $metrics['total_orders']);
        $this->assertGreaterThanOrEqual(4500, $metrics['lifetime_spend']);
    }

    public function test_customer_service_normalizes_nepal_phones_accurately(): void
    {
        $this->assertEquals('9841234567', CustomerService::normalizePhone('+977-9841234567'));
        $this->assertEquals('9841234567', CustomerService::normalizePhone('9779841234567'));
        $this->assertEquals('9841234567', CustomerService::normalizePhone('9841234567'));
        $this->assertEquals('9841234567', CustomerService::normalizePhone('01-9841234567'));
        $this->assertNull(CustomerService::normalizePhone(''));
    }

    public function test_customer_order_history_combines_online_and_pos_sales(): void
    {
        $history = $this->customerService->getCustomerOrderHistory($this->customer);
        $this->assertIsArray($history);
        $this->assertNotEmpty($history);
        foreach ($history as $entry) {
            $this->assertArrayHasKey('number', $entry);
            $this->assertArrayHasKey('channel', $entry);
            $this->assertArrayHasKey('total_amount', $entry);
            $this->assertArrayHasKey('status', $entry);
        }
    }

    public function test_order_timeline_events_sorted_chronologically(): void
    {
        $timeline = $this->order->getTimelineEvents();
        $this->assertIsArray($timeline);

        $prevTimestamp = 0;
        foreach ($timeline as $event) {
            $t = $event['timestamp'] instanceof \Carbon\Carbon ? $event['timestamp']->timestamp : strtotime((string)$event['timestamp']);
            $this->assertGreaterThanOrEqual($prevTimestamp, $t);
            $prevTimestamp = $t;
        }
    }

    public function test_order_cancellation_preserves_audit_trail_and_marks_cancelled(): void
    {
        $testOrder = Order::create([
            'order_number' => 'LJ-TEST-CANCEL-' . time(),
            'channel' => Order::CHANNEL_ONLINE,
            'guest_access_token' => 'token_' . time(),
            'first_name' => 'Test',
            'last_name' => 'Buyer',
            'phone' => '9800000099',
            'email' => 'cancel_test@laijau.com',
            'total_amount' => 3500.00,
            'subtotal' => 3500.00,
            'status' => Order::STATUS_PENDING,
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'payment_method' => 'cod',
            'is_inside_valley' => true,
            'shipping_address' => 'Kathmandu, Nepal',
        ]);

        $testOrder->update([
            'status' => Order::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancellation_reason' => 'Customer requested cancellation before dispatch',
        ]);

        $this->assertEquals(Order::STATUS_CANCELLED, $testOrder->fresh()->status);
        $this->assertNotNull($testOrder->fresh()->cancelled_at);
        $this->assertEquals('Customer requested cancellation before dispatch', $testOrder->fresh()->cancellation_reason);
    }
}
