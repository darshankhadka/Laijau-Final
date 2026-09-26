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

        $station = \App\Models\Hardware\PosStation::first() ?? \App\Models\Hardware\PosStation::create(['name' => 'POS Terminal 1', 'station_code' => 'POS-T1', 'is_active' => true]);
        try {
            app(\App\Services\Pos\PosSessionService::class)->openSession($station->id, 1000.0, $admin);
        } catch (\Throwable $e) {}

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

        $station = \App\Models\Hardware\PosStation::first() ?? \App\Models\Hardware\PosStation::create(['name' => 'POS Terminal 1', 'station_code' => 'POS-T1', 'is_active' => true]);
        try {
            app(\App\Services\Pos\PosSessionService::class)->openSession($station->id, 1000.0, $admin);
        } catch (\Throwable $e) {}

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

    public function test_storefront_mobile_header_is_responsive_and_prevents_overflow(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);

        // Verify account and wishlist are hidden sm:flex to prevent mobile header blowout
        $response->assertSee('hidden sm:flex items-center gap-2', false);
        $response->assertSee('hidden sm:flex relative p-2', false);

        // Verify announcement bar has overflow safety
        $response->assertSee('min-w-0 flex-1 flex items-center gap-2 overflow-hidden', false);
    }

    public function test_pos_mobile_header_supports_full_tab_navigation_and_usb_scanner_buffer(): void
    {
        $posBlade = file_exists(resource_path('views/filament/pages/offline-sales/styles.blade.php'))
            ? file_get_contents(resource_path('views/filament/pages/offline-sales.blade.php')) .
              file_get_contents(resource_path('views/filament/pages/offline-sales/header.blade.php')) .
              file_get_contents(resource_path('views/filament/pages/offline-sales/scripts.blade.php')) .
              file_get_contents(resource_path('views/filament/pages/offline-sales/styles.blade.php'))
            : file_get_contents(resource_path('views/filament/pages/offline-sales.blade.php'));

        // Mobile topbar uses order 3 for tab navigation and nowrap topbar
        $this->assertStringContainsString('order: 3 !important', $posBlade);
        $this->assertStringContainsString('overflow-x: auto !important', $posBlade);

        // Hardware USB barcode scanner buffer & threshold
        $this->assertStringContainsString('barcodeBuffer', $posBlade);
        $this->assertStringContainsString('timeDiff > 120', $posBlade);
        $this->assertStringContainsString('handleBarcodeScan', $posBlade);
    }

    public function test_pos_and_offline_receipt_include_80mm_thermal_printing_styles_and_autoprint(): void
    {
        $posBlade = file_exists(resource_path('views/filament/pages/offline-sales/styles.blade.php'))
            ? file_get_contents(resource_path('views/filament/pages/offline-sales.blade.php')) .
              file_get_contents(resource_path('views/filament/pages/offline-sales/scripts.blade.php')) .
              file_get_contents(resource_path('views/filament/pages/offline-sales/styles.blade.php'))
            : file_get_contents(resource_path('views/filament/pages/offline-sales.blade.php'));
        $receiptBlade = file_get_contents(resource_path('views/offline-receipt.blade.php'));

        // Check 80mm thermal paper configuration
        $this->assertStringContainsString('size: 80mm auto', $posBlade);
        $this->assertStringContainsString('size: 80mm auto', $receiptBlade);

        // Check autoprint support
        $this->assertStringContainsString('sale-completed-print', $posBlade);
        $this->assertStringContainsString('lj_pos_autoprint', $posBlade);
        $this->assertStringContainsString("urlParams.has('autoprint')", $receiptBlade);
    }

    public function test_pos_internal_documents_display_non_tax_invoice_notice_and_tax_invoices_remain_clean(): void
    {
        $disclaimer = 'THIS IS NOT A TAX INVOICE. FOR LAIJAU INTERNAL USE ONLY. PLEASE RETAIN YOUR TAX INVOICE FROM THE COUNTER.';

        // 1. Offline thermal receipt
        $offlineReceipt = file_get_contents(resource_path('views/offline-receipt.blade.php'));
        $this->assertStringContainsString($disclaimer, $offlineReceipt);

        // 2. POS order receipt
        $posReceipt = file_get_contents(resource_path('views/pos-receipt.blade.php'));
        $this->assertStringContainsString($disclaimer, $posReceipt);

        // 3. POS terminal modal
        $posPage = file_exists(resource_path('views/filament/pages/offline-sales/print/thermal.blade.php'))
            ? file_get_contents(resource_path('views/filament/pages/offline-sales/print/thermal.blade.php'))
            : file_get_contents(resource_path('views/filament/pages/offline-sales.blade.php'));
        $this->assertStringContainsString($disclaimer, $posPage);

        // 4. Warehouse packing slip
        $packingSlip = file_get_contents(resource_path('views/print/packing-slip.blade.php'));
        $this->assertStringContainsString($disclaimer, $packingSlip);

        // 5. Official Statutory Tax Invoice MUST NOT have the disclaimer
        $taxInvoice = file_get_contents(resource_path('views/print/invoice.blade.php'));
        $this->assertStringNotContainsString($disclaimer, $taxInvoice);
        $this->assertStringContainsString('Retail Tax Invoice', $taxInvoice);
        $this->assertStringContainsString('कर बिजक / Tax Compliant', $taxInvoice);
    }
}
