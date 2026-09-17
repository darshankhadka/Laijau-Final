<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Services\TaxCalculatorService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CheckoutCalculationComprehensiveTest extends TestCase
{
    use DatabaseTransactions;

    protected Category $category;
    protected Product $productStandard;
    protected Product $productHighValue;

    protected function setUp(): void
    {
        parent::setUp();

        // Enforce Nepal Statutory Settings
        Setting::set('vat_enabled', '1');
        Setting::set('default_vat_rate', '13.0');
        Setting::set('display_prices_with_vat', '1');
        Setting::set('default_currency', 'NPR');

        $this->category = Category::create([
            'name' => 'Apparel & Clothing',
            'slug' => 'apparel-clothing-' . time(),
            'is_active' => true,
        ]);

        $this->productStandard = Product::create([
            'name' => 'Classic Cotton Oxford Shirt',
            'slug' => 'classic-cotton-oxford-shirt-' . time(),
            'sku' => 'LAI-SHT-' . rand(1000, 9999),
            'category_id' => $this->category->id,
            'price' => 1500.00,
            'quantity' => 20,
            'is_active' => true,
            'is_published' => true,
        ]);

        $this->productHighValue = Product::create([
            'name' => 'Premium Leather Winter Jacket',
            'slug' => 'premium-leather-winter-jacket-' . time(),
            'sku' => 'LAI-JKT-' . rand(1000, 9999),
            'category_id' => $this->category->id,
            'price' => 8500.00,
            'quantity' => 10,
            'is_active' => true,
            'is_published' => true,
        ]);
    }

    /**
     * 1. Test standard cart validation for Kathmandu Valley destination.
     * Product = NPR 1,500, Subtotal = NPR 1,500, Valley Delivery (<2000) = NPR 100, Total = NPR 1,600.
     */
    public function test_nepal_product_cart_validation_returns_authoritative_npr_totals(): void
    {
        $payload = [
            'items' => [
                [
                    'product_id' => $this->productStandard->id,
                    'quantity' => 1,
                ]
            ],
            'shipping_country' => 'NP',
            'district' => 'Kathmandu',
            'currency' => 'NPR',
        ];

        $response = $this->postJson('/api/cart/validate', $payload);

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertTrue($data['success']);
        $this->assertEquals(1500.00, $data['subtotal']);
        $this->assertEquals(100.00, $data['shipping_fee']);
        $this->assertEquals(1600.00, $data['total']);
        $this->assertTrue($data['is_inside_valley']);
        $this->assertTrue($data['cod_available']);
        $this->assertEquals('NPR', $data['currency']);
    }

    /**
     * 2. Test high value order receives complimentary delivery (>= threshold NPR 2,000 in Valley).
     */
    public function test_high_value_product_receives_free_shipping_in_nepal(): void
    {
        $payload = [
            'items' => [
                [
                    'product_id' => $this->productHighValue->id,
                    'quantity' => 1,
                ]
            ],
            'shipping_country' => 'NP',
            'district' => 'Kathmandu',
            'currency' => 'NPR',
        ];

        $response = $this->postJson('/api/cart/validate', $payload);

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertEquals(8500.00, $data['subtotal']);
        $this->assertEquals(0.00, $data['shipping_fee']);
        $this->assertEquals(8500.00, $data['total']);
    }

    /**
     * 3. Test Outside Valley destination (e.g. Kaski / Pokhara):
     * Shipping = NPR 150.00, COD strictly NOT available.
     */
    public function test_outside_valley_shipping_fee_and_cod_restriction(): void
    {
        $payload = [
            'items' => [
                [
                    'product_id' => $this->productStandard->id,
                    'quantity' => 1,
                ]
            ],
            'shipping_country' => 'NP',
            'district' => 'Kaski',
            'currency' => 'NPR',
        ];

        $response = $this->postJson('/api/cart/validate', $payload);

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertEquals(1500.00, $data['subtotal']);
        $this->assertEquals(150.00, $data['shipping_fee']);
        $this->assertEquals(1650.00, $data['total']);
        $this->assertFalse($data['is_inside_valley']);
        $this->assertFalse($data['cod_available']);
        
        $methodIds = collect($data['eligible_payment_methods'])->pluck('id')->all();
        $this->assertContains('connectips', $methodIds);
        $this->assertContains('esewa', $methodIds);
        $this->assertNotContains('cod', $methodIds);
    }

    /**
     * 4. Test coupon discount integration.
     */
    public function test_coupon_discount_calculation(): void
    {
        $coupon = Coupon::create([
            'code' => 'LAIJAU10',
            'type' => 'percentage',
            'value' => 10.0,
            'is_active' => true,
        ]);

        $payload = [
            'items' => [
                [
                    'product_id' => $this->productStandard->id,
                    'quantity' => 1,
                ]
            ],
            'shipping_country' => 'NP',
            'district' => 'Kathmandu',
            'currency' => 'NPR',
            'coupon_code' => 'LAIJAU10',
        ];

        $response = $this->postJson('/api/cart/validate', $payload);

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertEquals(1500.00, $data['subtotal']);
        // 10% of 1500 = 150 NPR discount
        $this->assertEquals(150.00, $data['discount']);
        $this->assertEquals(1350.00, $data['discounted_subtotal']);
        // Shipping 100 -> Total = 1350 + 100 = 1450 NPR
        $this->assertEquals(1450.00, $data['total']);
    }

    /**
     * 5. Test complete checkout order creation with Nepal address hierarchy and COD inside valley.
     */
    public function test_checkout_order_creation_persists_correct_totals(): void
    {
        $checkoutPayload = [
            'customer' => [
                'first_name' => 'Aarav',
                'last_name' => 'Sharma',
                'email' => 'aarav.sharma@example.com',
                'phone' => '9841234567',
                'province' => 'Bagmati Province',
                'district' => 'Kathmandu',
                'municipality' => 'Kathmandu Metropolitan City',
                'ward' => '03',
                'tole' => 'Lazimpat',
                'landmark' => 'Near Embassy of France',
                'notes' => 'Call before arrival',
            ],
            'items' => [
                [
                    'product_id' => $this->productStandard->id,
                    'quantity' => 1,
                ]
            ],
            'payment_method' => 'cod',
            'currency' => 'NPR',
        ];

        $response = $this->postJson('/api/checkout', $checkoutPayload);

        $response->assertStatus(200);
        $orderNumber = $response->json('order_number');
        $this->assertNotEmpty($orderNumber);

        $order = Order::where('order_number', $orderNumber)->first();
        $this->assertNotNull($order);
        $this->assertEquals('Aarav', $order->first_name);
        $this->assertEquals('Sharma', $order->last_name);
        $this->assertEquals('9841234567', $order->phone);
        $this->assertEquals('Kathmandu', $order->district);
        $this->assertEquals(1, $order->is_inside_valley);
        $this->assertEquals('cod', $order->payment_method);
        $this->assertEquals('NPR', $order->currency);
        $this->assertEquals(1500.00, (float)$order->subtotal);
        $this->assertEquals(100.00, (float)$order->shipping_fee);
        $this->assertEquals(1600.00, (float)$order->total_amount);
        $this->assertEquals('unpaid', $order->payment_status);
        $this->assertEquals('pending', $order->status);
    }
}
