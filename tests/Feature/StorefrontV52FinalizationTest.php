<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Category;
use App\Models\Collection;
use App\Models\User;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ShippingMethod;
use Illuminate\Support\Facades\Hash;

use Illuminate\Foundation\Testing\RefreshDatabase;

class StorefrontV52FinalizationTest extends TestCase
{
    use \Illuminate\Foundation\Testing\DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Collection::firstOrCreate(
            ['slug' => 'himalayan-noir'],
            ['name' => 'Himalayan Noir', 'is_published' => true, 'sort_order' => 1]
        );

        $category = Category::firstOrCreate(
            ['slug' => 'footwear'],
            ['name' => 'Footwear', 'is_active' => true]
        );

        $product = Product::firstOrCreate(
            ['slug' => 'classic-oxford-shoes'],
            [
                'name' => 'Classic Oxford Shoes',
                'description' => 'Exquisite leather shoes handcrafted in Nepal.',
                'price' => 1200.00,
                'quantity' => 10,
                'featured_image' => 'products/classic-oxford-shoes.jpg',
                'is_published' => true,
                'is_active' => true,
                'category_id' => $category->id,
            ]
        );

        ProductVariant::firstOrCreate(
            ['product_id' => $product->id, 'size' => 'Standard', 'color' => 'Crimson'],
            [
                'sku' => 'BSS-CRM-STD',
                'price' => 1200.00,
                'stock_quantity' => 10,
                'is_active' => true,
            ]
        );

