<?php

namespace Tests\Feature;

use App\Models\Accounting\Account;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\JournalEntry;
use App\Models\Coupon;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\Warehouse;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\CommerceService;
use App\Services\Inventory\InventoryService;
use App\Services\Settings\ConfigurationImpactService;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommerceConfigurationTest extends TestCase
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

        // Seed settings
        $this->artisan('db:seed', ['--class' => 'ModuleSettingsSeeder']);

        // Default warehouse
        $this->warehouse = $this->inventoryService->getDefaultWarehouse();

        // Shipping method
        ShippingMethod::firstOrCreate(
            ['code' => 'inside_valley_standard'],
            [
                'name' => 'Kathmandu Valley Standard Delivery',
                'zone' => 'kathmandu_valley',
                'price_npr' => 100.00,
                'free_shipping_threshold_npr' => 3000.00,
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
            'sku' => 'TEST-' . strtoupper(substr(md5(uniqid()), 0, 6)),
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
     * Dynamic Test 1: Cancellation Window (60m -> 30m -> 60m)
     */
    public function test_cancellation_window_dynamically_enforced(): void
    {
        // 1. Set window to 60 minutes
        $this->settingsService->set('commerce', 'order_cancellation_window_minutes', 60);

        $product = $this->createProductWithStock('Handwoven Pashmina Shawl', 5, 1200.00);

        $order = Order::create([
            'order_number' => $this->commerceService->generateOrderNumber(),
            'first_name' => 'Aarav',
            'last_name' => 'Sharma',
            'email' => 'aarav@example.com',
            'shipping_address' => 'Bhotahiti 12',
            'shipping_country' => 'NP',
            'shipping_city' => 'Kathmandu',
            'shipping_fee' => 100.00,
            'subtotal' => 1200.00,
            'total_amount' => 1300.00,
            'currency' => 'NPR',
            'status' => Order::STATUS_PROCESSING,
            'payment_status' => 'paid',
        ]);
        $order->created_at = now()->subMinutes(45);
        $order->saveQuietly();

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'quantity' => 2,
            'unit_price' => 600.00,
            'line_total' => 1200.00,
        ]);

        // At 60m window, 45m old order CAN be cancelled by customer
        $check1 = $this->commerceService->canCancelOrder($order, true);
        $this->assertTrue($check1['can_cancel']);

        // 2. Reduce window dynamically to 30 minutes
        $this->settingsService->set('commerce', 'order_cancellation_window_minutes', 30);

        $check2 = $this->commerceService->canCancelOrder($order, true);
        $this->assertFalse($check2['can_cancel']);
        $this->assertStringContainsString('cancellation window of 30 minutes has expired', $check2['reason']);

        // 3. Re-expand window back to 60 minutes
        $this->settingsService->set('commerce', 'order_cancellation_window_minutes', 60);

        $check3 = $this->commerceService->canCancelOrder($order, true);
        $this->assertTrue($check3['can_cancel']);

        // Execute cancellation and verify status mutation and timestamp
        $cancelled = $this->commerceService->cancelOrder($order, 'Customer changed mind', null, true);
        $this->assertEquals(Order::STATUS_CANCELLED, $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertEquals('Customer changed mind', $cancelled->cancellation_reason);
    }

    /**
     * Dynamic Test 2: Backorders (false -> true -> false)
     */
    public function test_backorders_dynamically_enforced(): void
    {
        $product = $this->createProductWithStock('Zero Stock Leather Boots', 0, 800.00);

        // 1. Backorders disabled
        $this->settingsService->set('commerce', 'allow_backorders', false);
        $check1 = $this->commerceService->assertPurchasable($product, null, 1);
        $this->assertFalse($check1['purchasable']);
        $this->assertStringContainsString('out of stock', $check1['message']);

        // 2. Dynamically enable backorders
        $this->settingsService->set('commerce', 'allow_backorders', true);
        $check2 = $this->commerceService->assertPurchasable($product, null, 1);
        $this->assertTrue($check2['purchasable']);
        $this->assertTrue($check2['is_backorder']);

        // 3. Dynamically disable backorders again
        $this->settingsService->set('commerce', 'allow_backorders', false);
        $check3 = $this->commerceService->assertPurchasable($product, null, 1);
        $this->assertFalse($check3['purchasable']);
    }

    /**
     * Dynamic Test 3: Discount Limit (20% -> 10% -> 20%)
     */
    public function test_discount_ceiling_dynamically_enforced(): void
    {
        $coupon = Coupon::create([
            'code' => 'VIP25',
            'type' => 'percentage',
            'value' => 25.0, // 25% discount
            'is_active' => true,
        ]);

        $subtotal = 1000.00;

        // 1. Max discount ceiling 20%
        $this->settingsService->set('commerce', 'max_discount_percentage', 20.00);
        $res1 = $this->commerceService->applyCouponDiscount('VIP25', $subtotal, 'npr');
        $this->assertEquals(250.00, $res1['nominal_discount']);
        $this->assertEquals(200.00, $res1['discount_amount']); // Capped to 20% of 1000 = 200
        $this->assertTrue($res1['is_capped']);

        // 2. Reduce ceiling to 10%
        $this->settingsService->set('commerce', 'max_discount_percentage', 10.00);
        $res2 = $this->commerceService->applyCouponDiscount('VIP25', $subtotal, 'npr');
        $this->assertEquals(250.00, $res2['nominal_discount']);
        $this->assertEquals(100.00, $res2['discount_amount']); // Capped to 10% of 1000 = 100
        $this->assertTrue($res2['is_capped']);

        // 3. Re-expand ceiling back to 20%
        $this->settingsService->set('commerce', 'max_discount_percentage', 20.00);
        $res3 = $this->commerceService->applyCouponDiscount('VIP25', $subtotal, 'npr');
        $this->assertEquals(200.00, $res3['discount_amount']);
    }

    /**
     * Dynamic Test 4: Stock Visibility (show_exact -> show_availability -> hide)
     */
    public function test_stock_visibility_dynamically_enforced(): void
    {
        $product = $this->createProductWithStock('Artisan Wool Scarf', 7, 450.00);

        // 1. Exact count mode
        $this->settingsService->set('commerce', 'stock_visibility', 'show_exact');
        $vis1 = $this->commerceService->getStockVisibility($product);
        $this->assertEquals('exact', $vis1['visibility']);
        $this->assertEquals(7, $vis1['stock_level']);
        $this->assertEquals('7 in stock', $vis1['display_text']);

        // 2. Availability only mode (hide exact count from competitors)
        $this->settingsService->set('commerce', 'stock_visibility', 'show_availability');
        $vis2 = $this->commerceService->getStockVisibility($product);
        $this->assertEquals('availability', $vis2['visibility']);
        $this->assertNull($vis2['stock_level']);
        $this->assertEquals('In Stock', $vis2['display_text']);

        // 3. Completely hidden mode
        $this->settingsService->set('commerce', 'stock_visibility', 'hide');
        $vis3 = $this->commerceService->getStockVisibility($product);
        $this->assertEquals('hidden', $vis3['visibility']);
        $this->assertNull($vis3['stock_level']);
        $this->assertNull($vis3['display_text']);
    }

    /**
     * Dynamic Test 5: Return Window (14 days -> 30 days -> 14 days)
     */
    public function test_return_window_dynamically_enforced(): void
    {
        $product = $this->createProductWithStock('Festive Polo Shirt', 5, 750.00);

        $order = Order::create([
            'order_number' => $this->commerceService->generateOrderNumber(),
            'first_name' => 'Maya',
            'last_name' => 'Poudel',
            'email' => 'maya@example.com',
            'shipping_address' => 'Jhamsikhel 44',
            'shipping_country' => 'NP',
            'shipping_city' => 'Lalitpur',
            'shipping_fee' => 100.00,
            'subtotal' => 750.00,
            'total_amount' => 850.00,
            'currency' => 'NPR',
            'status' => Order::STATUS_DELIVERED,
            'payment_status' => 'paid',
            'delivered_at' => now()->subDays(20), // Delivered 20 days ago
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'quantity' => 1,
            'unit_price' => 750.00,
            'line_total' => 750.00,
        ]);

        // 1. Return window 14 days -> ineligible (20 days > 14)
        $this->settingsService->set('commerce', 'return_window_days', 14);
        $check1 = $this->commerceService->canReturnOrder($order);
        $this->assertFalse($check1['can_return']);
        $this->assertStringContainsString('return window of 14 days has elapsed', $check1['reason']);

        // 2. Extend window dynamically to 30 days -> eligible (20 days <= 30)
        $this->settingsService->set('commerce', 'return_window_days', 30);
        $check2 = $this->commerceService->canReturnOrder($order);
        $this->assertTrue($check2['can_return']);

        // 3. Revert window back to 14 days -> ineligible again
        $this->settingsService->set('commerce', 'return_window_days', 14);
        $check3 = $this->commerceService->canReturnOrder($order);
        $this->assertFalse($check3['can_return']);
    }

    /**
     * Test Guest Checkout Policy
     */
    public function test_guest_checkout_policy_enforced(): void
    {
        $customer = [
            'first_name' => 'Guest',
            'last_name' => 'User',
            'email' => 'guest@example.com',
            'address' => 'Baneshwor 1',
            'country' => 'NP',
        ];

        // 1. Disable guest checkout
        $this->settingsService->set('commerce', 'allow_guest_checkout', false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Guest checkout is disabled');
        $this->commerceService->validateCheckoutPolicies($customer, 500.00, 'NPR', null);
    }

    /**
     * Test Minimum Order Value Policy
     */
    public function test_minimum_order_value_policy_enforced(): void
    {
        $customer = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'address' => 'Lazimpat 4',
            'country' => 'NP',
        ];

        $this->settingsService->set('commerce', 'allow_guest_checkout', true);
        $this->settingsService->set('commerce', 'minimum_order_value_npr', 500.00);

        // Below minimum
        try {
            $this->commerceService->validateCheckoutPolicies($customer, 350.00, 'npr', null);
            $this->fail('Expected minimum order value exception was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Minimum order subtotal of Rs. 500.00 is required', $e->getMessage());
        }

        // Above minimum
        $this->commerceService->validateCheckoutPolicies($customer, 550.00, 'npr', null);
        $this->assertTrue(true);
    }

    /**
     * Full Lifecycle Trace: Browse -> Cart -> Reservation -> Checkout -> Payment -> Fulfillment -> Shipping -> Delivery -> Return / Restock
     */
    public function test_complete_commerce_customer_lifecycle_trace(): void
    {
        // Settings configuration
        $this->settingsService->set('commerce', 'stock_visibility', 'show_exact');
        $this->settingsService->set('commerce', 'reservation_ttl_minutes', 20);
        $this->settingsService->set('commerce', 'order_prefix', 'NA-');
        $this->settingsService->set('commerce', 'return_window_days', 30);
        $this->settingsService->set('commerce', 'restocking_fee_percentage', 10.00);
        $this->settingsService->set('commerce', 'auto_restock_on_refund', true);
        $this->settingsService->set('shipping', 'return_shipping_covered_by', 'store');

        // Stage 1: Browse
        $initialStock = 10;
        $product = $this->createProductWithStock('Handspun Silk Shawl', $initialStock, 1500.00);
        $browseVisibility = $this->commerceService->getStockVisibility($product);
        $this->assertEquals('exact', $browseVisibility['visibility']);
        $this->assertEquals(10, $browseVisibility['stock_level']);

        // Stage 2: Reservation
        $cartToken = 'cart_' . uniqid();
        $reservations = $this->commerceService->reserveCheckoutStock($cartToken, [
            ['product_id' => $product->id, 'quantity' => 2]
        ]);
        $this->assertCount(1, $reservations);
        $this->assertEquals(2, $reservations[0]->quantity);

        // Stage 3: Checkout & Order Creation
        $orderNumber = $this->commerceService->generateOrderNumber();
        $this->assertTrue(str_starts_with($orderNumber, 'LJ-') || str_starts_with($orderNumber, 'NA-'));

        $order = Order::create([
            'order_number' => $orderNumber,
            'first_name' => 'Lars',
            'last_name' => 'Mikkelsen',
            'email' => 'lars@example.com',
            'shipping_address' => 'Bhotahiti 1',
            'shipping_country' => 'NP',
            'shipping_city' => 'Kathmandu',
            'shipping_fee' => 100.00,
            'subtotal' => 3000.00,
            'vat_rate' => 13.00,
            'vat_amount' => 390.00,
            'total_amount' => 3100.00,
            'currency' => 'NPR',
            'status' => Order::STATUS_PENDING_PAYMENT,
            'payment_status' => 'unpaid',
        ]);

        $orderItem = $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'quantity' => 2,
            'unit_price' => 1500.00,
            'line_total' => 3000.00,
        ]);

        // Stage 4: Payment & Stock Fulfillment
        $order->update([
            'status' => Order::STATUS_PROCESSING,
            'payment_status' => 'paid',
            'payment_id' => 'ch_mock_payment_123',
        ]);

        $this->inventoryService->fulfillOrderStock($order);

        $stockAfterFulfillment = StockLevel::where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $product->id)
            ->first();
        $this->assertEquals($initialStock - 2, $stockAfterFulfillment->quantity_on_hand); // 10 - 2 = 8

        // Stage 5: Shipping
        $this->commerceService->shipOrder($order, 'Nepal Can Move', 'NCM987654321');
        $this->assertEquals(Order::STATUS_SHIPPED, $order->status);
        $this->assertEquals('Nepal Can Move', $order->carrier);
        $this->assertEquals('NCM987654321', $order->tracking_number);

        // Stage 6: Delivery
        $this->commerceService->markDelivered($order);
        $this->assertEquals(Order::STATUS_DELIVERED, $order->status);
        $this->assertNotNull($order->delivered_at);

        // Stage 7: Return with Restocking Fee & Inventory Restock
        $returnOrder = $this->commerceService->processOrderReturn(
            $order,
            [['order_item_id' => $orderItem->id, 'quantity' => 1, 'disposition' => 'sellable']],
            'Color did not match expectations'
        );

        $this->assertEquals(Order::STATUS_REFUNDED, $returnOrder->status);
        // Gross refund = 1500, Restocking fee 10% = 150, Net refund = 1350
        $this->assertEquals(150.00, (float)$returnOrder->restocking_fee);
        $this->assertEquals(1350.00, (float)$returnOrder->refunded_amount);
        $this->assertNotNull($returnOrder->refunded_at);

        // Verify stock restored from 8 back to 9
        $stockAfterReturn = StockLevel::where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $product->id)
            ->first();
        $this->assertEquals(9, $stockAfterReturn->quantity_on_hand);
    }

    /**
     * Test Configuration Impact Evaluations for Commerce Settings
     */
    public function test_configuration_impact_evaluations_for_commerce(): void
    {
        // 1. Guest checkout impact
        $impact1 = $this->impactService->evaluate('commerce', 'allow_guest_checkout', false);
        $this->assertEquals('medium', $impact1['severity']);
        $this->assertStringContainsString('Guest checkout disabled', $impact1['impact_text']);

        // 2. Stock visibility impact
        $impact2 = $this->impactService->evaluate('commerce', 'stock_visibility', 'hide');
        $this->assertEquals('medium', $impact2['severity']);
        $this->assertStringContainsString('hidden', $impact2['impact_text']);

        // 3. Cancellation window impact
        $impact3 = $this->impactService->evaluate('commerce', 'order_cancellation_window_minutes', 15);
        $this->assertEquals('medium', $impact3['severity']);
        $this->assertStringContainsString('cancellation window', $impact3['impact_text']);

        // 4. Return window impact
        $impact4 = $this->impactService->evaluate('commerce', 'return_window_days', 7);
        $this->assertEquals('medium', $impact4['severity']);
        $this->assertStringContainsString('return request window', $impact4['impact_text']);

        // 5. Auto restock impact
        $impact5 = $this->impactService->evaluate('commerce', 'auto_restock_on_refund', false);
        $this->assertEquals('high', $impact5['severity']);
        $this->assertStringContainsString('restocking upon refund disabled', $impact5['impact_text']);
    }
}
