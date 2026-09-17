<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\NepalLocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NepalLaijauTransformationTest extends TestCase
{
    /**
     * Test Nepal provinces, districts, and Kathmandu Valley detection.
     */
    public function test_nepal_location_service_provinces_and_valley_detection(): void
    {
        $this->assertCount(7, NepalLocationService::PROVINCES_WITH_DISTRICTS);

        // Valley districts
        $this->assertTrue(NepalLocationService::isKathmanduValley('Kathmandu'));
        $this->assertTrue(NepalLocationService::isKathmanduValley('Lalitpur'));
        $this->assertTrue(NepalLocationService::isKathmanduValley('Bhaktapur'));
        $this->assertTrue(NepalLocationService::isCodAvailable('Kathmandu'));

        // Non-Valley districts
        $this->assertFalse(NepalLocationService::isKathmanduValley('Kaski'));
        $this->assertFalse(NepalLocationService::isKathmanduValley('Morang'));
        $this->assertFalse(NepalLocationService::isKathmanduValley('Chitwan'));
        $this->assertFalse(NepalLocationService::isCodAvailable('Kaski'));
    }

    /**
     * Test dynamic shipping rate calculation.
     */
    public function test_nepal_shipping_rate_calculation(): void
    {
        // Inside valley
        $this->assertEquals(100.0, NepalLocationService::calculateShippingFee('Kathmandu', 500));
        $this->assertEquals(0.0, NepalLocationService::calculateShippingFee('Kathmandu', 2500));

        // Outside valley
        $this->assertEquals(150.0, NepalLocationService::calculateShippingFee('Kaski', 500));
        $this->assertEquals(0.0, NepalLocationService::calculateShippingFee('Kaski', 4000));
    }

    /**
     * Test checkout strictly rejects Cash on Delivery outside Kathmandu Valley.
     */
    public function test_checkout_rejects_cod_outside_kathmandu_valley(): void
    {
        $product = Product::firstOrCreate(
            ['sku' => 'TEST-DRS-001'],
            [
                'name' => 'Test Cotton Casual Dress',
                'slug' => 'test-cotton-casual-dress-' . time(),
                'price' => 4500,
                'is_active' => true,
                'is_published' => true,
                'quantity' => 10,
            ]
        );

        $payload = [
            'customer' => [
                'first_name' => 'Subash',
                'last_name' => 'Gurung',
                'phone' => '9846000000',
                'email' => 'subash@example.com',
                'province' => 'Gandaki Province',
                'district' => 'Kaski',
                'municipality' => 'Pokhara Metropolitan City',
                'ward' => '8',
                'tole' => 'Srijana Chowk',
            ],
            'payment_method' => 'cod', // Invalid for Pokhara / Kaski!
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ]
            ],
        ];

        $res = $this->postJson('/api/checkout', $payload);
        $res->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertStringContainsString('Kathmandu Valley', $res->json('message'));
    }

    /**
     * Test successful guest checkout with COD inside Kathmandu Valley.
     */
    public function test_guest_checkout_succeeds_with_cod_in_kathmandu(): void
    {
        $product = Product::firstOrCreate(
            ['sku' => 'TEST-JCK-001'],
            [
                'name' => 'Test Winter Fleece Jacket',
                'slug' => 'test-winter-fleece-jacket-' . time(),
                'price' => 3200,
                'is_active' => true,
                'is_published' => true,
                'quantity' => 10,
            ]
        );

        $payload = [
            'customer' => [
                'first_name' => 'Prashant',
                'last_name' => 'Khadka',
                'phone' => '9801234567',
                'province' => 'Bagmati Province',
                'district' => 'Kathmandu',
                'municipality' => 'Kathmandu Metropolitan City',
                'ward' => '28',
                'tole' => 'Putalisadak',
                'landmark' => 'Near Star Mall',
            ],
            'payment_method' => 'cod',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ]
            ],
        ];

        $res = $this->postJson('/api/checkout', $payload);
        $res->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $orderNumber = $res->json('order_number');
        $this->assertNotEmpty($orderNumber);

        $order = Order::where('order_number', $orderNumber)->first();
        $this->assertNotNull($order);
        $this->assertEquals('cod', $order->payment_method);
        $this->assertEquals('unpaid', $order->payment_status);
        $this->assertTrue((bool)$order->is_inside_valley);
        $this->assertNotEmpty($order->guest_access_token);
        $this->assertEquals('Putalisadak', $order->tole);
    }

    /**
     * Test successful prepaid checkout outside valley with transaction reference.
     */
    public function test_prepaid_checkout_outside_valley_with_esewa(): void
    {
        $this->withoutExceptionHandling();
        $product = Product::firstOrCreate(
            ['sku' => 'TEST-SHAWL-001'],
            [
                'name' => 'Test Pashmina Shawl',
                'slug' => 'test-pashmina-shawl-' . time(),
                'price' => 5000,
                'is_active' => true,
                'is_published' => true,
                'quantity' => 5,
            ]
        );

        $payload = [
            'customer' => [
                'first_name' => 'Bikash',
                'last_name' => 'Rana',
                'phone' => '9812345678',
                'province' => 'Lumbini Province',
                'district' => 'Rupandehi',
                'municipality' => 'Butwal Sub-Metropolitan City',
                'ward' => '4',
                'tole' => 'Traffic Chowk',
            ],
            'payment_method' => 'esewa',
            'payment_reference' => 'ESEWA-TX-987654',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ]
            ],
        ];

        $res = $this->postJson('/api/checkout', $payload);
        $res->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $orderNumber = $res->json('order_number');
        $order = Order::where('order_number', $orderNumber)->first();
        $this->assertNotNull($order);
        $this->assertEquals('esewa', $order->payment_method);
        $this->assertEquals('payment_verification_pending', $order->payment_status);
        $this->assertEquals('ESEWA-TX-987654', $order->payment_reference);
        $this->assertFalse((bool)$order->is_inside_valley);
    }

    /**
     * Test guest customer can look up and track order via /track-order.
     */
    public function test_guest_can_track_order_with_order_number_and_phone(): void
    {
        $order = Order::create([
            'order_number' => 'NA-' . strtoupper(\Illuminate\Support\Str::random(8)),
            'guest_access_token' => \Illuminate\Support\Str::random(32),
            'first_name' => 'Suman',
            'last_name' => 'Adhikari',
            'email' => 'suman@example.com',
            'phone' => '9851000000',
            'province' => 'Bagmati Province',
            'district' => 'Kathmandu',
            'municipality' => 'Kathmandu',
            'ward' => '10',
            'tole' => 'Baneshwor',
            'is_inside_valley' => true,
            'shipping_address' => 'Baneshwor, Ward 10, Kathmandu',
            'shipping_country' => 'NP',
            'subtotal' => 4500,
            'shipping_fee' => 0,
            'total_amount' => 4500,
            'currency' => 'NPR',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'status' => 'ready_for_delivery',
        ]);

        $res = $this->get('/track-order?order_number=' . $order->order_number . '&phone=9851000000');
        $res->assertStatus(200)
            ->assertSee($order->order_number)
            ->assertSee('Baneshwor')
            ->assertSee('Ready for Delivery');
    }
}
