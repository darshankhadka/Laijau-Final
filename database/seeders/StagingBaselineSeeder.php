<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\ProductAttribute;
use App\Models\Setting;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class StagingBaselineSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Enforce staging database safety
        $activeDb = \Illuminate\Support\Facades\DB::connection()->getDatabaseName();
        if ($activeDb === 'LAIJAU') {
            config(['database.default' => 'staging']);
            \Illuminate\Support\Facades\DB::setDefaultConnection('staging');
            $activeDb = \Illuminate\Support\Facades\DB::connection()->getDatabaseName();
        }

        if ($activeDb !== 'laijau_staging') {
            throw new \RuntimeException("ABORT: StagingBaselineSeeder can only run on 'laijau_staging', attempted on '{$activeDb}'");
        }

        // 1. RBAC Roles & Granular Permissions
        $this->call(RolePermissionSeeder::class);

        // 2. Staging Super Administrator
        $roleAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'admin']);
        $roleWeb = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);

        $admin = User::updateOrCreate(
            ['email' => 'admin@laijau.com'],
            [
                'name' => 'Laijau Super Admin',
                'password' => bcrypt('Laijau2026!'),
                'role' => 'super_admin',
                'country' => 'NP',
                'is_active' => true,
            ]
        );

        if (!$admin->hasRole('Super Admin', 'admin')) {
            $admin->assignRole($roleAdmin);
        }
        if (!$admin->hasRole('Super Admin', 'web')) {
            $admin->assignRole($roleWeb);
        }

        // 3. Core Store Identity & Statutory Settings
        Setting::set('store_name', 'Laijau');
        Setting::set('legal_business_name', 'Delta Nine business group');
        Setting::set('vat_number', '604335148');
        Setting::set('address', 'Bohara Tol, Kageshwori Manahara 09, Kathmandu, Nepal');
        Setting::set('support_email', 'info@laijau.com');
        Setting::set('support_phone', '9843512095');
        Setting::set('whatsapp_number', '9843512095');
        Setting::set('default_currency', 'NPR');
        Setting::set('currency_symbol', 'Rs. ');
        Setting::set('tax_system', 'nepal_ird');

        // 4. Authoritative Module Settings & Control Plane
        $this->call(ModuleSettingsSeeder::class);

        // 5. Nepal Statutory Chart of Accounts (NAS / IRD General Ledger)
        app(AccountingService::class)->seedDefaultChartOfAccounts();

        // 6. Foundational Nepal Shipping Methods
        ShippingMethod::updateOrCreate(
            ['code' => 'inside_valley_standard'],
            [
                'name' => 'Inside Kathmandu Valley Standard',
                'zone' => 'kathmandu_valley',
                'price_npr' => 100.00,
                'free_shipping_threshold_npr' => 5000.00,
                'estimated_delivery' => '1-2 business days',
                'is_active' => true,
            ]
        );

        ShippingMethod::updateOrCreate(
            ['code' => 'inside_valley_express'],
            [
                'name' => 'Inside Kathmandu Valley Express (Same Day)',
                'zone' => 'kathmandu_valley',
                'price_npr' => 200.00,
                'free_shipping_threshold_npr' => null,
                'estimated_delivery' => 'Same day (Orders before 1 PM)',
                'is_active' => true,
            ]
        );

        ShippingMethod::updateOrCreate(
            ['code' => 'outside_valley_courier'],
            [
                'name' => 'Outside Valley Courier (NCM / Pathao)',
                'zone' => 'outside_valley',
                'price_npr' => 150.00,
                'free_shipping_threshold_npr' => 7000.00,
                'estimated_delivery' => '2-4 business days',
                'is_active' => true,
            ]
        );

        ShippingMethod::updateOrCreate(
            ['code' => 'store_pickup'],
            [
                'name' => 'Showroom Store Pickup (Kathmandu)',
                'zone' => 'all_nepal',
                'price_npr' => 0.00,
                'free_shipping_threshold_npr' => 0.00,
                'estimated_delivery' => 'Ready in 2 hours',
                'is_active' => true,
            ]
        );

        // 7. Foundational Apparel Sizing Attributes
        $sizeAttributes = [
            ['name' => '32', 'code' => 'SZ-32', 'type' => 'size', 'category_group' => 'apparel', 'value' => 'Bust 32" / 81cm', 'sort_order' => 1, 'is_active' => true],
            ['name' => '34', 'code' => 'SZ-34', 'type' => 'size', 'category_group' => 'apparel', 'value' => 'Bust 34" / 86cm', 'sort_order' => 2, 'is_active' => true],
            ['name' => '36', 'code' => 'SZ-36', 'type' => 'size', 'category_group' => 'apparel', 'value' => 'Bust 36" / 91cm', 'sort_order' => 3, 'is_active' => true],
            ['name' => '38', 'code' => 'SZ-38', 'type' => 'size', 'category_group' => 'apparel', 'value' => 'Bust 38" / 97cm', 'sort_order' => 4, 'is_active' => true],
            ['name' => '40', 'code' => 'SZ-40', 'type' => 'size', 'category_group' => 'apparel', 'value' => 'Bust 40" / 102cm', 'sort_order' => 5, 'is_active' => true],
            ['name' => '42', 'code' => 'SZ-42', 'type' => 'size', 'category_group' => 'apparel', 'value' => 'Bust 42" / 107cm', 'sort_order' => 6, 'is_active' => true],
            ['name' => 'XS', 'code' => 'SZ-XS', 'type' => 'size', 'category_group' => 'apparel', 'value' => 'Extra Small', 'sort_order' => 11, 'is_active' => true],
            ['name' => 'S', 'code' => 'SZ-S', 'type' => 'size', 'category_group' => 'apparel', 'value' => 'Small', 'sort_order' => 12, 'is_active' => true],
            ['name' => 'M', 'code' => 'SZ-M', 'type' => 'size', 'category_group' => 'apparel', 'value' => 'Medium', 'sort_order' => 13, 'is_active' => true],
            ['name' => 'L', 'code' => 'SZ-L', 'type' => 'size', 'category_group' => 'apparel', 'value' => 'Large', 'sort_order' => 14, 'is_active' => true],
            ['name' => 'XL', 'code' => 'SZ-XL', 'type' => 'size', 'category_group' => 'apparel', 'value' => 'Extra Large', 'sort_order' => 15, 'is_active' => true],
            ['name' => 'XXL', 'code' => 'SZ-XXL', 'type' => 'size', 'category_group' => 'apparel', 'value' => 'Double Extra Large', 'sort_order' => 16, 'is_active' => true],
            ['name' => 'Standard (180cm)', 'code' => 'DIM-ACC-180', 'type' => 'dimension', 'category_group' => 'accessories', 'value' => '180cm x 30cm Scarf', 'sort_order' => 20, 'is_active' => true],
            ['name' => 'Standard (2m)', 'code' => 'DIM-SH-20', 'type' => 'dimension', 'category_group' => 'accessories', 'value' => '200cm x 70cm Wrap', 'sort_order' => 21, 'is_active' => true],
            ['name' => 'Free Size', 'code' => 'SZ-FREE', 'type' => 'size', 'category_group' => 'apparel', 'value' => 'One Size / Free Size', 'sort_order' => 25, 'is_active' => true],
        ];

        foreach ($sizeAttributes as $attr) {
            ProductAttribute::updateOrCreate(
                ['name' => $attr['name'], 'type' => $attr['type']],
                $attr
            );
        }
    }
}
