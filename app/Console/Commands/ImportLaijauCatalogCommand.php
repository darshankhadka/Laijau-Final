<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\Warehouse;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportLaijauCatalogCommand extends Command
{
    protected $signature = 'laijau:import-catalog';
    protected $description = 'Import real Laijau products and categories from readyecommerce database';

    public function handle(): int
    {
        $this->info('Starting Laijau catalog import from readyecommerce database...');

        // 1. Check readyecommerce tables exist
        try {
            $catCount = DB::select('SELECT COUNT(*) as cnt FROM readyecommerce.categories')[0]->cnt;
            $prodCount = DB::select('SELECT COUNT(*) as cnt FROM readyecommerce.products')[0]->cnt;
            $this->info("Found {$catCount} categories and {$prodCount} products in readyecommerce.");
        } catch (\Throwable $e) {
            $this->error('Failed to access readyecommerce database: ' . $e->getMessage());
            return 1;
        }

        $centralWh = Warehouse::where('code', 'WH-KTM-MAIN')->first() ?? Warehouse::first();
        $showroomWh = Warehouse::where('code', 'STORE-KTM-01')->first() ?? $centralWh;

        // 2. Import Categories
        $this->info('Importing categories...');
        $sourceCategories = DB::select('SELECT * FROM readyecommerce.categories ORDER BY parent_id ASC, id ASC');
        $categoryMap = []; // old_id => new_id

        foreach ($sourceCategories as $srcCat) {
            $slug = !empty($srcCat->slug) ? Str::slug($srcCat->slug) : Str::slug($srcCat->name);

            // Ensure unique slug
            $existing = Category::where('slug', $slug)->first();
            if ($existing) {
                $categoryMap[$srcCat->id] = $existing->id;
                continue;
            }

            $iconPath = !empty($srcCat->icon) ? 'category/' . $srcCat->icon : null;
            $parentId = ($srcCat->parent_id && isset($categoryMap[$srcCat->parent_id])) ? $categoryMap[$srcCat->parent_id] : null;

            $cat = Category::create([
                'name' => $srcCat->name,
                'slug' => $slug,
                'parent_id' => $parentId,
                'image' => $iconPath,
                'is_active' => true,
                'seo_title' => $srcCat->name . ' - Buy Online in Nepal | Laijau',
                'seo_description' => "Shop high-quality {$srcCat->name} online at Laijau. Fast delivery across Kathmandu Valley and all 77 districts of Nepal.",
            ]);

            $categoryMap[$srcCat->id] = $cat->id;
        }
        $this->info('Categories imported successfully. Total active categories in Laijau: ' . Category::count());

        // 3. Import Products
        $this->info('Importing products...');
        $sourceProducts = DB::select('SELECT * FROM readyecommerce.products WHERE status = 1');
        $imported = 0;
        $skipped = 0;

        foreach ($sourceProducts as $srcProd) {
            $unitPrice = round((float)$srcProd->unit_price, 2);
            if ($unitPrice <= 0) {
                $unitPrice = 1500.00;
            }

            $discount = round((float)$srcProd->discount, 2);
            $compareAtPrice = null;
            if ($discount > 0) {
                if ($srcProd->discount_type === 'percent') {
                    $compareAtPrice = round($unitPrice / (1 - ($discount / 100)), 2);
                } else {
                    $compareAtPrice = round($unitPrice + $discount, 2);
                }
            }

            // Clean thumbnail path
            $thumb = null;
            if (!empty($srcProd->thumbnail)) {
                if (is_string($srcProd->thumbnail)) {
                    $thumb = 'product/thumbnail/' . $srcProd->thumbnail;
                } elseif (is_array($srcProd->thumbnail)) {
                    $thumb = 'product/thumbnail/' . ($srcProd->thumbnail['image_name'] ?? ($srcProd->thumbnail['image'] ?? ''));
                }
            }

            // Gallery images
            $gallery = [];
            if (!empty($srcProd->images)) {
                $decoded = is_array($srcProd->images) ? $srcProd->images : json_decode($srcProd->images, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $img) {
                        if (is_string($img)) {
                            $gallery[] = 'product/' . $img;
                        } elseif (is_array($img)) {
                            $imgName = $img['image_name'] ?? ($img['image'] ?? '');
                            if ($imgName) {
                                $gallery[] = 'product/' . $imgName;
                            }
                        }
                    }
                }
            }
            if (empty($gallery) && $thumb) {
                $gallery[] = $thumb;
            }

            $sku = !empty($srcProd->code) ? $srcProd->code : 'LJ-SH-' . str_pad((string)$srcProd->id, 5, '0', STR_PAD_LEFT);
            $slugBase = !empty($srcProd->slug) ? Str::slug($srcProd->slug) : Str::slug($srcProd->name);

            $existingProd = Product::where('sku', $sku)->first();
            $slug = $existingProd ? $existingProd->slug : $slugBase;

            if (!$existingProd) {
                $slugCheck = Product::where('slug', $slug)->first();
                if ($slugCheck) {
                    $slug = $slugBase . '-' . $srcProd->id;
                }
            }

            $qty = max(5, (int)($srcProd->current_stock ?? 10));

            $product = Product::updateOrCreate(
                ['sku' => $sku],
                [
                    'name' => $srcProd->name,
                    'slug' => $slug,
                    'price' => $unitPrice,
                    'price_npr' => $unitPrice,
                    'compare_at_price' => $compareAtPrice,
                    'cost_price_npr' => round($unitPrice * 0.6, 2),
                    'quantity' => $qty,
                    'track_quantity' => true,
                    'is_active' => true,
                    'is_published' => true,
                    'is_featured' => (bool)$srcProd->featured_status,
                    'is_new_arrival' => $imported < 40,
                    'featured_image' => $thumb,
                    'images' => $gallery,
                    'description' => $srcProd->details ?? $srcProd->name,
                    'short_description' => Str::limit(strip_tags((string)$srcProd->details), 160) ?: "Order authentic {$srcProd->name} online at Laijau Nepal.",
                    'seo_title' => $srcProd->name . ' - Price in Nepal | Laijau',
                    'seo_description' => "Buy {$srcProd->name} at best price in Nepal. Cash on Delivery in Kathmandu Valley, fast nationwide delivery.",
                    'dimensions' => is_string($srcProd->choice_options) ? $srcProd->choice_options : json_encode($srcProd->choice_options),
                ]
            );

            // Map Category IDs
            if (!empty($srcProd->category_ids)) {
                $catIdsArr = json_decode($srcProd->category_ids, true);
                if (is_array($catIdsArr)) {
                    $attachIds = [];
                    foreach ($catIdsArr as $cItem) {
                        $oldId = (int)($cItem['id'] ?? 0);
                        if (isset($categoryMap[$oldId])) {
                            $attachIds[] = $categoryMap[$oldId];
                        }
                    }
                    if (!empty($attachIds)) {
                        $product->categories()->sync(array_unique($attachIds));
                    }
                }
            }

            // Create stock levels in both central warehouse and showroom
            if ($centralWh) {
                StockLevel::updateOrCreate(
                    [
                        'warehouse_id' => $centralWh->id,
                        'product_id' => $product->id,
                        'variant_id' => null,
                    ],
                    [
                        'quantity_on_hand' => $qty,
                        'quantity_reserved' => 0,
                        'unit_cost_npr' => round($unitPrice * 0.6, 2),
                    ]
                );
            }

            if ($showroomWh && $showroomWh->id !== $centralWh->id) {
                StockLevel::updateOrCreate(
                    [
                        'warehouse_id' => $showroomWh->id,
                        'product_id' => $product->id,
                        'variant_id' => null,
                    ],
                    [
                        'quantity_on_hand' => min(5, $qty),
                        'quantity_reserved' => 0,
                        'unit_cost_npr' => round($unitPrice * 0.6, 2),
                    ]
                );
            }

            $imported++;
            if ($imported % 100 === 0) {
                $this->info("Imported {$imported} products...");
            }
        }

        $this->info("Successfully imported {$imported} products into active LAIJAU database!");
        $this->info("Total products in database: " . Product::count());

        return 0;
    }
}
