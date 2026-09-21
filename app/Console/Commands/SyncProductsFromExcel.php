<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Inventory\StockLevel;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductVariant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncProductsFromExcel extends Command
{
    protected $signature = 'app:sync-products-from-excel';
    protected $description = 'Sync authoritative product catalog from Excel for Master & POS usage';

    public function handle(): int
    {
        $jsonPath = '/tmp/parsed_products_to_sync.json';
        if (!file_exists($jsonPath)) {
            $this->error("Parsed JSON not found at {$jsonPath}. Run python3 parse_excel_products.py first.");
            return 1;
        }

        $groups = json_decode(file_get_contents($jsonPath), true);
        $this->info("Loaded " . count($groups) . " product groups to sync.");

        // 1. Ensure Product Attributes (Sizes) exist
        $this->info("Ensuring size attributes exist in product_attributes...");
        $allSizes = [];
        foreach ($groups as $g) {
            foreach ($g['variants'] as $v) {
                if (!empty($v['size'])) {
                    $allSizes[$v['size']] = true;
                }
            }
        }

        foreach (array_keys($allSizes) as $sz) {
            $existing = ProductAttribute::where('type', 'size')->where('name', $sz)->first();
            if (!$existing) {
                ProductAttribute::create([
                    'name' => $sz,
                    'slug' => Str::slug($sz),
                    'type' => 'size',
                    'values' => null,
                ]);
            }
        }
        $this->info("Product attributes verified. Total in DB: " . ProductAttribute::count());

        // 2. Ensure Categories exist
        $this->info("Ensuring categories exist...");
        $categoryMap = [];
        $existingCats = Category::all();
        foreach ($existingCats as $c) {
            $categoryMap[strtolower(trim($c->name))] = $c->id;
            $categoryMap[$c->slug] = $c->id;
        }

        foreach ($groups as $g) {
            $catName = trim($g['category']);
            $norm = strtolower($catName);
            if (!isset($categoryMap[$norm])) {
                $slug = Str::slug($catName);
                $c = Category::firstOrCreate(
                    ['slug' => $slug],
                    ['name' => ucwords($catName), 'is_active' => true, 'sort_order' => 99]
                );
                $categoryMap[$norm] = $c->id;
                $categoryMap[$slug] = $c->id;
            }
        }
        $this->info("Categories verified.");

        // 3. Process Products & Variants inside DB Transaction
        $this->info("Beginning Master Product & Variant creation...");
        $defaultWarehouseId = 43; // Laijau Central Fulfillment Hub
        
        $createdProducts = 0;
        $updatedProducts = 0;
        $createdVariants = 0;

        DB::beginTransaction();
        try {
            foreach ($groups as $idx => $g) {
                $sku = $g['sku'];
                $product = Product::where('sku', $sku)->first();

                $catName = trim($g['category']);
                $catId = $categoryMap[strtolower($catName)] ?? $categoryMap[Str::slug($catName)] ?? 81; // fallback sneakers

                if (!$product) {
                    $slug = Str::slug($g['name'] . '-' . $sku);
                    if (Product::where('slug', $slug)->exists()) {
                        $slug = Str::slug($g['name'] . '-' . $sku . '-' . uniqid());
                    }

                    $product = Product::create([
                        'name' => $g['name'],
                        'sku' => $sku,
                        'slug' => $slug,
                        'type' => count($g['variants']) > 1 ? 'variable' : 'simple',
                        'description' => "<p>Authentic {$g['name']}. Full size run and colors available for POS and Storefront.</p>",
                        'price' => $g['price'],
                        'cost_price' => $g['cost_price'],
                        'price_npr' => (int)$g['price'],
                        'cost_price_npr' => (int)$g['cost_price'],
                        'supplier_name' => $g['supplier_name'],
                        'is_active' => true,
                        'is_published' => false, // Only products with verified physical images are published to storefront
                        'track_quantity' => true,
                        'quantity' => 0,
                    ]);

                    $product->categories()->syncWithoutDetaching([$catId]);
                    $createdProducts++;
                } else {
                    // Update costs/prices/supplier without touching published or images
                    $product->update([
                        'price' => $g['price'],
                        'cost_price' => $g['cost_price'],
                        'price_npr' => (int)$g['price'],
                        'cost_price_npr' => (int)$g['cost_price'],
                        'supplier_name' => $g['supplier_name'] ?? $product->supplier_name,
                    ]);
                    $product->categories()->syncWithoutDetaching([$catId]);
                    $updatedProducts++;
                }

                // Attach Variants
                foreach ($g['variants'] as $vData) {
                    $vSku = $vData['sku'];
                    $variant = ProductVariant::where('sku', $vSku)->first();
                    if (!$variant) {
                        $variant = ProductVariant::create([
                            'product_id' => $product->id,
                            'sku' => $vSku,
                            'size' => $vData['size'],
                            'color' => $vData['color'],
                            'price' => $vData['price'],
                            'cost_price' => $vData['cost_price'],
                            'price_npr' => (int)$vData['price'],
                            'cost_price_npr' => (int)$vData['cost_price'],
                            'stock_quantity' => 10, // Initial ready POS stock
                            'is_active' => true,
                        ]);

                        // Ensure stock level in warehouse
                        StockLevel::firstOrCreate(
                            [
                                'warehouse_id' => $defaultWarehouseId,
                                'product_id' => $product->id,
                                'variant_id' => $variant->id,
                            ],
                            [
                                'quantity_on_hand' => 10,
                                'quantity_reserved' => 0,
                            ]
                        );

                        $createdVariants++;
                    }
                }

                // Update product total quantity
                $totalStock = ProductVariant::where('product_id', $product->id)->sum('stock_quantity');
                $product->update(['quantity' => $totalStock]);
            }

            DB::commit();
            $this->info("Sync completed successfully!");
            $this->info("Created Products: {$createdProducts}");
            $this->info("Updated Products: {$updatedProducts}");
            $this->info("Created Variants: {$createdVariants}");
            $this->info("Total Master Products now: " . Product::count());
            $this->info("Total Published Storefront Products: " . Product::where('is_published', true)->count());
            $this->info("Total Active POS Products: " . Product::where('is_active', true)->count());
            $this->info("Total Variants now: " . ProductVariant::count());

            return 0;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("Sync failed: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return 1;
        }
    }
}
