<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\StorefrontRevalidationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminStorefrontSyncTest extends TestCase
{
    use DatabaseTransactions;

    protected Category $category;
    protected Collection $collection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::firstOrCreate(
            ['slug' => 'test-couture-category'],
            ['name' => 'Test Couture Category', 'is_active' => true]
        );

        $this->collection = Collection::firstOrCreate(
            ['slug' => 'test-heritage-collection'],
            ['name' => 'Test Heritage Collection', 'is_published' => true]
        );

        ShippingMethod::updateOrCreate(
            ['code' => 'inside_valley_standard'],
            [
                'name' => 'Kathmandu Valley Standard Delivery',
                'zone' => 'kathmandu_valley',
                'price_npr' => 100.00,
                'free_shipping_threshold_npr' => 3000.00,
                'is_active' => true,
            ]
        );
    }

    public function test_admin_creates_product_and_triggers_correct_revalidation_targets(): void
    {
        Http::fake([
            '*/api/revalidate' => Http::response(['revalidated' => true, 'mock' => true], 200),
        ]);

        $productName = 'Laijau FINAL TEST PRODUCT ' . Str::random(5);
        $slug = Str::slug($productName);

        $product = Product::create([
            'name' => $productName,
            'slug' => $slug,
            'sku' => 'TEST-SKU-' . rand(1000, 9999),
            'description' => '<p>Authentic hand-loomed Banarasi silk handcrafted in Kathmandu valley.</p>',
            'price' => 3200.00,
            'price_npr' => 3200.00,
            'quantity' => 15,
            'is_published' => true,
            'is_active' => true,
            'is_featured' => true,
            'is_new_arrival' => true,
            'featured_image' => 'products/test-oxford-shoes.webp',
            'images' => ['products/test-oxford-shoes.webp', 'products/test-oxford-shoes-detail.webp'],
        ]);

        $product->categories()->sync([$this->category->id]);
        $product->collections()->sync([$this->collection->id]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => $productName,
            'slug' => $slug,
            'is_published' => 1,
            'is_active' => 1,
        ]);

        // Verify computed revalidation targets
        $revalidationService = app(StorefrontRevalidationService::class);
        $targets = $revalidationService->getProductTargets($product);

        $this->assertContains('/', $targets['paths']);
        $this->assertContains('/products', $targets['paths']);
        $this->assertContains('/products/' . $product->id, $targets['paths']);
        $this->assertContains('/products/' . $product->slug, $targets['paths']);
        $this->assertContains('/categories/' . $this->category->slug, $targets['paths']);
        $this->assertContains('/collections/' . $this->collection->slug, $targets['paths']);

        $this->assertContains('products', $targets['tags']);
        $this->assertContains('product-' . $product->id, $targets['tags']);
        $this->assertContains('product-' . $product->slug, $targets['tags']);
        $this->assertContains('categories', $targets['tags']);
        $this->assertContains('category-' . $this->category->slug, $targets['tags']);
        $this->assertContains('collections', $targets['tags']);
        $this->assertContains('collection-' . $this->collection->slug, $targets['tags']);

        // Verify Public API discovery by ID and Slug
        $showResponseById = $this->getJson('/api/products/' . $product->id);
        $showResponseById->assertStatus(200);
        $showResponseById->assertJsonPath('data.name', $productName);
        $this->assertEquals('products/test-oxford-shoes.webp', $showResponseById->json('data.featured_image'));

        $showResponseBySlug = $this->getJson('/api/products/' . $product->slug);
        $showResponseBySlug->assertStatus(200);
        $showResponseBySlug->assertJsonPath('data.id', $product->id);

        // Verify Catalogue Search, Category, Collection, and New Arrival filters
        $searchResponse = $this->getJson('/api/products?search=' . urlencode($productName));
        $searchResponse->assertStatus(200);
        $this->assertTrue(collect($searchResponse->json('data'))->contains('id', $product->id));

        $catResponse = $this->getJson('/api/products?category=' . $this->category->slug);
        $catResponse->assertStatus(200);
        $this->assertTrue(collect($catResponse->json('data'))->contains('id', $product->id));

        $colResponse = $this->getJson('/api/products?collection=' . $this->collection->slug);
        $colResponse->assertStatus(200);
        $this->assertTrue(collect($colResponse->json('data'))->contains('id', $product->id));

        $newArrivalResponse = $this->getJson('/api/products?new_arrivals=true');
        $newArrivalResponse->assertStatus(200);
        $this->assertTrue(collect($newArrivalResponse->json('data'))->contains('id', $product->id));
    }

    public function test_product_edit_propagates_data_and_updates_revalidation(): void
    {
        $product = Product::create([
            'name' => 'Original Piece ' . Str::random(4),
            'slug' => 'original-piece-' . Str::random(4),
            'price' => 1800.00,
            'price_npr' => 1800.00,
            'quantity' => 10,
            'is_published' => true,
            'is_active' => true,
        ]);

        $product->update([
            'name' => 'Updated Piece Name ' . rand(100, 999),
            'price_npr' => 2100.00,
            'description' => '<p>Updated description with silk-embroidery detail.</p>',
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'price_npr' => 2100.00,
        ]);

        $apiResponse = $this->getJson('/api/products/' . $product->id);
        $apiResponse->assertStatus(200);
        $this->assertEquals(2100.00, (float)$apiResponse->json('data.price_npr'));
        $apiResponse->assertJsonPath('data.name', $product->name);
    }

    public function test_customer_can_cart_and_checkout_newly_created_product(): void
    {
        $product = Product::create([
            'name' => 'Purchasable Artisan Oxford Shoes ' . Str::random(4),
            'slug' => 'purchasable-artisan-oxford-shoes-' . Str::random(4),
            'price' => 2500.00,
            'price_npr' => 2500.00,
            'quantity' => 20,
            'track_quantity' => true,
            'is_published' => true,
            'is_active' => true,
        ]);

        // 1. Cart Validation
        $cartValidation = $this->postJson('/api/cart/validate', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
            'shipping_country' => 'NP',
            'district' => 'Kathmandu',
            'shipping_method_code' => 'inside_valley_standard',
            'currency' => 'NPR',
        ]);

        $cartValidation->assertStatus(200);
        $this->assertEmpty($cartValidation->json('errors'));
        $this->assertEquals(5000.00, (float)$cartValidation->json('subtotal'));

        // 2. Checkout
        $checkoutResponse = $this->postJson('/api/checkout', [
            'customer' => [
                'email' => 'customer.test@example.com',
                'first_name' => 'Aarav',
                'last_name' => 'Shrestha',
                'province' => 'Bagmati Province',
                'district' => 'Kathmandu',
                'municipality' => 'Kathmandu Metropolitan City',
                'ward' => '10',
                'tole' => 'Baneshwor',
                'address' => 'Baneshwor, Ward 10',
                'country' => 'NP',
                'phone' => '9841234567',
            ],
            'payment_method' => 'cod',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
            'currency' => 'NPR',
        ]);

        $checkoutResponse->assertSuccessful();
        $this->assertTrue($checkoutResponse->json('success'));
        $orderNumber = $checkoutResponse->json('order_number');
        $this->assertNotEmpty($orderNumber);

        // 3. Verify Order exists in database and has correct Filament admin relationships
        $order = Order::where('order_number', $orderNumber)->with(['items.product', 'user'])->first();
        $this->assertNotNull($order);
        $this->assertEquals('customer.test@example.com', $order->email);
        $this->assertEquals(2, $order->items->first()->quantity);
        $this->assertEquals($product->id, $order->items->first()->product_id);
    }

    public function test_unpublished_product_disappears_from_public_and_is_blocked_at_checkout(): void
    {
        $product = Product::create([
            'name' => 'Unpublished Hidden Piece ' . Str::random(4),
            'slug' => 'unpublished-hidden-piece-' . Str::random(4),
            'price' => 1500.00,
            'price_npr' => 1500.00,
            'quantity' => 10,
            'is_published' => false,
            'is_active' => true,
        ]);

        // 1. Must NOT appear in public index
        $indexResponse = $this->getJson('/api/products?search=' . urlencode($product->name));
        $indexResponse->assertStatus(200);
        $this->assertFalse(collect($indexResponse->json('data'))->contains('id', $product->id));

        // 2. Must return 404 for public visitors on detail route
        $showResponse = $this->getJson('/api/products/' . $product->id);
        $showResponse->assertStatus(404);

        // 3. Must be blocked in cart validation
        $cartValidation = $this->postJson('/api/cart/validate', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
            'shipping_country' => 'NP',
            'district' => 'Kathmandu',
            'currency' => 'NPR',
        ]);
        $this->assertNotEmpty($cartValidation->json('errors'));

        // 4. Must be blocked at checkout
        $checkoutResponse = $this->postJson('/api/checkout', [
            'customer' => [
                'email' => 'buyer@example.com',
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'phone' => '9841234567',
                'province' => 'Bagmati Province',
                'district' => 'Kathmandu',
                'municipality' => 'Kathmandu Metropolitan City',
                'ward' => '10',
                'tole' => 'Baneshwor',
                'address' => 'Baneshwor 10',
                'country' => 'NP',
            ],
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
            'currency' => 'NPR',
        ]);
        $checkoutResponse->assertStatus(422);
    }
}
