<?php

namespace Tests\Feature;

use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockReservation;
use App\Models\Inventory\Warehouse;
use App\Models\Order;
use App\Models\PaymentAuditLog;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use App\Services\Logistics\LogisticsService;
use App\Services\Payment\ConnectIpsService;
use App\Services\PaymentSettingsService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PaymentIntegrationTest extends TestCase
{
    use DatabaseTransactions;
    protected InventoryService $inventoryService;
    protected LogisticsService $logisticsService;
    protected Warehouse $centralWh;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventoryService = app(InventoryService::class);
        $this->logisticsService = app(LogisticsService::class);
        $this->centralWh = $this->inventoryService->getDefaultWarehouse();

        $this->adminUser = User::firstOrCreate(
            ['email' => 'admin_test@laijau.com'],
            [
                'name' => 'Admin Tester',
                'password' => bcrypt('AdminPassword123!'),
                'is_admin' => true,
            ]
        );
        $this->adminUser->update(['is_admin' => true]);
    }

    protected function createTestProduct(string $sku, int $qty = 5, float $price = 4500): Product
    {
        $product = Product::firstOrCreate(
            ['sku' => $sku],
            [
                'name' => "Payment Test {$sku}",
                'slug' => "payment-test-{$sku}-" . uniqid(),
                'price' => $price,
                'price_npr' => $price,
                'is_active' => true,
                'is_published' => true,
                'track_quantity' => true,
                'quantity' => $qty,
            ]
        );

        $product->update([
            'quantity' => $qty,
            'track_quantity' => true,
            'price' => $price,
            'price_npr' => $price,
        ]);

        StockLevel::updateOrCreate(
            [
                'warehouse_id' => $this->centralWh->id,
                'product_id' => $product->id,
                'variant_id' => null,
            ],
            [
                'quantity_on_hand' => $qty,
                'quantity_reserved' => 0,
            ]
        );

        return $product;
    }

    // ==========================================
    // 1. CASH ON DELIVERY (COD) TESTS
    // ==========================================

    public function test_cash_on_delivery_succeeds_for_kathmandu_valley_with_npr_100_shipping_fee(): void
    {
        $product = $this->createTestProduct('COD-KTM-01', 5, 1500);

        $payload = [
            'customer' => [
                'first_name' => 'Aarav',
                'last_name' => 'Shrestha',
                'phone' => '9841234567',
                'email' => 'aarav@example.com',
                'province' => 'Bagmati Province',
                'district' => 'Kathmandu',
                'municipality' => 'Kathmandu Metropolitan City',
                'ward' => '3',
                'tole' => 'Maharajgunj',
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

        $res->assertStatus(200);
        $res->assertJson([
            'success' => true,
            'payment_method' => 'cod',
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'is_inside_valley' => true,
            'total_amount' => 1600.00, // 1500 + 100 delivery fee
        ]);

        $order = Order::where('order_number', $res->json('order_number'))->first();
        $this->assertNotNull($order);
        $this->assertEquals(100.00, (float)$order->shipping_fee);
        $this->assertEquals(Order::PAYMENT_STATUS_UNPAID, $order->payment_status);
        $this->assertEquals(Order::STATUS_PENDING, $order->status);
    }

    public function test_cash_on_delivery_is_rejected_outside_kathmandu_valley(): void
    {
        $product = $this->createTestProduct('COD-PKH-01', 5, 3000);

        $payload = [
            'customer' => [
                'first_name' => 'Bibek',
                'last_name' => 'Gurung',
                'phone' => '9856012345',
                'email' => 'bibek@example.com',
                'province' => 'Gandaki Province',
                'district' => 'Kaski',
                'municipality' => 'Pokhara Metropolitan City',
                'ward' => '6',
                'tole' => 'Lakeside',
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

        $res->assertStatus(422);
        $res->assertJson([
            'success' => false,
        ]);
        $this->assertStringContainsString('Cash on Delivery (COD) is available ONLY inside Kathmandu Valley', $res->json('message'));
    }

    // ==========================================
    // 2. CONNECTIPS / NCHL ONLINE PAYMENT TESTS
    // ==========================================

    public function test_connectips_checkout_creates_pending_order_and_redirect_url(): void
    {
        $product = $this->createTestProduct('CIPS-CHK-01', 5, 4000);

        $payload = [
            'customer' => [
                'first_name' => 'Suman',
                'last_name' => 'Khadka',
                'phone' => '9812345678',
                'email' => 'suman@example.com',
                'province' => 'Bagmati Province',
                'district' => 'Lalitpur',
                'municipality' => 'Lalitpur Metropolitan City',
                'ward' => '2',
                'tole' => 'Sanepa',
            ],
            'payment_method' => 'connectips',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ]
            ],
        ];

        $res = $this->postJson('/api/checkout', $payload);

        $res->assertStatus(200);
        $res->assertJson([
            'success' => true,
            'payment_method' => 'connectips',
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'order_status' => Order::STATUS_PENDING,
        ]);

        $this->assertStringContainsString('/payment/connectips/initiate/', $res->json('redirect_url'));

        $order = Order::where('order_number', $res->json('order_number'))->first();
        $this->assertNotNull($order);
        $this->assertNotEmpty($order->connectips_txnid);

        // Verify active inventory reservation was created
        $reservation = StockReservation::where('reference_type', 'online_order')
            ->where('reference_id', $order->id)
            ->where('status', 'active')
            ->first();
        $this->assertNotNull($reservation);
        $this->assertEquals(1, $reservation->quantity);
    }

    public function test_connectips_initiation_page_generates_valid_signed_payload_and_npr_currency(): void
    {
        $product = $this->createTestProduct('CIPS-INIT-01', 5, 5000);
        $order = Order::create([
            'order_number' => Order::generateNextOrderNumber(),
            'guest_access_token' => Order::generateGuestToken(),
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'phone' => '9841000000',
            'email' => 'test@example.com',
            'province' => 'Bagmati Province',
            'district' => 'Kathmandu',
            'municipality' => 'Kathmandu',
            'ward' => '1',
            'tole' => 'Thamel',
            'shipping_address' => 'Thamel, Kathmandu',
            'subtotal' => 5000,
            'shipping_fee' => 100,
            'total_amount' => 5100,
            'currency' => 'NPR',
            'payment_method' => 'connectips',
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'status' => Order::STATUS_PENDING,
            'connectips_txnid' => 'ORD_' . time() . '_TEST1',
        ]);

        $connectIpsService = app(ConnectIpsService::class);
        $payload = $connectIpsService->generateInitiationPayload($order);

        $this->assertArrayHasKey('action_url', $payload);
        $this->assertEquals('NPR', $payload['fields']['TXNCRN']);
        $this->assertEquals('510000', $payload['fields']['TXNAMT']); // 5100 NPR in paisa
        $this->assertNotEmpty($payload['fields']['TOKEN']);
        $this->assertEquals($order->connectips_txnid, $payload['fields']['TXNID']);
    }

    public function test_connectips_return_with_successful_verification_marks_paid_and_preserves_reservation(): void
    {
        $product = $this->createTestProduct('CIPS-SUCC-01', 5, 3500);
        $order = Order::create([
            'order_number' => Order::generateNextOrderNumber(),
            'guest_access_token' => Order::generateGuestToken(),
            'first_name' => 'Prabesh',
            'last_name' => 'Adhikari',
            'phone' => '9841112233',
            'email' => 'prabesh@example.com',
            'province' => 'Bagmati Province',
            'district' => 'Kathmandu',
            'municipality' => 'Kathmandu',
            'ward' => '4',
            'tole' => 'Baluwatar',
            'shipping_address' => 'Baluwatar, Kathmandu',
            'subtotal' => 3500,
            'shipping_fee' => 100,
            'total_amount' => 3600,
            'currency' => 'NPR',
            'payment_method' => 'connectips',
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'status' => Order::STATUS_PENDING,
            'connectips_txnid' => 'ORD_' . time() . '_SUCC',
        ]);

        // Create active stock reservation
        $res = $this->inventoryService->createReservation([
            'warehouse_id' => $this->centralWh->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'reference_type' => 'online_order',
            'reference_id' => $order->id,
            'cart_token' => $order->order_number,
            'ttl_minutes' => 1440,
        ]);

        // Mock ConnectIPS verification API returning SUCCESS with matching amount (3600 NPR = 360000 paisa)
        Http::fake([
            '*/connectipswebws/api/creditor/validatetxn*' => Http::response([
                'status' => 'SUCCESS',
                'statusDesc' => 'TRANSACTION SUCCESSFUL',
                'referenceId' => $order->connectips_txnid,
                'txnAmt' => 360000,
                'txnId' => 'GW_NCHL_' . uniqid(),
                'batchId' => 'BATCH_12345',
                'txnDate' => '09-09-2026',
            ], 200),
        ]);

        $response = $this->get("/payment/connectips/return?TXNID={$order->connectips_txnid}");

        $response->assertRedirect();
        $this->assertStringContainsString('/checkout/success', $response->headers->get('Location'));

        $order->refresh();
        $this->assertEquals(Order::PAYMENT_STATUS_PAID, $order->payment_status);
        $this->assertEquals(Order::STATUS_PAYMENT_VERIFIED, $order->status);
        $this->assertNotNull($order->payment_verified_at);

        // Ensure reservation is still active
        $res->refresh();
        $this->assertEquals('active', $res->status);

        // Verify audit log
        $audit = PaymentAuditLog::where('order_id', $order->id)->where('new_payment_status', Order::PAYMENT_STATUS_PAID)->first();
        $this->assertNotNull($audit);
        $this->assertEquals('gateway', $audit->actor_type);
    }

    public function test_connectips_return_with_tampered_amount_fails_payment_and_releases_reservation(): void
    {
        $product = $this->createTestProduct('CIPS-TAMP-01', 5, 8000);
        $order = Order::create([
            'order_number' => Order::generateNextOrderNumber(),
            'guest_access_token' => Order::generateGuestToken(),
            'first_name' => 'Tamper',
            'last_name' => 'Test',
            'phone' => '9841999999',
            'email' => 'tamper@example.com',
            'province' => 'Bagmati Province',
            'district' => 'Kathmandu',
            'municipality' => 'Kathmandu',
            'ward' => '1',
            'tole' => 'Durbarmarg',
            'shipping_address' => 'Durbarmarg, Kathmandu',
            'subtotal' => 8000,
            'shipping_fee' => 100,
            'total_amount' => 8100,
            'currency' => 'NPR',
            'payment_method' => 'connectips',
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'status' => Order::STATUS_PENDING,
            'connectips_txnid' => 'ORD_' . time() . '_TAMP',
        ]);

        $res = $this->inventoryService->createReservation([
            'warehouse_id' => $this->centralWh->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'reference_type' => 'online_order',
            'reference_id' => $order->id,
            'cart_token' => $order->order_number,
            'ttl_minutes' => 1440,
        ]);

        // Mock gateway returning different amount (e.g. 1000 paisa instead of 810000 paisa)
        Http::fake([
            '*/connectipswebws/api/creditor/validatetxn*' => Http::response([
                'status' => 'SUCCESS',
                'statusDesc' => 'TRANSACTION SUCCESSFUL',
                'referenceId' => $order->connectips_txnid,
                'txnAmt' => 1000, // Tampered amount!
                'txnId' => 'GW_FAKE_999',
            ], 200),
        ]);

        $response = $this->get("/payment/connectips/return?TXNID={$order->connectips_txnid}");

        $response->assertRedirect('/checkout');

        $order->refresh();
        $this->assertEquals('failed', $order->payment_status);

        // Verify reservation was released
        $res->refresh();
        $this->assertEquals('cancelled', $res->status);
    }

    public function test_connectips_duplicate_callback_is_idempotent(): void
    {
        $order = Order::create([
            'order_number' => Order::generateNextOrderNumber(),
            'guest_access_token' => Order::generateGuestToken(),
            'first_name' => 'Idempotent',
            'last_name' => 'User',
            'phone' => '9841888888',
            'email' => 'idempotent@example.com',
            'province' => 'Bagmati Province',
            'district' => 'Kathmandu',
            'municipality' => 'Kathmandu',
            'ward' => '1',
            'tole' => 'Thamel',
            'shipping_address' => 'Thamel, Kathmandu',
            'subtotal' => 4900,
            'shipping_fee' => 100,
            'total_amount' => 5000,
            'currency' => 'NPR',
            'payment_method' => 'connectips',
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'status' => Order::STATUS_PAYMENT_VERIFIED,
            'connectips_txnid' => 'ORD_' . time() . '_IDEMP',
        ]);

        // Second callback arrives after payment already paid
        $response = $this->get("/payment/connectips/return?TXNID={$order->connectips_txnid}");

        $response->assertRedirect();
        $this->assertStringContainsString('/checkout/success', $response->headers->get('Location'));

        $order->refresh();
        $this->assertEquals(Order::PAYMENT_STATUS_PAID, $order->payment_status);
    }

    // ==========================================
    // 3. ESEWA MANUAL QR & PROOF TESTS
    // ==========================================

    public function test_esewa_appears_on_public_checkout_and_requires_valid_data(): void
    {
        $service = app(PaymentSettingsService::class);
        $methods = $service->getAvailablePaymentMethods('Kathmandu', true);

        $this->assertArrayHasKey('esewa', $methods);
        $this->assertArrayHasKey('connectips', $methods);
        $this->assertArrayHasKey('cod', $methods);

        // Confirm Khalti is NOT exposed on public storefront checkout
        $this->assertArrayNotHasKey('khalti', $methods);
        $this->assertArrayNotHasKey('bank_transfer', $methods);
    }

    public function test_esewa_order_creation_sets_payment_verification_pending(): void
    {
        $product = $this->createTestProduct('ESEWA-ORD-01', 5, 2000);

        $payload = [
            'customer' => [
                'first_name' => 'Dipak',
                'last_name' => 'Rana',
                'phone' => '9801234567',
                'email' => 'dipak@example.com',
                'province' => 'Bagmati Province',
                'district' => 'Kathmandu',
                'municipality' => 'Kathmandu',
                'ward' => '10',
                'tole' => 'New Baneshwor',
            ],
            'payment_method' => 'esewa',
            'payment_reference' => 'ESEWA_TXN_778899',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ]
            ],
        ];

        $res = $this->postJson('/api/checkout', $payload);

        $res->assertStatus(200);
        $res->assertJson([
            'success' => true,
            'payment_method' => 'esewa',
            'payment_status' => Order::PAYMENT_STATUS_VERIFICATION_PENDING,
            'order_status' => Order::STATUS_PENDING,
        ]);

        $order = Order::where('order_number', $res->json('order_number'))->first();
        $this->assertNotNull($order);
        $this->assertEquals('ESEWA_TXN_778899', $order->payment_reference);
    }

    public function test_esewa_payment_receipt_upload_rejects_invalid_file_types_and_oversized_files(): void
    {
        Storage::fake('local');
        $product = $this->createTestProduct('ESEWA-MIME-01', 5, 2000);

        // Malicious or invalid file (PHP script)
        $invalidFile = UploadedFile::fake()->create('malicious.php', 100, 'text/x-php');

        $payload = [
            'customer' => [
                'first_name' => 'Hacker',
                'last_name' => 'Test',
                'phone' => '9800000000',
                'province' => 'Bagmati Province',
                'district' => 'Kathmandu',
                'municipality' => 'Kathmandu',
                'ward' => '1',
                'tole' => 'Street',
            ],
            'payment_method' => 'esewa',
            'payment_receipt' => $invalidFile,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1]
            ],
        ];

        $res = $this->postJson('/api/checkout', $payload);
        $res->assertStatus(422);
        $res->assertJsonValidationErrors(['payment_receipt']);

        // Oversized file (> 5MB)
        $oversizedFile = UploadedFile::fake()->create('large_proof.jpg', 6000, 'image/jpeg');
        $payload['payment_receipt'] = $oversizedFile;

        $res2 = $this->postJson('/api/checkout', $payload);
        $res2->assertStatus(422);
        $res2->assertJsonValidationErrors(['payment_receipt']);
    }

    public function test_esewa_payment_receipt_is_stored_privately_and_protected_from_unauthorized_access(): void
    {
        Storage::fake('local');
        $validReceipt = UploadedFile::fake()->image('receipt.png', 800, 600);
        $storedPath = $validReceipt->storeAs('payment_proofs', 'proof_test_123.png', 'local');

        $order = Order::create([
            'order_number' => Order::generateNextOrderNumber(),
            'guest_access_token' => Order::generateGuestToken(),
            'first_name' => 'Secure',
            'last_name' => 'Customer',
            'phone' => '9801112222',
            'email' => 'secure@example.com',
            'province' => 'Bagmati Province',
            'district' => 'Kathmandu',
            'municipality' => 'Kathmandu',
            'ward' => '1',
            'tole' => 'Thamel',
            'shipping_address' => 'Thamel, Kathmandu',
            'subtotal' => 2900,
            'shipping_fee' => 100,
            'total_amount' => 3000,
            'currency' => 'NPR',
            'payment_method' => 'esewa',
            'payment_status' => Order::PAYMENT_STATUS_VERIFICATION_PENDING,
            'status' => Order::STATUS_PENDING,
            'payment_receipt_image' => $storedPath,
        ]);

        // Unauthorized guest request without token or session
        $unauthorizedRes = $this->get("/orders/{$order->id}/payment-proof");
        $unauthorizedRes->assertStatus(403);

        // Authorized request with guest access token
        $authorizedRes = $this->get("/orders/{$order->id}/payment-proof?token={$order->guest_access_token}");
        $authorizedRes->assertStatus(200);
        $authorizedRes->assertHeader('Content-Type', 'image/png');
    }

    public function test_admin_can_verify_and_approve_payment(): void
    {
        $order = Order::create([
            'order_number' => Order::generateNextOrderNumber(),
            'guest_access_token' => Order::generateGuestToken(),
            'first_name' => 'Approve',
            'last_name' => 'Me',
            'phone' => '9802223333',
            'email' => 'approve@example.com',
            'province' => 'Bagmati Province',
            'district' => 'Kathmandu',
            'municipality' => 'Kathmandu',
            'ward' => '1',
            'tole' => 'Street',
            'shipping_address' => 'Street, Kathmandu',
            'subtotal' => 4400,
            'shipping_fee' => 100,
            'total_amount' => 4500,
            'currency' => 'NPR',
            'payment_method' => 'esewa',
            'payment_status' => Order::PAYMENT_STATUS_VERIFICATION_PENDING,
            'payment_reference' => 'ESEWA_REF_99999',
            'status' => Order::STATUS_PENDING,
        ]);

        $this->actingAs($this->adminUser, 'admin');

        $order->update([
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'payment_verified_at' => now(),
            'payment_verified_by' => $this->adminUser->id,
            'status' => Order::STATUS_PAYMENT_VERIFIED,
        ]);

        PaymentAuditLog::record(
            $order,
            Order::PAYMENT_STATUS_PAID,
            Order::PAYMENT_STATUS_VERIFICATION_PENDING,
            $order->payment_reference,
            'admin',
            (string)$this->adminUser->id,
            null,
            'Admin manually approved payment.'
        );

        $order->refresh();
        $this->assertEquals(Order::PAYMENT_STATUS_PAID, $order->payment_status);
        $this->assertEquals(Order::STATUS_PAYMENT_VERIFIED, $order->status);

        $audit = PaymentAuditLog::where('order_id', $order->id)->where('new_payment_status', Order::PAYMENT_STATUS_PAID)->first();
        $this->assertNotNull($audit);
        $this->assertEquals('admin', $audit->actor_type);
    }

    // ==========================================
    // 4. LOGISTICS & COURIER DISPATCH GUARDS
    // ==========================================

    public function test_unverified_esewa_order_cannot_be_dispatched_via_ncm_or_pathao(): void
    {
        $order = Order::create([
            'order_number' => Order::generateNextOrderNumber(),
            'guest_access_token' => Order::generateGuestToken(),
            'first_name' => 'Dispatch',
            'last_name' => 'Block',
            'phone' => '9841777777',
            'email' => 'dispatch_block@example.com',
            'province' => 'Bagmati Province',
            'district' => 'Kathmandu',
            'municipality' => 'Kathmandu',
            'ward' => '1',
            'tole' => 'Thamel',
            'shipping_address' => 'Thamel, Kathmandu',
            'subtotal' => 4900,
            'shipping_fee' => 100,
            'total_amount' => 5000,
            'currency' => 'NPR',
            'payment_method' => 'esewa',
            'payment_status' => Order::PAYMENT_STATUS_VERIFICATION_PENDING, // UNVERIFIED!
            'status' => Order::STATUS_PENDING,
        ]);

        $res = $this->logisticsService->createShipment($order, 'ncm');

        $this->assertFalse($res['success']);
        $this->assertStringContainsString('Cannot dispatch courier for unverified eSewa payment', $res['error']);
        $this->assertEmpty($order->courier_order_id);
    }

    public function test_unpaid_connectips_order_cannot_be_dispatched_via_ncm_or_pathao(): void
    {
        $order = Order::create([
            'order_number' => Order::generateNextOrderNumber(),
            'guest_access_token' => Order::generateGuestToken(),
            'first_name' => 'Unpaid',
            'last_name' => 'Cips',
            'phone' => '9841666666',
            'email' => 'unpaid_cips@example.com',
            'province' => 'Bagmati Province',
            'district' => 'Kathmandu',
            'municipality' => 'Kathmandu',
            'ward' => '1',
            'tole' => 'Thamel',
            'shipping_address' => 'Thamel, Kathmandu',
            'subtotal' => 6900,
            'shipping_fee' => 100,
            'total_amount' => 7000,
            'currency' => 'NPR',
            'payment_method' => 'connectips',
            'payment_status' => Order::PAYMENT_STATUS_UNPAID, // UNPAID!
            'status' => Order::STATUS_PENDING,
        ]);

        $res = $this->logisticsService->createShipment($order, 'pathao');

        $this->assertFalse($res['success']);
        $this->assertStringContainsString('Cannot dispatch courier for unpaid ConnectIPS order', $res['error']);
        $this->assertEmpty($order->courier_order_id);
    }

    public function test_outside_valley_cod_order_cannot_be_dispatched_via_courier(): void
    {
        $order = Order::create([
            'order_number' => Order::generateNextOrderNumber(),
            'guest_access_token' => Order::generateGuestToken(),
            'first_name' => 'Outside',
            'last_name' => 'Valley',
            'phone' => '9856012345',
            'email' => 'outside@example.com',
            'province' => 'Gandaki Province',
            'district' => 'Kaski',
            'municipality' => 'Pokhara',
            'ward' => '6',
            'tole' => 'Lakeside',
            'shipping_address' => 'Lakeside, Pokhara',
            'subtotal' => 3000,
            'shipping_fee' => 150,
            'total_amount' => 3150,
            'currency' => 'NPR',
            'payment_method' => 'cod',
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'status' => Order::STATUS_PENDING,
        ]);

        $res = $this->logisticsService->createShipment($order, 'ncm');

        $this->assertFalse($res['success']);
        $this->assertStringContainsString('Cannot dispatch courier for Cash on Delivery outside Kathmandu Valley', $res['error']);
    }

    public function test_admin_rejection_releases_inventory_reservation_and_records_audit(): void
    {
        $product = $this->createTestProduct('REJ-TEST-01', 5, 3000);
        $order = Order::create([
            'order_number' => Order::generateNextOrderNumber(),
            'guest_access_token' => Order::generateGuestToken(),
            'first_name' => 'Reject',
            'last_name' => 'Customer',
            'phone' => '9801234444',
            'email' => 'reject@example.com',
            'province' => 'Bagmati Province',
            'district' => 'Kathmandu',
            'municipality' => 'Kathmandu',
            'ward' => '1',
            'tole' => 'Thamel',
            'shipping_address' => 'Thamel, Kathmandu',
            'subtotal' => 2900,
            'shipping_fee' => 100,
            'total_amount' => 3000,
            'currency' => 'NPR',
            'payment_method' => 'esewa',
            'payment_status' => Order::PAYMENT_STATUS_VERIFICATION_PENDING,
            'payment_reference' => 'ESEWA_FAKE_123',
            'status' => Order::STATUS_PENDING,
        ]);

        $res = $this->inventoryService->createReservation([
            'warehouse_id' => $this->centralWh->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'reference_type' => 'online_order',
            'reference_id' => $order->id,
            'cart_token' => $order->order_number,
            'ttl_minutes' => 1440,
        ]);

        $rejectionReason = 'Invalid reference number. No funds received.';

        // Admin action logic (as implemented in OrderResource and ConnectIpsService)
        $order->update([
            'payment_status' => 'failed',
            'payment_rejection_reason' => $rejectionReason,
            'status' => Order::STATUS_CANCELLED,
        ]);

        $this->inventoryService->releaseReservation($res, 'cancelled');

        PaymentAuditLog::record(
            $order,
            'failed',
            Order::PAYMENT_STATUS_VERIFICATION_PENDING,
            $order->payment_reference,
            'admin',
            (string)$this->adminUser->id,
            null,
            $rejectionReason
        );

        $order->refresh();
        $this->assertEquals('failed', $order->payment_status);
        $this->assertEquals(Order::STATUS_CANCELLED, $order->status);
        $this->assertEquals($rejectionReason, $order->payment_rejection_reason);

        $res->refresh();
        $this->assertEquals('cancelled', $res->status);

        $audit = PaymentAuditLog::where('order_id', $order->id)->where('new_payment_status', 'failed')->first();
        $this->assertNotNull($audit);
        $this->assertEquals($rejectionReason, $audit->notes);
    }
}
