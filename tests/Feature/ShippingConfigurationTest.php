<?php

namespace Tests\Feature;

use App\Models\Inventory\StockLevel;
use App\Models\Inventory\Warehouse;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\CommerceService;
use App\Services\Inventory\InventoryService;
use App\Services\Settings\ConfigurationImpactService;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ShippingConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected SettingsService $settingsService;
    protected CommerceService $commerceService;
    protected InventoryService $inventoryService;
    protected ConfigurationImpactService $impactService;
    protected Warehouse $warehouse;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settingsService = app(SettingsService::class);
        $this->commerceService = app(CommerceService::class);
        $this->inventoryService = app(InventoryService::class);
        $this->impactService = app(ConfigurationImpactService::class);

        $this->artisan('db:seed', ['--class' => 'ModuleSettingsSeeder']);

        Cache::forget('active_shipping_methods');

        $this->warehouse = $this->inventoryService->getDefaultWarehouse();

        ShippingMethod::updateOrCreate(
            ['code' => 'inside_valley_standard'],
            [
                'name' => 'Kathmandu Valley Standard Delivery',
                'carrier' => 'Pathao / Local Rider',
                'zone' => 'kathmandu_valley',
                'price_npr' => 100.00,
                'free_shipping_threshold_npr' => 3000.00,
                'is_active' => true,
            ]
        );

        ShippingMethod::updateOrCreate(
            ['code' => 'outside_valley_courier'],
            [
                'name' => 'Nationwide Courier (Outside Valley)',
                'carrier' => 'Nepal Can Move (NCM)',
                'zone' => 'outside_valley',
                'price_npr' => 150.00,
                'free_shipping_threshold_npr' => 4000.00,
                'is_active' => true,
            ]
        );

        $this->adminUser = User::firstOrCreate(
            ['email' => 'admin@laijau.com'],
            ['name' => 'Admin User', 'role' => 'admin', 'password' => bcrypt('password')]
        );
    }

    private function createProductWithStock(string $name, int $stock = 10, float $priceNpr = 500.00): Product
    {
        $product = Product::create([
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)) . '-' . uniqid(),
            'sku' => 'SHIP-' . strtoupper(substr(md5(uniqid()), 0, 6)),
            'price' => $priceNpr,
            'price_npr' => $priceNpr,
            'quantity' => $stock,
            'track_quantity' => true,
            'is_published' => true,
            'is_active' => true,
        ]);

        StockLevel::updateOrCreate(
            ['warehouse_id' => $this->warehouse->id, 'product_id' => $product->id, 'variant_id' => null],
            ['quantity_on_hand' => $stock, 'quantity_reserved' => 0]
        );

        return $product;
    }

    /**
     * Dynamic Test 1: Free Shipping Threshold for Valley Standard
     */
    public function test_free_shipping_threshold_dynamically_enforced(): void
    {
        $subtotal = 2500.00;

        // 1. High threshold: 3,000 NPR (2,500 NPR does not qualify)
        $rate1 = ShippingMethod::getRateForCountry('NP', $subtotal, 'NPR', 'inside_valley_standard', 'Kathmandu');
        $this->assertFalse($rate1['is_free']);
        $this->assertEquals(100.00, (float)$rate1['cost']);

        // 2. Subtotal exceeds threshold (3,500 NPR qualifies for free delivery)
        $rate2 = ShippingMethod::getRateForCountry('NP', 3500.00, 'NPR', 'inside_valley_standard', 'Kathmandu');
        $this->assertTrue($rate2['is_free']);
        $this->assertEquals(0.00, (float)$rate2['cost']);
    }

    /**
     * Dynamic Test 2: Outside Valley vs Inside Valley Delivery Methods
     */
    public function test_outside_valley_vs_inside_valley_delivery_methods(): void
    {
        // 1. Kathmandu Valley district
        $valleyMethods = ShippingMethod::getAvailableMethodsForCountry('NP', 2000.00, 'NPR', 'Kathmandu');
        $this->assertNotEmpty($valleyMethods);
        $this->assertEquals('inside_valley_standard', $valleyMethods[0]['code']);
        $this->assertTrue($valleyMethods[0]['is_cod_eligible']);

        // 2. Outside Valley district (e.g. Kaski / Pokhara)
        $outsideMethods = ShippingMethod::getAvailableMethodsForCountry('NP', 2000.00, 'NPR', 'Kaski');
        $this->assertNotEmpty($outsideMethods);
        $this->assertEquals('outside_valley_courier', $outsideMethods[0]['code']);
        $this->assertFalse($outsideMethods[0]['is_cod_eligible']);
    }

    /**
     * Dynamic Test 3: Free Shipping Master Toggle
     */
    public function test_free_shipping_master_toggle_dynamically_enforced(): void
    {
        $cartSubtotal = 5000.00; // Well exceeds threshold

        $rate1 = ShippingMethod::getRateForCountry('NP', $cartSubtotal, 'NPR', 'inside_valley_standard', 'Kathmandu');
        $this->assertTrue($rate1['is_free']);
        $this->assertEquals(0.00, (float)$rate1['cost']);
    }

    /**
     * Dynamic Test 4: Tracking Number Enforcement on Dispatch
     */
    public function test_tracking_number_enforcement_on_order_dispatch(): void
    {
        $order = Order::create([
            'order_number' => $this->commerceService->generateOrderNumber(),
            'first_name' => 'Aarav',
            'last_name' => 'Shrestha',
            'email' => 'aarav@example.com',
            'shipping_address' => 'Bhotahiti 10',
            'shipping_country' => 'NP',
            'shipping_city' => 'Kathmandu',
            'shipping_fee' => 100.00,
            'subtotal' => 3000.00,
            'total_amount' => 3100.00,
            'currency' => 'NPR',
            'status' => Order::STATUS_PROCESSING,
            'payment_status' => 'paid',
        ]);

        // 1. Strict tracking enforcement enabled -> empty tracking throws
        $this->settingsService->set('shipping', 'require_tracking_number_on_dispatch', true);

        try {
            $this->commerceService->shipOrder($order, 'Pathao', '');
            $this->fail('Expected tracking number exception was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('tracking number is required', $e->getMessage());
        }

        // 2. Tracking enforcement relaxed -> empty tracking allowed
        $this->settingsService->set('shipping', 'require_tracking_number_on_dispatch', false);

        $shippedOrder = $this->commerceService->shipOrder($order, 'Pathao', '');
        $this->assertEquals(Order::STATUS_SHIPPED, $shippedOrder->status);
        $this->assertEquals('Pathao', $shippedOrder->carrier);

        // 3. Ship with valid tracking number
        $shippedWithTracking = $this->commerceService->shipOrder($order, 'Pathao', 'PATHAO-NP-12345');
        $this->assertEquals('PATHAO-NP-12345', $shippedWithTracking->tracking_number);
    }

    /**
     * Dynamic Test 5: Return Shipping Fee Coverage Policy (customer vs store)
     */
    public function test_return_shipping_fee_coverage_policy_enforced(): void
    {
        $product = $this->createProductWithStock('Handspun Pashmina Shawl', 5, 3000.00);

        $this->settingsService->set('shipping', 'return_shipping_fee_npr', 150.00);
        $this->settingsService->set('commerce', 'restocking_fee_percentage', 0.00);
        $this->settingsService->set('commerce', 'return_window_days', 30);

        // Scenario A: Customer covers return shipping (fee deducted from refund)
        $this->settingsService->set('shipping', 'return_shipping_covered_by', 'customer');

        $orderA = Order::create([
            'order_number' => $this->commerceService->generateOrderNumber(),
            'first_name' => 'Prashant',
            'last_name' => 'Adhikari',
            'email' => 'prashant@example.com',
            'shipping_address' => 'Jhamsikhel, Lalitpur',
            'shipping_country' => 'NP',
            'shipping_city' => 'Lalitpur',
            'subtotal' => 3000.00,
            'total_amount' => 3100.00,
            'currency' => 'NPR',
            'status' => Order::STATUS_DELIVERED,
            'payment_status' => 'paid',
            'delivered_at' => now()->subDays(5),
        ]);

        $itemA = $orderA->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'quantity' => 1,
            'unit_price' => 3000.00,
            'line_total' => 3000.00,
        ]);

        $refundedA = $this->commerceService->processOrderReturn(
            $orderA,
            [['order_item_id' => $itemA->id, 'quantity' => 1, 'disposition' => 'sellable']],
            'Wrong size'
        );

        // 3000.00 gross - 150.00 return courier fee = 2850.00 net refund
        $this->assertEquals(2850.00, (float)$refundedA->refunded_amount);

        // Scenario B: Store covers return shipping (complimentary returns, 0 deduction)
        $this->settingsService->set('shipping', 'return_shipping_covered_by', 'store');

        $orderB = Order::create([
            'order_number' => $this->commerceService->generateOrderNumber(),
            'first_name' => 'Sunita',
            'last_name' => 'Thapa',
            'email' => 'sunita@example.com',
            'shipping_address' => 'New Baneshwor',
            'shipping_country' => 'NP',
            'shipping_city' => 'Kathmandu',
            'subtotal' => 3000.00,
            'total_amount' => 3100.00,
            'currency' => 'NPR',
            'status' => Order::STATUS_DELIVERED,
            'payment_status' => 'paid',
            'delivered_at' => now()->subDays(5),
        ]);

        $itemB = $orderB->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'quantity' => 1,
            'unit_price' => 3000.00,
            'line_total' => 3000.00,
        ]);

        $refundedB = $this->commerceService->processOrderReturn(
            $orderB,
            [['order_item_id' => $itemB->id, 'quantity' => 1, 'disposition' => 'sellable']],
            'Wrong color'
        );

        // 3000.00 gross - 0.00 return courier fee = 3000.00 net refund
        $this->assertEquals(3000.00, (float)$refundedB->refunded_amount);
    }

    /**
     * Test Failed Delivery Handling
     */
    public function test_failed_delivery_handling_returns_consignment_to_warehouse(): void
    {
        $product = $this->createProductWithStock('Handmade Dhaka Topi', 10, 800.00);

        $order = Order::create([
            'order_number' => $this->commerceService->generateOrderNumber(),
            'first_name' => 'Bikram',
            'last_name' => 'Gurung',
            'email' => 'bikram@example.com',
            'shipping_address' => 'Mahendrapool, Pokhara',
            'shipping_country' => 'NP',
            'shipping_city' => 'Pokhara',
            'subtotal' => 1600.00,
            'total_amount' => 1750.00,
            'currency' => 'NPR',
            'status' => Order::STATUS_PROCESSING,
            'payment_status' => 'paid',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'quantity' => 2,
            'unit_price' => 800.00,
            'line_total' => 1600.00,
        ]);

        // Fulfill stock: 10 - 2 = 8
        $this->inventoryService->fulfillOrderStock($order);
        $stockAfterFulfill = StockLevel::where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $product->id)
            ->first();
        $this->assertEquals(8, $stockAfterFulfill->quantity_on_hand);

        // Failed delivery with return_to_warehouse policy
        $this->settingsService->set('shipping', 'failed_delivery_action', 'return_to_warehouse');
        $cancelled = $this->commerceService->handleFailedDelivery($order, 'Courier returned: Recipient unreachable');

        $this->assertEquals(Order::STATUS_CANCELLED, $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertStringContainsString('Recipient unreachable', $cancelled->cancellation_reason);

        // Stock restored back from 8 to 10
        $stockAfterReturn = StockLevel::where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $product->id)
            ->first();
        $this->assertEquals(10, $stockAfterReturn->quantity_on_hand);
    }

    /**
     * Test Configuration Impact Evaluations for Shipping Settings
     */
    public function test_configuration_impact_evaluations_for_shipping(): void
    {
        // 1. Free shipping threshold impact
        $impact1 = $this->impactService->evaluate('shipping', 'free_shipping_threshold_npr', 4000.00);
        $this->assertEquals('high', $impact1['severity']);
        $this->assertStringContainsString('Free shipping qualification threshold updated', $impact1['impact_text']);

        // 2. Free shipping master toggle impact
        $impact2 = $this->impactService->evaluate('shipping', 'enable_free_shipping', false);
        $this->assertEquals('high', $impact2['severity']);
        $this->assertStringContainsString('Complimentary free shipping promotions disabled', $impact2['impact_text']);

        // 3. Require tracking impact
        $impact3 = $this->impactService->evaluate('shipping', 'require_tracking_number_on_dispatch', true);
        $this->assertEquals('medium', $impact3['severity']);
        $this->assertStringContainsString('Parcel tracking number is strictly enforced', $impact3['impact_text']);

        // 4. Return shipping covered by impact
        $impact4 = $this->impactService->evaluate('shipping', 'return_shipping_covered_by', 'store');
        $this->assertEquals('medium', $impact4['severity']);
        $this->assertStringContainsString('Store absorbs all return freight costs', $impact4['impact_text']);
    }
}
