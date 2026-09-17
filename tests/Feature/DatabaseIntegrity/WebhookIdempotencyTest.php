<?php

declare(strict_types=1);

namespace Tests\Feature\DatabaseIntegrity;

use App\Http\Controllers\Api\CheckoutController;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockMovement;
use App\Models\Order;
use App\Models\Product;
use App\Services\Inventory\InventoryService;
use App\Services\Settings\SettingsService;
use Database\Seeders\ModuleSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WebhookIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected InventoryService $inventoryService;
    protected SettingsService $settingsService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModuleSettingsSeeder::class);
        $this->inventoryService = app(InventoryService::class);
        $this->settingsService = app(SettingsService::class);
        Mail::fake();
    }

    /**
     * Verify payment confirmation is strictly idempotent and executes stock deductions exactly once.
     */
    public function test_duplicate_payment_confirmation_is_strictly_idempotent(): void
    {
        // 1. Setup product with 10 units in stock
        $product = Product::create([
            'name' => 'Handcrafted Oxford Leather Shoes',
            'slug' => 'handcrafted-oxford-leather-shoes',
            'sku' => 'SHOES-OXF-001',
            'price' => 4500.00,
            'is_active' => true,
        ]);

        $wh = $this->inventoryService->getDefaultWarehouse();
        StockLevel::create([
            'warehouse_id' => $wh->id,
            'product_id' => $product->id,
            'quantity_on_hand' => 10,
            'unit_cost_npr' => 2200.00,
        ]);

        // 2. Setup pending order for 2 units
        $order = Order::create([
            'order_number' => 'ORD-TEST-001',
            'first_name' => 'Aarav',
            'last_name' => 'Sharma',
            'email' => 'aarav.sharma@example.com',
            'phone' => '9841234567',
            'shipping_address' => 'New Road, Kathmandu',
            'shipping_city' => 'Kathmandu',
            'shipping_country' => 'NP',
            'subtotal' => 9000.00,
            'total_amount' => 9000.00,
            'currency' => 'NPR',
            'status' => Order::STATUS_PENDING,
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'payment_method' => 'connectips',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'quantity' => 2,
            'unit_price' => 4500.00,
        ]);

        // First payment confirmation
        $confirmedOrder = CheckoutController::confirmOrderPayment($order, 'TXN_CONNECTIPS_123');

        // Verify order is paid
        $order->refresh();
        $this->assertEquals(Order::STATUS_PROCESSING, $order->status);
        $this->assertEquals('paid', $order->payment_status);

        // Verify stock deducted from 10 to 8
        $stockLevel = StockLevel::where('warehouse_id', $wh->id)->where('product_id', $product->id)->first();
        $this->assertEquals(8, $stockLevel->quantity_on_hand);

        $movementsCount = StockMovement::where('reference_type', 'order')->where('reference_id', $order->id)->count();
        $this->assertEquals(1, $movementsCount);

        // Second duplicate payment confirmation call with same transaction
        $dupOrder = CheckoutController::confirmOrderPayment($order, 'TXN_CONNECTIPS_123');

        // Verify stock was NOT decremented again (still 8, not 6!)
        $stockLevel->refresh();
        $this->assertEquals(8, $stockLevel->quantity_on_hand);

        // Verify no second movement created
        $this->assertEquals(1, StockMovement::where('reference_type', 'order')->where('reference_id', $order->id)->count());
    }

    /**
     * Verify concurrent confirmation calls execute atomically.
     */
    public function test_concurrent_confirmation_calls_are_atomic(): void
    {
        $product = Product::create([
            'name' => 'Premium Cotton Canvas Backpack',
            'slug' => 'premium-cotton-canvas-backpack',
            'sku' => 'BAG-CAN-01',
            'price' => 3200.00,
            'is_active' => true,
        ]);

        $wh = $this->inventoryService->getDefaultWarehouse();
        StockLevel::create([
            'warehouse_id' => $wh->id,
            'product_id' => $product->id,
            'quantity_on_hand' => 4,
            'unit_cost_npr' => 1400.00,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-TEST-002',
            'first_name' => 'Bikash',
            'last_name' => 'Thapa',
            'email' => 'bikash.thapa@example.com',
            'phone' => '9851098765',
            'shipping_address' => 'Kupondole, Lalitpur',
            'shipping_city' => 'Lalitpur',
            'shipping_country' => 'NP',
            'subtotal' => 3200.00,
            'total_amount' => 3200.00,
            'currency' => 'NPR',
            'status' => Order::STATUS_PENDING,
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'payment_method' => 'esewa',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'quantity' => 1,
            'unit_price' => 3200.00,
        ]);

        // Call confirmOrderPayment twice consecutively (simulating webhook + redirect)
        $confirmed1 = CheckoutController::confirmOrderPayment($order, 'ESEWA_REF_001');
        $confirmed2 = CheckoutController::confirmOrderPayment($order, 'ESEWA_REF_001');

        $this->assertEquals('paid', $confirmed1->payment_status);
        $this->assertEquals('paid', $confirmed2->payment_status);

        // Stock must be exactly 3 (4 - 1, not 2!)
        $stockLevel = StockLevel::where('warehouse_id', $wh->id)->where('product_id', $product->id)->first();
        $this->assertEquals(3, $stockLevel->quantity_on_hand);

        // Exactly 1 StockMovement record created
        $movements = StockMovement::where('reference_type', 'order')->where('reference_id', $order->id)->count();
        $this->assertEquals(1, $movements);
    }
}
