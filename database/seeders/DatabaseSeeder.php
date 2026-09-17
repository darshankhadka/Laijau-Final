<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Setting;
use App\Models\ShippingMethod;
use App\Models\Category;
use App\Models\Collection;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        $roleAdmin = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'admin']);
        $roleWeb = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);

        $admin = User::updateOrCreate(
            ['email' => 'admin@laijau.com'],
            [
                'name' => 'Laijau Super Admin',
                'password' => bcrypt('Laijau2026!'),
                'role' => 'admin',
                'country' => 'NP',
            ]
        );

        if (!$admin->hasRole('Super Admin', 'admin')) {
            $admin->assignRole($roleAdmin);
        }
        if (!$admin->hasRole('Super Admin', 'web')) {
            $admin->assignRole($roleWeb);
        }

        // Seed default store settings if not set
        Setting::set('store_name', 'Laijau');
        Setting::set('legal_business_name', 'Delta Nine business group');
        Setting::set('vat_number', '604335148');
        Setting::set('address', 'Bohara Tol, Kageshwori Manahara 09, Kathmandu, Nepal');
        Setting::set('support_email', 'info@laijau.com');
        Setting::set('support_phone', '9843512095');
        Setting::set('whatsapp_number', '9843512095');
        Setting::set('default_currency', 'NPR');

        // 1. Purge any legacy European shipping methods
        ShippingMethod::whereIn('code', ['pickup_point_dk', 'home_delivery_dk', 'dhl_eu'])
            ->orWhereIn('zone', ['denmark', 'eu', 'europe'])
            ->delete();

        // Seed default Nepal Shipping Methods
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

        // Purge legacy ethnic wear categories
        Category::whereIn('slug', ['sarees', 'lehengas', 'kurtas', 'shawls'])->delete();

        // Seed foundational categories with seeded imagery
        $categories = [
            [
                'name' => 'Footwear',
                'slug' => 'footwear',
                'description' => 'Premium handcrafted leather shoes, boots, and contemporary sneakers.',
                'image' => 'products/footwear.webp',
                'sort_order' => 1,
                'is_featured' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Apparel',
                'slug' => 'apparel',
                'description' => 'Modern clothing and essentials crafted in organic textiles and wool blends.',
                'image' => 'products/apparel.webp',
                'sort_order' => 2,
                'is_featured' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Outerwear & Jackets',
                'slug' => 'outerwear',
                'description' => 'Contemporary jackets, coats, and overshirts designed for Himalayan seasons.',
                'image' => 'products/jacket.webp',
                'sort_order' => 3,
                'is_featured' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Accessories',
                'slug' => 'accessories',
                'description' => 'Fine leather belts, wallets, scarves, and bags sourced ethically in Nepal.',
                'image' => 'products/accessories.webp',
                'sort_order' => 4,
                'is_featured' => true,
                'is_active' => true,
            ],
        ];
        foreach ($categories as $cat) {
            Category::updateOrCreate(['slug' => $cat['slug']], $cat);
        }

        // Seed foundational collections with seeded imagery
        $collections = [
            [
                'name' => 'Himalayan Noir',
                'slug' => 'himalayan-noir',
                'description' => 'Deep midnight tones and textured leather crafted for winter in Kathmandu.',
                'image' => 'products/jacket.webp',
                'is_published' => true,
                'is_featured' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Kathmandu Contemporary',
                'slug' => 'kathmandu-contemporary',
                'description' => 'Modern silhouettes woven with organic handloom textiles by artisan cooperatives across Nepal.',
                'image' => 'products/apparel.webp',
                'is_published' => true,
                'is_featured' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Royal Heritage',
                'slug' => 'royal-heritage',
                'description' => 'Fine artisanal craftsmanship passed down through generations of Newari leatherworkers and weavers.',
                'image' => 'products/footwear.webp',
                'is_published' => true,
                'is_featured' => true,
                'sort_order' => 3,
            ],
        ];
        foreach ($collections as $col) {
            Collection::updateOrCreate(['slug' => $col['slug']], $col);
        }

        // Seed standard garment size attributes (Numeric 32-42 & Alpha XS-XXL)
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
            \App\Models\ProductAttribute::updateOrCreate(
                ['name' => $attr['name'], 'type' => $attr['type']],
                $attr
            );
        }

        $this->call(ProductSeeder::class);

        // Seed nepalese Chart of Accounts & Accounting Registers
        app(\App\Services\Accounting\AccountingService::class)->seedDefaultChartOfAccounts();

        // Seed nepalese HRM Organization, Positions, Staff, Payroll, Leaves & Expenses
        $this->call(HrmSeeder::class);

        // Seed Enterprise Multi-Location Inventory, Warehouses, Suppliers & Stock Ledger
        $this->call(InventorySeeder::class);
    }
}
