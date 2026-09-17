<?php

namespace Tests\Feature;

use App\Filament\Pages\FulfillmentHubPage;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\Warehouse;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FulfillmentHubTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;
    protected Warehouse $warehouse;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.ncm.api_token' => 'test_token',
            'services.ncm.mode' => 'sandbox',
            'services.pathao.client_id' => 'test_id',
            'services.pathao.client_secret' => 'test_secret',
            'services.pathao.username' => 'test_user',
            'services.pathao.password' => 'test_pass',
        ]);

        $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'admin']);

        $this->adminUser = User::firstOrCreate(
            ['email' => 'admin@laijau.com'],
            [
                'name' => 'Laijau Fulfillment Admin',
                'password' => Hash::make('password123'),
                'role' => 'admin',
            ]
        );

        if (!$this->adminUser->hasRole('Super Admin', 'admin')) {
            $this->adminUser->assignRole($role);
        }

        $this->warehouse = Warehouse::firstOrCreate([
            'code' => 'WH-KTM-MAIN',
        ], [
            'name' => 'Central Hub Kathmandu',
            'address' => 'Kathmandu',
            'city' => 'Kathmandu',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'name' => 'Nepali Dhaka Shawl',
            'slug' => 'dhaka-shawl-' . uniqid(),
            'sku' => 'SHAWL-01',
            'price' => 2500,
            'is_active' => true,
            'is_published' => true,
        ]);

        StockLevel::create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'variant_id' => null,
            'quantity_on_hand' => 20,
            'quantity_reserved' => 2,
        ]);
    }

    protected function createOrder(array $attributes = []): Order
    {
        $order = Order::create(array_merge([
            'order_number' => Order::generateNextOrderNumber(),
            'guest_access_token' => Order::generateGuestToken(),
            'first_name' => 'Ramesh',
            'last_name' => 'Adhikari',
            'email' => 'ramesh@example.com',
            'phone' => '9851012345',
            'shipping_address' => 'Patan Durbar Square',
            'shipping_country' => 'Nepal',
            'province' => 'Bagmati Province',
            'district' => 'Lalitpur',
            'municipality' => 'Lalitpur',
            'ward' => '5',
            'is_inside_valley' => true,
            'subtotal' => 2500,
            'shipping_fee' => 100,
            'total_amount' => 2600,
            'currency' => 'NPR',
            'status' => Order::STATUS_PROCESSING,
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
        ], $attributes));

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'sku' => $this->product->sku,
            'quantity' => 1,
            'unit_price' => 2500,
        ]);

        return $order->fresh(['items']);
    }

    public function test_admin_can_access_fulfillment_hub_page(): void
    {
        $this->actingAs($this->adminUser, 'admin');

        $response = $this->get('/intadmin/fulfillment-hub');
        $response->assertSuccessful();
        $response->assertSee('Ready to Pick');
        $response->assertSee('Ready to Dispatch');
    }

    public function test_fulfillment_hub_renders_kpis_and_active_tab_records(): void
    {
        $this->actingAs($this->adminUser, 'admin');

        $order1 = $this->createOrder(['status' => Order::STATUS_PROCESSING]);
        $order2 = $this->createOrder(['status' => Order::STATUS_HANDED_TO_COURIER, 'tracking_number' => 'NCM-887711']);

        Livewire::test(FulfillmentHubPage::class)
            ->set('searchQuery', $order1->order_number)
            ->assertSee($order1->order_number)
            ->assertSee('Ramesh Adhikari')
            ->set('searchQuery', '')
            ->set('activeTab', 'in_transit')
            ->set('searchQuery', $order2->order_number)
            ->assertSee($order2->order_number)
            ->assertSee('NCM-887711');
    }

    public function test_single_order_dispatch_with_manual_carrier(): void
    {
        $this->actingAs($this->adminUser, 'admin');
        $order = $this->createOrder(['status' => Order::STATUS_PROCESSING]);

        Livewire::test(FulfillmentHubPage::class)
            ->call('openDispatchModal', $order->id)
            ->set('selectedCourier', 'manual')
            ->set('manualCourierName', 'Local Express Kathmandu')
            ->set('manualTrackingNumber', 'LOCAL-EXPRESS-101')
            ->call('confirmDispatch');

        $order->refresh();
        $this->assertEquals(Order::STATUS_HANDED_TO_COURIER, $order->status);
        $this->assertEquals('LOCAL-EXPRESS-101', $order->tracking_number);
        $this->assertEquals('Local Express Kathmandu', $order->carrier);
        $this->assertNotNull($order->courier_pickup_date);
    }

    public function test_book_ncm_courier_shipment_creates_external_tracking(): void
    {
        $this->actingAs($this->adminUser, 'admin');
        $order = $this->createOrder(['status' => Order::STATUS_PROCESSING]);

        Http::fake([
            '*/api/v1/order/create' => Http::response([
                'Message' => 'Order Successfully Created',
                'orderid' => 991122,
            ], 200),
        ]);

        Livewire::test(FulfillmentHubPage::class)
            ->call('openDispatchModal', $order->id)
            ->set('selectedCourier', 'ncm')
            ->call('confirmDispatch');

        $order->refresh();
        $this->assertEquals(Order::STATUS_HANDED_TO_COURIER, $order->status);
        $this->assertEquals('991122', $order->tracking_number);

        $this->assertDatabaseHas('logistics_events', [
            'order_id' => $order->id,
            'provider' => 'ncm',
            'external_order_id' => '991122',
        ]);
    }

    public function test_mark_order_delivered_updates_status(): void
    {
        $this->actingAs($this->adminUser, 'admin');
        $order = $this->createOrder([
            'status' => Order::STATUS_HANDED_TO_COURIER,
            'tracking_number' => 'NCM-112233',
            'carrier' => 'Nepal Can Move (NCM)',
            'payment_method' => 'esewa',
            'payment_status' => 'unpaid',
        ]);

        Livewire::test(FulfillmentHubPage::class)
            ->call('markDelivered', $order->id);

        $order->refresh();
        $this->assertEquals(Order::STATUS_DELIVERED, $order->status);
        $this->assertEquals('paid', $order->payment_status);
        $this->assertNotNull($order->delivered_at);
    }

    public function test_mark_order_returned_restores_inventory_via_inventory_service(): void
    {
        $this->actingAs($this->adminUser, 'admin');
        $order = $this->createOrder([
            'status' => Order::STATUS_HANDED_TO_COURIER,
            'tracking_number' => 'NCM-998877',
            'carrier' => 'Nepal Can Move (NCM)',
        ]);

        $initialStock = StockLevel::where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $this->product->id)
            ->first()
            ->quantity_on_hand;

        Livewire::test(FulfillmentHubPage::class)
            ->call('openExceptionModal', $order->id)
            ->set('exceptionReason', 'customer_refused')
            ->set('exceptionAction', 'return_stock')
            ->call('confirmException', $order->id);

        $order->refresh();
        $this->assertEquals(Order::STATUS_RETURNED, $order->status);

        $finalStock = StockLevel::where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $this->product->id)
            ->first()
            ->quantity_on_hand;

        // Verify that 1 unit was returned back to stock
        $this->assertEquals($initialStock + 1, $finalStock);
    }

    public function test_bulk_dispatch_moves_all_ready_orders_to_handed_to_courier(): void
    {
        $this->actingAs($this->adminUser, 'admin');
        $order1 = $this->createOrder(['status' => Order::STATUS_READY_FOR_DELIVERY]);
        $order2 = $this->createOrder(['status' => Order::STATUS_READY_FOR_DELIVERY]);

        Livewire::test(FulfillmentHubPage::class)
            ->call('bulkDispatchReady');

        $order1->refresh();
        $order2->refresh();

        $this->assertEquals(Order::STATUS_HANDED_TO_COURIER, $order1->status);
        $this->assertEquals(Order::STATUS_HANDED_TO_COURIER, $order2->status);
    }
}
