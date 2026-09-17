<?php

namespace Tests\Feature;

use App\Filament\Pages\ModuleSettings;
use App\Models\Settings\ModuleSetting;
use App\Models\Settings\ModuleSettingAuditLog;
use App\Models\Settings\ModuleStatus;
use App\Models\Setting;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\Settings\ModuleRegistry;
use App\Services\Settings\SettingsService;
use Database\Seeders\ModuleSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ModuleSettingsSystemTest extends TestCase
{
    use RefreshDatabase;

    protected SettingsService $settingsService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->settingsService = app(SettingsService::class);
        $this->seed(ModuleSettingsSeeder::class);
    }

    /**
     * Test 1: Module Registry defines all 13 business modules correctly.
     */
    public function test_module_registry_contains_all_thirteen_modules(): void
    {
        $all = ModuleRegistry::all();
        $this->assertCount(13, $all);

        $expectedModules = [
            'commerce',
            'inventory',
            'purchasing',
            'pos',
            'accounting',
            'hrm',
            'crm',
            'shipping',
            'payments',
            'notifications',
            'marketing',
            'analytics',
            'system',
        ];

        foreach ($expectedModules as $moduleId) {
            $this->assertTrue(ModuleRegistry::exists($moduleId), "Module '{$moduleId}' must exist in registry.");
            $descriptor = ModuleRegistry::get($moduleId);
            $this->assertNotEmpty($descriptor['name']);
            $this->assertNotEmpty($descriptor['description']);
            $this->assertNotEmpty($descriptor['icon']);
            $this->assertNotEmpty($descriptor['category']);
            $this->assertIsArray($descriptor['groups']);
        }
    }

    /**
     * Test 2: Seeder populates comprehensive defaults and is strictly idempotent.
     */
    public function test_module_settings_seeder_populates_defaults_and_is_idempotent(): void
    {
        $initialCount = ModuleSetting::count();
        $this->assertGreaterThanOrEqual(70, $initialCount);

        // Run seeder a second time
        $this->seed(ModuleSettingsSeeder::class);
        $secondCount = ModuleSetting::count();

        $this->assertEquals($initialCount, $secondCount, 'Seeder must be idempotent and not create duplicate rows.');
    }

    /**
     * Test 3: Strongly typed getters return correct types and safe defaults.
     */
    public function test_settings_service_typed_getters_and_safe_defaults(): void
    {
        // Boolean
        $this->assertTrue($this->settingsService->getBoolean('inventory', 'enable_reservations'));
        $this->assertFalse($this->settingsService->getBoolean('system', 'maintenance_mode'));
        $this->assertTrue($this->settingsService->getBoolean('inventory', 'non_existent_bool_key', true));

        // Integer / Duration
        $ttl = $this->settingsService->getInteger('inventory', 'reservation_ttl_minutes');
        $this->assertGreaterThan(0, $ttl);
        $this->assertEquals(999, $this->settingsService->getInteger('inventory', 'non_existent_int_key', 999));

        // Decimal / Currency
        $freeShipping = $this->settingsService->getDecimal('shipping', 'free_shipping_threshold_npr');
        $this->assertEquals(5000.00, $freeShipping);
        $this->assertEquals(42.50, $this->settingsService->getDecimal('shipping', 'non_existent_decimal', 42.50));

        // String
        $warehouseCode = $this->settingsService->getString('inventory', 'default_warehouse_code');
        $this->assertEquals('WH-KTM-MAIN', $warehouseCode);
        $this->assertEquals('DEFAULT-VAL', $this->settingsService->getString('inventory', 'non_existent_str', 'DEFAULT-VAL'));

        // JSON / Multi-select
        $currencies = $this->settingsService->getJson('payments', 'accepted_currencies');
        $this->assertIsArray($currencies);
        $this->assertContains('NPR', array_map('strtoupper', $currencies));
    }

    /**
     * Test 4: Validation rules prevent invalid values.
     */
    public function test_settings_service_validation_rules_prevent_invalid_values(): void
    {
        // 1. Invalid select option
        $this->expectException(ValidationException::class);
        $this->settingsService->set('accounting', 'vat_reporting_frequency', 'daily_invalid_option');
    }

    /**
     * Test 5: Validation rules enforce numeric ranges.
     */
    public function test_settings_service_validation_enforces_numeric_ranges(): void
    {
        // Reservation TTL must be between 5 and 120
        $this->expectException(ValidationException::class);
        $this->settingsService->set('inventory', 'reservation_ttl_minutes', 9999);
    }

    /**
     * Test 6: setMultiple transactional updates rollback on validation failure.
     */
    public function test_set_multiple_transactional_rollback(): void
    {
        $originalTtl = $this->settingsService->getInteger('inventory', 'reservation_ttl_minutes');
        $originalWarehouse = $this->settingsService->getString('inventory', 'default_warehouse_code');

        try {
            $this->settingsService->setMultiple('inventory', [
                'reservation_ttl_minutes' => 45, // Valid
                'negative_stock_policy' => 'completely_invalid_policy', // Invalid, triggers exception!
            ]);
            $this->fail('Should have thrown ValidationException');
        } catch (ValidationException $e) {
            // Expected
        }

        // Verify transaction was rolled back: valid setting was NOT updated
        $this->assertEquals($originalTtl, $this->settingsService->getInteger('inventory', 'reservation_ttl_minutes'));
        $this->assertEquals($originalWarehouse, $this->settingsService->getString('inventory', 'default_warehouse_code'));
    }

    /**
     * Test 7: Sensitive values are encrypted at rest and redacted in audit logs.
     */
    public function test_sensitive_secrets_encrypted_at_rest_and_redacted_in_audit_logs(): void
    {
        $plainSecret = '8gBm/:&SecretKeyLive99998888';
        $user = User::factory()->create(['role' => 'super_admin', 'email' => 'secadmin_' . uniqid() . '@laijau.com']);

        $this->settingsService->set('payments', 'esewa_secret_key', $plainSecret, $user->id);

        // 1. Check database storage is encrypted
        $rawDb = ModuleSetting::where('module', 'payments')->where('key', 'esewa_secret_key')->value('value');
        $this->assertStringStartsWith('enc:', $rawDb);
        $this->assertNotEquals($plainSecret, $rawDb);

        // 2. Check service decrypts on consumption
        $decrypted = $this->settingsService->getString('payments', 'esewa_secret_key');
        $this->assertEquals($plainSecret, $decrypted);

        // 3. Check audit log contains ONLY redacted value
        $auditLog = ModuleSettingAuditLog::where('module', 'payments')
            ->where('key', 'esewa_secret_key')
            ->latest('id')
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertStringContainsString('••••', $auditLog->new_value);
        $this->assertStringNotContainsString($plainSecret, $auditLog->new_value);
        $this->assertEquals($user->id, $auditLog->user_id);
    }

    /**
     * Test 8: Cache invalidation upon mutation.
     */
    public function test_cache_invalidation_upon_mutation(): void
    {
        // First read populates cache
        $val1 = $this->settingsService->getString('commerce', 'order_prefix');
        $this->assertNotEmpty($val1);

        // Mutate setting
        $this->settingsService->set('commerce', 'order_prefix', 'NA-ORD-');

        // Second read should immediately reflect new value without cache stale issue
        $val2 = $this->settingsService->getString('commerce', 'order_prefix');
        $this->assertEquals('NA-ORD-', $val2);
    }

    /**
     * Test 9: Module enable/disable with dependency protection.
     */
    public function test_module_dependency_protection_blocks_unsafe_disabling(): void
    {
        // Attempting to disable 'commerce' must be blocked because 'inventory' and 'pos' depend on it
        $this->expectException(\InvalidArgumentException::class);
        $this->settingsService->setModuleStatus('commerce', false);
    }

    /**
     * Test 10: Non-dependent modules can be disabled and re-enabled safely.
     */
    public function test_non_dependent_module_can_be_disabled_and_re_enabled(): void
    {
        $this->assertTrue($this->settingsService->isModuleEnabled('hrm'));

        // Disable HRM
        $user = User::factory()->create(['role' => 'super_admin']);
        $this->settingsService->setModuleStatus('hrm', false, $user->id, 'Temporary HR maintenance');

        $this->assertFalse($this->settingsService->isModuleEnabled('hrm'));

        // When module is disabled, isEnabled returns false
        $this->assertFalse($this->settingsService->isEnabled('hrm'));

        // Re-enable HRM
        $this->settingsService->setModuleStatus('hrm', true, $user->id);
        $this->assertTrue($this->settingsService->isModuleEnabled('hrm'));
    }

    /**
     * Test 11: Role-based authorization policies on User model.
     */
    public function test_user_authorization_policies(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'email' => 'super_' . uniqid() . '@laijau.com']);
        $workspaceAdmin = User::factory()->create(['role' => 'workspace_admin', 'email' => 'ws_' . uniqid() . '@laijau.com']);
        $viewer = User::factory()->create(['role' => 'viewer', 'email' => 'viewer_' . uniqid() . '@laijau.com']);

        // Super Admin has all permissions
        $this->assertTrue($superAdmin->canViewModuleSettings());
        $this->assertTrue($superAdmin->canEditModuleSettings());
        $this->assertTrue($superAdmin->canManageModuleStatus());
        $this->assertTrue($superAdmin->canManageSensitiveSettings());

        // Workspace Admin can view and edit general settings, but cannot toggle module statuses or manage sensitive keys
        $this->assertTrue($workspaceAdmin->canViewModuleSettings());
        $this->assertTrue($workspaceAdmin->canEditModuleSettings());
        $this->assertFalse($workspaceAdmin->canManageModuleStatus());
        $this->assertFalse($workspaceAdmin->canManageSensitiveSettings());

        // Viewer can only view
        $this->assertTrue($viewer->canViewModuleSettings());
        $this->assertFalse($viewer->canEditModuleSettings());
        $this->assertFalse($viewer->canManageModuleStatus());
        $this->assertFalse($viewer->canManageSensitiveSettings());
    }

    /**
     * Test 12: Livewire Filament ModuleSettings Page renders and executes actions.
     */
    public function test_filament_module_settings_page_renders_and_executes(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'email' => 'admin_' . uniqid() . '@laijau.com']);

        Livewire::actingAs($admin)
            ->test(ModuleSettings::class)
            ->assertSuccessful()
            ->assertSee('Enterprise Module Settings')
            ->assertSee('Enterprise Inventory')
            ->call('openModule', 'inventory')
            ->assertSet('activeModuleId', 'inventory')
            ->set('formData.default_reorder_point', 5)
            ->call('saveModuleSettings')
            ->assertHasNoErrors();

        $this->assertEquals(5, $this->settingsService->getInteger('inventory', 'default_reorder_point'));
    }

    /**
     * Test 13: Reset to default single setting and all module settings.
     */
    public function test_reset_to_default_actions(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'email' => 'admin_' . uniqid() . '@laijau.com']);

        // Change reorder point to 15
        $this->settingsService->set('inventory', 'default_reorder_point', 15, $admin->id);
        $this->assertEquals(15, $this->settingsService->getInteger('inventory', 'default_reorder_point'));

        // Reset to default via service
        $this->settingsService->resetToDefault('inventory', 'default_reorder_point', $admin->id);
        $this->assertEquals(3, $this->settingsService->getInteger('inventory', 'default_reorder_point'));
    }

    /**
     * Test 14: Existing legacy Setting model and ShippingMethod resource remain unbroken.
     */
    public function test_existing_settings_and_shipping_methods_remain_functional(): void
    {
        // Legacy setting
        Setting::set('legacy_test_key_sample', 'Retail_Store_Value_100');
        $this->assertEquals('Retail_Store_Value_100', Setting::get('legacy_test_key_sample'));

        // Shipping methods
        $shipping = ShippingMethod::create([
            'name' => 'Kathmandu Valley Express Delivery',
            'code' => 'EXPRESS_KTM_' . uniqid(),
            'carrier' => 'Pathao',
            'zone' => 'kathmandu_valley',
            'price_npr' => 150.00,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('shipping_methods', [
            'id' => $shipping->id,
            'name' => 'Kathmandu Valley Express Delivery',
        ]);
    }

    /**
     * Test 15: Configuration Impact Service evaluates business consequences and severity.
     */
    public function test_configuration_impact_service_evaluates_business_consequences(): void
    {
        // Reservation TTL change evaluation
        $impact = \App\Services\Settings\ConfigurationImpactService::evaluateImpact(
            'inventory',
            'reservation_ttl_minutes',
            30,
            60
        );

        $this->assertEquals('medium', $impact['severity']);
        $this->assertEquals('Checkout Cart Holds & Stock Availability', $impact['affects']);
        $this->assertStringContainsString('60 minutes', $impact['impact_text']);

        // Negative stock change evaluation (High severity)
        $impactNegative = \App\Services\Settings\ConfigurationImpactService::evaluateImpact(
            'inventory',
            'allow_negative_stock',
            false,
            true
        );

        $this->assertEquals('high', $impactNegative['severity']);
        $this->assertStringContainsString('CRITICAL', $impactNegative['impact_text']);
    }

    /**
     * Test 16: InventoryService dynamically responds to Module Settings for reservation TTL.
     */
    public function test_inventory_service_dynamically_consumes_reservation_ttl_from_module_settings(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'email' => 'admin_' . uniqid() . '@laijau.com']);
        $invService = app(\App\Services\Inventory\InventoryService::class);
        $wh = $invService->getDefaultWarehouse();

        $product = \App\Models\Product::create([
            'name' => 'Cashmere Pashmina Shawl ' . uniqid(),
            'sku' => 'SHAWL-' . uniqid(),
            'price_npr' => 1200.00,
            'quantity' => 10,
            'is_active' => true,
        ]);

        $invService->recordStockMovement([
            'warehouse_id' => $wh->id,
            'product_id' => $product->id,
            'movement_type' => 'opening_stock',
            'quantity' => 10,
            'unit_cost_npr' => 450.00,
        ]);

        // Default TTL is 30/60m
        $this->settingsService->set('inventory', 'reservation_ttl_minutes', 45, $admin->id);

        $reservation = $invService->createReservation([
            'warehouse_id' => $wh->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'cart_token' => 'cart_' . uniqid(),
        ]);

        // Expiration should be ~45 minutes in future (tolerance: 2 minutes)
        $diffMinutes = (int)now()->diffInMinutes($reservation->expires_at, false);
        $this->assertTrue($diffMinutes >= 44 && $diffMinutes <= 46);
    }

    /**
     * Test 17: OfflineSaleService dynamically respects pos.offline_sales_enabled.
     */
    public function test_offline_sale_service_dynamically_respects_pos_module_settings(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'email' => 'admin_' . uniqid() . '@laijau.com']);
        $offlineService = app(\App\Services\OfflineSaleService::class);

        $product = \App\Models\Product::create([
            'name' => 'Heritage Leather Belt ' . uniqid(),
            'sku' => 'BELT-' . uniqid(),
            'price_npr' => 2500.00,
            'quantity' => 5,
            'is_active' => true,
        ]);

        // Disable offline sales via Module Settings
        $this->settingsService->set('pos', 'offline_sales_enabled', false, $admin->id);

        try {
            $offlineService->createSale([
                'currency' => 'npr',
                'customer_name' => 'Walk-in Shopper',
            ], [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 2500.00,
                ],
            ], $admin);
            $this->fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Offline/POS sales are currently disabled in Module Settings', $e->getMessage());
        } finally {
            $this->settingsService->set('pos', 'offline_sales_enabled', true, $admin->id);
        }
    }

    /**
     * Test 18: Configuration export sanitizes sensitive keys.
     */
    public function test_configuration_export_sanitizes_secrets(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'email' => 'admin_' . uniqid() . '@laijau.com']);
        $this->settingsService->set('payments', 'esewa_secret_key', '8gBm/:&VerySecretToken99', $admin->id);

        $bundle = $this->settingsService->exportConfiguration('payments', true);

        $this->assertIsArray($bundle);
        $this->assertEquals('payments', $bundle['scope']);
        $this->assertArrayHasKey('payments.esewa_secret_key', $bundle['settings']);
        $this->assertEquals('[REDACTED_SECRET]', $bundle['settings']['payments.esewa_secret_key']['value']);
    }

    /**
     * Test 19: Pre-flight dry run and transactional configuration import.
     */
    public function test_configuration_import_dry_run_and_transactional_commit(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'email' => 'admin_' . uniqid() . '@laijau.com']);

        $payload = [
            'schema_version' => '1.0',
            'settings' => [
                [
                    'module' => 'inventory',
                    'key' => 'default_reorder_point',
                    'value' => 8,
                ],
                [
                    'module' => 'accounting',
                    'key' => 'default_vat_rate',
                    'value' => 25.0,
                ],
            ],
        ];

        // 1. Dry run preview
        $preview = $this->settingsService->previewImport($payload);
        $this->assertTrue($preview['valid']);
        $this->assertEquals(1, $preview['change_count']); // only reorder point changed from 3 to 8
        $this->assertEquals('default_reorder_point', $preview['changes'][0]['key']);
        $this->assertEquals(3, $preview['changes'][0]['current_value']);
        $this->assertEquals(8, $preview['changes'][0]['new_value']);

        // 2. Commit import
        $result = $this->settingsService->importConfiguration($payload, $admin->id);
        $this->assertEquals(1, $result['imported_count']);

        // Verify updated setting
        $this->assertEquals(8, $this->settingsService->getInteger('inventory', 'default_reorder_point'));
    }

    /**
     * Test 20: Livewire Filament ModuleSettings page triggers Impact Review Modal on high/medium severity mutations.
     */
    public function test_filament_page_triggers_impact_review_modal(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'email' => 'admin_' . uniqid() . '@laijau.com']);

        \Livewire\Livewire::actingAs($admin)
            ->test(ModuleSettings::class)
            ->call('openModule', 'inventory')
            ->set('formData.reservation_ttl_minutes', 90)
            ->call('requestSaveModuleSettings')
            ->assertSet('showImpactConfirmModal', true)
            ->assertCount('pendingImpactChanges', 1)
            ->call('executeCommitModuleSettings')
            ->assertSet('showImpactConfirmModal', false);

        $this->assertEquals(90, $this->settingsService->getInteger('inventory', 'reservation_ttl_minutes'));
    }
}
