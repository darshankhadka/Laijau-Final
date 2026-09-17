<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ProductionSecurityAuditTest extends TestCase
{
    use \Illuminate\Foundation\Testing\DatabaseTransactions;

    protected Product $product;
    protected ShippingMethod $shippingMethod;

    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\Cache::forget('active_shipping_methods');

        $this->shippingMethod = ShippingMethod::updateOrCreate(
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

        $this->product = Product::create([
            'name' => 'Audit Security Silk Scarf',
            'slug' => 'audit-security-silk-scarf-' . uniqid(),
            'sku' => 'SEC-SKU-' . uniqid(),
            'price' => 895.00,
            'price_npr' => 895.00,
            'quantity' => 15,
            'track_quantity' => true,
            'is_active' => true,
            'is_published' => true,
        ]);
    }

    public function test_guest_cannot_lookup_another_guest_or_customer_order_without_authorization()
    {
        $guestOrder = Order::create([
            'order_number' => 'ORD-SEC-GUEST-' . uniqid(),
            'user_id' => null,
            'first_name' => 'Guest',
            'last_name' => 'Victim',
            'email' => 'guest_victim@example.com',
            'phone' => '+97712345678',
            'shipping_address' => 'Thamel Marg 1',
            'shipping_city' => 'Kathmandu',
            'shipping_postal_code' => '1000',
            'shipping_country' => 'NP',
            'subtotal' => 120.00,
            'shipping_fee' => 7.00,
            'vat_amount' => 25.40,
            'coupon_discount' => 0,
            'total_amount' => 127.00,
            'currency' => 'npr',
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'esewa',
            'payment_id' => 'pm_test_secret_session_xyz',
        ]);

        // Unauthenticated request without session credentials must return 403
        $response = $this->getJson('/api/orders/lookup/' . $guestOrder->order_number);
        $response->assertStatus(403);
    }

    public function test_guest_can_lookup_order_with_matching_payment_session_id()
    {
        $guestOrder = Order::create([
            'order_number' => 'ORD-SEC-GUEST-' . uniqid(),
            'user_id' => null,
            'first_name' => 'Guest',
            'last_name' => 'Legit',
            'email' => 'guest_legit@example.com',
            'phone' => '+97712345678',
            'shipping_address' => 'New Road 12',
            'shipping_city' => 'Kathmandu',
            'shipping_postal_code' => '1100',
            'shipping_country' => 'NP',
            'subtotal' => 120.00,
            'shipping_fee' => 7.00,
            'vat_amount' => 25.40,
            'coupon_discount' => 0,
            'total_amount' => 127.00,
            'currency' => 'npr',
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'esewa',
            'payment_id' => 'pm_test_legit_secret_123',
        ]);

        // Request with matching payment session_id must succeed
        $response = $this->getJson('/api/orders/lookup/' . $guestOrder->order_number . '?session_id=pm_test_legit_secret_123');
        $response->assertStatus(200);
        $response->assertJsonPath('order.order_number', $guestOrder->order_number);

        // Request with mismatched session_id must fail
        $badResponse = $this->getJson('/api/orders/lookup/' . $guestOrder->order_number . '?session_id=pm_test_wrong');
        $badResponse->assertStatus(403);
    }

    public function test_guest_can_lookup_order_when_placed_in_current_browser_session()
    {
        $guestOrder = Order::create([
            'order_number' => 'ORD-SEC-SESS-' . uniqid(),
            'user_id' => null,
            'first_name' => 'Guest',
            'last_name' => 'Sess',
            'email' => 'guest_sess@example.com',
            'phone' => '+97712345678',
            'shipping_address' => 'Durbar Marg 5',
            'shipping_city' => 'Kathmandu',
            'shipping_postal_code' => '1160',
            'shipping_country' => 'NP',
            'subtotal' => 120.00,
            'shipping_fee' => 7.00,
            'vat_amount' => 25.40,
            'coupon_discount' => 0,
            'total_amount' => 127.00,
            'currency' => 'npr',
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'esewa',
        ]);

        // Request with session placed_orders must succeed
        $response = $this->withSession(['placed_orders' => [$guestOrder->order_number]])
            ->getJson('/api/orders/lookup/' . $guestOrder->order_number);

        $response->assertStatus(200);
        $response->assertJsonPath('order.order_number', $guestOrder->order_number);
    }

    public function test_customer_cannot_lookup_another_customers_order()
    {
        /** @var User $customerA */
        $customerA = User::factory()->create(['email' => 'customera@example.com']);
        /** @var User $customerB */
        $customerB = User::factory()->create(['email' => 'customerb@example.com']);

        $orderA = Order::create([
            'order_number' => 'ORD-SEC-CUSTA-' . uniqid(),
            'user_id' => $customerA->id,
            'first_name' => 'Customer',
            'last_name' => 'A',
            'email' => $customerA->email,
            'phone' => '+97712345678',
            'shipping_address' => 'Lazimpat 10',
            'shipping_city' => 'Kathmandu',
            'shipping_postal_code' => '1620',
            'shipping_country' => 'NP',
            'subtotal' => 120.00,
            'shipping_fee' => 7.00,
            'vat_amount' => 25.40,
            'coupon_discount' => 0,
            'total_amount' => 127.00,
            'currency' => 'npr',
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'esewa',
        ]);

        // Customer B attempting to lookup Customer A's order must be forbidden
        $response = $this->actingAs($customerB, 'web')
            ->getJson('/api/orders/lookup/' . $orderA->order_number);

        $response->assertStatus(403);

        // Customer A accessing their own order must succeed
        $responseOwner = $this->actingAs($customerA, 'web')
            ->getJson('/api/orders/lookup/' . $orderA->order_number);

        $responseOwner->assertStatus(200);
        $responseOwner->assertJsonPath('order.order_number', $orderA->order_number);
    }

    public function test_order_receipt_access_control()
    {
        /** @var User $customerA */
        $customerA = User::factory()->create(['email' => 'receipta@example.com']);
        /** @var User $customerB */
        $customerB = User::factory()->create(['email' => 'receiptb@example.com']);

        $orderA = Order::create([
            'order_number' => 'ORD-SEC-REC-' . uniqid(),
            'user_id' => $customerA->id,
            'first_name' => 'Receipt',
            'last_name' => 'Owner',
            'email' => $customerA->email,
            'phone' => '+97712345678',
            'shipping_address' => 'Durbar Marg 20',
            'shipping_city' => 'Kathmandu',
            'shipping_postal_code' => '1260',
            'shipping_country' => 'NP',
            'subtotal' => 120.00,
            'shipping_fee' => 7.00,
            'vat_amount' => 25.40,
            'coupon_discount' => 0,
            'total_amount' => 127.00,
            'currency' => 'npr',
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'esewa',
        ]);

        // 1. Unauthenticated guest -> 403
        $this->get('/orders/' . $orderA->id . '/receipt')
            ->assertStatus(403);

        // 2. Customer B -> 403
        $this->actingAs($customerB, 'web')
            ->get('/orders/' . $orderA->id . '/receipt')
            ->assertStatus(403);

        // 3. Customer A (Owner) -> 200
        $this->actingAs($customerA, 'web')
            ->get('/orders/' . $orderA->id . '/receipt')
            ->assertStatus(200)
            ->assertSee($orderA->order_number);

        // 4. Guest with placed_orders in session -> 200
        $guestOrder = Order::create([
            'order_number' => 'ORD-SEC-GUESTREC-' . uniqid(),
            'user_id' => null,
            'first_name' => 'Guest',
            'last_name' => 'Buyer',
            'email' => 'guest_rec@example.com',
            'phone' => '+97712345678',
            'shipping_address' => 'Baluwatar 1',
            'shipping_city' => 'Kathmandu',
            'shipping_postal_code' => '44600',
            'shipping_country' => 'NP',
            'subtotal' => 120.00,
            'shipping_fee' => 7.00,
            'vat_amount' => 25.40,
            'coupon_discount' => 0,
            'total_amount' => 127.00,
            'currency' => 'npr',
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'esewa',
        ]);

        $this->withSession(['placed_orders' => [$guestOrder->order_number]])
            ->get('/orders/' . $guestOrder->id . '/receipt')
            ->assertStatus(200)
            ->assertSee($guestOrder->order_number);
    }

    public function test_order_endpoints_prevent_unauthorized_access()
    {
        /** @var User $customerA */
        $customerA = User::factory()->create(['email' => 'pay_ownera@example.com']);
        /** @var User $customerB */
        $customerB = User::factory()->create(['email' => 'pay_attackerb@example.com']);

        $orderA = Order::create([
            'order_number' => 'ORD-SEC-PAY-' . uniqid(),
            'user_id' => $customerA->id,
            'first_name' => 'Pay',
            'last_name' => 'Owner',
            'email' => $customerA->email,
            'phone' => '+97712345678',
            'shipping_address' => 'Thamel Marg 4',
            'shipping_city' => 'Kathmandu',
            'shipping_postal_code' => '1160',
            'shipping_country' => 'NP',
            'subtotal' => 120.00,
            'shipping_fee' => 7.00,
            'vat_amount' => 25.40,
            'coupon_discount' => 0,
            'total_amount' => 127.00,
            'currency' => 'npr',
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'esewa',
        ]);

        // Unauthenticated / unauthorized attacker trying to cancel Customer A's order
        $this->postJson('/api/orders/' . $orderA->order_number . '/cancel')->assertStatus(403);

        // Customer B trying to cancel Customer A's order
        $this->actingAs($customerB, 'web')
            ->postJson('/api/orders/' . $orderA->order_number . '/cancel')->assertStatus(403);

        // Customer B trying to return Customer A's order
        $this->actingAs($customerB, 'web')
            ->postJson('/api/orders/' . $orderA->order_number . '/return')->assertStatus(403);
    }

    public function test_payment_confirmation_idempotency_prevents_duplicate_stock_decrement()
    {
        $initialStock = $this->product->quantity;

        $order = Order::create([
            'order_number' => 'ORD-SEC-IDEMP-' . uniqid(),
            'user_id' => null,
            'first_name' => 'Payment',
            'last_name' => 'Tester',
            'email' => 'payment_test@example.com',
            'phone' => '+97712345678',
            'shipping_address' => 'New Road 2',
            'shipping_city' => 'Kathmandu',
            'shipping_postal_code' => '1060',
            'shipping_country' => 'NP',
            'subtotal' => 120.00,
            'shipping_fee' => 7.00,
            'vat_amount' => 25.40,
            'coupon_discount' => 0,
            'total_amount' => 127.00,
            'currency' => 'npr',
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'esewa',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'sku' => $this->product->sku,
            'unit_price' => 120.00,
            'quantity' => 2,
        ]);

        // First execution via CheckoutController::confirmOrderPayment
        \App\Http\Controllers\Api\CheckoutController::confirmOrderPayment($order, 'pi_audit_test_1');

        $this->product->refresh();
        $this->assertEquals($initialStock - 2, $this->product->quantity, 'Stock should decrement by 2 on first confirmation');
        $this->assertEquals('paid', $order->fresh()->payment_status);

        // Replay of payment confirmation (simulating duplicate callback or race condition)
        \App\Http\Controllers\Api\CheckoutController::confirmOrderPayment($order->fresh(), 'pi_audit_test_1');

        $this->product->refresh();
        $this->assertEquals($initialStock - 2, $this->product->quantity, 'Stock should NOT decrement a second time on replay');
    }

    public function test_checkout_ignores_client_tampered_price_and_enforces_database_pricing()
    {
        // Product actual price is 120.00 npr. Client attempts to pass price: 1.00
        $response = $this->withHeaders(['X-Test-Mock' => 'true'])->postJson('/api/checkout', [
            'customer' => [
                'first_name' => 'Tamper',
                'last_name' => 'Tester',
                'email' => 'tamper@example.com',
                'phone' => '9841234567',
                'address' => 'Thamel Marg, Ward 26',
                'province' => 'Bagmati Province',
                'district' => 'Kathmandu',
                'municipality' => 'Kathmandu Metropolitan City',
                'ward' => '26',
                'tole' => 'Thamel',
                'country' => 'Nepal',
            ],
            'shipping_method_code' => $this->shippingMethod->code,
            'payment_method' => 'cod',
            'currency' => 'NPR',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 1,
                    'price' => 1.00, // Tampered client price
                ]
            ],
        ]);

        $response->assertSuccessful();
        $orderId = $response->json('order_id');
        $this->assertNotNull($orderId);

        $order = Order::find($orderId);
        $this->assertNotNull($order);

        // Subtotal must be based on DB price, not tampered 1.00
        $this->assertEquals((float)$this->product->price, (float)$order->subtotal);
        $this->assertEquals((float)($this->product->price + $order->shipping_fee), (float)$order->total_amount);
    }

    public function test_checkout_rejects_quantity_exceeding_stock()
    {
        $response = $this->withHeaders(['X-Test-Mock' => 'true'])->postJson('/api/checkout', [
            'customer' => [
                'first_name' => 'Stock',
                'last_name' => 'Tester',
                'email' => 'stock@example.com',
                'phone' => '9841234567',
                'address' => 'Thamel Marg, Ward 26',
                'province' => 'Bagmati Province',
                'district' => 'Kathmandu',
                'municipality' => 'Kathmandu Metropolitan City',
                'ward' => '26',
                'tole' => 'Thamel',
                'country' => 'Nepal',
            ],
            'shipping_method_code' => $this->shippingMethod->code,
            'payment_method' => 'cod',
            'currency' => 'NPR',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 9999, // Exceeds product stock (15)
                ]
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['success' => false]);
    }

    public function test_customer_private_profile_and_measurements_isolation()
    {
        /** @var User $customerA */
        $customerA = User::factory()->create([
            'email' => 'user_a_priv@example.com',
            'name' => 'Alice Private',
            'saved_measurements' => ['bust' => 88, 'waist' => 68, 'hips' => 96],
        ]);

        /** @var User $customerB */
        $customerB = User::factory()->create([
            'email' => 'user_b_priv@example.com',
            'name' => 'Bob Private',
            'saved_measurements' => ['bust' => 102, 'waist' => 85, 'hips' => 110],
        ]);

        // Customer B fetches profile
        $responseB = $this->actingAs($customerB, 'sanctum')->getJson('/api/user');
        $responseB->assertStatus(200);
        $responseB->assertJsonPath('email', $customerB->email);
        $responseB->assertJsonPath('name', $customerB->name);
        $responseB->assertJsonPath('saved_measurements.bust', 102);

        // Never see Customer A's measurements
        $this->assertNotEquals('Alice Private', $responseB->json('name'));
        $this->assertNotEquals(88, $responseB->json('saved_measurements.bust'));
    }

    public function test_customer_orders_endpoint_isolation()
    {
        /** @var User $customerA */
        $customerA = User::factory()->create(['email' => 'ord_a@example.com']);
        /** @var User $customerB */
        $customerB = User::factory()->create(['email' => 'ord_b@example.com']);

        $orderA = Order::create([
            'order_number' => 'ORD-SEC-PRIV-A-' . uniqid(),
            'user_id' => $customerA->id,
            'first_name' => 'Alice',
            'last_name' => 'Owner',
            'email' => $customerA->email,
            'phone' => '+97712345678',
            'shipping_address' => 'Alice St 1',
            'shipping_city' => 'Kathmandu',
            'shipping_postal_code' => '1000',
            'shipping_country' => 'NP',
            'subtotal' => 120.00,
            'shipping_fee' => 7.00,
            'vat_amount' => 25.40,
            'coupon_discount' => 0,
            'total_amount' => 127.00,
            'currency' => 'npr',
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'esewa',
        ]);

        // Customer B requesting Customer A's order details
        $response = $this->actingAs($customerB, 'sanctum')
            ->getJson('/api/user/orders/' . $orderA->order_number);

        // Must be 404 (not found or access denied)
        $response->assertStatus(404);

        // Customer B's order list must NOT contain Customer A's order
        $listResponse = $this->actingAs($customerB, 'sanctum')
            ->getJson('/api/user/orders');
        $listResponse->assertStatus(200);
        $orderNumbers = collect($listResponse->json())->pluck('order_number')->toArray();
        $this->assertNotContains($orderA->order_number, $orderNumbers);
    }
}
