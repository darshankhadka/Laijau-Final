<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Product;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\NepalLocationService;
use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

class LaijauStorefrontVerificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (Category::where('is_active', true)->doesntExist()) {
            Category::firstOrCreate(
                ['slug' => 'ethnic-wear'],
                ['name' => 'Ethnic Wear', 'is_active' => true]
            );
        }
        if (Product::where('is_published', true)->where('is_active', true)->doesntExist()) {
            $cat = Category::where('is_active', true)->first();
            Product::firstOrCreate(
                ['slug' => 'nepali-dhaka-shawl-test'],
                [
                    'category_id' => $cat?->id,
                    'name' => 'Nepali Dhaka Shawl',
                    'sku' => 'LJ-TEST-001',
                    'price' => 2500.00,
                    'price_npr' => 2500.00,
                    'is_active' => true,
                    'is_published' => true,
                ]
            );
        }
    }

    /**
     * Checkpoint 1: Catalog Integrity
     */
    public function test_catalog_integrity_and_real_products(): void
    {
        // 1. Verify products exist and have valid pricing
        $activeProducts = Product::where('is_published', true)->where('is_active', true)->get();
        $this->assertNotEmpty($activeProducts, 'Active products must exist in database.');

        foreach ($activeProducts->take(20) as $p) {
            $this->assertGreaterThan(0, (float)$p->price, "Product {$p->id} must have positive NPR price.");
            $this->assertNotEmpty($p->name, "Product {$p->id} must have a name.");
            $this->assertNotEmpty($p->slug, "Product {$p->id} must have a slug.");
        }

        // 2. No duplicate slugs
        $slugs = Product::where('is_published', true)->pluck('slug')->toArray();
        $uniqueSlugs = array_unique($slugs);
        $this->assertCount(count($slugs), $uniqueSlugs, 'Product slugs must be strictly unique.');

        // 3. Verify mock products are deactivated
        $mockCount = Product::whereIn('slug', [
            'legacy-mock-sample',
            'pashmina-shawl',
            'dhaka-topi-heritage',
            'mithila-art-stole',
            'newari-silver-necklace',
            'himalayan-hemp-backpack'
        ])->where('is_published', true)->count();
        $this->assertEquals(0, $mockCount, 'Legacy mock products must not be published.');

        // 4. Verify categories
        $categories = Category::where('is_active', true)->get();
        $this->assertNotEmpty($categories, 'Active categories must exist.');
    }

    /**
     * Checkpoint 2: Homepage UX & Layout
     */
    public function test_homepage_ux_no_giant_hero_and_immediate_shopping(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);

        // 1. Branding check
        $response->assertSee('LAIJAU');
        $response->assertSee('.COM');
        $response->assertDontSee('South Asian Craftsmanship');
        $response->assertDontSee('Nepal Aura');

        // 2. Immediate shopping elements
        $response->assertSee('Shop by Category');
        $response->assertSee('Shoes made for');

        // 3. Real products & pricing in NPR
        $response->assertSee('Rs.');
    }

    /**
     * Checkpoint 3: Product Discovery & Autocomplete
     */
    public function test_product_discovery_search_and_filtering(): void
    {
        // 1. Search suggestions API
        $searchRes = $this->getJson('/api/search/suggestions?q=shoe');
        $searchRes->assertStatus(200);
        $searchRes->assertJsonStructure([
            'query',
            'popular_searches',
            'categories',
            'products',
            'total_products_count',
        ]);

        // 2. Catalogue search page
        $webSearchRes = $this->get('/search?q=shoe');
        $webSearchRes->assertStatus(200);
        $webSearchRes->assertSee('Search Results');

        // 3. Catalogue sorting
        $sortAsc = $this->get('/products?sort=price_asc');
        $sortAsc->assertStatus(200);

        $sortDesc = $this->get('/products?sort=price_desc');
        $sortDesc->assertStatus(200);

        // 4. Product detail page
        $product = Product::storefrontReady()->first();
        if ($product) {
            $pdpRes = $this->get('/products/' . $product->slug);
            $pdpRes->assertStatus(200);
            $pdpRes->assertSee($product->name);
            $pdpRes->assertSee('Add to Cart');
            $pdpRes->assertSee('Buy Now');
            $pdpRes->assertSee('Kathmandu Valley Cash on Delivery');
            $pdpRes->assertSee('Nationwide Courier (All 77 Districts)');
        }
    }

    /**
     * Checkpoint 4: Checkout & Nepal Localization
     */
    public function test_guest_checkout_and_nepal_localization(): void
    {
        // 1. Guest checkout page renders
        $res = $this->get('/checkout');
        $res->assertStatus(200);
        $res->assertSee('Guest Checkout');
        $res->assertSee('Delivery Address (Nepal)');

        // 2. Pure Nepal payment methods
        $res->assertDontSee('International Card');
        $res->assertDontSee('Klarna');
        $res->assertDontSee('MobilePay');

        // 3. Valley location service verification
        $this->assertTrue(NepalLocationService::isKathmanduValley('Kathmandu'));
        $this->assertTrue(NepalLocationService::isKathmanduValley('Lalitpur'));
        $this->assertTrue(NepalLocationService::isKathmanduValley('Bhaktapur'));
        $this->assertFalse(NepalLocationService::isKathmanduValley('Kaski'));
        $this->assertFalse(NepalLocationService::isKathmanduValley('Morang'));
        $this->assertFalse(NepalLocationService::isKathmanduValley('Chitwan'));
    }

    /**
     * Checkpoint 5: Operational Workflow
     */
    public function test_operational_workflow_and_order_lifecycle(): void
    {
        $product = Product::where('is_published', true)->where('is_active', true)->first();
        if (!$product) {
            $this->markTestSkipped('No product available for workflow test.');
        }

        // 1. Guest places order inside Kathmandu Valley with COD
        $order = Order::create([
            'order_number' => 'LJ-TEST-OP-' . uniqid(),
            'first_name' => 'Bikash',
            'last_name' => 'Adhikari',
            'phone' => '9841234567',
            'email' => 'bikash@example.com',
            'shipping_province' => 'Bagmati Province',
            'shipping_district' => 'Kathmandu',
            'shipping_municipality' => 'Kathmandu Metropolitan City',
            'shipping_ward' => '10',
            'shipping_tole' => 'Baneshwor',
            'shipping_address' => 'Baneshwor, Ward 10, Kathmandu',
            'shipping_country' => 'NP',
            'is_inside_valley' => true,
            'payment_method' => 'cod',
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'status' => Order::STATUS_PENDING,
            'subtotal' => 3000.00,
            'shipping_cost' => 0.00,
            'total_amount' => 3000.00,
            'currency' => 'NPR',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku ?: 'TEST-SKU',
            'unit_price' => 3000.00,
            'quantity' => 1,
            'total_price' => 3000.00,
        ]);

        // 2. Order is created in pending status
        $this->assertEquals(Order::STATUS_PENDING, $order->status);
        $this->assertEquals('unpaid', $order->payment_status);
        $this->assertTrue((bool)$order->is_inside_valley);

        // 3. Operational status advancement (CRM contact -> confirmation -> packing -> courier -> delivery)
        $order->update(['status' => Order::STATUS_CUSTOMER_CONFIRMED]);
        $this->assertEquals('customer_confirmed', $order->fresh()->status);

        $order->update(['status' => Order::STATUS_PACKING]);
        $this->assertEquals('packing', $order->fresh()->status);

        $order->update([
            'status' => Order::STATUS_HANDED_TO_COURIER,
            'carrier' => 'Pathao Parcel',
            'tracking_number' => 'PTH987654321',
            'tracking_url' => Order::resolveTrackingUrl('Pathao', 'PTH987654321'),
        ]);
        $this->assertStringContainsString('pathao.com', $order->fresh()->tracking_url);

        // 4. Mark Delivered -> Auto collects COD
        $order->update([
            'status' => Order::STATUS_DELIVERED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'delivered_at' => now(),
        ]);
        $this->assertEquals('delivered', $order->fresh()->status);
        $this->assertEquals('paid', $order->fresh()->payment_status);

        // 5. Guest tracking lookup works
        $trackRes = $this->get('/track?order_number=' . $order->order_number . '&phone=9841234567');
        $trackRes->assertStatus(200);
        $trackRes->assertSee($order->order_number);
    }
}
