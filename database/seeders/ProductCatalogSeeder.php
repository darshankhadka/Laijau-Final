<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Category;
use App\Models\Collection;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;

class ProductCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $footwearCat = Category::where('slug', 'footwear')->first();
        $apparelCat = Category::where('slug', 'apparel')->first();
        $outerwearCat = Category::where('slug', 'outerwear')->first();
        $accessoriesCat = Category::where('slug', 'accessories')->first();

        $himalayanCol = Collection::where('slug', 'himalayan-noir')->first();
        $contemporaryCol = Collection::where('slug', 'kathmandu-contemporary')->first();
        $royalCol = Collection::where('slug', 'royal-heritage')->first();

        // 1. Classic Oxford Leather Shoes
        $p1 = Product::updateOrCreate(
            ['slug' => 'classic-oxford-leather-shoes'],
            [
                'name' => 'Classic Oxford Leather Shoes',
                'sku' => 'LJ-SHO-001',
                'short_description' => 'Full-grain calfskin leather dress shoes with Goodyear welt construction by Newari leather artisans.',
                'description' => '<p>Crafted in the heritage workshops of Kathmandu, our Classic Oxford Leather Shoes represent the pinnacle of formal footwear. Featuring premium full-grain black calfskin, a closed lacing system, and a durable stacked leather sole. Built for formal occasions, business elegance, and all-day comfort.</p>',
                'price' => 8500.00,
                'compare_at_price' => 10500.00,
                'cost_price' => 4200.00,
                'lowest_price_30_days' => 8500.00,
                'tax_class' => 'standard',
                'quantity' => 20,
                'track_quantity' => true,
                'low_stock_threshold' => 4,
                'is_active' => true,
                'is_published' => true,
                'is_featured' => true,
                'is_new_arrival' => true,
                'material' => '100% Full-Grain Calfskin Leather',
                'fabric' => 'Hand-finished Box Calf',
                'country_of_origin' => 'Nepal',
                'dimensions' => 'Standard Footwear Sizing 40-44',
                'care_instructions' => 'Condition with natural beeswax polish. Store with cedar shoe trees.',
                'featured_image' => 'https://images.unsplash.com/photo-1614252235316-8c857d38b5f4?auto=format&fit=crop&w=1200&q=80',
                'images' => [
                    'https://images.unsplash.com/photo-1614252235316-8c857d38b5f4?auto=format&fit=crop&w=1200&q=80',
                    'https://images.unsplash.com/photo-1595950653106-6c9ebd614d3a?auto=format&fit=crop&w=1200&q=80',
                ],
                'seo_title' => 'Classic Oxford Leather Shoes | Laijau',
                'seo_description' => 'Handcrafted full-grain leather Oxford dress shoes. Premium Goodyear welt construction. Fast delivery across Nepal.',
            ]
        );
        if ($footwearCat) $p1->categories()->syncWithoutDetaching([$footwearCat->id]);
        if ($royalCol) $p1->collections()->syncWithoutDetaching([$royalCol->id]);

        foreach (['40', '41', '42', '43', '44'] as $size) {
            ProductVariant::updateOrCreate(
                ['sku' => "LJ-SHO-001-BLK-{$size}"],
                [
                    'product_id' => $p1->id,
                    'size' => $size,
                    'color' => 'Classic Black',
                    'color_hex' => '#111111',
                    'price' => 8500.00,
                    'cost_price' => 4200.00,
                    'stock_quantity' => 4,
                    'is_active' => true,
                ]
            );
        }

        // 2. Urban Low-Top Leather Sneakers
        $p2 = Product::updateOrCreate(
            ['slug' => 'urban-low-top-leather-sneakers'],
            [
                'name' => 'Urban Low-Top Leather Sneakers',
                'sku' => 'LJ-SHO-002',
                'short_description' => 'Minimalist leather cupsole sneakers designed for refined urban versatility.',
                'description' => '<p>A modern staple engineered for tactile luxury and everyday movement. Constructed in premium white milled leather with cushioned antimicrobial insoles and a durable vulcanized rubber outsole.</p>',
                'price' => 6500.00,
                'compare_at_price' => 7800.00,
                'cost_price' => 3200.00,
                'lowest_price_30_days' => 6500.00,
                'tax_class' => 'standard',
                'quantity' => 16,
                'track_quantity' => true,
                'low_stock_threshold' => 3,
                'is_active' => true,
                'is_published' => true,
                'is_featured' => true,
                'is_new_arrival' => false,
                'material' => 'Milled Nappa Leather & Rubber Cupsole',
                'fabric' => 'Soft Nappa Leather',
                'country_of_origin' => 'Nepal',
                'dimensions' => 'Standard Footwear Sizing 40-43',
                'care_instructions' => 'Wipe clean with a damp cloth and mild sneaker cleaner.',
                'featured_image' => 'https://images.unsplash.com/photo-1595950653106-6c9ebd614d3a?auto=format&fit=crop&w=1200&q=80',
                'images' => [
                    'https://images.unsplash.com/photo-1595950653106-6c9ebd614d3a?auto=format&fit=crop&w=1200&q=80',
                ],
                'seo_title' => 'Urban Low-Top Leather Sneakers | Laijau',
                'seo_description' => 'Minimalist white leather sneakers handcrafted with vulcanized rubber cupsole. Direct Kathmandu dispatch.',
            ]
        );
        if ($footwearCat) $p2->categories()->syncWithoutDetaching([$footwearCat->id]);
        if ($himalayanCol) $p2->collections()->syncWithoutDetaching([$himalayanCol->id]);

        foreach (['40', '41', '42', '43'] as $size) {
            ProductVariant::updateOrCreate(
                ['sku' => "LJ-SHO-002-WHT-{$size}"],
                [
                    'product_id' => $p2->id,
                    'size' => $size,
                    'color' => 'Monochrome White',
                    'color_hex' => '#F8F9FA',
                    'price' => 6500.00,
                    'cost_price' => 3200.00,
                    'stock_quantity' => 4,
                    'is_active' => true,
                ]
            );
        }

        // 3. Slim-Fit Cotton Chino Trousers
        $p3 = Product::updateOrCreate(
            ['slug' => 'slim-fit-cotton-chino-trousers'],
            [
                'name' => 'Slim-Fit Cotton Chino Trousers',
                'sku' => 'LJ-APP-003',
                'short_description' => 'Structured stretch-cotton twill chinos with clean front detailing.',
                'description' => '<p>Minimalist design meets all-day comfort. Our Slim-Fit Cotton Chino Trousers are designed for smart-casual sophistication. Cut slim through the thigh with a tapered leg opening, woven from long-staple organic cotton twill.</p>',
                'price' => 4200.00,
                'compare_at_price' => 5000.00,
                'cost_price' => 2100.00,
                'lowest_price_30_days' => 4200.00,
                'tax_class' => 'standard',
                'quantity' => 16,
                'track_quantity' => true,
                'low_stock_threshold' => 4,
                'is_active' => true,
                'is_published' => true,
                'is_featured' => true,
                'is_new_arrival' => true,
                'material' => '98% Organic Cotton, 2% Elastane',
                'fabric' => 'Structured Stretch Twill',
                'country_of_origin' => 'Nepal',
                'dimensions' => 'Waist 30-36, Inseam 32"',
                'care_instructions' => 'Machine wash cold on gentle cycle. Warm iron if needed.',
                'featured_image' => 'https://images.unsplash.com/photo-1624378439575-d8705ad7ae80?auto=format&fit=crop&w=1200&q=80',
                'images' => [
                    'https://images.unsplash.com/photo-1624378439575-d8705ad7ae80?auto=format&fit=crop&w=1200&q=80',
                ],
                'seo_title' => 'Slim-Fit Cotton Chino Trousers | Laijau',
                'seo_description' => 'Organic stretch-cotton chino trousers with tapered cut. Crafted for comfort and versatility.',
            ]
        );
        if ($apparelCat) $p3->categories()->syncWithoutDetaching([$apparelCat->id]);
        if ($contemporaryCol) $p3->collections()->syncWithoutDetaching([$contemporaryCol->id]);

        foreach (['30', '32', '34', '36'] as $size) {
            ProductVariant::updateOrCreate(
                ['sku' => "LJ-APP-003-NVY-{$size}"],
                [
                    'product_id' => $p3->id,
                    'size' => $size,
                    'color' => 'Midnight Navy',
                    'color_hex' => '#1B2A4A',
                    'price' => 4200.00,
                    'cost_price' => 2100.00,
                    'stock_quantity' => 4,
                    'is_active' => true,
                ]
            );
        }

        // 4. Pure Himalayan Cashmere Scarf
        $p4 = Product::updateOrCreate(
            ['slug' => 'pure-himalayan-cashmere-scarf'],
            [
                'name' => 'Pure Himalayan Cashmere Scarf',
                'sku' => 'LJ-ACC-004',
                'short_description' => 'Certified Grade-A mountain cashmere hand-spun and woven on traditional wooden treadle looms.',
                'description' => '<p>Direct from the high-altitude pastures of Mustang, Nepal. Our cashmere scarves represent the purest cashmere fiber in existence (under 14.5 microns). Ultra-lightweight yet intensely insulating, finished with hand-twisted fringes.</p>',
                'price' => 9800.00,
                'compare_at_price' => 12000.00,
                'cost_price' => 5200.00,
                'lowest_price_30_days' => 9800.00,
                'tax_class' => 'standard',
                'quantity' => 15,
                'track_quantity' => true,
                'low_stock_threshold' => 3,
                'is_active' => true,
                'is_published' => true,
                'is_featured' => true,
                'is_new_arrival' => false,
                'material' => '100% Certified Himalayan Cashmere',
                'fabric' => 'Diamond Twill Weave',
                'country_of_origin' => 'Nepal',
                'dimensions' => '180cm x 30cm',
                'care_instructions' => 'Dry clean or gentle hand wash using mild wool detergent. Dry flat.',
                'featured_image' => 'https://images.unsplash.com/photo-1607522370275-f14206abe5d3?auto=format&fit=crop&w=1200&q=80',
                'images' => [
                    'https://images.unsplash.com/photo-1607522370275-f14206abe5d3?auto=format&fit=crop&w=1200&q=80',
                ],
                'seo_title' => 'Pure Himalayan Cashmere Scarf | Laijau',
                'seo_description' => 'Authentic Grade-A certified Himalayan cashmere scarf. Handwoven in Nepal.',
            ]
        );
        if ($accessoriesCat) $p4->categories()->syncWithoutDetaching([$accessoriesCat->id]);
        if ($himalayanCol) $p4->collections()->syncWithoutDetaching([$himalayanCol->id]);

        ProductVariant::updateOrCreate(
            ['sku' => 'LJ-ACC-004-CASHMERE'],
            ['product_id' => $p4->id, 'size' => 'One Size (180x30cm)', 'color' => 'Warm Cashmere Natural', 'color_hex' => '#F5EFE6', 'price' => 9800.00, 'cost_price' => 5200.00, 'stock_quantity' => 10, 'is_active' => true]
        );
        ProductVariant::updateOrCreate(
            ['sku' => 'LJ-ACC-004-CHARCOAL'],
            ['product_id' => $p4->id, 'size' => 'One Size (180x30cm)', 'color' => 'Deep Charcoal', 'color_hex' => '#222222', 'price' => 9800.00, 'cost_price' => 5200.00, 'stock_quantity' => 5, 'is_active' => true]
        );
    }
}
