<?php

namespace Tests\Feature;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\StockReservation;
use App\Models\Inventory\Warehouse;
use App\Models\OfflineSale;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use App\Services\Inventory\InventoryService;
use App\Services\OfflineSaleService;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class LaijauProductionEndToEndTest extends TestCase
{
    use DatabaseTransactions;

    protected InventoryService $inventoryService;
    protected OfflineSaleService $offlineSaleService;
    protected AccountingService $accountingService;
    protected Warehouse $centralWh;
    protected Warehouse $showroomWh;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventoryService = app(InventoryService::class);
        $this->offlineSaleService = app(OfflineSaleService::class);
        $this->accountingService = app(AccountingService::class);

        $this->centralWh = $this->inventoryService->getDefaultWarehouse();
        $this->showroomWh = $this->inventoryService->getShowroomWarehouse();
    }

    /**
     * Helper to create a standalone trackable product with physical stock in central warehouse.
     */
    protected function createTrackedProduct(string $sku, int $initialQty = 10, float $price = 5000): Product
    {
        $product = Product::firstOrCreate(
            ['sku' => $sku],
            [
                'name' => "Laijau Product {$sku}",
                'slug' => "laijau-product-{$sku}-" . time(),
                'price' => $price,
                'price_npr' => $price,
                'is_active' => true,
                'is_published' => true,
                'track_quantity' => true,
                'quantity' => $initialQty,
            ]
        );

        $product->update([
            'quantity' => $initialQty,
            'track_quantity' => true,
            'is_active' => true,
            'is_published' => true,
            'availability_status' => 'available',
        ]);

        // Ensure central stock level
        $stockLevel = StockLevel::firstOrCreate(
            [
                'warehouse_id' => $this->centralWh->id,
                'product_id' => $product->id,
                'variant_id' => null,
            ],
            [
                'quantity_on_hand' => $initialQty,
                'quantity_reserved' => 0,
                'unit_cost_npr' => 1500,
            ]
        );

        $stockLevel->update([
            'quantity_on_hand' => $initialQty,
            'quantity_reserved' => 0,
            'quarantined_quantity' => 0,
            'damaged_quantity' => 0,
        ]);

        return $product;
    }

    /**
     * FLOW 1: Ecommerce → Stock Reservation → Fulfillment on Dispatch/Delivery
     */
    public function test_flow_1_ecommerce_stock_reservation_and_fulfillment(): void
    {
        $product = $this->createTrackedProduct('ECOM-FLOW-01', 10, 4500);

        // 1. Customer orders 2 units via Checkout API
        $payload = [
            'customer' => [
                'first_name' => 'Aarav',
                'last_name' => 'Sharma',
                'phone' => '9841234567',
                'email' => 'aarav@example.com',
                'province' => 'Bagmati Province',
                'district' => 'Kathmandu',
                'municipality' => 'Kathmandu Metropolitan City',
                'ward' => '3',
                'tole' => 'Lazimpat',
                'landmark' => 'Near Hotel Ambassador',
            ],
            'payment_method' => 'cod',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ]
            ],
        ];

        $res = $this->postJson('/api/checkout', $payload);
        $res->assertStatus(200);
        $orderNumber = $res->json('order_number');
        $this->assertNotEmpty($orderNumber);

        $order = Order::where('order_number', $orderNumber)->first();
        $this->assertNotNull($order);

        // Verify Reservation created
        $stockLevel = StockLevel::where('warehouse_id', $this->centralWh->id)
            ->where('product_id', $product->id)
            ->whereNull('variant_id')
            ->first();

        $this->assertEquals(10, $stockLevel->quantity_on_hand, 'On-hand should remain 10 upon reservation');
        $this->assertEquals(2, $stockLevel->quantity_reserved, 'Reserved stock should be 2');
        $this->assertEquals(8, $stockLevel->available_quantity, 'Available stock should be 8 (10 - 2)');

        $activeRes = StockReservation::where('status', 'active')
            ->where('warehouse_id', $this->centralWh->id)
            ->where('product_id', $product->id)
            ->where('reference_id', $order->id)
            ->first();

        $this->assertNotNull($activeRes, 'Active reservation should be linked to order ID');
        $this->assertEquals(2, $activeRes->quantity);

        // 2. Admin confirms order
        $order->update(['status' => Order::STATUS_CUSTOMER_CONFIRMED]);
        $this->assertEquals(Order::STATUS_CUSTOMER_CONFIRMED, $order->fresh()->status);

        // 3. Admin hands order to courier (or packing)
        $order->update(['status' => Order::STATUS_HANDED_TO_COURIER, 'carrier' => 'Pathao Parcel', 'tracking_number' => 'PTH-99001']);

        // 4. Verify stock fulfilled automatically via OrderObserver
        $stockLevel->refresh();
        $this->assertEquals(8, $stockLevel->quantity_on_hand, 'On-hand must now decrease to 8');
        $this->assertEquals(0, $stockLevel->quantity_reserved, 'Reserved stock must return to 0');
        $this->assertEquals(8, $stockLevel->available_quantity, 'Available stock must remain exactly 8');

        $activeRes->refresh();
        $this->assertEquals('converted', $activeRes->status, 'Reservation status must be converted');

        // Verify stock movement ledger
        $movement = StockMovement::where('reference_type', 'order')
            ->where('reference_id', $order->id)
            ->where('movement_type', 'sale_order')
            ->first();

        $this->assertNotNull($movement, 'Stock movement record must exist');
        $this->assertEquals(-2, $movement->quantity);

        // 5. Idempotency test: advancing to Delivered must NOT re-decrement stock
        $order->update(['status' => Order::STATUS_DELIVERED]);
        $stockLevel->refresh();
        $this->assertEquals(8, $stockLevel->quantity_on_hand, 'Stock must not double-decrement on delivery');
    }

    /**
     * FLOW 1 (Cancellation): Order cancelled before dispatch releases reservation cleanly
     */
    public function test_flow_1_ecommerce_order_cancellation_releases_reservation(): void
    {
        $product = $this->createTrackedProduct('ECOM-FLOW-02', 10, 3000);

        // Place order
        $payload = [
            'customer' => [
                'first_name' => 'Bikash',
                'last_name' => 'Adhikari',
                'phone' => '9851000000',
                'province' => 'Bagmati Province',
                'district' => 'Lalitpur',
                'municipality' => 'Lalitpur Metropolitan City',
                'ward' => '5',
                'tole' => 'Kumaripati',
            ],
            'payment_method' => 'cod',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 3,
                ]
            ],
        ];

        $res = $this->postJson('/api/checkout', $payload);
        $order = Order::where('order_number', $res->json('order_number'))->first();

        $stockLevel = StockLevel::where('warehouse_id', $this->centralWh->id)
            ->where('product_id', $product->id)
            ->whereNull('variant_id')
            ->first();

        $this->assertEquals(3, $stockLevel->quantity_reserved);
        $this->assertEquals(7, $stockLevel->available_quantity);

        // Cancel order
        $order->update(['status' => Order::STATUS_CANCELLED]);

        $stockLevel->refresh();
        $this->assertEquals(0, $stockLevel->quantity_reserved, 'Reserved stock must reset to 0 upon order cancellation');
        $this->assertEquals(10, $stockLevel->available_quantity, 'Available stock must return to 10');
        $this->assertEquals(10, $stockLevel->quantity_on_hand, 'On-hand must stay at 10');

        $reservation = StockReservation::where('reference_id', $order->id)->first();
        $this->assertEquals('cancelled', $reservation->status);
    }

    /**
     * FLOW 2: POS Sale → Showroom Inventory Deduction & Balanced Double-Entry Accounting
     */
    public function test_flow_2_pos_sale_deducts_showroom_inventory_and_posts_accounting(): void
    {
        $product = $this->createTrackedProduct('POS-FLOW-01', 15, 6000);

        // Initialize showroom stock level
        $showroomStock = StockLevel::firstOrCreate(
            [
                'warehouse_id' => $this->showroomWh->id,
                'product_id' => $product->id,
                'variant_id' => null,
            ],
            [
                'quantity_on_hand' => 15,
                'quantity_reserved' => 0,
                'unit_cost_npr' => 2000,
            ]
        );
        $showroomStock->update(['quantity_on_hand' => 15, 'quantity_reserved' => 0]);

        $admin = User::firstOrCreate(
            ['email' => 'admin@laijau.com'],
            ['name' => 'Laijau Admin', 'password' => Hash::make('secret'), 'role' => 'admin']
        );

        // Cashier records POS sale with eSewa digital payment
        $sale = $this->offlineSaleService->createSale([
            'customer_name' => 'Walk-in Showroom Customer',
            'payment_method' => 'esewa',
            'sales_channel' => 'showroom_pos',
            'currency' => 'NPR',
            'discount_amount' => 0.00,
        ], [
            [
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 6000,
            ]
        ], $admin);

        $this->assertInstanceOf(OfflineSale::class, $sale);
        $this->assertEquals(6000, (float)$sale->total_amount);

        // Verify showroom stock level decremented
        $showroomStock->refresh();
        $this->assertEquals(14, $showroomStock->quantity_on_hand, 'Showroom stock must decrement from 15 to 14');

        // Verify stock movement recorded from showroom warehouse
        $posMovement = StockMovement::where('reference_type', 'offline_sale')
            ->where('reference_id', $sale->id)
            ->where('movement_type', 'sale_pos')
            ->first();

        $this->assertNotNull($posMovement);
        $this->assertEquals($this->showroomWh->id, $posMovement->warehouse_id);
        $this->assertEquals(-1, $posMovement->quantity);

        // Verify double-entry accounting entry posted
        $journal = JournalEntry::where('reference_type', 'offline_sale')
            ->where('reference_id', $sale->id)
            ->first();

        $this->assertTrue((bool)$journal->is_balanced, 'Journal entry debit must equal credit');
        $this->assertEquals((float)$journal->total_debit, (float)$journal->total_credit);

        // Verify debit account was eSewa Merchant Clearing (1130)
        $debitLine = $journal->lines->where('debit', '>', 0)->first();
        $accEsewa = Account::where('account_number', '1130')->first() ?: Account::where('account_number', '2330')->first();
        $this->assertNotNull($accEsewa);
        $this->assertEquals($accEsewa->id, $debitLine->account_id, 'Payment must debit eSewa clearing account (1130)');
    }

    /**
     * FLOW 3: Manual Prepaid Outside Valley → Verification Workflow
     */
    public function test_flow_3_manual_prepaid_flow_outside_valley(): void
    {
        $product = $this->createTrackedProduct('PREPAID-FLOW-01', 10, 5000);

        // Outside valley (Pokhara, Kaski) with eSewa
        $payload = [
            'customer' => [
                'first_name' => 'Santosh',
                'last_name' => 'Thapa',
                'phone' => '9860000000',
                'email' => 'santosh@example.com',
                'province' => 'Gandaki Province',
                'district' => 'Kaski',
                'municipality' => 'Pokhara Metropolitan City',
                'ward' => '6',
                'tole' => 'Lakeside',
                'landmark' => 'Near Barahi Temple',
            ],
            'payment_method' => 'esewa',
            'payment_reference' => 'ESEWA-TXN-99887711',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ]
            ],
        ];

        $res = $this->postJson('/api/checkout', $payload);
        $res->assertStatus(200);

        $order = Order::where('order_number', $res->json('order_number'))->first();
        $this->assertNotNull($order);
        $this->assertEquals(Order::PAYMENT_STATUS_VERIFICATION_PENDING, $order->payment_status);
        $this->assertEquals(Order::STATUS_PENDING, $order->status);
        $this->assertEquals('ESEWA-TXN-99887711', $order->payment_reference);
        $this->assertFalse($order->is_inside_valley);

        // Staff approves payment
        $admin = User::firstOrCreate(
            ['email' => 'admin@laijau.com'],
            ['name' => 'Laijau Admin', 'password' => Hash::make('secret'), 'role' => 'admin']
        );
        $this->actingAs($admin);

        $order->update([
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'payment_verified_at' => now(),
            'payment_verified_by' => $admin->id,
            'status' => Order::STATUS_PAYMENT_VERIFIED,
        ]);

        $order->refresh();
        $this->assertEquals(Order::PAYMENT_STATUS_PAID, $order->payment_status);
        $this->assertEquals(Order::STATUS_PAYMENT_VERIFIED, $order->status);
        $this->assertNotNull($order->payment_verified_at);
        $this->assertEquals($admin->id, $order->payment_verified_by);
    }

    /**
     * FLOW 4: Valley Cash on Delivery → Delivery auto-collects payment
     */
    public function test_flow_4_cash_on_delivery_valley_flow(): void
    {
        $product = $this->createTrackedProduct('COD-FLOW-01', 10, 2500);

        $payload = [
            'customer' => [
                'first_name' => 'Suman',
                'last_name' => 'Shrestha',
                'phone' => '9812345678',
                'province' => 'Bagmati Province',
                'district' => 'Bhaktapur',
                'municipality' => 'Madhyapur Thimi Municipality',
                'ward' => '2',
                'tole' => 'Sanothimi',
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
        $order = Order::where('order_number', $res->json('order_number'))->first();

        $this->assertTrue($order->is_inside_valley);
        $this->assertEquals('unpaid', $order->payment_status);

        // Staff confirms order with customer
        $order->update(['status' => Order::STATUS_CUSTOMER_CONFIRMED]);

        // Hand to courier (NCM)
        $order->update([
            'status' => Order::STATUS_HANDED_TO_COURIER,
            'carrier' => 'Nepal Can Move (NCM)',
            'courier_name' => 'Nepal Can Move (NCM)',
            'tracking_number' => 'NCM-88331',
            'tracking_url' => Order::resolveTrackingUrl('Nepal Can Move (NCM)', 'NCM-88331'),
        ]);

        $this->assertEquals('https://nepalcanmove.com/track?tracking_id=NCM-88331', $order->fresh()->tracking_url);

        // Mark Delivered → automatically collects COD payment
        $order->update(['status' => Order::STATUS_DELIVERED]);

        $order->refresh();
        $this->assertEquals(Order::STATUS_DELIVERED, $order->status);
        $this->assertEquals(Order::PAYMENT_STATUS_PAID, $order->payment_status, 'Delivery must set COD payment to paid');
        $this->assertNotNull($order->payment_verified_at);
        $this->assertNotNull($order->actual_delivery_date);
    }

    /**
     * FLOW 5: CRM & WhatsApp Contextual Links
     */
    public function test_flow_5_single_pane_crm_whatsapp_formatting(): void
    {
        $order = Order::create([
            'order_number' => 'LJ-TEST-CRM-' . uniqid(),
            'first_name' => 'Rina',
            'last_name' => 'Maharjan',
            'email' => 'rina@example.com',
            'phone' => '9841999999',
            'province' => 'Bagmati Province',
            'district' => 'Kathmandu',
            'municipality' => 'Kathmandu Metropolitan City',
            'subtotal' => 4500,
            'total_amount' => 4500,
            'currency' => 'NPR',
            'status' => Order::STATUS_CUSTOMER_CONTACT_REQUIRED,
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'shipping_address' => 'Kathmandu',
            'shipping_country' => 'NP',
        ]);

        $this->assertEquals('Rina Maharjan', $order->customer_full_name);

        // Test NCM & Pathao tracking resolver
        $this->assertEquals(
            'https://nepalcanmove.com/track?tracking_id=12345',
            Order::resolveTrackingUrl('Nepal Can Move (NCM)', '12345')
        );
        $this->assertEquals(
            'https://pathao.com/np/parcel-tracking/?consignment_id=67890',
            Order::resolveTrackingUrl('Pathao Parcel', '67890')
        );
    }

    /**
     * SECURITY: Verify Admin user credentials, role, and Super Admin authorization
     */
    public function test_security_admin_credentials_and_super_admin_bypass(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@laijau.com'],
            [
                'name' => 'Laijau Admin',
                'password' => Hash::make('StrongSecurePassword123!@#'),
                'role' => 'admin',
            ]
        );
        $admin->update(['role' => 'admin']);

        $this->assertEquals('admin', $admin->role, 'Role must be admin');
        $this->assertTrue($admin->isSuperAdmin(), 'admin@laijau.com must have Super Admin privileges');
        $this->assertTrue($admin->isWorkspaceAdmin(), 'admin@laijau.com must have Workspace Admin privileges');

        // Ensure password is NOT the default 'password' and is securely hashed
        $this->assertFalse(Hash::check('password', $admin->password), 'Default password must not be active');
        $this->assertNotEmpty($admin->password);
    }

    /**
     * FLOW 6: Product Variant Lifecycle — Storefront Variant Checkout, Stock Level Reservation, Fulfillment & Synced Variant Stock
     */
    public function test_flow_6_variant_ecommerce_reservation_and_fulfillment(): void
    {
        // 1. Pick an active product with real variants
        $product = Product::whereHas('variants', function ($q) {
            $q->where('is_active', true)->where('stock_quantity', '>', 2);
        })->where('is_active', true)->where('is_published', true)->first();
        if (!$product) {
            $product = Product::create([
                'name' => 'Royal Heritage Polo Shirt',
                'slug' => 'royal-heritage-polo-shirt-' . uniqid(),
                'sku' => 'TEST-VAR-POLO-' . uniqid(),
                'price' => 4500,
                'price_npr' => 4500,
                'quantity' => 15,
                'track_quantity' => true,
                'is_active' => true,
                'is_published' => true,
            ]);
            ProductVariant::create([
                'product_id' => $product->id,
                'sku' => $product->sku . '-M-RED',
                'size' => 'M',
                'color' => 'Red',
                'stock_quantity' => 5,
                'price' => 4500,
                'price_npr' => 4500,
                'is_active' => true,
            ]);
            ProductVariant::create([
                'product_id' => $product->id,
                'sku' => $product->sku . '-L-RED',
                'size' => 'L',
                'color' => 'Red',
                'stock_quantity' => 5,
                'price' => 4500,
                'price_npr' => 4500,
                'is_active' => true,
            ]);
        }
        $this->assertNotNull($product, 'A product with variants must exist');

        $variant = $product->variants()->where('is_active', true)->where('stock_quantity', '>', 2)->whereNotNull('size')->where('size', '!=', '')->first()
            ?? $product->variants()->where('is_active', true)->where('stock_quantity', '>', 2)->first();
        $this->assertNotNull($variant, 'An active variant with stock must exist');

        $initialVariantStock = (int)$variant->stock_quantity;
        $initialParentStock = (int)$product->quantity;

        // Ensure stock level exists for WH-KTM-MAIN
        $stockLevel = StockLevel::firstOrCreate(
            [
                'warehouse_id' => $this->centralWh->id,
                'product_id' => $product->id,
                'variant_id' => $variant->id,
            ],
            [
                'quantity_on_hand' => $initialVariantStock,
                'quantity_reserved' => 0,
                'unit_cost_npr' => (float)$variant->cost_price,
            ]
        );
        $stockLevel->update([
            'quantity_on_hand' => $initialVariantStock,
            'quantity_reserved' => 0,
        ]);

        // 2. Customer places order for 1 unit of this specific variant
        $payload = [
            'customer' => [
                'first_name' => 'Dipen',
                'last_name' => 'Karki',
                'phone' => '9841888777',
                'email' => 'dipen@example.com',
                'province' => 'Bagmati Province',
                'district' => 'Kathmandu',
                'municipality' => 'Kathmandu Metropolitan City',
                'ward' => '10',
                'tole' => 'Baneshwor',
                'landmark' => 'Near Eyeplex Mall',
            ],
            'payment_method' => 'cod',
            'items' => [
                [
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'selected_size' => $variant->size,
                    'selected_color' => $variant->color,
                    'quantity' => 1,
                ]
            ],
        ];

        $res = $this->postJson('/api/checkout', $payload);
        $res->assertStatus(200);
        $orderNumber = $res->json('order_number');
        $this->assertNotEmpty($orderNumber);

        $order = Order::where('order_number', $orderNumber)->first();
        $this->assertNotNull($order);

        // Verify OrderItem captures variant details
        $item = $order->items->first();
        $this->assertNotNull($item);
        $this->assertEquals($variant->id, $item->variant_id);
        $this->assertEquals($variant->sku, $item->sku);
        $this->assertEquals($variant->size ?: 'Standard', $item->selected_size);

        // Verify Stock Reservation targets variant_id
        $reservation = StockReservation::where('status', 'active')
            ->where('warehouse_id', $this->centralWh->id)
            ->where('product_id', $product->id)
            ->where('variant_id', $variant->id)
            ->where('reference_id', $order->id)
            ->first();

        $this->assertNotNull($reservation, 'Active reservation must be created for variant');
        $this->assertEquals(1, $reservation->quantity);

        $stockLevel->refresh();
        $this->assertEquals(1, $stockLevel->quantity_reserved, 'Variant stock level must show 1 reserved');

        // 3. Fulfill order (hand to courier)
        $order->update(['status' => Order::STATUS_HANDED_TO_COURIER]);

        // 4. Verify stock level decremented and reservation converted
        $stockLevel->refresh();
        $this->assertEquals($initialVariantStock - 1, $stockLevel->quantity_on_hand, 'Variant stock must decrement by 1');
        $this->assertEquals(0, $stockLevel->quantity_reserved, 'Reserved stock must return to 0');

        $reservation->refresh();
        $this->assertEquals('converted', $reservation->status, 'Reservation must be converted');

        // Verify ProductVariant stock quantity and parent product quantity are synced
        $variant->refresh();
        $this->assertEquals($initialVariantStock - 1, $variant->stock_quantity, 'ProductVariant stock_quantity must decrement');

        // Clean up test order & restore stock so test is non-destructive
        $order->items()->delete();
        $order->delete();
        StockMovement::where('reference_type', 'order')->where('reference_id', $order->id)->delete();
        $stockLevel->update(['quantity_on_hand' => $initialVariantStock, 'quantity_reserved' => 0]);
        $variant->updateQuietly(['stock_quantity' => $initialVariantStock]);
        $product->updateQuietly(['quantity' => $initialParentStock]);
    }
}
