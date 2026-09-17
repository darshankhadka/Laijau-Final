<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {

        $catFootwear = Category::where('slug', 'footwear')->first();
        $catApparel = Category::where('slug', 'apparel')->first();
        $catOuterwear = Category::where('slug', 'outerwear')->first();
        $catAccessories = Category::where('slug', 'accessories')->first();

        $colNoir = Collection::where('slug', 'himalayan-noir')->first();
        $colContemporary = Collection::where('slug', 'kathmandu-contemporary')->first();
        $colHeritage = Collection::where('slug', 'royal-heritage')->first();

        $shoeSizes = ['40', '41', '42', '43', '44'];
        $apparelSizes = ['S', 'M', 'L', 'XL'];

        // =========================================================================
        // 1. Classic Derby Leather Shoes (Footwear)
        // =========================================================================
        $p1 = Product::updateOrCreate(
            ['slug' => 'classic-derby-leather-shoes'],
            [
                'name' => 'Classic Derby Leather Shoes',
                'sku' => 'LJ-SHO-101',
                'barcode' => '9770123101001',
                'supplier_name' => 'Kathmandu Leathercraft Cooperative',
                'supplier_sku' => 'KLC-2026-DRB01',
                'price' => 8100.00,
                'compare_at_price' => 10980.00,
                'cost_price' => 3200.00,
                'featured_image' => 'products/footwear.webp',
                'images' => [
                    'products/footwear.webp',
                ],
                'quantity' => 17,
                'track_quantity' => true,
                'low_stock_threshold' => 3,
                'is_active' => true,
                'is_published' => true,
                'is_featured' => true,
                'is_new_arrival' => true,
                'tax_class' => 'standard',
                'availability_status' => 'available',
                'short_description' => 'Full-grain leather derby dress shoes with Goodyear welt construction and durable stacked sole.',
                'description' => '<p>Crafted in Kathmandu from vegetable-tanned full-grain leather, these classic Derby shoes offer unmatched durability, structured support, and timeless aesthetic versatility.</p>',
                'material' => 'Full-Grain Vegetable Tanned Leather',
                'fabric' => 'Hand-buffed Leather Upper',
                'dimensions' => 'Standard Footwear Sizing 40-44',
                'care_instructions' => 'Polish with natural wax balm. Store with shoe trees.',
                'country_of_origin' => 'Nepal',
                'weight' => 0.95,
                'seo_title' => 'Classic Derby Leather Shoes | Laijau',
                'seo_description' => 'Handcrafted full-grain leather Derby dress shoes. Premium Goodyear welt construction. Fast Nepal delivery.',
            ]
        );
        if ($catFootwear) $p1->categories()->sync([$catFootwear->id]);
        if ($colHeritage) $p1->collections()->sync([$colHeritage->id]);

        $p1->variants()->delete();
        $p1Barcodes = ['40' => '9770123101401', '41' => '9770123101418', '42' => '9770123101425', '43' => '9770123101432', '44' => '9770123101449'];
        foreach ($shoeSizes as $idx => $size) {
            ProductVariant::create([
                'product_id' => $p1->id,
                'sku' => "LJ-SHO-101-BLK-{$size}",
                'barcode' => $p1Barcodes[$size] ?? null,
                'size' => $size,
                'color' => 'Classic Black',
                'color_hex' => '#111111',
                'stock_quantity' => 3 + ($idx % 2),
                'price' => 8100.00,
                'cost_price' => 3200.00,
                'is_active' => true,
            ]);
        }

        // =========================================================================
        // 2. Minimalist Low-Top Sneakers (Footwear)
        // =========================================================================
        $p2 = Product::updateOrCreate(
            ['slug' => 'minimalist-low-top-sneakers'],
            [
                'name' => 'Minimalist Low-Top Sneakers',
                'sku' => 'LJ-SHO-102',
                'barcode' => '9770123201008',
                'supplier_name' => 'Patan Leather Goods Guild',
                'supplier_sku' => 'PLG-2026-SNK02',
                'price' => 6980.00,
                'compare_at_price' => 8980.00,
                'cost_price' => 2800.00,
                'featured_image' => 'products/footwear.webp',
                'images' => [
                    'products/footwear.webp',
                ],
                'quantity' => 16,
                'track_quantity' => true,
                'low_stock_threshold' => 3,
                'is_active' => true,
                'is_published' => true,
                'is_featured' => true,
                'is_new_arrival' => true,
                'tax_class' => 'standard',
                'availability_status' => 'available',
                'short_description' => 'Crisp monochrome white leather sneakers with stitched cupsole and padded collar.',
                'description' => '<p>Minimalist silhouette built for everyday comfort. Features soft calfskin upper, cushioned insole, and vulcanized rubber sole.</p>',
                'material' => 'White Calfskin Leather & Rubber Outsole',
                'fabric' => 'Smooth Calfskin',
                'dimensions' => 'Standard Footwear Sizing 40-44',
                'care_instructions' => 'Clean with damp cloth and sneaker protector.',
                'country_of_origin' => 'Nepal',
                'weight' => 0.85,
                'seo_title' => 'Minimalist Low-Top Sneakers | Laijau',
                'seo_description' => 'White leather minimalist sneakers with durable cupsole. Direct Kathmandu dispatch.',
            ]
        );
        if ($catFootwear) $p2->categories()->sync([$catFootwear->id]);
        if ($colNoir) $p2->collections()->sync([$colNoir->id]);

        $p2->variants()->delete();
        $p2Barcodes = ['40' => '9770123201408', '41' => '9770123201415', '42' => '9770123201422', '43' => '9770123201439', '44' => '9770123201446'];
        foreach ($shoeSizes as $idx => $size) {
            ProductVariant::create([
                'product_id' => $p2->id,
                'sku' => "LJ-SHO-102-WHT-{$size}",
                'barcode' => $p2Barcodes[$size] ?? null,
                'size' => $size,
                'color' => 'Optical White',
                'color_hex' => '#FFFFFF',
                'stock_quantity' => 3 + ($idx % 2),
                'price' => 6980.00,
                'cost_price' => 2800.00,
                'is_active' => true,
            ]);
        }

        // =========================================================================
        // 3. Merino Wool Crewneck Knit (Apparel)
        // =========================================================================
        $p3 = Product::updateOrCreate(
            ['slug' => 'merino-wool-crewneck-knit'],
            [
                'name' => 'Merino Wool Crewneck Knit',
                'sku' => 'LJ-APP-103',
                'barcode' => '9770123301005',
                'supplier_name' => 'Himalayan Knitwear Cooperative',
                'supplier_sku' => 'HKC-2026-MRN03',
                'price' => 5400.00,
                'compare_at_price' => 6800.00,
                'cost_price' => 2200.00,
                'featured_image' => 'products/apparel.webp',
                'images' => [
                    'products/apparel.webp',
                ],
                'quantity' => 15,
                'track_quantity' => true,
                'low_stock_threshold' => 3,
                'is_active' => true,
                'is_published' => true,
                'is_featured' => true,
                'is_new_arrival' => true,
                'tax_class' => 'standard',
                'availability_status' => 'available',
                'short_description' => 'Ultra-fine 100% merino wool crewneck sweater with ribbed trims.',
                'description' => '<p>Spun from premium Himalayan-grade merino wool, this lightweight knit provides superior thermal regulation and natural breathability.</p>',
                'material' => '100% Extra-fine Merino Wool',
                'fabric' => 'Fine Gauge Jersey Knit',
                'dimensions' => 'Regular Fit Chest 38-44"',
                'care_instructions' => 'Hand wash cold or gentle wool cycle. Dry flat.',
                'country_of_origin' => 'Nepal',
                'weight' => 0.40,
                'seo_title' => 'Merino Wool Crewneck Knit | Laijau',
                'seo_description' => 'Pure merino wool crewneck sweater. Lightweight warmth crafted in Nepal.',
            ]
        );
        if ($catApparel) $p3->categories()->sync([$catApparel->id]);
        if ($colContemporary) $p3->collections()->sync([$colContemporary->id]);

        $p3->variants()->delete();
        $p3Barcodes = ['S' => '9770123301012', 'M' => '9770123301029', 'L' => '9770123301036', 'XL' => '9770123301043'];
        foreach ($apparelSizes as $idx => $size) {
            ProductVariant::create([
                'product_id' => $p3->id,
                'sku' => "LJ-APP-103-CHR-{$size}",
                'barcode' => $p3Barcodes[$size] ?? null,
                'size' => $size,
                'color' => 'Charcoal Heather',
                'color_hex' => '#36454F',
                'stock_quantity' => 3 + ($idx % 2),
                'price' => 5400.00,
                'cost_price' => 2200.00,
                'is_active' => true,
            ]);
        }

        // =========================================================================
        // 4. Organic Cotton Tapered Chinos (Apparel)
        // =========================================================================
        $p4 = Product::updateOrCreate(
            ['slug' => 'organic-cotton-tapered-chinos'],
            [
                'name' => 'Organic Cotton Tapered Chinos',
                'sku' => 'LJ-APP-104',
                'barcode' => '9770123401002',
                'supplier_name' => 'Kathmandu Weaving Cooperative',
                'supplier_sku' => 'KWC-2026-CHN04',
                'price' => 4200.00,
                'compare_at_price' => 5200.00,
                'cost_price' => 1800.00,
                'featured_image' => 'products/apparel.webp',
                'images' => [
                    'products/apparel.webp',
                ],
                'quantity' => 16,
                'track_quantity' => true,
                'low_stock_threshold' => 3,
                'is_active' => true,
                'is_published' => true,
                'is_featured' => false,
                'is_new_arrival' => false,
                'tax_class' => 'standard',
                'availability_status' => 'available',
                'short_description' => 'Stretch organic cotton twill chinos with clean tapered silhouette.',
                'description' => '<p>Everyday trousers cut with a clean tapered leg, angled front pockets, and buttoned rear welt pockets. Pre-washed for soft feel.</p>',
                'material' => '98% Organic Cotton, 2% Spandex',
                'fabric' => 'Mid-weight Twill',
                'dimensions' => 'Waist 30-36", Inseam 32"',
                'care_instructions' => 'Machine wash cold. Tumble dry low.',
                'country_of_origin' => 'Nepal',
                'weight' => 0.50,
                'seo_title' => 'Organic Cotton Tapered Chinos | Laijau',
                'seo_description' => 'Organic cotton twill tapered chinos for everyday versatile styling.',
            ]
        );
        if ($catApparel) $p4->categories()->sync([$catApparel->id]);
        if ($colContemporary) $p4->collections()->sync([$colContemporary->id]);

        $p4->variants()->delete();
        $p4Barcodes = ['S' => '9770123401019', 'M' => '9770123401026', 'L' => '9770123401033', 'XL' => '9770123401040'];
        foreach ($apparelSizes as $idx => $size) {
            ProductVariant::create([
                'product_id' => $p4->id,
                'sku' => "LJ-APP-104-KHK-{$size}",
                'barcode' => $p4Barcodes[$size] ?? null,
                'size' => $size,
                'color' => 'Classic Khaki',
                'color_hex' => '#C3B091',
                'stock_quantity' => 4,
                'price' => 4200.00,
                'cost_price' => 1800.00,
                'is_active' => true,
            ]);
        }

        // =========================================================================
        // 5. Heavyweight Canvas Overshirt (Outerwear)
        // =========================================================================
        $p5 = Product::updateOrCreate(
            ['slug' => 'heavyweight-canvas-overshirt'],
            [
                'name' => 'Heavyweight Canvas Overshirt',
                'sku' => 'LJ-OUT-105',
                'barcode' => '9770123501009',
                'supplier_name' => 'Kathmandu Weaving Cooperative',
                'supplier_sku' => 'KWC-2026-OVS05',
                'price' => 5800.00,
                'compare_at_price' => 7200.00,
                'cost_price' => 2400.00,
                'featured_image' => 'products/jacket.webp',
                'images' => [
                    'products/jacket.webp',
                ],
                'quantity' => 14,
                'track_quantity' => true,
                'low_stock_threshold' => 3,
                'is_active' => true,
                'is_published' => true,
                'is_featured' => true,
                'is_new_arrival' => true,
                'tax_class' => 'standard',
                'availability_status' => 'available',
                'short_description' => 'Durable organic cotton canvas utility overshirt with twin chest pockets.',
                'description' => '<p>Built for rugged layering in changing weather. Constructed from 10oz organic cotton duck canvas with reinforced stitching and corozo buttons.</p>',
                'material' => '100% Organic Cotton Canvas (10oz)',
                'fabric' => 'Heavy Duck Canvas',
                'dimensions' => 'Relaxed Overshirt Fit S-XL',
                'care_instructions' => 'Machine wash warm with like colors. Hang to dry.',
                'country_of_origin' => 'Nepal',
                'weight' => 0.65,
                'seo_title' => 'Heavyweight Canvas Overshirt | Laijau',
                'seo_description' => 'Durable 10oz canvas utility overshirt for all-season layering. Made in Nepal.',
            ]
        );
        if ($catOuterwear) $p5->categories()->sync([$catOuterwear->id]);
        if ($colNoir) $p5->collections()->sync([$colNoir->id]);

        $p5->variants()->delete();
        $p5Barcodes = ['S' => '9770123501016', 'M' => '9770123501023', 'L' => '9770123501030', 'XL' => '9770123501047'];
        foreach ($apparelSizes as $idx => $size) {
            ProductVariant::create([
                'product_id' => $p5->id,
                'sku' => "LJ-OUT-105-OLV-{$size}",
                'barcode' => $p5Barcodes[$size] ?? null,
                'size' => $size,
                'color' => 'Olive Drab',
                'color_hex' => '#556B2F',
                'stock_quantity' => 3 + ($idx % 2),
                'price' => 5800.00,
                'cost_price' => 2400.00,
                'is_active' => true,
            ]);
        }

        // =========================================================================
        // 6. Handcrafted Leather Wallet & Belt Set (Accessories)
        // =========================================================================
        $p6 = Product::updateOrCreate(
            ['slug' => 'handcrafted-leather-wallet-belt-set'],
            [
                'name' => 'Handcrafted Leather Wallet & Belt Set',
                'sku' => 'LJ-ACC-106',
                'barcode' => '9770123601006',
                'supplier_name' => 'Kathmandu Leathercraft Cooperative',
                'supplier_sku' => 'KLC-2026-SET06',
                'price' => 4500.00,
                'compare_at_price' => 5500.00,
                'cost_price' => 1900.00,
                'featured_image' => 'products/accessories.webp',
                'images' => [
                    'products/accessories.webp',
                ],
                'quantity' => 18,
                'track_quantity' => true,
                'low_stock_threshold' => 4,
                'is_active' => true,
                'is_published' => true,
                'is_featured' => false,
                'is_new_arrival' => false,
                'tax_class' => 'standard',
                'availability_status' => 'available',
                'short_description' => 'Full-grain leather bifold wallet with coordinating brass buckle belt.',
                'description' => '<p>Matched accessories crafted from supple vegetable-tanned cowhide. Features burnished edges, hand-waxed thread stitching, and a solid brass belt buckle.</p>',
                'material' => 'Full-Grain Vegetable Tanned Leather & Solid Brass',
                'fabric' => 'Smooth Veg-Tan Leather',
                'dimensions' => 'Wallet: 11cm x 9cm; Belt: 35mm width, 105cm length',
                'care_instructions' => 'Condition with natural leather balm.',
                'country_of_origin' => 'Nepal',
                'weight' => 0.45,
                'seo_title' => 'Handcrafted Leather Wallet & Belt Set | Laijau',
                'seo_description' => 'Full-grain leather bifold wallet and brass buckle belt gift set. Crafted in Nepal.',
            ]
        );
        if ($catAccessories) $p6->categories()->sync([$catAccessories->id]);
        if ($colHeritage) $p6->collections()->sync([$colHeritage->id]);

        $p6->variants()->delete();
        ProductVariant::create([
            'product_id' => $p6->id,
            'sku' => 'LJ-ACC-106-BRN-STD',
            'barcode' => '9770123601013',
            'size' => 'Standard',
            'color' => 'Cognac Brown',
            'color_hex' => '#9E4714',
            'stock_quantity' => 18,
            'price' => 4500.00,
            'cost_price' => 1900.00,
            'is_active' => true,
        ]);
    }
}
