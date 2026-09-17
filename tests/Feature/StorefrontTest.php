<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Product;
use App\Models\Category;
use App\Models\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }
    public function test_homepage_loads_successfully()
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('Laijau');
        $response->assertDontSee('Nepal Aura');
        $response->assertDontSee('Made for Modern nprope');
    }

    public function test_catalogue_loads_successfully()
    {
        $response = $this->get('/products');
        $response->assertStatus(200);
        $response->assertSee('Catalog');
        $response->assertSee('Products');
    }

    public function test_catalogue_new_arrivals_filter()
    {
        $response = $this->get('/products?new_arrivals=true');
        $response->assertStatus(200);
    }

    public function test_catalogue_sorting()
    {
        $response = $this->get('/products?sort=price_asc');
        $response->assertStatus(200);
    }

    public function test_product_detail_page_loads()
    {
        $product = Product::storefrontReady()->first();
        if ($product) {
            $response = $this->get('/products/' . ($product->slug ?: $product->id));
            $response->assertStatus(200);
            $response->assertSee($product->name);
            $response->assertSee('Add to Cart');
        } else {
            $this->markTestSkipped('No published product in database.');
        }
    }

    public function test_collections_and_categories_pages()
    {
        $response = $this->get('/collections');
        $response->assertStatus(200);

        $collection = Collection::where('is_published', true)->first();
        if ($collection) {
            $res = $this->get('/collections/' . $collection->slug);
            $res->assertStatus(200);
            $res->assertSee($collection->name);
        }

        $category = Category::first();
        if ($category) {
            $res = $this->get('/categories/' . $category->slug);
            $res->assertStatus(200);
            $res->assertSee($category->name);
        }
    }

    public function test_policy_and_content_pages()
    {
        $this->get('/shipping')->assertStatus(200);
        $this->get('/returns')->assertStatus(200)->assertSee('Returns');
        $this->get('/privacy')->assertStatus(200)->assertSee('Privacy Policy');
        $this->get('/terms')->assertStatus(200)->assertSee('Terms');
    }

    public function test_cart_and_checkout_pages()
    {
        $this->get('/cart')->assertStatus(200)->assertSee('Cart');
        $this->get('/checkout')->assertStatus(200)->assertSee('Checkout');
        $this->get('/account')->assertStatus(200)->assertSee('Sign In');
    }

    public function test_storefront_catalogue_search_and_api_search()
    {
        // 1. Web search page loads
        $response = $this->get('/search?q=shoes');
        $response->assertStatus(200);
        $response->assertSee('Search Results');

        // 2. Empty query redirects or displays cleanly
        $emptyRes = $this->get('/search');
        $emptyRes->assertStatus(200);

        // 3. API product live search endpoint
        $apiRes = $this->getJson('/api/products?search=shoes');
        $apiRes->assertStatus(200);
        $apiRes->assertJsonStructure(['data']);
    }

    public function test_shopping_bag_features()
    {
        // 1. Cart page loads
        $response = $this->get('/cart');
        $response->assertStatus(200);
        $response->assertSee('Cart');
        $response->assertSee('Order Summary');
        $response->assertSee('Proceed to Checkout');

        // 2. Cart drawer component rendered in storefront layout
        $homeRes = $this->get('/');
        $homeRes->assertStatus(200);
        $homeRes->assertSee('Cart');
    }

    public function test_account_portal_and_order_search()
    {
        // 1. Guest view includes authentication, password reset, and registration
        $guestRes = $this->get('/account');
        $guestRes->assertStatus(200);
        $guestRes->assertSee('Sign In');
        $guestRes->assertSee('Forgot Password?');
        $guestRes->assertSee('Create Account');

        // 2. Authenticated customer view includes tabs and live order search
        $user = \App\Models\User::first();
        if ($user) {
            $authRes = $this->actingAs($user)->get('/account');
            $authRes->assertStatus(200);
            $authRes->assertSee('Welcome back,');
            $authRes->assertSee('Delivery Addresses');

            // 3. Authenticated customer orders API
            \Laravel\Sanctum\Sanctum::actingAs($user);
            $apiOrdersRes = $this->getJson('/api/user/orders');
            $apiOrdersRes->assertStatus(200);
        }
    }

    public function test_admin_created_product_immediately_appears_on_storefront()
    {
        // 1. Simulate Admin creating a product in Filament
        $testProduct = Product::create([
            'name' => 'Royal Amber Leather Boots Test',
            'slug' => 'royal-amber-leather-boots-test',
            'price' => 2400.00,
            'featured_image' => 'products/royal-amber-leather-boots-test.jpg',
            'quantity' => 5,
            'track_quantity' => true,
            'is_published' => true,
            'is_active' => true,
            'is_new_arrival' => true,
            'short_description' => 'Test handcrafted boots created by administrator.',
        ]);

        try {
            // 2. Immediate public visibility check on /products
            $response = $this->get('/products');
            $response->assertStatus(200);
            $response->assertSee('Royal Amber Leather Boots Test');

            // 3. Immediate public visibility check on product detail
            $detailRes = $this->get('/products/royal-amber-leather-boots-test');
            $detailRes->assertStatus(200);
            $detailRes->assertSee('Royal Amber Leather Boots Test');

            // 4. Simulate Admin editing the price in Filament
            $testProduct->update([
                'name' => 'Royal Emerald Leather Boots Test Updated',
            ]);

            // 5. Immediate reflection on storefront
            $updatedRes = $this->get('/products');
            $updatedRes->assertStatus(200);
            $updatedRes->assertSee('Royal Emerald Leather Boots Test Updated');

            // 6. Simulate Admin unpublishing the product in Filament
            $testProduct->update([
                'is_published' => false,
            ]);

            // 7. Immediate disappearance from public catalogue
            $unpubRes = $this->get('/products');
            $unpubRes->assertStatus(200);
            $unpubRes->assertDontSee('Royal Emerald Leather Boots Test Updated');
        } finally {
            // Clean up test record
            $testProduct->delete();
        }
    }

    public function test_collection_detail_and_sitemap()
    {
        $collection = \App\Models\Collection::where('is_published', true)->first();
        if ($collection) {
            $response = $this->get('/collections/' . $collection->slug);
            $response->assertStatus(200);
            $response->assertSee($collection->name);
        }

        $sitemap = $this->get('/sitemap.xml');
        $sitemap->assertStatus(200);
        $sitemap->assertHeader('Content-Type', 'text/xml; charset=UTF-8');
    }
}
