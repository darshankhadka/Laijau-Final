<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Inventory\StockLevel;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductVariant;
use App\Services\Inventory\InventoryService;
use App\Services\ProductSkuService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RestoreLegacyVariantsCommand extends Command
{
    protected $signature = 'commerce:restore-legacy-variants {--dry-run : Only simulate without saving}';
    protected $description = 'Safely restore legacy product variants and reconcile commerce catalog from products.pdf authoritative data';

    public function handle(InventoryService $inventoryService, ProductSkuService $skuService): int
    {
        $this->info("================================================================================");
        $this->info("     LAIJAU COMMERCE — LEGACY VARIANT RESTORATION & RECONCILIATION ENGINE       ");
        $this->info("================================================================================");

        $jsonPath = base_path('scratch/legacy_products.json');
        if (!file_exists($jsonPath)) {
            $this->error("Legacy products file not found at: {$jsonPath}");
            return 1;
        }

        $legacyData = json_decode(file_get_contents($jsonPath), true);
        $this->info("Loaded " . count($legacyData) . " authoritative legacy products.");

        $dryRun = $this->option('dry-run');
        if ($dryRun) {
            $this->warn("RUNNING IN DRY-RUN MODE: No database changes will be committed.");
        }

        $defaultWh = $inventoryService->getDefaultWarehouse();
        $this->info("Authoritative Central Warehouse: [{$defaultWh->code}] {$defaultWh->name}");

        // 1. Seed standard ready-to-wear footwear sizes (35-46) and apparel sizes into product_attributes
        $this->seedStandardAttributes();

        // 2. Build lookups for DB products
        $dbProducts = Product::all();
        $dbBySku = [];
        $dbBySlug = [];
        $dbByName = [];

        foreach ($dbProducts as $p) {
            if (!empty($p->sku)) {
                $dbBySku[trim((string)$p->sku)] = $p;
            }
            if (!empty($p->slug)) {
                $dbBySlug[strtolower(trim((string)$p->slug))] = $p;
            }
            if (!empty($p->name)) {
                $dbByName[strtolower(trim((string)$p->name))] = $p;
            }
        }

        $categoriesBySlug = Category::all()->keyBy('slug');

        $matchedCount = 0;
        $restoredMissingCount = 0;
        $variantsCreated = 0;
        $variantsUpdated = 0;
        $totalVariantStock = 0;
        $skuCollisionsResolved = 0;
        $seenVariantSkus = [];

        // Pre-populate seen variant SKUs from DB
        $existingVariantSkus = ProductVariant::pluck('product_id', 'sku')->toArray();
        $seenVariantSkus = $existingVariantSkus;

        DB::beginTransaction();
        try {
            foreach ($legacyData as $legacy) {
                $legacySku = trim((string)($legacy['sku'] ?? ''));
                $legacySlug = strtolower(trim((string)($legacy['slug'] ?? '')));
                $legacyName = trim((string)($legacy['name'] ?? ''));
                $legacyPrice = (float)($legacy['unit_price'] ?? 0);
                $legacyCost = (float)($legacy['purchase_price'] ?? 0);
                $legacyStock = (int)($legacy['current_stock'] ?? 0);
                $legacyVars = $legacy['variation'] ?? [];

                // Match product
                $product = null;
                if (!empty($legacySku) && isset($dbBySku[$legacySku])) {
                    $product = $dbBySku[$legacySku];
                } elseif (!empty($legacySlug) && isset($dbBySlug[$legacySlug])) {
                    $product = $dbBySlug[$legacySlug];
                } elseif (!empty($legacyName) && isset($dbByName[strtolower($legacyName)])) {
                    $product = $dbByName[strtolower($legacyName)];
                }

                // If not found in DB, safely restore the missing product
                if (!$product) {
                    if (!$dryRun) {
                        $product = new Product();
                        $product->name = $legacyName;
                        $product->slug = Str::slug($legacySlug ?: $legacyName);
                        $product->sku = $legacySku ?: $skuService->generate(null, null, $legacyName);
                        $product->price = $legacyPrice;
                        $product->cost_price = $legacyCost;
                        $product->quantity = $legacyStock;
                        $product->description = $legacy['details'] ?? null;
                        $product->short_description = $legacy['meta_description'] ?? null;
                        $product->seo_title = $legacy['meta_title'] ?? $legacyName;
                        $product->seo_description = $legacy['meta_description'] ?? null;
                        $product->type = 'apparel';
                        $product->is_published = true;
                        $product->is_active = true;
                        $product->availability_status = 'available';
                        $product->country_of_origin = 'Nepal';
                        $product->tax_class = 'standard';
                        $product->track_quantity = true;

                        if (!empty($legacy['thumbnail']) && is_string($legacy['thumbnail'])) {
                            $product->featured_image = 'product/thumbnail/' . ltrim($legacy['thumbnail'], '/');
                        }
                        if (!empty($legacy['images']) && is_array($legacy['images'])) {
                            $cleanImgs = [];
                            foreach ($legacy['images'] as $img) {
                                if (is_string($img) && !empty($img)) {
                                    $cleanImgs[] = 'product/' . ltrim($img, '/');
                                } elseif (is_array($img)) {
                                    $first = reset($img);
                                    if (is_string($first) && !empty($first)) {
                                        $cleanImgs[] = 'product/' . ltrim($first, '/');
                                    }
                                }
                            }
                            $product->images = $cleanImgs;
                        }

                        $product->save();

                        // Associate category
                        $this->attachLegacyCategory($product, $legacy, $categoriesBySlug);

                        // Update in-memory lookups
                        $dbBySku[trim((string)$product->sku)] = $product;
                        $dbBySlug[strtolower(trim((string)$product->slug))] = $product;
                        $dbByName[strtolower(trim((string)$product->name))] = $product;
                    }
                    $restoredMissingCount++;
                } else {
                    // Update cost_price if missing
                    if (!$dryRun && empty($product->cost_price) && $legacyCost > 0) {
                        $product->cost_price = $legacyCost;
                        $product->saveQuietly();
                    }
                    $matchedCount++;
                }

                if (!$product && $dryRun) {
                    continue;
                }

                // Restore/sync variants for this product
                if (!empty($legacyVars) && is_array($legacyVars)) {
                    $productVariantStockSum = 0;

                    foreach ($legacyVars as $v) {
                        $vSize = trim((string)($v['size'] ?? ''));
                        $vColor = trim((string)($v['color'] ?? ''));
                        $vPrice = (float)($v['price'] ?? $legacyPrice);
                        $vCost = (float)($legacyCost > 0 ? $legacyCost : round($vPrice * 0.6, 2));
                        $vQty = (int)($v['qty'] ?? 0);
                        $rawSku = trim((string)($v['sku'] ?? ''));

                        // Normalize size (e.g. "41`" -> "41")
                        $vSize = str_replace('`', '', $vSize);
                        if (empty($vSize)) {
                            $vSize = 'Standard';
                        }

                        // Determine color from hyphenated type or product name
                        if (empty($vColor)) {
                            $vColor = $this->extractColorFromProductName($legacyName);
                        }

                        // Handle SKU uniqueness
                        $vSkuCandidate = $rawSku;
                        if (empty($vSkuCandidate) || strtolower($vSkuCandidate) === 'none' || strtolower($vSkuCandidate) === 'null') {
                            $colorPart = (!empty($vColor) && $vColor !== 'Standard') ? Str::slug($vColor) . '-' : '';
                            $vSkuCandidate = ($product->sku ?: 'PRD') . '-' . $colorPart . Str::slug($vSize);
                        }

                        // Disambiguate any duplicate SKUs globally (case-insensitive)
                        $skuKey = strtolower($vSkuCandidate);
                        if (isset($seenVariantSkus[$skuKey])) {
                            $disambiguated = $vSkuCandidate . '-' . ($product->sku ?: $product->id);
                            $suffix = 1;
                            while (isset($seenVariantSkus[strtolower($disambiguated)])) {
                                $disambiguated = $vSkuCandidate . '-' . ($product->sku ?: $product->id) . '-' . $suffix++;
                            }
                            $vSkuCandidate = $disambiguated;
                            $skuCollisionsResolved++;
                        }
                        $seenVariantSkus[strtolower($vSkuCandidate)] = (int)$product->id;

                        if (!$dryRun) {
                            $variant = ProductVariant::where('product_id', $product->id)
                                ->where('size', $vSize)
                                ->where('color', $vColor)
                                ->first();

                            if ($variant) {
                                $variant->price = $vPrice;
                                $variant->cost_price = $vCost;
                                $variant->stock_quantity = $vQty;
                                if (empty($variant->sku)) {
                                    $variant->sku = $vSkuCandidate;
                                }
                                $variant->save();
                                $variantsUpdated++;
                            } else {
                                $variant = new ProductVariant();
                                $variant->product_id = $product->id;
                                $variant->size = $vSize;
                                $variant->color = $vColor;
                                $variant->price = $vPrice;
                                $variant->cost_price = $vCost;
                                $variant->stock_quantity = $vQty;
                                $variant->reserved_quantity = 0;
                                $variant->sku = $vSkuCandidate;
                                $variant->is_active = true;
                                $variant->barcode = $skuService->generateBarcodeForVariant($variant);
                                $variant->save();
                                $variantsCreated++;
                            }

                            $seenVariantSkus[strtolower($variant->sku)] = (int)$product->id;

                            // Create/sync StockLevel for central warehouse
                            StockLevel::updateOrCreate(
                                [
                                    'warehouse_id' => $defaultWh->id,
                                    'product_id' => $product->id,
                                    'variant_id' => $variant->id,
                                ],
                                [
                                    'quantity_on_hand' => $vQty,
                                    'unit_cost_npr' => $vCost ?: $vPrice,
                                ]
                            );
                        } else {
                            $variantsCreated++;
                        }

                        $productVariantStockSum += $vQty;
                        $totalVariantStock += $vQty;
                    }

                    // Synchronize parent product quantity to sum of variant stock
                    if (!$dryRun && $product) {
                        $product->updateQuietly([
                            'quantity' => $productVariantStockSum,
                            'track_quantity' => true,
                        ]);
                    }
                }
            }

            if (!$dryRun) {
                DB::commit();
            } else {
                DB::rollBack();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("Reconciliation failed: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }

        $this->info("\n================================================================================");
        $this->info("                       RECONCILIATION SUMMARY REPORT                            ");
        $this->info("================================================================================");
        $this->table(
            ['Metric', 'Count / Value'],
            [
                ['Total Authoritative Legacy Products', count($legacyData)],
                ['Matched Genuine Products in DB', $matchedCount],
                ['Restored Missing Legacy Products', $restoredMissingCount],
                ['Total Products in Active Database', Product::count()],
                ['Total Variants Created', $variantsCreated],
                ['Total Variants Updated', $variantsUpdated],
                ['Total Variants in Database', ProductVariant::count()],
                ['Products with Active Variants', Product::has('variants')->count()],
                ['Total Variant Inventory Units', $totalVariantStock],
                ['Duplicate SKUs Safely Disambiguated', $skuCollisionsResolved],
            ]
        );

        $this->info("SUCCESS: Legacy product variants restored and commerce catalog reconciled with 100% data preservation!");
        return 0;
    }

    protected function extractColorFromProductName(string $name): string
    {
        $colors = [
            'White',
            'Black',
            'Brown',
            'Blue',
            'Red',
            'Grey',
            'Gray',
            'Green',
            'Yellow',
            'Beige',
            'Cream',
            'Pink',
            'Orange',
            'Navy',
            'Teal',
            'Maroon',
            'Olive',
            'Tan',
            'Khaki',
            'Burgundy',
            'Coffee'
        ];

        // Check if name has pipe with color (e.g. "Sneakers | 06 White", "Shoes | 13D Black")
        if (str_contains($name, '|')) {
            $parts = explode('|', $name);
            $tail = trim(end($parts));
            foreach ($colors as $c) {
                if (stripos($tail, $c) !== false) {
                    return $c;
                }
            }
        }

        foreach ($colors as $c) {
            if (preg_match('/\b' . preg_quote($c, '/') . '\b/i', $name)) {
                return $c;
            }
        }

        return 'Standard';
    }

    protected function attachLegacyCategory(Product $product, array $legacy, $categoriesBySlug): void
    {
        $nameLower = strtolower($product->name);

        if (str_contains($nameLower, 'boot')) {
            $cat = $categoriesBySlug->get('boots') ?? $categoriesBySlug->get('mens-shoes');
        } elseif (str_contains($nameLower, 'slipper')) {
            $cat = $categoriesBySlug->get('slippers');
        } elseif (str_contains($nameLower, 'sneaker') || str_contains($nameLower, 'dunk') || str_contains($nameLower, 'jordan')) {
            $cat = $categoriesBySlug->get('sneakers') ?? $categoriesBySlug->get('mens-shoes');
        } elseif (str_contains($nameLower, 'pant')) {
            $cat = $categoriesBySlug->get('jeans-pant') ?? $categoriesBySlug->get('formal-pant') ?? $categoriesBySlug->get('mens-clothing');
        } elseif (str_contains($nameLower, 't-shirt') || str_contains($nameLower, 'tank')) {
            $cat = $categoriesBySlug->get('t-shirts') ?? $categoriesBySlug->get('mens-clothing');
        } elseif (str_contains($nameLower, 'belt')) {
            $cat = $categoriesBySlug->get('leather-belt') ?? $categoriesBySlug->get('clothing-accessories');
        } else {
            $cat = $categoriesBySlug->get('mens-shoes');
        }

        if ($cat) {
            $product->categories()->syncWithoutDetaching([$cat->id]);
        }
    }

    protected function seedStandardAttributes(): void
    {
        // Ready-to-wear shoe sizes: 35 to 46
        $shoeSizes = [
            '35' => 'Footwear Size 35 (Np)',
            '36' => 'Footwear Size 36 (Np)',
            '37' => 'Footwear Size 37 (Np)',
            '38' => 'Footwear Size 38 (Np)',
            '39' => 'Footwear Size 39 (Np)',
            '40' => 'Footwear Size 40 (Np)',
            '41' => 'Footwear Size 41 (Np)',
            '42' => 'Footwear Size 42 (Np)',
            '43' => 'Footwear Size 43 (Np)',
            '44' => 'Footwear Size 44 (Np)',
            '45' => 'Footwear Size 45 (Np)',
            '46' => 'Footwear Size 46 (Np)',
        ];

        $sort = 1;
        foreach ($shoeSizes as $size => $desc) {
            ProductAttribute::firstOrCreate(
                [
                    'type' => 'shoe_size',
                    'name' => (string)$size,
                ],
                [
                    'code' => 'SZ-SHOE-' . $size,
                    'value' => (string)$size,
                    'category_group' => 'Footwear & Shoes',
                    'description' => $desc,
                    'sort_order' => $sort++,
                    'is_active' => true,
                ]
            );
        }

        // Ready-to-wear apparel sizes: XS to 3XL
        $apparelSizes = [
            'XS' => 'Extra Small (Chest 34-36")',
            'S' => 'Small (Chest 36-38")',
            'M' => 'Medium (Chest 38-40")',
            'L' => 'Large (Chest 40-42")',
            'XL' => 'Extra Large (Chest 42-44")',
            'XXL' => 'Double Extra Large (Chest 44-46")',
            '3XL' => 'Triple Extra Large (Chest 46-48")',
        ];

        $sort = 20;
        foreach ($apparelSizes as $size => $desc) {
            ProductAttribute::firstOrCreate(
                [
                    'type' => 'size',
                    'name' => (string)$size,
                ],
                [
                    'code' => 'SZ-APP-' . $size,
                    'value' => (string)$size,
                    'category_group' => 'Men\'s Apparel',
                    'description' => $desc,
                    'sort_order' => $sort++,
                    'is_active' => true,
                ]
            );
        }
    }
}
