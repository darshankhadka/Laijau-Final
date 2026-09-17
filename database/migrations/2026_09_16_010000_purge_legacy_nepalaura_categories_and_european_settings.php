<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        // 1. PURGE LEGACY ETHNIC CATEGORIES & PIVOTS
        $legacyCategorySlugs = ['sarees', 'lehengas', 'kurtas', 'shawls', 'womens-sarees', 'scarves-shawls'];
        $legacyCategoryIds = DB::table('categories')->whereIn('slug', $legacyCategorySlugs)->pluck('id');

        if ($legacyCategoryIds->isNotEmpty()) {
            DB::table('category_product')->whereIn('category_id', $legacyCategoryIds)->delete();
            DB::table('categories')->whereIn('id', $legacyCategoryIds)->delete();
        }

        // Ensure canonical Laijau categories exist
        $canonicalCategories = [
            [
                'name' => 'Footwear',
                'slug' => 'footwear',
                'description' => 'Premium handcrafted leather shoes, boots, and contemporary sneakers.',
                'image' => 'products/footwear.webp',
                'sort_order' => 1,
                'is_featured' => 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Apparel',
                'slug' => 'apparel',
                'description' => 'Modern clothing and essentials crafted in organic textiles and wool blends.',
                'image' => 'products/apparel.webp',
                'sort_order' => 2,
                'is_featured' => 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Outerwear & Jackets',
                'slug' => 'outerwear',
                'description' => 'Contemporary jackets, coats, and overshirts designed for Himalayan seasons.',
                'image' => 'products/jacket.webp',
                'sort_order' => 3,
                'is_featured' => 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Accessories',
                'slug' => 'accessories',
                'description' => 'Fine leather belts, wallets, scarves, and bags sourced ethically in Nepal.',
                'image' => 'products/accessories.webp',
                'sort_order' => 4,
                'is_featured' => 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($canonicalCategories as $cat) {
            $exists = DB::table('categories')->where('slug', $cat['slug'])->first();
            if ($exists) {
                DB::table('categories')->where('id', $exists->id)->update([
                    'name' => $cat['name'],
                    'description' => $cat['description'],
                    'image' => $cat['image'],
                    'sort_order' => $cat['sort_order'],
                    'is_featured' => 1,
                    'is_active' => 1,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('categories')->insert($cat);
            }
        }

        // 2. PURGE LEGACY PRODUCTS (Kurtas, Sarees, Lehengas, Anarkalis, Tunics)
        $legacyProductSlugs = [
            'the-crimson-zardozi-georgette-kurta-palazzo-set',
            'pistachio-tissue-organza-saree-scalloped-blouse-set',
            'ivory-crimson-embroidered-anarkali-set',
            'mauve-rose-tissue-organza-saree-scalloped-blouse-size-38',
            'ivory-slate-floral-embroidered-anarkali-set',
            'raw-silk-royal-kurta-set',
        ];

        $legacyProductIds = DB::table('products')
            ->whereIn('slug', $legacyProductSlugs)
            ->orWhere('name', 'LIKE', '%kurta%')
            ->orWhere('name', 'LIKE', '%saree%')
            ->orWhere('name', 'LIKE', '%anarkali%')
            ->pluck('id');

        if ($legacyProductIds->isNotEmpty()) {
            DB::table('category_product')->whereIn('product_id', $legacyProductIds)->delete();
            if (Schema::hasTable('product_variants')) {
                DB::table('product_variants')->whereIn('product_id', $legacyProductIds)->delete();
            }
            if (Schema::hasTable('inventory_stock_levels')) {
                DB::table('inventory_stock_levels')->whereIn('product_id', $legacyProductIds)->delete();
            }
            if (Schema::hasTable('stock_levels')) {
                DB::table('stock_levels')->whereIn('product_id', $legacyProductIds)->delete();
            }
            if (Schema::hasTable('inventory_stock_reservations')) {
                DB::table('inventory_stock_reservations')->whereIn('product_id', $legacyProductIds)->delete();
            }
            if (Schema::hasTable('stock_reservations')) {
                DB::table('stock_reservations')->whereIn('product_id', $legacyProductIds)->delete();
            }
            if (Schema::hasTable('inventory_stock_movements')) {
                DB::table('inventory_stock_movements')->whereIn('product_id', $legacyProductIds)->delete();
            }
            if (Schema::hasTable('order_items')) {
                DB::table('order_items')->whereIn('product_id', $legacyProductIds)->delete();
            }
            DB::table('products')->whereIn('id', $legacyProductIds)->delete();
        }

        // 3. PURGE DANISH & EUROPEAN SHIPPING METHODS
        DB::table('shipping_methods')
            ->whereIn('code', ['pickup_point_dk', 'home_delivery_dk', 'dhl_eu'])
            ->orWhereIn('zone', ['denmark', 'eu', 'europe'])
            ->delete();

        // Ensure canonical Nepal shipping methods
        $nepalShippingMethods = [
            [
                'code' => 'inside_valley_standard',
                'name' => 'Inside Kathmandu Valley Standard',
                'zone' => 'kathmandu_valley',
                'price_npr' => 100.00,
                'free_shipping_threshold_npr' => 5000.00,
                'estimated_delivery' => '1-2 business days',
                'is_active' => 1,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'inside_valley_express',
                'name' => 'Inside Kathmandu Valley Express (Same Day)',
                'zone' => 'kathmandu_valley',
                'price_npr' => 200.00,
                'free_shipping_threshold_npr' => null,
                'estimated_delivery' => 'Same day (Orders before 1 PM)',
                'is_active' => 1,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'outside_valley_courier',
                'name' => 'Outside Valley Courier (NCM / Pathao)',
                'zone' => 'outside_valley',
                'price_npr' => 150.00,
                'free_shipping_threshold_npr' => 7000.00,
                'estimated_delivery' => '2-4 business days',
                'is_active' => 1,
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'store_pickup',
                'name' => 'Showroom Store Pickup (Kathmandu)',
                'zone' => 'all_nepal',
                'price_npr' => 0.00,
                'free_shipping_threshold_npr' => 0.00,
                'estimated_delivery' => 'Ready in 2 hours',
                'is_active' => 1,
                'sort_order' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($nepalShippingMethods as $sm) {
            $existing = DB::table('shipping_methods')->where('code', $sm['code'])->first();
            if ($existing) {
                DB::table('shipping_methods')->where('id', $existing->id)->update([
                    'name' => $sm['name'],
                    'zone' => $sm['zone'],
                    'price_npr' => $sm['price_npr'],
                    'free_shipping_threshold_npr' => $sm['free_shipping_threshold_npr'],
                    'estimated_delivery' => $sm['estimated_delivery'],
                    'is_active' => 1,
                    'sort_order' => $sm['sort_order'],
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('shipping_methods')->insert($sm);
            }
        }

        // 4. PURGE STRIPE, PAYPAL, DKK, EUR, EUROPE FROM MODULE SETTINGS & SETTINGS
        if (Schema::hasTable('module_settings')) {
            DB::table('module_settings')
                ->where('key', 'LIKE', '%stripe%')
                ->orWhere('key', 'LIKE', '%paypal%')
                ->orWhere('key', 'LIKE', '%dkk%')
                ->orWhere('key', 'LIKE', '%_eur')
                ->orWhere('key', 'LIKE', '%blouse_tailoring%')
                ->orWhere('key', 'LIKE', 'enable_eu_%')
                ->orWhere('key', 'gl_eu_oss_vat_account')
                ->orWhere('key', 'enable_international_shipping')
                ->delete();
        }

        if (Schema::hasTable('settings')) {
            DB::table('settings')
                ->where('key', 'LIKE', '%stripe%')
                ->orWhere('key', 'LIKE', '%paypal%')
                ->orWhere('key', 'LIKE', '%dkk%')
                ->orWhere('key', 'LIKE', '%eur%')
                ->delete();
        }

        // 5. UPDATE PRODUCT ATTRIBUTES (Replace kurta & saree category groups)
        if (Schema::hasTable('product_attributes')) {
            DB::table('product_attributes')
                ->where('category_group', 'LIKE', '%kurta%')
                ->update([
                    'category_group' => 'apparel',
                    'description' => DB::raw("REPLACE(description, 'blouses, kurtas, and dresses', 'apparel, shirts, and knitwear')"),
                ]);

            DB::table('product_attributes')
                ->where('category_group', 'LIKE', '%saree%')
                ->orWhere('category_group', 'LIKE', '%shawl%')
                ->update([
                    'category_group' => 'accessories',
                ]);
        }

        // 6. UPDATE COLLECTIONS IMAGES
        if (Schema::hasTable('collections')) {
            DB::table('collections')->where('slug', 'himalayan-noir')->update([
                'image' => 'products/jacket.webp',
            ]);
            DB::table('collections')->where('slug', 'kathmandu-contemporary')->update([
                'image' => 'products/apparel.webp',
            ]);
            DB::table('collections')->where('slug', 'royal-heritage')->update([
                'image' => 'products/footwear.webp',
            ]);
        }

        // 7. STANDARDIZE REMAINING CURRENCIES TO NPR
        if (Schema::hasTable('inventory_stock_movements')) {
            DB::table('inventory_stock_movements')->where('currency', '!=', 'NPR')->update(['currency' => 'NPR']);
        }

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Irreversible clean architecture purge
    }
};