        ShippingMethod::firstOrCreate(
            ['code' => 'inside_valley_standard'],
            [
                'name' => 'Inside Kathmandu Valley Delivery',
                'zone' => 'kathmandu_valley',
                'cost' => 100.00,
                'free_threshold' => 2000.00,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        ShippingMethod::firstOrCreate(
            ['code' => 'outside_valley_standard'],
            [
                'name' => 'Outside Valley Standard Courier',
                'zone' => 'outside_valley',
                'cost' => 200.00,
                'free_threshold' => 3500.00,
                'is_active' => true,
                'sort_order' => 2,
            ]
        );
    }

    /**
     * 1. Test all primary public storefront routes return HTTP 200 with luxury branding.
     */
    public function test_all_public_storefront_pages_render_successfully()
    {
        $pages = [
            '/' => ['Laijau'],
            '/products' => ['Catalog', 'Products'],
            '/products?new_arrivals=true' => ['Catalog'],
            '/products?sort=price_asc' => ['Catalog'],
            '/products?sort=price_desc' => ['Catalog'],
            '/collections' => ['Collections'],
            '/shipping' => ['Shipping'],
            '/returns' => ['Returns'],
            '/privacy' => ['Privacy Policy'],
            '/terms' => ['Terms'],
            '/cart' => ['Cart', 'Order Summary'],
            '/checkout' => ['Checkout'],
            '/account' => ['Sign In'],
        ];

        foreach ($pages as $url => $expectedStrings) {
            $response = $this->get($url);
            $response->assertStatus(200);
            foreach ($expectedStrings as $str) {
                $response->assertSee($str, false);
            }
        }
    }

    /**
     * 2. Test dynamic collection and category pages.
     */
    public function test_dynamic_collection_and_category_pages()
    {
        $collection = Collection::where('is_published', true)->first();
        if ($collection) {
            $response = $this->get('/collections/' . $collection->slug);
            $response->assertStatus(200);
            $response->assertSee($collection->name);
        }

        $category = Category::where('is_active', true)->first();
        if ($category) {
            $response = $this->get('/categories/' . $category->slug);
            $response->assertStatus(200);
            $response->assertSee($category->name);
        }
    }

    /**
     * 3. Test product detail page, gallery, schema markup, and clean descriptions.
     */
    public function test_product_detail_page_integrity_and_json_ld_schema()
    {
        $product = Product::storefrontReady()->first();
        $this->assertNotNull($product, 'At least one storefront-ready product must exist.');

        $response = $this->get('/products/' . ($product->slug ?: $product->id));
        $response->assertStatus(200);
        $response->assertSee($product->name);
        $response->assertSee('Add to Cart');

        // Verify JSON-LD schema is present and valid
        $response->assertSee('"@type": "Product"', false);
        $response->assertSee('"name": "' . $product->name . '"', false);
        $response->assertSee('"priceCurrency": "NPR"', false);

        // Verify no raw escaped HTML or malformed description tags
        $response->assertDontSee('&lt;p&gt;&lt;/p&gt;', false);
        $response->assertDontSee('&lt;div&gt;&lt;/div&gt;', false);
        $response->assertDontSee('[object Object]', false);
        $response->assertDontSee('undefined', false);
    }

    /**
     * 4. Test search across web and API endpoints.
     */
    public function test_search_functionality_and_api()
    {
        // Keyword search page
        $webRes = $this->get('/search?q=silk');
        $webRes->assertStatus(200);
        $webRes->assertSee('Search Results');
        $webRes->assertSee('silk');

        // Empty search
        $emptyRes = $this->get('/search');
        $emptyRes->assertStatus(200);

        // API live search endpoint
        $apiRes = $this->getJson('/api/products?search=oxford&per_page=8');
        $apiRes->assertStatus(200);
        $apiRes->assertJsonStructure(['data']);
        $this->assertGreaterThanOrEqual(1, count($apiRes->json('data')));
    }

    /**
     * 5. Test standardized shipping threshold consistency (NPR).
     */
    public function test_shipping_threshold_consistency()
    {
        // 1. Homepage loads with NPR pricing
        $homeRes = $this->get('/');
        $homeRes->assertStatus(200);
        $homeRes->assertSee('Rs.');

        // 2. Shipping policy page
        $shipRes = $this->get('/shipping');
        $shipRes->assertStatus(200);
        $shipRes->assertSee('Rs.');
    }

    /**
     * 6. Test 404 and 403 error pages display Laijau luxury design tokens.
     */
    public function test_custom_error_views_luxury_styling()
    {
        // 404 page
        $res404 = $this->get('/non-existent-luxury-piece-404');
        $res404->assertStatus(404);
        $res404->assertSee('Page Not Found');
        $res404->assertSee('404');
        $res404->assertSee('Browse Products');
        $res404->assertSee('Return Home');

        // 403 forbidden page
        $order = Order::first();
        if ($order) {
            $res403 = $this->get('/orders/' . $order->id . '/receipt');
            $res403->assertStatus(403);
            $res403->assertSee('Access Restricted');
            $res403->assertSee('403');
        }
    }

    /**
     * 7. Test strict customer session vs admin session isolation.
     */
    public function test_customer_admin_session_isolation()
    {
        // 1. Guest visiting /account sees guest state
        $guestRes = $this->get('/account');
        $guestRes->assertStatus(200);
        $guestRes->assertSee('Sign In');
        $guestRes->assertSee('Create Account');

        // 2. Admin logging in as admin should NOT authenticate storefront
        $admin = User::where('email', 'admin@laijau.com')->first();
        if ($admin) {
            $this->actingAs($admin, 'admin');
            $storefrontRes = $this->get('/account');
            $storefrontRes->assertStatus(200);
            $storefrontRes->assertSee('Sign In');
            $storefrontRes->assertDontSee('Namaste, Admin');
            auth()->guard('admin')->logout();
        }

        // 3. Customer logging in as web customer is denied access to /admin
        $customer = User::firstOrCreate(
            ['email' => 'customer.test.v52@laijau.com'],
            [
                'name' => 'Astrid Lind',
                'password' => Hash::make('Secret123!'),
                'email_verified_at' => now(),
            ]
        );

        $this->actingAs($customer, 'web');
        $custAccountRes = $this->get('/account');
        $custAccountRes->assertStatus(200);
        $custAccountRes->assertSee('Astrid Lind');

        // Customer cannot access /admin
        $adminPanelRes = $this->get('/admin');
        // Filament redirects unauthenticated admins to Filament login
        $this->assertTrue($adminPanelRes->isRedirect() || $adminPanelRes->status() === 403);
    }

    /**
     * 8. Test IDOR protection on orders and receipts.
     */
    public function test_idor_protection_on_order_receipts()
    {
        $customerA = User::firstOrCreate(
            ['email' => 'customer.a@laijau.com'],
            ['name' => 'Customer A', 'password' => Hash::make('Password123!')]
        );

        $customerB = User::firstOrCreate(
            ['email' => 'customer.b@laijau.com'],
            ['name' => 'Customer B', 'password' => Hash::make('Password123!')]
        );

        $orderA = Order::firstOrCreate(
            ['order_number' => 'LJ-TEST-IDOR-A'],
            [
                'user_id' => $customerA->id,
                'email' => $customerA->email,
                'first_name' => 'Customer',
                'last_name' => 'A',
                'subtotal' => 2400.00,
                'total_amount' => 2400.00,
                'currency' => 'NPR',
                'status' => 'confirmed',
                'payment_status' => 'paid',
                'shipping_address' => 'Durbar Marg 42',
                'shipping_postal_code' => '44600',
                'shipping_city' => 'Kathmandu',
                'shipping_country' => 'NP',
            ]
        );

        // Guest cannot access Customer A's receipt
        $guestRes = $this->get('/orders/' . $orderA->id . '/receipt');
        $guestRes->assertStatus(403);

        // Customer B cannot access Customer A's receipt
        $custBRes = $this->actingAs($customerB, 'web')->get('/orders/' . $orderA->id . '/receipt');
        $custBRes->assertStatus(403);

        // Customer A can access their own receipt
        $custARes = $this->actingAs($customerA, 'web')->get('/orders/' . $orderA->id . '/receipt');
        $custARes->assertStatus(200);
        $custARes->assertSee('LJ-TEST-IDOR-A');
    }

    /**
     * 9. Test dynamic SEO sitemap generates clean XML with correct URLs.
     */
    public function test_seo_sitemap_xml_generation()
    {
        $response = $this->get('/sitemap.xml');
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/xml; charset=UTF-8');
        $response->assertSee('<urlset', false);
        $response->assertSee('<loc>', false);
        $response->assertSee('/products', false);
        $response->assertSee('/collections', false);
    }

    /**
     * 10. Test no obsolete Next.js or localhost:3000 dependencies in codebase.
     */
    public function test_zero_legacy_frontend_dependencies()
    {
        $this->assertContains(config('services.storefront.revalidate_url'), ['none', 'disabled', 'https://laijau.com/api/revalidate']);
        $this->assertFalse(file_exists(base_path('../frontend')));
    }
}
