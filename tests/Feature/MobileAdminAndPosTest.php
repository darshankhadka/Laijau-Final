<?php

namespace Tests\Feature;

use App\Filament\Pages\OfflineSales;
use App\Models\User;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MobileAdminAndPosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getAdminUser(): User
    {
        return User::firstOrCreate(
            ['email' => 'admin@laijau.com'],
            [
                'name' => 'Admin User',
                'password' => bcrypt('Laijau2026!'),
                'role' => 'admin',
            ]
        );
    }

    public function test_global_mobile_bottom_dock_renders_on_admin_pages(): void
    {
        $admin = $this->getAdminUser();

        $response = $this->actingAs($admin, 'admin')->get('/intadmin');
        $response->assertStatus(200);

        // Verify mobile dock and its 5 core destinations
        $response->assertSee('id="lj-mobile-bottom-dock"', false);
        $response->assertSee('lj-mobile-bottom-nav', false);
        $response->assertSee('POS', false);
        $response->assertSee('Orders', false);
        $response->assertSee('Stock', false);
        $response->assertSee('Dispatch', false);
        $response->assertSee('Menu', false);
        $response->assertSee('$store.sidebar', false);
    }

    public function test_pos_renders_mobile_segmented_controls_and_responsive_panels(): void
    {
        $admin = $this->getAdminUser();

        $response = $this->actingAs($admin, 'admin')->get('/intadmin/offline-sales');
        $response->assertStatus(200);

        // Verify Alpine state initialization
        $response->assertSee('mobilePosTab', false);

        // Verify segmented toggle
        $response->assertSee('lj-pos-mobile-toggle', false);
        $response->assertSee('Products Catalog', false);
        $response->assertSee('Cart', false);

        // Verify mobile panel visibility bindings
        $response->assertSee(":class=\"mobilePosTab !== 'catalog' ? 'mobile-hidden' : ''\"", false);
        $response->assertSee(":class=\"mobilePosTab !== 'cart' ? 'mobile-hidden' : ''\"", false);

        // Verify mobile return to catalog button in cart panel
        $response->assertSee('lj-mobile-back-btn', false);
        $response->assertSee('Back to Products Catalog', false);
    }

    public function test_pos_livewire_component_shows_floating_bar_when_items_in_cart(): void
    {
        $admin = $this->getAdminUser();

        $product = Product::create([
            'name' => 'Mobile Test Denim Jacket ' . uniqid(),
            'slug' => 'mobile-test-denim-jacket-' . uniqid(),
            'sku' => 'MBL-TEST-' . uniqid(),
            'price' => 4500,
            'is_active' => true,
            'is_published' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'VAR-' . uniqid(),
            'price' => 4500,
            'stock_quantity' => 20,
            'color' => 'Crimson Red',
            'is_active' => true,
        ]);

        $component = Livewire::actingAs($admin, 'admin')
            ->test(OfflineSales::class)
            ->call('addToCart', $product->id, $variant->id, 1);

        // Cart now has items
        $component->assertSeeHtml('lj-pos-mobile-floating-bar');
        $component->assertSee('View Cart & Pay', false);
        $component->assertSeeHtml('Rs. 4,500');
    }

    public function test_pos_checkout_modal_contains_mobile_bottom_sheet_classes(): void
    {
        $admin = $this->getAdminUser();

        $product = Product::create([
            'name' => 'Cotton Hoodie Set ' . uniqid(),
            'slug' => 'cotton-hoodie-set-' . uniqid(),
            'sku' => 'HOODIE-' . uniqid(),
            'price' => 2500,
            'is_active' => true,
            'is_published' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'HOODIE-VAR-' . uniqid(),
            'price' => 2500,
            'stock_quantity' => 15,
            'is_active' => true,
        ]);

        $component = Livewire::actingAs($admin, 'admin')
            ->test(OfflineSales::class)
            ->call('addToCart', $product->id, $variant->id, 1)
            ->call('openCheckoutModal');

        // Modal should be open
        $component->assertSeeHtml('lj-modal-card');
        $component->assertSee('Complete Showroom Sale');
        $component->assertSeeHtml('lj-tender-grid');
        $component->assertSeeHtml('lj-quick-tender-row');
        $component->assertSee('Cash Received from Customer');
        $component->assertSee('COMPLETE SALE', false);
    }

    public function test_mobile_admin_css_includes_touch_and_ios_zoom_protections(): void
    {
        $cssPath = public_path('css/laijau-admin-v2.css');
        $this->assertFileExists($cssPath);

        $css = file_get_contents($cssPath);

        // Check for 16px font-size to prevent iOS zoom
        $this->assertStringContainsString('font-size: 16px !important', $css);

        // Check for touch-action manipulation
        $this->assertStringContainsString('touch-action: manipulation', $css);

        // Check for safe area insets
        $this->assertStringContainsString('env(safe-area-inset-bottom', $css);

        // Check for mobile bottom dock styles
        $this->assertStringContainsString('lj-mobile-bottom-nav', $css);

        // Check for modal bottom sheet styles
        $this->assertStringContainsString('fi-modal-window', $css);
        $this->assertStringContainsString('border-top-left-radius: 1.25rem', $css);
    }

    public function test_mobile_bottom_dock_is_strictly_hidden_on_desktop_viewports(): void
    {
        $cssPath = public_path('css/laijau-admin-v2.css');
        $css = file_get_contents($cssPath);

        // Check that mobile bottom dock is strictly hidden on desktop (min-width: 1024px)
        $this->assertStringContainsString('@media (min-width: 1024px)', $css);
        $this->assertStringContainsString('#lj-mobile-bottom-dock', $css);
        $this->assertStringContainsString('display: none !important', $css);

        // Check that component itself has scoped fallback styles for desktop hiding
        $bladePath = resource_path('views/filament/components/mobile-bottom-nav.blade.php');
        $blade = file_get_contents($bladePath);
        $this->assertStringContainsString('@media (min-width: 1024px)', $blade);
        $this->assertStringContainsString('display: none !important', $blade);
    }
}
