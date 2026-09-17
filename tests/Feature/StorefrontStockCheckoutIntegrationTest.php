<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Inventory\StockLevel;
use App\Models\Inventory\Warehouse;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Inventory\InventoryService;
use Database\Seeders\ModuleSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontStockCheckoutIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected InventoryService $inventoryService;
    protected Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModuleSettingsSeeder::class);
        $this->inventoryService = app(InventoryService::class);

        $this->warehouse = $this->inventoryService->getDefaultWarehouse();
    }

    public function test_product_page_displays_in_stock_variants(): void
    {
        $uniqueId = uniqid();
        $product = Product::create([
            'name' => 'Brown Microfiber Boots',
            'slug' => 'brown-microfiber-boots-' . $uniqueId,
            'sku' => 'TEST-1001Brown5K-' . $uniqueId,
            'type' => 'footwear',
            'price' => 3000.00,
            'price_npr' => 3000.00,
            'featured_image' => 'products/test-boot.webp',
            'is_published' => true,
            'is_active' => true,
            'track_quantity' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'TEST-1001Brown5K-40-' . $uniqueId,
            'size' => '40',
            'color' => 'Brown',
            'price' => 3000.00,
            'price_npr' => 3000.00,
            'is_active' => true,
            'stock_quantity' => 0,
        ]);

        // Add 6 units of physical stock to the variant
        StockLevel::create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'quantity_on_hand' => 6,
            'quantity_reserved' => 0,
        ]);

        // Sync legacy stock attributes
        $this->inventoryService->syncLegacyStockAttributes($product->id);

        $variant->refresh();
        $product->refresh();

        $this->assertEquals(6, $variant->stock_quantity);
        $this->assertEquals(6, $product->quantity);

        // Verify storefront product page loads 200 and reflects stock
        $response = $this->get('/products/' . $product->slug);
        $response->assertStatus(200);
        $response->assertSee('Brown Microfiber Boots');
        $response->assertSee('Add to Cart');
        $response->assertSee('Buy Now');
        $response->assertSee('availableStock: 6');
    }

    public function test_checkout_reserves_stock_and_rejects_insufficient_stock(): void
    {
        $uniqueId = uniqid();
        $product = Product::create([
            'name' => 'Basketball Style Sneakers',
            'slug' => 'basketball-style-sneakers-' . $uniqueId,
            'sku' => 'TEST-140855-' . $uniqueId,
            'type' => 'footwear',
            'price' => 2500.00,
            'price_npr' => 2500.00,
            'is_published' => true,
            'is_active' => true,
            'track_quantity' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'TEST-140855-44-' . $uniqueId,
            'size' => '44',
            'color' => 'Black',
            'price' => 2500.00,
            'price_npr' => 2500.00,
            'is_active' => true,
            'stock_quantity' => 2,
        ]);

        $stockLevel = StockLevel::create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'quantity_on_hand' => 2,
            'quantity_reserved' => 0,
        ]);

        $this->inventoryService->syncLegacyStockAttributes($product->id);

        // 1. Order 1 unit - Should succeed
        $orderData = [
            'customer' => [
                'first_name' => 'Suman',
                'last_name' => 'Shrestha',
                'phone' => '9841234567',
                'province' => 'Bagmati',
                'district' => 'Kathmandu',
                'municipality' => 'Kathmandu Metro',
                'ward' => '10',
                'tole' => 'New Baneshwor',
            ],
            'payment_method' => 'cod',
            'items' => [
                [
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'quantity' => 1,
                ],
            ],
        ];

        $response = $this->postJson('/api/checkout/process', $orderData);
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $stockLevel->refresh();
        $this->assertEquals(1, $stockLevel->quantity_reserved);
        $this->assertEquals(2, $stockLevel->quantity_on_hand);
        $this->assertEquals(1, $stockLevel->quantity_available);

        // Sync again - Available stock should now be 1
        $this->inventoryService->syncLegacyStockAttributes($product->id);
        $variant->refresh();
        $this->assertEquals(1, $variant->stock_quantity);

        // 2. Attempt to order 2 units when only 1 is available - Should be rejected with 422
        $orderData2 = [
            'customer' => [
                'first_name' => 'Aayush',
                'last_name' => 'KC',
                'phone' => '9841987654',
                'province' => 'Bagmati',
                'district' => 'Kathmandu',
                'municipality' => 'Kathmandu Metro',
                'ward' => '3',
                'tole' => 'Lazimpat',
            ],
            'payment_method' => 'cod',
            'items' => [
                [
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'quantity' => 2,
                ],
            ],
        ];

        $response2 = $this->postJson('/api/checkout/process', $orderData2);
        $response2->assertStatus(422);
        $response2->assertJson(['success' => false]);
        $this->assertStringContainsString('Insufficient stock', $response2->json('message'));
    }

    public function test_order_fulfillment_deducts_inventory_atomically(): void
    {
        $uniqueId = uniqid();
        $product = Product::create([
            'name' => 'Inspired Black Microfiber Boots',
            'slug' => 'inspired-black-microfiber-boots-' . $uniqueId,
            'sku' => 'TEST-1350BlackOiled-' . $uniqueId,
            'type' => 'footwear',
            'price' => 4500.00,
            'price_npr' => 4500.00,
            'is_published' => true,
            'is_active' => true,
            'track_quantity' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'TEST-1350BlackOiled-39-' . $uniqueId,
            'size' => '39',
            'color' => 'Black',
            'price' => 4500.00,
            'price_npr' => 4500.00,
            'is_active' => true,
            'stock_quantity' => 4,
        ]);

        $stockLevel = StockLevel::create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'quantity_on_hand' => 4,
            'quantity_reserved' => 0,
        ]);

        $this->inventoryService->syncLegacyStockAttributes($product->id);

        // Place order
        $orderData = [
            'customer' => [
                'first_name' => 'Bikash',
                'last_name' => 'Gurung',
                'phone' => '9801234567',
                'province' => 'Bagmati',
                'district' => 'Lalitpur',
                'municipality' => 'Lalitpur Metro',
                'ward' => '2',
                'tole' => 'Patan',
            ],
            'payment_method' => 'cod',
            'items' => [
                [
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'quantity' => 2,
                ],
            ],
        ];

        $response = $this->postJson('/api/checkout/process', $orderData);
        $response->assertStatus(200);

        $orderId = $response->json('order_id');
        $order = Order::find($orderId);
        $this->assertNotNull($order);

        // Stock reserved
        $stockLevel->refresh();
        $this->assertEquals(2, $stockLevel->quantity_reserved);
        $this->assertEquals(4, $stockLevel->quantity_on_hand);

        // Fulfill order
        $this->inventoryService->fulfillOrderStock($order);

        $stockLevel->refresh();
        $variant->refresh();
        $product->refresh();

        // 2 units deducted permanently from warehouse on-hand
        $this->assertEquals(2, $stockLevel->quantity_on_hand);
        $this->assertEquals(0, $stockLevel->quantity_reserved);
        $this->assertEquals(2, $variant->stock_quantity);
        $this->assertEquals(2, $product->quantity);
    }

    public function test_sync_legacy_stock_attributes_includes_unassigned_stock_and_variant_stock(): void
    {
        $product = Product::create([
            'name' => 'Compass Pant Test',
            'slug' => 'compass-pant-test',
            'sku' => 'TEST-CP-01',
            'type' => 'apparel',
            'price' => 1500.00,
            'price_npr' => 1500.00,
            'is_published' => false,
            'is_active' => true,
            'track_quantity' => true,
        ]);

        $var30 = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'TEST-CP-01-30',
            'size' => '30',
            'price' => 1500.00,
            'price_npr' => 1500.00,
            'stock_quantity' => 0,
            'is_active' => true,
        ]);

        $var32 = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'TEST-CP-01-32',
            'size' => '32',
            'price' => 1500.00,
            'price_npr' => 1500.00,
            'stock_quantity' => 0,
            'is_active' => true,
        ]);

        // Stock for var30: 5 units
        StockLevel::create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $product->id,
            'variant_id' => $var30->id,
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
        ]);

        // Stock for var32: 3 units
        StockLevel::create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $product->id,
            'variant_id' => $var32->id,
            'quantity_on_hand' => 3,
            'quantity_reserved' => 0,
        ]);

        // Stock unassigned (variant_id = null): 2 units
        StockLevel::create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $product->id,
            'variant_id' => null,
            'quantity_on_hand' => 2,
            'quantity_reserved' => 0,
        ]);

        $this->inventoryService->syncLegacyStockAttributes($product->id);

        $var30->refresh();
        $var32->refresh();
        $product->refresh();

        $this->assertEquals(5, $var30->stock_quantity);
        $this->assertEquals(3, $var32->stock_quantity);
        // Total product quantity must be 5 + 3 + 2 = 10 units!
        $this->assertEquals(10, $product->quantity);
    }
}
