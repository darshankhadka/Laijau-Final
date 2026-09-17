<?php

namespace Tests\Feature;

use App\Models\LogisticsEvent;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Logistics\LogisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LogisticsIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.ncm.api_token' => 'test_ncm_token_123',
            'services.ncm.mode' => 'sandbox',
            'services.ncm.webhook_secret' => null,
            'services.pathao.client_id' => 'test_pathao_client',
            'services.pathao.client_secret' => 'test_pathao_secret',
            'services.pathao.username' => 'test_pathao_user',
            'services.pathao.password' => 'test_pathao_pass',
            'services.pathao.mode' => 'sandbox',
            'services.pathao.webhook_secret' => null,
        ]);
    }

    protected function createTestOrder(string $paymentMethod = 'cod', string $paymentStatus = 'unpaid'): Order
    {
        $product = Product::first() ?? Product::create([
            'name' => 'Signature Cotton Crewneck',
            'slug' => 'signature-cotton-crewneck-' . uniqid(),
            'sku' => 'TEST-CREW-' . uniqid(),
            'price' => 1500,
            'is_active' => true,
            'is_published' => true,
        ]);

        $order = Order::create([
            'order_number' => Order::generateNextOrderNumber(),
            'guest_access_token' => Order::generateGuestToken(),
            'first_name' => 'Aayush',
            'last_name' => 'Sharma',
            'email' => 'aayush.test@laijau.com',
            'phone' => '9841234567',
            'shipping_address' => 'Thamel Ward 1, Kathmandu',
            'shipping_country' => 'Nepal',
            'province' => 'Bagmati Province',
            'district' => 'Kathmandu',
            'municipality' => 'Kathmandu Metropolitan City',
            'ward' => '1',
            'tole' => 'Thamel',
            'is_inside_valley' => true,
            'subtotal' => 1500,
            'shipping_fee' => 100,
            'total_amount' => 1600,
            'currency' => 'NPR',
            'status' => Order::STATUS_CUSTOMER_CONFIRMED,
            'payment_method' => $paymentMethod,
            'payment_status' => $paymentStatus,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'selected_size' => 'L',
            'selected_color' => 'Navy',
            'quantity' => 1,
            'unit_price' => 1500,
        ]);

        return $order->fresh(['items']);
    }

    public function test_ncm_shipment_creation_and_tracking_resolution(): void
    {
        $orderIdNum = rand(710000, 790000);
        $orderIdStr = (string) $orderIdNum;

        Http::fake([
            '*/api/v1/order/create' => Http::response([
                'Message' => 'Order Successfully Created',
                'orderid' => $orderIdNum,
            ], 200),
        ]);

        $order = $this->createTestOrder('cod', 'unpaid');
        $service = app(LogisticsService::class);

        $result = $service->createShipment($order, 'ncm');

        $this->assertTrue($result['success']);
        $this->assertEquals($orderIdStr, $result['external_order_id']);

        $order->refresh();
        $this->assertEquals($orderIdStr, $order->courier_order_id);
        $this->assertEquals($orderIdStr, $order->tracking_number);
        $this->assertStringContainsString("nepalcanmove.com/track?tracking_id={$orderIdStr}", $order->tracking_url);
        $this->assertEquals(Order::STATUS_READY_FOR_DELIVERY, $order->status);

        // Verify audit event
        $event = LogisticsEvent::where('order_id', $order->id)->where('provider', 'ncm')->first();
        $this->assertNotNull($event);
        $this->assertEquals('shipment_created', $event->event);
        $this->assertEquals($orderIdStr, $event->external_order_id);
    }

    public function test_ncm_status_sync_and_comment(): void
    {
        $orderIdStr = (string) rand(710000, 790000);

        Http::fake([
            '*/api/v1/order/status*' => Http::response([
                'status' => 'In Transit',
                'comments' => 'Package at Pokhara hub',
            ], 200),
            '*/api/v1/comment' => Http::response([
                'Message' => 'Comment Added',
            ], 200),
        ]);

        $order = $this->createTestOrder();
        $order->update([
            'carrier' => 'Nepal Can Move (NCM)',
            'courier_order_id' => $orderIdStr,
            'tracking_number' => $orderIdStr,
            'status' => Order::STATUS_READY_FOR_DELIVERY,
        ]);

        $service = app(LogisticsService::class);

        // Sync status
        $syncRes = $service->syncShipmentStatus($order);
        $this->assertTrue($syncRes['success']);
        $this->assertEquals('In Transit', $syncRes['courier_status']);

        $order->refresh();
        $this->assertEquals(Order::STATUS_IN_TRANSIT, $order->status);
        $this->assertEquals('Package at Pokhara hub', $order->courier_comments);

        // Add operational comment
        $commentRes = $service->addComment($order, 'Customer confirmed availability');
        $this->assertTrue($commentRes['success']);
        $order->refresh();
        $this->assertStringContainsString('Customer confirmed availability', $order->courier_comments);
    }

    public function test_ncm_webhook_processing_single_bulk_and_idempotency(): void
    {
        $orderIdStr = (string) rand(710000, 790000);
        $uniqueTs = now()->toIso8601String();

        $order = $this->createTestOrder('cod', 'unpaid');
        $order->update([
            'courier_order_id' => $orderIdStr,
            'tracking_number' => $orderIdStr,
            'status' => Order::STATUS_READY_FOR_DELIVERY,
        ]);

        // 1. Single Webhook (Dispatched -> In Transit)
        $payload1 = [
            'order_id' => (int) $orderIdStr,
            'status' => 'Sent for Delivery',
            'event' => 'sent_for_delivery',
            'timestamp' => $uniqueTs,
        ];

        $response1 = $this->postJson('/api/webhooks/ncm', $payload1);
        $response1->assertStatus(200);
        $response1->assertJson(['status' => 'success']);

        $order->refresh();
        $this->assertEquals(Order::STATUS_IN_TRANSIT, $order->status);
        $this->assertEquals('Sent for Delivery', $order->courier_status);

        // 2. Idempotency test: send identical payload again
        $responseDuplicate = $this->postJson('/api/webhooks/ncm', $payload1);
        $responseDuplicate->assertStatus(200);
        $this->assertEquals(1, $responseDuplicate->json('data.skipped_duplicates'));

        // 3. Bulk Webhook with Delivery Complete
        $payloadDelivery = [
            'order_ids' => [(int) $orderIdStr],
            'status' => 'Delivered',
            'event' => 'delivery_completed',
            'timestamp' => now()->addMinutes(5)->toIso8601String(),
        ];

        $responseDelivery = $this->postJson('/api/webhooks/ncm', $payloadDelivery);
        $responseDelivery->assertStatus(200);

        $order->refresh();
        $this->assertEquals(Order::STATUS_DELIVERED, $order->status);
        $this->assertNotNull($order->delivered_at);
        // COD payment should be auto-reconciled to paid upon verified delivery
        $this->assertEquals(Order::PAYMENT_STATUS_PAID, $order->payment_status);
        $this->assertNotNull($order->payment_verified_at);
    }

    public function test_ncm_test_webhook_ping(): void
    {
        $uniqueTestOrderId = 'TEST-' . rand(100000, 999999);
        $testPayload = [
            'test' => true,
            'event' => 'order.status.changed',
            'order_id' => $uniqueTestOrderId,
        ];

        $response = $this->postJson('/api/webhooks/ncm', $testPayload);
        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        $event = LogisticsEvent::where('external_order_id', $uniqueTestOrderId)->first();
        $this->assertNotNull($event);
        $this->assertEquals('order.status.changed', $event->event);
    }

    public function test_pathao_token_and_shipment_creation(): void
    {
        $consignmentId = 'PT' . rand(100000, 999999);

        Http::fake([
            '*/aladdin/api/v1/issue-token' => Http::response([
                'access_token' => 'test_bearer_token_abc',
                'refresh_token' => 'test_refresh_token_xyz',
                'token_type' => 'Bearer',
                'expires_in' => 432000,
            ], 200),
            '*/aladdin/api/v1/orders' => Http::response([
                'type' => 'success',
                'code' => 200,
                'message' => 'Order created successfully',
                'data' => [
                    'consignment_id' => $consignmentId,
                    'merchant_order_id' => 'LJ-TEST',
                    'order_status' => 'Pending',
                ],
            ], 200),
        ]);

        $order = $this->createTestOrder();
        $service = app(LogisticsService::class);

        $result = $service->createShipment($order, 'pathao');

        $this->assertTrue($result['success']);
        $this->assertEquals($consignmentId, $result['external_order_id']);

        $order->refresh();
        $this->assertEquals($consignmentId, $order->courier_order_id);
        $this->assertStringContainsString("pathao.com/np/parcel-tracking/?consignment_id={$consignmentId}", $order->tracking_url);
    }

    public function test_idempotency_prevents_duplicate_shipment_creation(): void
    {
        $existingId = 'EXISTING_CONSIGNMENT_' . rand(100000, 999999);

        $order = $this->createTestOrder();
        $order->update([
            'courier_order_id' => $existingId,
            'tracking_number' => $existingId,
            'courier_status' => 'In Transit',
        ]);

        $service = app(LogisticsService::class);
        $result = $service->createShipment($order, 'ncm');

        $this->assertTrue($result['success']);
        $this->assertTrue($result['already_exists'] ?? false);
        $this->assertEquals($existingId, $result['external_order_id']);
    }

    public function test_webhook_secret_protection_for_ncm_and_pathao(): void
    {
        config([
            'services.ncm.webhook_secret' => 'secure_ncm_secret_123',
            'services.pathao.webhook_secret' => 'secure_pathao_secret_456',
        ]);

        $payload = ['order_id' => '12345', 'status' => 'Delivered'];

        // 1. NCM Webhook without secret -> 401
        $resNcmBad = $this->postJson('/api/webhooks/ncm', $payload);
        $resNcmBad->assertStatus(401);

        // 2. NCM Webhook with valid secret -> 200
        $resNcmGood = $this->postJson('/api/webhooks/ncm', $payload, [
            'X-NCM-Webhook-Secret' => 'secure_ncm_secret_123',
        ]);
        $resNcmGood->assertStatus(200);

        // 3. Pathao Webhook without secret -> 401
        $resPathaoBad = $this->postJson('/api/webhooks/pathao', $payload);
        $resPathaoBad->assertStatus(401);

        // 4. Pathao Webhook with valid secret -> 200
        $resPathaoGood = $this->postJson('/api/webhooks/pathao', $payload, [
            'X-Pathao-Signature' => 'secure_pathao_secret_456',
        ]);
        $resPathaoGood->assertStatus(200);
    }
}
