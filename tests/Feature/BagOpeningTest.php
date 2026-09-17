<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class BagOpeningTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Customer', 'guard_name' => 'web']);

        Product::firstOrCreate(
            ['slug' => 'classic-oxford-shoes'],
            [
                'name' => 'Classic Oxford Shoes',
                'description' => 'Exquisite leather shoes handcrafted in Nepal.',
                'price' => 1200.00,
                'price_npr' => 1200.00,
                'price_npr' => 160.86,
                'quantity' => 10,
                'is_published' => true,
                'is_active' => true,
            ]
        );
    }

    /**
     * 1. Test Bag desktop trigger exists and has authoritative attributes
     */
    public function test_bag_desktop_trigger_exists_with_authoritative_handler(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);

        // Desktop header button
        $response->assertSee('id="header-cart-button"', false);
        $response->assertSee('data-open-cart', false);
        $response->assertSee('$store.store.openCartDrawer()', false);
    }

    /**
     * 2. Test Bag mobile bottom navigation trigger exists and has authoritative attributes
     */
    public function test_bag_mobile_bottom_trigger_exists_with_authoritative_handler(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);

        // Mobile bottom navigation nav has x-data
        $response->assertSee('<nav', false);
        $response->assertSee('x-data', false);

        // Mobile bottom navigation button
        $response->assertSee('id="mobile-nav-cart-button"', false);
        $response->assertSee('aria-label="Bag"', false);
    }

    /**
     * 3. Test Bag mobile slide-out menu trigger exists
     */
    public function test_bag_mobile_menu_trigger_exists(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);

        $response->assertSee('id="mobile-menu-cart-button"', false);
    }

    /**
     * 4. Test Cart Drawer component is embedded in DOM with x-data, x-cloak, z-[70]
     */
    public function test_cart_drawer_dom_structure_and_alpine_component(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);

        // Root cart drawer element
        $response->assertSee('id="cart-drawer"', false);
        $response->assertSee('z-[70]', false);
        $response->assertSee('x-show="$store.store.isCartDrawerOpen"', false);

        // Close button
        $response->assertSee('id="cart-drawer-close"', false);
        $response->assertSee('$store.store.closeCartDrawer()', false);

        // Empty state
        $response->assertSee('Your bag is empty', false);
        $response->assertSee('Continue Shopping', false);

        // Checkout & View Bag CTAs
        $response->assertSee('Proceed to Checkout', false);
        $response->assertSee('View Bag', false);
    }

    /**
     * 5. Test Bag triggers and drawer exist across diverse storefront pages
     */
    public function test_bag_triggers_and_drawer_exist_across_all_pages(): void
    {
        $urls = [
            '/',
            '/products',
            '/collections',
            '/cart',
            '/account',
        ];

        foreach ($urls as $url) {
            $res = $this->get($url);
            $res->assertStatus(200);
            $res->assertSee('id="header-cart-button"', false);
            $res->assertSee('id="cart-drawer"', false);
            $res->assertSee('$store.store.openCartDrawer()', false);
        }
    }

    /**
     * 6. Test product detail page contains Add to Bag and cart drawer
     */
    public function test_product_detail_page_has_bag_trigger_and_drawer(): void
    {
        $product = Product::storefrontReady()->first() ?: Product::create([
            'name' => 'Authentic Dhaka Topi Detail Test',
            'slug' => 'dhaka-topi-detail-test',
            'sku' => 'TOP-DETAIL-01',
            'price' => 1500,
            'is_published' => true,
            'is_active' => true,
            'featured_image' => 'products/dhaka-topi.jpg',
        ]);

        $response = $this->get('/products/' . ($product->slug ?: $product->id));
        $response->assertStatus(200);

        // Header trigger
        $response->assertSee('id="header-cart-button"', false);
        // Cart drawer modal
        $response->assertSee('id="cart-drawer"', false);
        // Add to bag button
        $response->assertSee('addToBag()', false);
    }

    /**
     * 7. Test Bag drawer and triggers remain functional for authenticated customer
     */
    public function test_bag_drawer_and_triggers_for_authenticated_customer(): void
    {
        $customer = User::create([
            'name' => 'Priya Sharma',
            'email' => 'priya.drawer.' . uniqid() . '@laijau.com',
            'password' => Hash::make('Password123!'),
        ]);
        $customer->assignRole('Customer');

        $response = $this->actingAs($customer, 'web')->get('/');
        $response->assertStatus(200);

        $response->assertSee('id="header-cart-button"', false);
        $response->assertSee('id="cart-drawer"', false);
        $response->assertSee('id="mobile-nav-cart-button"', false);
    }

    /**
     * 8. Test Bag drawer and triggers remain functional after customer logout
     */
    public function test_bag_drawer_and_triggers_after_logout(): void
    {
        $customer = User::create([
            'name' => 'Roshan Thapa',
            'email' => 'roshan.drawer.' . uniqid() . '@laijau.com',
            'password' => Hash::make('Password123!'),
        ]);
        $customer->assignRole('Customer');

        $this->actingAs($customer, 'web');
        $this->post('/logout');

        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('id="header-cart-button"', false);
        $response->assertSee('id="cart-drawer"', false);
    }
}
