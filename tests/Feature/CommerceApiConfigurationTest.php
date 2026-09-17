<?php

namespace Tests\Feature;

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
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommerceApiConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected SettingsService $settingsService;
    protected CommerceService $commerceService;
    protected Warehouse $warehouse;
    protected User $customerUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settingsService = app(SettingsService::class);
        $this->commerceService = app(CommerceService::class);

        $this->seed(\Database\Seeders\ModuleSettingsSeeder::class);

        $this->warehouse = Warehouse::firstOrCreate(
            ['code' => 'WH-KTM-MAIN'],
            ['name' => 'Kathmandu Central Warehouse', 'is_default' => true, 'is_active' => true]
        );

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

        $this->customerUser = User::factory()->create([
            'email' => 'customer@laijau.com',
            'role' => 'customer',
        ]);
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->settingsService)) {
                $this->settingsService->clearCache('commerce');
            }
        } catch (\Throwable $e) {
            // ignore
        }
        parent::tearDown();
    }

    private function createProductWithStock(string $name, int $stock = 10, float $priceNpr = 600.00): Product
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
     * Test API checkout guest checkout policy enforcement
     */
    public function test_api_checkout_rejects_guest_when_disabled(): void
    {
        $product = $this->createProductWithStock('Luxury Leather Boots', 5, 800.00);

        // 1. Disable guest checkout
        $this->settingsService->set('commerce', 'allow_guest_checkout', false);

        $response = $this->postJson('/api/checkout', [
            'customer' => [
                'first_name' => 'Unauthenticated',
                'last_name' => 'Guest',
                'email' => 'guest@example.com',
                'phone' => '9841234567',
                'province' => 'Bagmati Province',
                'district' => 'Kathmandu',
                'municipality' => 'Kathmandu Metropolitan City',
                'ward' => '03',
                'tole' => 'Lazimpat',
            ],
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1]
            ],
            'payment_method' => 'cod',
            'currency' => 'NPR',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
        $this->assertStringContainsString('Guest checkout is disabled', $response->json('message'));

        // 2. Enable guest checkout
        $this->settingsService->set('commerce', 'allow_guest_checkout', true);

        $response2 = $this->withHeader('X-Test-Mock', 'true')
            ->postJson('/api/checkout', [
                'customer' => [
                    'first_name' => 'Unauthenticated',
                    'last_name' => 'Guest',
                    'email' => 'guest@example.com',
                    'phone' => '9841234567',
                    'province' => 'Bagmati Province',
                    'district' => 'Kathmandu',
                    'municipality' => 'Kathmandu Metropolitan City',
                    'ward' => '03',
                    'tole' => 'Lazimpat',
                ],
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1]
                ],
                'payment_method' => 'cod',
                'currency' => 'NPR',
            ]);

        $this->assertTrue(in_array($response2->status(), [200, 201]));
        $response2->assertJson(['success' => true]);
    }

    /**
     * Test API checkout minimum order value enforcement
     */
    public function test_api_checkout_enforces_minimum_order_value(): void
    {
        $product = $this->createProductWithStock('Silk Dupatta', 10, 200.00);

        $this->settingsService->set('commerce', 'allow_guest_checkout', true);
        $this->settingsService->set('commerce', 'minimum_order_value_npr', 500.00);

        // Subtotal = 200 npr < 500 npr minimum
        $response = $this->postJson('/api/checkout', [
            'customer' => [
                'first_name' => 'Budget',
                'last_name' => 'Shopper',
                'email' => 'shopper@example.com',
                'phone' => '9841234567',
                'province' => 'Bagmati Province',
                'district' => 'Kathmandu',
                'municipality' => 'Kathmandu Metropolitan City',
                'ward' => '03',
                'tole' => 'Lazimpat',
            ],
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1]
            ],
            'payment_method' => 'cod',
            'currency' => 'NPR',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Minimum order subtotal of Rs. 500.00 is required', $response->json('message'));
    }

    /**
     * Test API order cancellation route honoring window
     */
    public function test_api_order_cancellation_honors_configured_window(): void
    {
        $product = $this->createProductWithStock('Pashmina Stole', 5, 500.00);

        $order = Order::create([
            'order_number' => $this->commerceService->generateOrderNumber(),
            'user_id' => $this->customerUser->id,
            'first_name' => 'Customer',
            'last_name' => 'User',
            'email' => $this->customerUser->email,
            'shipping_address' => 'Bhotahiti 2',
            'shipping_country' => 'NP',
            'shipping_city' => 'Kathmandu',
            'shipping_fee' => 100.00,
            'subtotal' => 500.00,
            'total_amount' => 600.00,
            'currency' => 'NPR',
            'status' => Order::STATUS_PROCESSING,
            'payment_status' => 'paid',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'quantity' => 1,
            'unit_price' => 500.00,
            'line_total' => 500.00,
        ]);

        // 45 minutes elapsed
        $order->created_at = now()->subMinutes(45);
        $order->saveQuietly();

        // 1. With 30 minute window, customer cancellation fails
        $this->settingsService->set('commerce', 'order_cancellation_window_minutes', 30);

        $failResponse = $this->actingAs($this->customerUser)
            ->postJson("/api/orders/{$order->order_number}/cancel", [
                'reason' => 'Changed my mind',
            ]);

        $failResponse->assertStatus(422);
        $this->assertStringContainsString('cancellation window of 30 minutes has expired', $failResponse->json('message'));

        // 2. Expand window to 60 minutes, customer cancellation succeeds
        $this->settingsService->set('commerce', 'order_cancellation_window_minutes', 60);

        $okResponse = $this->actingAs($this->customerUser)
            ->postJson("/api/orders/{$order->order_number}/cancel", [
                'reason' => 'Changed my mind',
            ]);

        $okResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'order' => [
                    'order_number' => $order->order_number,
                    'status' => Order::STATUS_CANCELLED,
                ],
            ]);
    }

    /**
     * Test API order return route honoring window and restocking fee
     */
    public function test_api_order_return_honors_window_and_restocking_fee(): void
    {
        $product = $this->createProductWithStock('Chanderi Silk Scarf', 5, 1000.00);

        $order = Order::create([
            'order_number' => $this->commerceService->generateOrderNumber(),
            'user_id' => $this->customerUser->id,
            'first_name' => 'Customer',
            'last_name' => 'User',
            'email' => $this->customerUser->email,
            'shipping_address' => 'Lazimpat 15',
            'shipping_country' => 'NP',
            'shipping_city' => 'Kathmandu',
            'shipping_fee' => 100.00,
            'subtotal' => 1000.00,
            'total_amount' => 1100.00,
            'currency' => 'NPR',
            'status' => Order::STATUS_DELIVERED,
            'payment_status' => 'paid',
            'delivered_at' => now()->subDays(20), // 20 days ago
        ]);

        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'quantity' => 1,
            'unit_price' => 1000.00,
            'line_total' => 1000.00,
        ]);

        // 1. With 14 day return window, return is rejected
        $this->settingsService->set('commerce', 'return_window_days', 14);

        $failRes = $this->actingAs($this->customerUser)
            ->postJson("/api/orders/{$order->order_number}/return", [
                'items' => [
                    ['order_item_id' => $item->id, 'quantity' => 1]
                ],
                'reason' => 'Too large',
            ]);

        $failRes->assertStatus(422);
        $this->assertStringContainsString('return window of 14 days has elapsed', $failRes->json('message'));

        // 2. With 30 day return window and 15% restocking fee
        $this->settingsService->set('commerce', 'return_window_days', 30);
        $this->settingsService->set('commerce', 'restocking_fee_percentage', 15.00);
        $this->settingsService->set('shipping', 'return_shipping_covered_by', 'store');

        $okRes = $this->actingAs($this->customerUser)
            ->postJson("/api/orders/{$order->order_number}/return", [
                'items' => [
                    ['order_item_id' => $item->id, 'quantity' => 1]
                ],
                'reason' => 'Too large',
            ]);

        $okRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'order' => [
                    'order_number' => $order->order_number,
                    'status' => Order::STATUS_REFUNDED,
                    'refunded_amount' => 850.00, // 1000 - 15% = 850
                    'restocking_fee' => 150.00,
                ],
            ]);
    }

    /**
     * Test storefront catalog hides depleted stock when out_of_stock_behavior is hide
     */
    public function test_storefront_catalog_out_of_stock_behavior_hide(): void
    {
        $inStockProduct = $this->createProductWithStock('In Stock Apparel', 5, 500.00);
        $depletedProduct = $this->createProductWithStock('Depleted Apparel', 0, 500.00);

        // 1. Default show_unavailable: both products in catalog
        $this->settingsService->set('commerce', 'out_of_stock_behavior', 'show_unavailable');
        $res1 = $this->getJson('/api/products');
        $res1->assertStatus(200);
        $slugs1 = collect($res1->json('data'))->pluck('slug');
        $this->assertTrue($slugs1->contains($inStockProduct->slug));
        $this->assertTrue($slugs1->contains($depletedProduct->slug));

        // 2. Hide out-of-stock items from catalog
        $this->settingsService->set('commerce', 'out_of_stock_behavior', 'hide');
        $res2 = $this->getJson('/api/products');
        $res2->assertStatus(200);
        $slugs2 = collect($res2->json('data'))->pluck('slug');
        $this->assertTrue($slugs2->contains($inStockProduct->slug));
        $this->assertFalse($slugs2->contains($depletedProduct->slug));
    }
}
