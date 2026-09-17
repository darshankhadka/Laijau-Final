<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageSettings;
use App\Models\Setting;
use App\Models\SettingAuditLog;
use App\Models\User;
use App\Services\StoreSettingsService;
use App\Services\CurrencyService;
use App\Services\MailSettingsService;
use App\Services\PaymentSettingsService;
use App\Services\TaxCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Reset maintenance mode to false for clean test baseline
        Setting::set('maintenance_mode', '0');
    }

    /**
     * Test 1: Settings CRUD, caching, and cache invalidation.
     */
    public function test_settings_crud_and_cache_invalidation(): void
    {
        Setting::set('test_sample_key', 'Retail_Value_1');
        $this->assertEquals('Retail_Value_1', Setting::get('test_sample_key'));

        // Direct cached verification
        $cached = Setting::allCached();
        $this->assertEquals('Retail_Value_1', $cached['test_sample_key']);

        // Update
        Setting::set('test_sample_key', 'Retail_Value_Updated');
        $this->assertEquals('Retail_Value_Updated', Setting::get('test_sample_key'));
    }

    /**
     * Test 2: Sensitive secrets are encrypted at rest and never exposed unmasked.
     */
    public function test_sensitive_secrets_encrypted_at_rest_and_masked(): void
    {
        $plainSecret = 'nchl_live_secret_key_1234567890abcdef';
        Setting::setSecret('connectips_secret_key', $plainSecret);

        // Verify stored in DB with 'enc:' prefix
        $rawInDb = Setting::where('key', 'connectips_secret_key')->value('value');
        $this->assertStringStartsWith('enc:', $rawInDb);
        $this->assertNotEquals($plainSecret, $rawInDb);

        // Verify decrypted by getSecret
        $decrypted = Setting::getSecret('connectips_secret_key');
        $this->assertEquals($plainSecret, $decrypted);

        // Verify masked representation
        $masked = Setting::maskSecret($decrypted);
        $this->assertStringContainsString('••••••••••••', $masked);
        $this->assertStringStartsWith('nchl_live_', $masked);
        $this->assertStringEndsWith('cdef', $masked);

        // Verify that passing masked secret back does NOT overwrite existing secret
        Setting::setSecret('connectips_secret_key', $masked);
        $this->assertEquals($plainSecret, Setting::getSecret('connectips_secret_key'));
    }

    /**
     * Test 3: Public Settings API never leaks secrets.
     */
    public function test_public_settings_api_never_exposes_secrets(): void
    {
        Setting::setSecret('connectips_secret_key', 'nchl_VerySecretKey9999');
        Setting::setSecret('smtp_password', 'MySuperSecretSmtpPass123!');

        $response = $this->getJson('/api/settings');
        $response->assertStatus(200);

        $jsonString = $response->getContent();
        $this->assertStringNotContainsString('nchl_VerySecretKey9999', $jsonString);
        $this->assertStringNotContainsString('MySuperSecretSmtpPass123!', $jsonString);
        $this->assertStringNotContainsString('enc:', $jsonString);
    }

    /**
     * Test 4: Role permissions enforce server-side access and restrictions.
     */
    public function test_role_permissions_enforce_server_side_authorization(): void
    {
        $uid = uniqid();
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => "superadmin_{$uid}@laijau.com",
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);
        $superAdmin->assignRole('Super Admin');

        $workspaceAdmin = User::create([
            'name' => 'Workspace Admin',
            'email' => "manager_{$uid}@laijau.com",
            'password' => bcrypt('password'),
            'role' => 'workspace_admin',
        ]);
        $workspaceAdmin->assignRole('Workspace Admin');

        $viewer = User::create([
            'name' => 'Viewer',
            'email' => "viewer_{$uid}@laijau.com",
            'password' => bcrypt('password'),
            'role' => 'viewer',
        ]);
        $viewer->assignRole('Viewer');

        $customer = User::create([
            'name' => 'Customer',
            'email' => "customer_{$uid}@laijau.com",
            'password' => bcrypt('password'),
            'role' => 'customer',
        ]);
        $customer->assignRole('Customer');

        // Customer cannot access settings
        $this->assertFalse($customer->canViewSettings());

        // Super Admin, Workspace Admin, and Viewer can view settings
        $this->assertTrue($superAdmin->canViewSettings());
        $this->assertTrue($workspaceAdmin->canViewSettings());
        $this->assertTrue($viewer->canViewSettings());

        // Super Admin has full permissions
        $this->assertTrue($superAdmin->canManageAllSettings());
        $this->assertTrue($superAdmin->canManageBusinessSettings());

        // Workspace Admin can manage business but not all (secrets/maintenance)
        $this->assertTrue($workspaceAdmin->canManageBusinessSettings());
        $this->assertFalse($workspaceAdmin->canManageAllSettings());

        // Viewer cannot manage business or all
        $this->assertFalse($viewer->canManageBusinessSettings());
        $this->assertFalse($viewer->canManageAllSettings());
    }

    /**
     * Test 5: Read-only viewers cannot save settings in Filament.
     */
    public function test_read_only_viewer_cannot_save_settings(): void
    {
        $uid = uniqid();
        $viewer = User::create([
            'name' => 'Viewer User',
            'email' => "viewer_save_{$uid}@laijau.com",
            'password' => bcrypt('password'),
            'role' => 'viewer',
        ]);
        $viewer->assignRole('Viewer');

        Setting::set('store_tagline', 'Original Tagline');

        Livewire::actingAs($viewer)
            ->test(ManageSettings::class)
            ->set('store_tagline', 'Malicious Modified Tagline')
            ->call('saveSettings')
            ->assertNotified('Access Denied');

        // Verify DB remains untouched
        $this->assertEquals('Original Tagline', Setting::get('store_tagline'));
    }

    /**
     * Test 6: Storefront Maintenance Middleware behavior.
     */
    public function test_storefront_maintenance_middleware(): void
    {
        // 1. When maintenance is disabled, storefront is accessible
        Setting::set('maintenance_mode', '0');
        $resp = $this->get('/');
        $resp->assertStatus(200);

        // 2. When maintenance is enabled, non-whitelisted visitors get 503
        Setting::set('maintenance_mode', '1');
        Setting::set('maintenance_allowed_ips', '198.51.100.1');
        Setting::set('maintenance_message', 'Scheduled Maintenance in Progress');

        $respMaint = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.50'])->get('/');
        $respMaint->assertStatus(503);
        $respMaint->assertSee('Scheduled Maintenance in Progress');

        // Whitelisted IP bypasses maintenance
        $respWhitelisted = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])->get('/');
        $respWhitelisted->assertStatus(200);

        // 3. Admin routes bypass maintenance
        $respAdmin = $this->get('/intadmin/login');
        $this->assertTrue(in_array($respAdmin->status(), [200, 302]));

        // 4. Critical health route bypasses maintenance
        $respUp = $this->get('/up');
        $this->assertTrue(in_array($respUp->status(), [200, 302]));

        // 5. Authenticated admin user bypasses maintenance
        $admin = User::where('email', 'admin@laijau.com')->first() ?: User::factory()->create([
            'email' => 'admin@laijau.com',
            'role' => 'admin',
        ]);
        $admin->assignRole('Super Admin');

        $respAuthed = $this->actingAs($admin, 'web')->withServerVariables(['REMOTE_ADDR' => '203.0.113.50'])->get('/');
        $respAuthed->assertStatus(200);

        // Turn maintenance off
        Setting::set('maintenance_mode', '0');
    }

    /**
     * Test 7: Nepal IRD Statutory VAT calculations.
     */
    public function test_tax_calculator_service_dynamic_settings(): void
    {
        $taxService = app(TaxCalculatorService::class);

        Setting::set('vat_enabled', '1');
        Setting::set('default_vat_rate', '13.0');
        Setting::set('display_prices_with_vat', '1');

        // Nepal VAT: 13% included in Rs. 1,130
        $npCalc = $taxService->calculate('NP', 1130.0);
        $this->assertEquals(13.0, $npCalc['rate']);
        // 1130 - (1130 / 1.13) = 130.00
        $this->assertEquals(130.0, $npCalc['amount']);

        // When VAT is disabled
        Setting::set('vat_enabled', '0');
        $disabledCalc = $taxService->calculate('NP', 1130.0);
        $this->assertEquals(0.0, $disabledCalc['rate']);
        $this->assertEquals(0.0, $disabledCalc['amount']);

        // Reset
        Setting::set('vat_enabled', '1');
    }

    /**
     * Test 8: Centralized Currency Service formatting (NPR only).
     */
    public function test_currency_service_conversions(): void
    {
        $currencyService = app(CurrencyService::class);

        $this->assertEquals('NPR', $currencyService->getDefaultCurrency());
        $this->assertTrue($currencyService->isValidCurrency('NPR'));
        $this->assertEquals(100.0, $currencyService->convert(100.0, 'NPR', 'NPR'));

        // Formatting
        $formattedNpr = $currencyService->format(1000.0, 'NPR');
        $this->assertStringContainsString('Rs.', $formattedNpr);
    }

    /**
     * Test 9: Setting audit logging records changes without exposing secrets.
     */
    public function test_setting_audit_logging_masks_secrets(): void
    {
        $admin = User::where('email', 'admin@laijau.com')->first() ?: User::factory()->create([
            'email' => 'admin@laijau.com',
        ]);

        $this->actingAs($admin, 'web');

        $log = SettingAuditLog::logChange('connectips_secret_key', 'nchl_oldsecret12345678', 'nchl_newsecret87654321');

        $this->assertNotNull($log->id);
        $this->assertEquals('connectips_secret_key', $log->setting_key);
        // Secrets must be masked in audit log
        $this->assertStringContainsString('••••••••••••', $log->old_value);
        $this->assertStringContainsString('••••••••••••', $log->new_value);
        $this->assertStringNotContainsString('nchl_oldsecret12345678', $log->old_value);
        $this->assertStringNotContainsString('nchl_newsecret87654321', $log->new_value);
    }

    /**
     * Test 10: Store Settings Service and Social Links filtering.
     */
    public function test_store_settings_service_and_social_links(): void
    {
        $store = app(StoreSettingsService::class);

        Setting::set('store_short_name', 'Laijau');
        Setting::set('social_links', json_encode([
            [
                'platform' => 'Instagram',
                'url' => 'https://instagram.com/laijau.nepal',
                'display_label' => '@laijau.nepal',
                'icon_identifier' => 'instagram',
                'enabled' => true,
                'sort_order' => 2,
            ],
            [
                'platform' => 'Disabled Network',
                'url' => 'https://disabled.com/test',
                'display_label' => 'Disabled',
                'icon_identifier' => 'link',
                'enabled' => false,
                'sort_order' => 1,
            ],
            [
                'platform' => 'Facebook',
                'url' => 'https://facebook.com/laijau.nepal',
                'display_label' => 'Laijau Nepal',
                'icon_identifier' => 'facebook',
                'enabled' => true,
                'sort_order' => 1,
            ],
        ]));

        $brand = $store->getBrandIdentity();
        $this->assertEquals('Laijau', $brand['store_short_name']);

        $links = $store->getSocialLinks();
        $this->assertCount(2, $links);
        $this->assertEquals('Facebook', $links[0]['platform']);
        $this->assertEquals('Instagram', $links[1]['platform']);
    }

    /**
     * Test 11: Mail Settings Service transactional event toggles.
     */
    public function test_mail_settings_service_event_toggles(): void
    {
        $mailService = app(MailSettingsService::class);

        // Default enabled
        Setting::set('mail_event_order_confirmation', '1');
        $this->assertTrue($mailService->isEventEnabled('order_confirmation'));

        // Toggle disabled
        Setting::set('mail_event_order_confirmation', '0');
        $this->assertFalse($mailService->isEventEnabled('order_confirmation'));

        // Reset
        Setting::set('mail_event_order_confirmation', '1');
    }

    /**
     * Test 12: Shipping Methods priority ordering and free threshold resolution.
     */
    public function test_shipping_methods_priority_ordering_and_free_thresholds(): void
    {
        // For Nepal with cart subtotal, verify delivery thresholds
        $methods = \App\Models\ShippingMethod::getAvailableMethodsForCountry('NP', 2500.0, 'NPR', 'Kathmandu');
        $this->assertNotEmpty($methods);

        foreach ($methods as $method) {
            $this->assertArrayHasKey('free_threshold', $method);
            if ($method['free_threshold'] > 0 && 2500.0 >= $method['free_threshold']) {
                $this->assertTrue($method['is_free']);
                $this->assertEquals(0.0, $method['cost']);
            }
        }
    }
}
