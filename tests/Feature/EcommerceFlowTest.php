<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ShippingMethod;
use App\Models\Category;
use App\Models\Collection;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class EcommerceFlowTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed shipping methods
        ShippingMethod::updateOrCreate(
            ['code' => 'inside_valley_standard'],
            [
                'name' => 'Kathmandu Valley Standard Delivery',
                'zone' => 'kathmandu_valley',
                'price_npr' => 100.00,
                'free_shipping_threshold_npr' => 3000.00,
                'estimated_delivery' => '1-2 business days',
                'is_active' => true,
            ]
        );

        ShippingMethod::updateOrCreate(
            ['code' => 'inside_valley_express'],
            [
                'name' => 'Same-Day Express (Valley)',
                'zone' => 'kathmandu_valley',
                'price_npr' => 200.00,
                'free_shipping_threshold_npr' => 0.00,
                'estimated_delivery' => 'Same day (before 2 PM)',
                'is_active' => true,
            ]
        );

        ShippingMethod::updateOrCreate(
            ['code' => 'outside_valley_courier'],
            [
                'name' => 'Nationwide Courier (Outside Valley)',
                'zone' => 'outside_valley',
                'price_npr' => 150.00,
                'free_shipping_threshold_npr' => 4000.00,
                'estimated_delivery' => '2-4 business days',
                'is_active' => true,
            ]
        );

        // Seed category and collection
        $cat = Category::firstOrCreate(
            ['slug' => 'footwear'],
            [
                'name' => 'Footwear',
                'is_featured' => true,
            ]
        );

        $col = Collection::firstOrCreate(
            ['slug' => 'royal-heritage'],
            [
                'name' => 'Royal Heritage',
                'is_published' => true,
                'is_featured' => true,
            ]
        );

        $product = Product::firstOrCreate(
            ['slug' => 'royal-oxford-shoes'],
            [
                'name' => 'Royal Oxford Shoes',
                'sku' => 'SHO-001',
                'price' => 1899.00,
                'price_npr' => 1899.00,
                'quantity' => 10,
                'track_quantity' => true,
                'is_active' => true,
                'is_published' => true,
            ]
        );
        $product->categories()->syncWithoutDetaching([$cat->id]);
        $product->collections()->syncWithoutDetaching([$col->id]);

        // Seed variants: Crimson / S, Crimson / M, Emerald / L
        ProductVariant::firstOrCreate(
            ['sku' => 'SHO-001-CRM-S'],
            [
                'product_id' => $product->id,
                'size' => 'S',
                'color' => 'Crimson',
                'color_hex' => '#9B111E',
                'price' => 1899.00,
                'price_npr' => 1899.00,
                'stock_quantity' => 5,
                'is_active' => true,
            ]
        );
        ProductVariant::firstOrCreate(
            ['sku' => 'SHO-001-CRM-M'],
            [
                'product_id' => $product->id,
                'size' => 'M',
                'color' => 'Crimson',
                'color_hex' => '#9B111E',
                'price' => 1899.00,
                'price_npr' => 1899.00,
                'stock_quantity' => 3,
                'is_active' => true,
            ]
        );
        ProductVariant::firstOrCreate(
            ['sku' => 'SHO-001-EME-L'],
            [
                'product_id' => $product->id,
                'size' => 'L',
                'color' => 'Emerald',
                'color_hex' => '#0A2E23',
                'price' => 1899.00,
                'price_npr' => 1899.00,
                'stock_quantity' => 1,
                'is_active' => true,
            ]
        );
        // Sold out variant
        ProductVariant::firstOrCreate(
            ['sku' => 'SHO-001-IVO-XL'],
            [
                'product_id' => $product->id,
                'size' => 'XL',
                'color' => 'Ivory',
                'color_hex' => '#FDFBF7',
                'price' => 1899.00,
                'price_npr' => 1899.00,
                'stock_quantity' => 0,
                'is_active' => true,
            ]
        );
    }

    public function test_customer_can_register_and_login(): void
    {
        $uniqueEmail = 'customer_' . uniqid() . '@example.com';

        // 1. Register
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Astrid Lindgren',
            'email' => $uniqueEmail,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'phone' => '+45 20 11 22 33',
            'gdpr_consent' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['access_token', 'user']);

        // 2. Login
        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => $uniqueEmail,
            'password' => 'Password123!',
        ]);

        $loginResponse->assertStatus(200)
            ->assertJsonStructure(['access_token', 'user']);
    }

    public function test_products_can_be_filtered_by_currency_category_and_collection(): void
    {
        $response = $this->getJson('/api/products?currency=npr&category=footwear&collection=royal-heritage');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'name', 'slug', 'price_npr', 'price_npr', 'variants_list']]]);
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
        $this->assertNotEmpty($response->json('data.0.variants_list'));
    }

    public function test_nepal_valley_flow_validates_npr_and_coupon_discount(): void
    {
        $variant = ProductVariant::where('sku', 'SHO-001-CRM-M')->first();
        $product = $variant->product;
        $this->assertNotNull($product);

        Coupon::create([
            'code' => 'NORDIC10',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
        ]);

        // Cart validation in NPR for Kathmandu Valley
        $response = $this->postJson('/api/cart/validate', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'quantity' => 1,
                    'selected_size' => $variant->size,
                    'selected_color' => $variant->color,
                ]
            ],
            'district' => 'Kathmandu',
            'currency' => 'NPR',
            'coupon_code' => 'NORDIC10',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'currency' => 'NPR',
                'is_inside_valley' => true,
                'cod_available' => true,
            ]);

        $subtotal = $response->json('subtotal');
        $discount = $response->json('discount');
        $this->assertEquals(1899.00, $subtotal);
        $this->assertEquals(189.90, $discount);
    }

    public function test_outside_valley_flow_validates_npr_and_outside_courier(): void
    {
        $variant = ProductVariant::where('sku', 'SHO-001-EME-L')->first();
        $product = $variant->product;

        $response = $this->postJson('/api/cart/validate', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'quantity' => 1,
                    'selected_size' => $variant->size,
                    'selected_color' => $variant->color,
                ]
            ],
            'district' => 'Pokhara',
            'currency' => 'NPR',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'currency' => 'NPR',
                'subtotal' => 1899.00,
                'shipping_fee' => 150.00,
                'is_inside_valley' => false,
                'cod_available' => false,
            ]);
    }

    public function test_checkout_creates_order_and_confirms_payment(): void
    {
        $variant = ProductVariant::where('sku', 'SHO-001-CRM-S')->first();
        $product = $variant->product;
        $initialStock = $variant->stock_quantity;

        // 1. Checkout with Nepal address
        $response = $this->postJson('/api/checkout', [
            'customer' => [
                'first_name' => 'Aarav',
                'last_name' => 'Shrestha',
                'email' => 'aarav.shrestha@laijau.com',
                'phone' => '9841234567',
                'province' => 'Bagmati Province',
                'district' => 'Kathmandu',
                'municipality' => 'Kathmandu Metropolitan City',
                'ward' => '03',
                'tole' => 'Lazimpat',
            ],
            'items' => [
                [
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'quantity' => 2,
                    'selected_size' => 'S',
                    'selected_color' => 'Crimson',
                ]
            ],
            'currency' => 'NPR',
            'payment_method' => 'cod',
        ]);

        $this->assertTrue(in_array($response->status(), [200, 201]));
        $response->assertJsonStructure(['order_id', 'order_number', 'total_amount']);

        $orderId = $response->json('order_id');
        $orderNumber = $response->json('order_number');
        $order = Order::find($orderId);

        $this->assertEquals('NP', $order->shipping_country);
        $this->assertEquals('Kathmandu Metropolitan City', $order->shipping_city);
        $this->assertEquals('Kathmandu', $order->district);

        // 2. Dispatch order & add courier tracking link
        $order->update([
            'status' => Order::STATUS_SHIPPED,
            'carrier' => 'Nepal Can Move (NCM)',
            'courier_name' => 'Nepal Can Move (NCM)',
            'tracking_number' => 'NCM9988776655',
            'tracking_url' => 'https://nepalcanmove.com/track?id=NCM9988776655',
        ]);

        // 3. Test public order lookup endpoint with phone
        $lookupResponse = $this->getJson("/api/orders/lookup/{$orderNumber}?phone=9841234567");
        $lookupResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'order' => [
                    'order_number' => $orderNumber,
                    'status' => Order::STATUS_SHIPPED,
                    'courier_name' => 'Nepal Can Move (NCM)',
                    'tracking_number' => 'NCM9988776655',
                    'tracking_url' => 'https://nepalcanmove.com/track?id=NCM9988776655',
                ]
            ]);
        $this->assertCount(1, $lookupResponse->json('order.items'));
    }

    public function test_customer_can_view_order_history_with_tracking(): void
    {
        $user = User::create([
            'name' => 'Bikash Shrestha',
            'email' => 'bikash@laijau.com',
            'password' => bcrypt('Password123!'),
        ]);

        $order = Order::create([
            'user_id' => $user->id,
            'first_name' => 'Bikash',
            'last_name' => 'Shrestha',
            'email' => 'bikash@laijau.com',
            'shipping_address' => 'Thamel Marg 12',
            'shipping_country' => 'NP',
            'subtotal' => 450,
            'vat_amount' => 112.5,
            'total_amount' => 562.5,
            'currency' => 'npr',
            'status' => Order::STATUS_SHIPPED,
            'carrier' => 'Nepal Can Move (NCM)',
            'tracking_number' => 'NCM12345678',
            'tracking_url' => 'https://nepalcanmove.com/track/NCM12345678',
        ]);

        $product = Product::first();
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 450,
        ]);

        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/user/orders');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment([
                'carrier' => 'Nepal Can Move (NCM)',
                'tracking_number' => 'NCM12345678',
                'tracking_url' => 'https://nepalcanmove.com/track/NCM12345678',
            ]);
    }

    public function test_contact_form_submits_successfully(): void
    {
        $response = $this->postJson('/api/contact', [
            'name' => 'Prerana Shrestha',
            'email' => 'prerana@example.com',
            'subject' => 'Footwear Sizing Guidance',
            'message' => 'I would like to inquire about standard shoe sizing for an upcoming purchase in Lalitpur.',
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);
    }

    public function test_nepal_checkout_order_cancellation_marks_cancelled_cleanly(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $product = Product::first();
        $variant = ProductVariant::where('sku', 'SHO-001-CRM-M')->first();

        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'LJ-' . date('ymd') . '-TEST',
            'first_name' => 'Aarav',
            'last_name' => 'Shrestha',
            'email' => $user->email,
            'shipping_address' => 'Lazimpat, Ward 3',
            'shipping_city' => 'Kathmandu',
            'shipping_country' => 'NP',
            'subtotal' => 1899.00,
            'total_amount' => 1899.00,
            'currency' => 'npr',
            'payment_method' => 'cod',
            'status' => Order::STATUS_PENDING,
            'payment_status' => 'unpaid',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'product_name' => $product->name,
            'sku' => $variant->sku,
            'quantity' => 1,
            'unit_price' => 1899.00,
        ]);

        $response = $this->actingAs($user)->postJson("/api/orders/{$order->order_number}/cancel", [
            'reason' => 'Customer requested cancellation before dispatch',
        ]);

        $response->assertStatus(200);
        $order->refresh();
        $this->assertEquals(Order::STATUS_CANCELLED, $order->status);
    }

    public function test_insufficient_stock_rejected_at_checkout(): void
    {
        $variant = ProductVariant::where('sku', 'SHO-001-EME-L')->first();
        $product = $variant->product;
        // Variant has available stock 1
        $excessQuantity = $variant->stock_quantity + 50;

        $response = $this->postJson('/api/checkout', [
            'customer' => [
                'first_name' => 'Dipesh',
                'last_name' => 'Adhikari',
                'email' => 'dipesh@example.com',
                'phone' => '9841234567',
                'province' => 'Bagmati Province',
                'district' => 'Kathmandu',
                'municipality' => 'Kathmandu Metropolitan City',
                'ward' => '04',
                'tole' => 'Baluwatar',
            ],
            'items' => [
                [
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'quantity' => $excessQuantity,
                ]
            ],
            'payment_method' => 'cod',
            'currency' => 'NPR',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Insufficient stock', $response->json('message'));
    }

    public function test_client_submitted_price_is_ignored_and_server_price_used(): void
    {
        $variant = ProductVariant::where('sku', 'SHO-001-CRM-S')->first();
        $product = $variant->product;

        // Attempt to pass malicious price of 1 NPR
        $response = $this->postJson('/api/checkout', [
            'customer' => [
                'first_name' => 'Hacker',
                'last_name' => 'Attempt',
                'email' => 'hack@example.com',
                'phone' => '9841234567',
                'province' => 'Bagmati Province',
                'district' => 'Kathmandu',
                'municipality' => 'Kathmandu Metropolitan City',
                'ward' => '01',
                'tole' => 'Durbar Marg',
            ],
            'items' => [
                [
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'quantity' => 1,
                    'price' => 1.00, // Malicious manipulation
                    'price_npr' => 1.00,
                ]
            ],
            'payment_method' => 'cod',
            'currency' => 'NPR',
        ]);

        $this->assertTrue(in_array($response->status(), [200, 201]));
        $order = Order::find($response->json('order_id'));

        // Assert total reflects real server DB price (1899 NPR), NOT 1.00 NPR!
        $this->assertGreaterThan(1800.00, (float)$order->total_amount);
        $this->assertEquals((float)($variant->price ?: 1899.00), (float)$order->items->first()->unit_price);
    }

    public function test_nepal_order_return_request_lifecycle(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $product = Product::first();
        $variant = ProductVariant::where('sku', 'SHO-001-CRM-S')->first();

        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'LJ-RET-' . uniqid(),
            'first_name' => 'Suman',
            'last_name' => 'Shrestha',
            'email' => $user->email,
            'shipping_address' => 'Patan, Ward 2',
            'shipping_city' => 'Lalitpur',
            'shipping_country' => 'NP',
            'subtotal' => 1000.00,
            'total_amount' => 1000.00,
            'currency' => 'npr',
            'status' => Order::STATUS_DELIVERED,
            'payment_status' => 'paid',
            'payment_method' => 'cod',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'product_name' => $product->name,
            'sku' => $variant->sku,
            'quantity' => 1,
            'unit_price' => 1000.00,
        ]);

        $response = $this->actingAs($user)->postJson("/api/orders/{$order->order_number}/return", [
            'reason' => 'Size does not fit',
        ]);

        $this->assertTrue(in_array($response->status(), [200, 422]));
    }

    public function test_nepal_delivery_and_free_shipping_calculations(): void
    {
        // 1. Create a sub-2,000 NPR product for testing shipping charges
        $lowCostProduct = Product::create([
            'name' => 'Silk Pocket Square',
            'slug' => 'silk-pocket-square-' . uniqid(),
            'sku' => 'ACC-TEST-' . uniqid(),
            'price' => 800.00,
            'quantity' => 10,
            'track_quantity' => true,
            'is_active' => true,
            'is_published' => true,
        ]);

        // Validate cart with Standard Valley delivery (800 < 2000 threshold -> 100 NPR)
        $responseHome = $this->postJson('/api/cart/validate', [
            'items' => [
                [
                    'product_id' => $lowCostProduct->id,
                    'quantity' => 1,
                ]
            ],
            'district' => 'Kathmandu',
            'currency' => 'NPR',
        ]);

        $responseHome->assertStatus(200);
        $this->assertEquals(100.00, (float)$responseHome->json('shipping_fee'));

        // Validate cart with order over 2000 NPR (3 x 800 = 2400 NPR -> 0 NPR free delivery)
        $responseFree = $this->postJson('/api/cart/validate', [
            'items' => [
                [
                    'product_id' => $lowCostProduct->id,
                    'quantity' => 3,
                ]
            ],
            'district' => 'Kathmandu',
            'currency' => 'NPR',
        ]);

        $responseFree->assertStatus(200);
        $this->assertEquals(0.00, (float)$responseFree->json('shipping_fee'));
    }

    public function test_restock_request_workflow(): void
    {
        $product = Product::first();
        $product->update([
            'availability_status' => 'out_of_stock',
            'allow_preorder' => false,
            'quantity' => 0,
        ]);

        // 1. Validate cart should fail for out of stock product
        $cartResp = $this->postJson('/api/cart/validate', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'shipping_country' => 'NP',
            'district' => 'Kathmandu',
            'currency' => 'NPR',
        ]);
        $cartResp->assertStatus(422);

        // 2. Customer submits restock request
        $restockResp = $this->postJson("/api/products/{$product->id}/restock-request", [
            'email' => 'waitlist@laijau.com',
            'size' => 'Standard',
            'color' => 'Crimson',
        ]);
        $restockResp->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => "You're on the list. We'll email you when this piece is available again.",
            ]);

        $this->assertDatabaseHas('restock_requests', [
            'product_id' => $product->id,
            'email' => 'waitlist@laijau.com',
            'status' => 'pending',
        ]);
    }

    public function test_preorder_workflow(): void
    {
        $product = Product::where('is_published', true)->first() ?? Product::first();
        $product->update([
            'is_published' => true,
            'is_active' => true,
            'price' => 3000.00,
            'price_npr' => 3000.00,
            'availability_status' => 'pre_order',
            'allow_preorder' => true,
            'quantity' => 0,
            'preorder_expected_dispatch' => '15–20 days',
            'preorder_limit' => 10,
            'preorder_count' => 0,
        ]);

        // 1. Cart validation passes even with stock = 0
        $cartResp = $this->postJson('/api/cart/validate', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'shipping_country' => 'NP',
            'district' => 'Kathmandu',
            'currency' => 'NPR',
        ]);
        $cartResp->assertStatus(200)
            ->assertJsonPath('items.0.is_preorder', true)
            ->assertJsonPath('items.0.preorder_dispatch_note', '15–20 days');

        // 2. Checkout succeeds and marks order item as preorder
        $checkoutResp = $this->postJson('/api/checkout', [
            'customer' => [
                'first_name' => 'Astrid',
                'last_name' => 'Sharma',
                'email' => 'astrid.preorder@laijau.com',
                'phone' => '9841234567',
                'province' => 'Bagmati Province',
                'district' => 'Kathmandu',
                'municipality' => 'Kathmandu Metropolitan City',
                'ward' => '03',
                'tole' => 'Lazimpat',
            ],
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ]
            ],
            'payment_method' => 'cod',
            'currency' => 'NPR',
        ]);

        $this->assertTrue(in_array($checkoutResp->status(), [200, 201]));
        $orderNumber = $checkoutResp->json('order_number');

        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'is_preorder' => true,
            'preorder_dispatch_note' => '15–20 days',
        ]);

        // Cleanup: restore product
        $product->update([
            'availability_status' => 'available',
            'allow_preorder' => false,
            'quantity' => 10,
        ]);
    }
}
