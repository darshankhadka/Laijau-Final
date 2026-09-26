<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class OfflineSalesRoutingVerificationTest extends TestCase
{
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::where('role', 'admin')->first()
            ?? User::factory()->create(['role' => 'admin']);
    }

    public function test_all_four_offline_sales_routes_resolve_for_authenticated_admin(): void
    {
        $routes = [
            '/intadmin/offline-sales/POS',
            '/intadmin/offline-sales/history',
            '/intadmin/offline-sales/dashboard',
            '/intadmin/offline-sales/metrics',
        ];

        foreach ($routes as $url) {
            $response = $this->actingAs($this->admin, 'admin')->get($url);
            $response->assertStatus(200);
        }
    }

    public function test_pos_route_is_isolated_fullscreen_without_filament_sidebar_or_topbar(): void
    {
        $response = $this->actingAs($this->admin, 'admin')->get('/intadmin/offline-sales/POS');
        $response->assertStatus(200);

        // Verify POS owns the entire viewport via dedicated fullscreen container
        $response->assertSee('lj-pos-fullscreen-app', false);

        // Verify normal Filament admin shell is bypassed
        $response->assertDontSee('fi-sidebar', false);
        $response->assertDontSee('fi-topbar', false);

        // Verify internal POS header is present while internal navigation links are removed to keep POS focused on selling
        $response->assertSee('lj-pos-topbar', false);
        $response->assertDontSee('<nav class="lj-tab-nav"', false);
        $response->assertDontSee('/intadmin/offline-sales/history', false);
        $response->assertDontSee('/intadmin/offline-sales/dashboard', false);
        $response->assertDontSee('/intadmin/offline-sales/metrics', false);
    }

    public function test_history_dashboard_metrics_retain_filament_admin_shell(): void
    {
        $adminRoutes = [
            '/intadmin/offline-sales/history',
            '/intadmin/offline-sales/dashboard',
            '/intadmin/offline-sales/metrics',
        ];

        foreach ($adminRoutes as $url) {
            $response = $this->actingAs($this->admin, 'admin')->get($url);
            $response->assertStatus(200);
            $response->assertSee('fi-sidebar', false);
            $response->assertSee('fi-topbar', false);
            // Verify they still have the POS navigation links
            $response->assertSee('/intadmin/offline-sales/POS', false);
            $response->assertSee('/intadmin/offline-sales/history', false);
            $response->assertSee('/intadmin/offline-sales/dashboard', false);
            $response->assertSee('/intadmin/offline-sales/metrics', false);
        }
    }

    public function test_entry_points_target_pos_in_new_tab(): void
    {
        // 1. Sidebar navigation item for POS
        $navItems = \App\Filament\Pages\OfflineSales::getNavigationItems();
        $this->assertNotEmpty($navItems);
        $posNavItem = $navItems[0];
        $this->assertEquals(url('/intadmin/offline-sales/POS'), $posNavItem->getUrl());
        $this->assertTrue($posNavItem->shouldOpenUrlInNewTab());

        // 2. Dashboard Quick Action
        $dashboard = new \App\Filament\Pages\Dashboard();
        $refMethod = new \ReflectionMethod($dashboard, 'getHeaderActions');
        $refMethod->setAccessible(true);
        $actions = $refMethod->invoke($dashboard);
        $posAction = null;
        foreach ($actions as $action) {
            if ($action->getName() === 'pos_sale') {
                $posAction = $action;
                break;
            }
        }
        $this->assertNotNull($posAction);
        $this->assertEquals(url('/intadmin/offline-sales/POS'), $posAction->getUrl());
        $this->assertTrue($posAction->shouldOpenUrlInNewTab());

        // 3. Mobile Bottom Nav
        $mobileNavBlade = file_get_contents(resource_path('views/filament/components/mobile-bottom-nav.blade.php'));
        $this->assertStringContainsString("url('/intadmin/offline-sales/POS')", $mobileNavBlade);
        $this->assertStringContainsString('target="_blank"', $mobileNavBlade);

        // 4. Keyboard Shortcuts (F2)
        $shortcutJs = file_get_contents(public_path('js/retail-keyboard-shortcuts.js'));
        $this->assertStringContainsString("window.open('/intadmin/offline-sales/POS', '_blank')", $shortcutJs);
    }

    public function test_pos_camera_scanner_integration_and_permissions_policy_compatibility(): void
    {
        // 1. POS view contains the camera scanner trigger and modal
        $response = $this->actingAs($this->admin, 'admin')->get('/intadmin/offline-sales/POS');
        $response->assertStatus(200);
        $response->assertSee('open-pos-camera', false);
        $response->assertSee('lj-pos-camera-btn', false);
        $response->assertSee('posCameraScanner()', false);
        $response->assertSee('/js/html5-qrcode.min.js', false);

        // 2. Hardware camera scanner library file exists
        $this->assertFileExists(public_path('js/html5-qrcode.min.js'));

        // 3. Security policies allow camera on self and required media streams/blobs
        $htaccessPath = public_path('.htaccess');
        $this->assertFileExists($htaccessPath);
        $content = file_get_contents($htaccessPath);

        // Permissions-Policy permits camera on self
        $this->assertStringContainsString('Header always set Permissions-Policy', $content);
        $this->assertStringContainsString('camera=(self)', $content);

        // Content-Security-Policy permits media streams and blob URIs for camera video/captures
        $this->assertStringContainsString('Header always set Content-Security-Policy', $content);
        $this->assertStringContainsString('media-src', $content);
        $this->assertStringContainsString('mediastream:', $content);
        $this->assertStringContainsString('blob:', $content);
    }
}

