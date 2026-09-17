<?php

declare(strict_types=1);

namespace App\Services\Operational;

use App\Models\Category;
use App\Models\Inventory\PurchaseOrderItem;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\Warehouse;
use App\Models\OfflineSaleItem;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CatalogSyncService
{
    protected string $extractedDir;
    protected string $migrationDir;

    public function __construct()
    {
        $this->extractedDir = storage_path('app/migration/real_data_extracted');
        $this->migrationDir = storage_path('app/migration');
        if (!File::isDirectory($this->migrationDir)) {
            File::makeDirectory($this->migrationDir, 0755, true);
        }
    }

    /**
     * Determine whether a product has a genuine physical, non-zero image file on disk.
     */
    public function hasPhysicalPhoto(Product $product): bool
    {
        if (!empty($product->featured_image)) {
            $p1 = public_path('storage/' . $product->featured_image);
            $p2 = storage_path('app/public/' . $product->featured_image);
            if ((File::exists($p1) && File::size($p1) > 0) || (File::exists($p2) && File::size($p2) > 0)) {
                return true;
            }
        }

        if (!empty($product->images) && is_array($product->images)) {
            foreach ($product->images as $img) {
                if (!empty($img)) {
                    $p1 = public_path('storage/' . $img);
                    $p2 = storage_path('app/public/' . $img);
                    if ((File::exists($p1) && File::size($p1) > 0) || (File::exists($p2) && File::size($p2) > 0)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Validate whether a product is eligible for public ecommerce publication.
     */
    public function validatePublicationEligibility(Product $product): array
    {
        $missing = [];

        if (!$this->hasPhysicalPhoto($product)) {
            $missing[] = 'Product photo (physical file verified on disk)';
        }

        if ((float)$product->price <= 0) {
            $missing[] = 'Valid selling price (> Rs. 0)';
        }

        if ($product->categories()->count() === 0) {
            $missing[] = 'Product category';
        }

        $sku = trim((string)$product->sku);
        if (empty($sku)) {
            $missing[] = 'Product SKU / code';
        } elseif (preg_match('/^(NA-PRD-|TEMP-|UNKNOWN)/i', $sku)) {
            $missing[] = 'Valid resolved product code (currently placeholder)';
        }

        if (preg_match('/^(COD|CIPS|ESEWA|KHALTI|TEST)/i', $sku) || preg_match('/(payment test|sample item|dummy)/i', $product->name)) {
            $missing[] = 'Staging payment test product cannot be published';
        }

        return [
            'eligible' => empty($missing),
            'missing' => $missing,
        ];
    }

    /**
     * Clean customer-facing retail title.
     * Retains underlying SKU, model codes, and colors while replacing trademark/brand claims
     * with neutral generic retail terminology according to Section 2, 5, 10.
     */
    public function cleanCustomerFacingTitle(string $rawTitle): string
    {
        $t = $rawTitle;

        // 1. Explicit whole-phrase replacements for trademark-inspired names
        $phraseSubs = [
            '/\bTimberland\s+yellow\s+boots(\s+for\s+men)?\b/i' => 'Rugged Outdoor Boots – Yellow',
            '/\bSteel\s+Toe\s+Timberland\s+Inspired\s+Boot\b/i' => 'Steel Toe Outdoor Work Boots',
            '/\bTimberland\s+Boots\s*–\s*Durable\s+Outdoor\s+Footwear\.?\s*/i' => 'Rugged Outdoor Boots – ',
            '/\bTimberland\s+Inspired\s+Boots?\b/i' => 'Rugged Outdoor Boots',
            '/\bTimberland\s+Boots?\b/i' => 'Rugged Outdoor Boots',
            '/\bCaterpillar\s+Inspired\s+Boots?\b/i' => 'Work Utility Boots',
            '/\bCaterpillar\s+boots?\b/i' => 'Work Utility Boots',
            '/\bDr\.?\s*Mart[ie]ns?[- ]Inspired\s+Triple\s+sole\s+shoes?\b/i' => 'Triple Sole Derby Shoes',
            '/\bDr\.?\s*Mart[ie]ns?[- ]Inspired\s+Double\s+sole\s+shoes?\b/i' => 'Double Sole Derby Shoes',
            '/\bDr\.?\s*Mart[ie]ns?[- ]Inspired\s+Design[- ]?/i' => 'Classic Derby Shoes – ',
            '/\bDr\.?\s*Mart[ie]ns?[- ]inspired\s+Shoes?\b/i' => 'Classic Derby Shoes',
            '/\bDr\.?\s*Mart[ie]ns?[- ]inspired\b/i' => 'Classic Derby Shoes',
            '/\bSamba\s+Inspired\s+Shoes\s+Black\/White\s*–\s*samba\s+Black\b/i' => 'Retro Casual Sneakers – Samba Black',
            '/\bSamba\s+Inspired\s+Shoes?\b/i' => 'Retro Casual Sneakers',
            '/\bAirforce\s*1\s+White\s+and\s+Brown\b/i' => 'Court Casual Sneakers – White and Brown',
            '/\bAirforce\s*1\b/i' => 'Court Casual Sneakers',
            '/\bJordan\s+Brown\s+and\s+White\b/i' => 'Basketball Style Sneakers – Brown and White',
            '/\bAir\s+Jordan\s+inspired\b/i' => 'Basketball Style Sneakers',
            '/\bAir\s+Jordan\b/i' => 'Basketball Style Sneakers',
            '/\bDunk\s+Low[- ]inspired\s+shoes?\b/i' => 'Low-Top Sneakers',
            '/\bDunk\s+Low\b/i' => 'Low-Top Sneakers',
            '/\bDunk\s+High[- ]inspired\s+shoes?\b/i' => 'High-Top Sneakers',
            '/\bDunk\s+High\b/i' => 'High-Top Sneakers',
            '/\bDior\s+High[- ]inspired\s+shoes?\b/i' => 'High-Top Streetwear Sneakers',
            '/\bDior\s+Inspired\s+Shoes?\b/i' => 'Casual Streetwear Sneakers',
            '/\bOnitsuka\s+Tiger\s+Inspired\b/i' => 'Heritage Casual Sneakers',
            '/\bOnitsuka\s+Tiger\b/i' => 'Heritage Casual Sneakers',
            '/\bHarley\s+Davidson\s+inspired\s+Boots?\b/i' => 'Classic Ankle Boots',
            '/\bHarley\s+Davidson\s+boot\b/i' => 'Classic Ankle Boots',
            '/\bWhites\s+inspired\s+Boots?\b/i' => 'Heavy Duty Work Boots',
            '/\bWhite\s+BOB\b/i' => 'Heavy Duty Work Boots',
            '/\bBlack\s+Pure\s+Leather\s+Men\'s\s+Western\s+Dingo\s+Boots\s*–\s*Dingo\s*/i' => 'Western Style Boots – ',
            '/\bPure\s+leather\s+western\s+boots\s*–\s*Dingo\s*/i' => 'Western Style Boots – ',
            '/\bCarhartt\s+WIP\s+Relaxed-Fit\s+Canvas\s+[\'"]Realtree[\'"]\s+Camo\s+TROUSER\b/i' => 'Relaxed-Fit Canvas Camo Trousers',
            '/\bTommy\s+Hilfiger\s+Classic\s+Leather\s+Belt\s+with\s+Brushed\s+Metal\s+Buckle\b/i' => 'Classic Metal Buckle Belt',
            '/\bGEMEI\s+Hair\s+Dryer-Model\s*–\s*/i' => 'Hair Dryer – ',
            '/\bMen\'s\s+Light\s+Green\s+Adidas\s+T-Shirt\s+with\s+Horizontal\s+Ribbed\s+Texture[- ]*/i' => 'Horizontal Ribbed Textured T-Shirt – ',
            '/\bPremium\s+Mauve\s+Lacoste\s+Crew\s+Neck\s+with\s+crocodile\s+graphic\s+wear\s+for\s+Men\b/i' => 'Crew Neck Graphic T-Shirt – Mauve',
            '/\bBlack\s+Cobra\s+leather\s+shoes(\s+for\s+men)?\s*–\s*/i' => 'Classic Oxford Shoes – ',
            '/\bBlack\s+Cobra\b/i' => 'Classic Shoes',
            '/\bTBL\s+Shoes\b/i' => 'Casual Sneakers',
            '/\bTBL\b/i' => 'Casual',
            '/\bNike\b/i' => '',
            '/\bJordan\b/i' => '',
            '/\bAdidas\b/i' => '',
            '/\bDr\.?\s*Mart[ie]ns?\b/i' => '',
            '/\bTimberland\b/i' => '',
            '/\bCaterpillar\b/i' => '',
            '/\bLacoste\b/i' => '',
            '/\bTommy\s+Hilfiger\b/i' => '',
            '/\bCarhartt(\s+WIP)?\b/i' => '',
            '/\bOnitsuka(\s+Tiger)?\b/i' => '',
            '/\bHarley\s+Davidson\b/i' => '',
            '/\bBirkenstock\b/i' => '',
            '/\bConverse\b/i' => '',
            '/\bPuma\b/i' => '',
            '/\bReebok\b/i' => '',
            '/\bNew\s+Balance\b/i' => '',
        ];

        foreach ($phraseSubs as $pat => $rep) {
            $t = preg_replace($pat, $rep, $t);
        }

        // 2. Strip unsupported marketing exaggerations and false claims
        $unsupportedClaims = [
            '/\b100\s*%\s*original\b/i',
            '/\b100\s*%\s*/',
            '/\bauthentic\b/i',
            '/\bgenuine\s+leather\b/i',
            '/\bpremium\s+leather\b/i',
            '/\bpure\s+leather\b/i',
            '/\bleather\b/i',
            '/\bhandmade\b/i',
            '/\bwaterproof\b/i',
            '/\borthopedic\b/i',
            '/\bluxury\b/i',
            '/\bimported\b/i',
            '/\bprofessional\b/i',
            '/\bbest\b/i',
            '/\btimeless\s+streetwear\b/i',
            '/\bmost\s+comfortable,?\s*easy\b/i',
            '/\bfor\s+ultimate\s+comfort\b/i',
            '/\bfor\s+men\b/i',
            '/\bfor\s+women\b/i',
            '/\bladies\b/i',
            '/\bpremium\b/i',
        ];

        foreach ($unsupportedClaims as $pat) {
            $t = preg_replace($pat, '', $t);
        }

        // 3. Clean delimiters and punctuation
        $t = preg_replace('/\s*\|\s*/u', ' – ', $t);
        $t = preg_replace('/\s+[-–—:]\s+/u', ' – ', $t);
        $t = preg_replace('/\s*–\s*–\s*/u', ' – ', $t);
        $t = preg_replace('/\s{2,}/', ' ', $t);
        $t = trim($t, " \t\n\r\0\x0B–-:");

        // 4. Remove duplicate consecutive words
        $t = preg_replace('/\b(\w+)\s+\1\b/i', '$1', $t);

        if (preg_match('/^([A-Za-z0-9]+)\s+Factory\s+Shoes\s*–\s*(.+)$/i', $t, $m)) {
            $factory = $m[1];
            $rest = ucwords(strtolower($m[2]));
            $t = "{$factory} Shoes – {$rest}";
        }

        return $t ?: $rawTitle;
    }

    /**
     * Generate concise original customer-facing description from verified attributes only (Section 12).
     */
    public function generateFactualDescription(Product $product, array $classification = []): string
    {
        $name = $product->name;
        $sku = (string)$product->sku;
        $type = $product->type ?: 'Product';
        if (empty($classification['department']) || empty($classification['leaf'])) {
            $cat = $product->categories()->first();
            $leaf = $cat ? $cat->name : 'Shoes';
            $department = $cat && $cat->parent ? $cat->parent->name : 'Men Footwear';
        } else {
            $department = ucwords(str_replace(['mens-', 'womens-', '-'], ['', '', ' '], $classification['department']));
            $leaf = ucwords(str_replace(['mens-', 'womens-', '-'], ['', '', ' '], $classification['leaf']));
        }

        $sizes = $product->variants()
            ->where('is_active', true)
            ->pluck('size')
            ->filter()
            ->unique()
            ->sort()
            ->implode(', ');

        $color = $product->variants()->first()?->color ?: '';
        if (empty($color) && preg_match('/–\s*([A-Za-z0-9.]+)\s+([A-Za-z]+)$/u', $name, $m)) {
            $color = ucwords(strtolower($m[2]));
        }

        $overview = match (strtolower($type)) {
            'footwear' => "Laijau {$leaf} designed for everyday durability and comfort. Built with a structured silhouette, flexible outsole, and reliable construction for active daily wear.",
            'accessory' => "Everyday essential {$leaf} selected by Laijau for practical utility and clean styling.",
            default => "Comfortable everyday {$leaf} offering a versatile fit and durable stitching for regular wear.",
        };

        $lines = [];
        $lines[] = "### Overview";
        $lines[] = $overview;
        $lines[] = "";
        $lines[] = "### Details";
        $lines[] = "- **Category**: {$department} → {$leaf}";
        if (!empty($color)) {
            $lines[] = "- **Color**: {$color}";
        }
        if (!empty($sizes)) {
            $lines[] = "- **Available Sizes**: {$sizes}";
        }
        if (!empty($sku)) {
            $lines[] = "- **Model Code**: {$sku}";
        }
        if (!empty($product->material) && !preg_match('/(pure|genuine|premium)\s+leather/i', $product->material)) {
            $lines[] = "- **Material**: " . trim($product->material);
        }

        return implode("\n", $lines);
    }

    /**
     * Load source datasets with file, sheet, and row provenance.
     */
    public function loadSourceData(): array
    {
        $shoesStock = File::exists("{$this->extractedDir}/shoes_stock.json")
            ? json_decode(File::get("{$this->extractedDir}/shoes_stock.json"), true)
            : [];

        $clothesStock = File::exists("{$this->extractedDir}/clothes_stock.json")
            ? json_decode(File::get("{$this->extractedDir}/clothes_stock.json"), true)
            : [];

        $purchases = File::exists("{$this->extractedDir}/purchases_jan_2026.json")
            ? json_decode(File::get("{$this->extractedDir}/purchases_jan_2026.json"), true)
            : [];

        return [
            'shoes' => $shoesStock,
            'clothes' => $clothesStock,
            'purchases' => $purchases,
        ];
    }

    /**
     * Perform multi-level matching between an existing public product and real source records.
     */
    public function matchProductToSource(Product $product, array $sourceIndexes): array
    {
        $sku = strtoupper(trim((string)$product->sku));
        $name = strtoupper(trim((string)$product->name));

        // Level 1: Exact SKU match in source index
        if (isset($sourceIndexes['by_sku'][$sku])) {
            return [
                'level' => 1,
                'status' => 'MATCHED',
                'confidence' => 'CONFIRMED',
                'reason' => "Exact normalized SKU match ('{$sku}')",
                'source' => $sourceIndexes['by_sku'][$sku],
            ];
        }

        // Level 2: Factory prefix decomposition
        // Prefixes used in Laijau: PF (Prasiddha), MAX (Max Factory), BC (Black Cobra), HS (Himshikhar), CAT (Caterpillar), RUN (Run Shoes), CUT (Citizen Cut)
        $cleanCode = null;
        $factoryHint = null;
        if (preg_match('/^(PF|MAX|BC|HS|CAT|RUN|CUT)([0-9A-Z.]+)(BLACK|BROWN|TAN|COFFEE|WHITE|GREEN|GREY|BL|BR)?$/i', $sku, $m)) {
            $factoryHint = $m[1];
            $cleanCode = $m[2];
            $colorHint = $m[3] ?? '';

            if (isset($sourceIndexes['by_code'][$cleanCode])) {
                $candidates = $sourceIndexes['by_code'][$cleanCode];
                foreach ($candidates as $cand) {
                    if (empty($colorHint) || stripos($cand['color'] ?? '', $colorHint) !== false || stripos($cand['colour'] ?? '', $colorHint) !== false) {
                        return [
                            'level' => 2,
                            'status' => 'MATCHED',
                            'confidence' => 'HIGH',
                            'reason' => "Decomposed prefixed SKU '{$sku}' into factory '{$factoryHint}', code '{$cleanCode}', color '{$colorHint}' matching source record",
                            'source' => $cand,
                        ];
                    }
                }
            }
        }

        // Level 2b: Code extracted from title suffix: 'Title | 1314 Broshof' or 'Title - 15 brown'
        if (preg_match('/[|–-]\s*([A-Za-z0-9.]+)\s+([A-Za-z\s]+)$/u', $product->name, $m)) {
            $titleCode = strtoupper(preg_replace('/[^A-Za-z0-9.]/', '', $m[1]));
            $titleColor = strtoupper(trim($m[2]));

            if (isset($sourceIndexes['by_code'][$titleCode])) {
                $candidates = $sourceIndexes['by_code'][$titleCode];
                foreach ($candidates as $cand) {
                    $candColor = strtoupper($cand['color'] ?? ($cand['colour'] ?? ''));
                    if (empty($titleColor) || strpos($candColor, $titleColor) !== false || strpos($titleColor, $candColor) !== false) {
                        return [
                            'level' => 2,
                            'status' => 'MATCHED',
                            'confidence' => 'HIGH',
                            'reason' => "Code '{$titleCode}' and color '{$titleColor}' extracted from title suffix matching source record",
                            'source' => $cand,
                        ];
                    }
                }
            }
        }

        // Level 3: Verified source identity (exact item name in Clothes Stock)
        $cleanName = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $product->name));
        if (isset($sourceIndexes['clothes_by_name'][$cleanName])) {
            return [
                'level' => 3,
                'status' => 'MATCHED',
                'confidence' => 'CONFIRMED',
                'reason' => "Exact clothing item name match in Clothes Stock",
                'source' => $sourceIndexes['clothes_by_name'][$cleanName],
            ];
        }

        // Check if staging / test
        if (preg_match('/^(COD|CIPS|ESEWA|KHALTI|TEST)/i', $sku) || preg_match('/(payment test|sample item|dummy)/i', $product->name)) {
            return [
                'level' => 4,
                'status' => 'STAGING',
                'confidence' => 'CONFIRMED',
                'reason' => 'Staging payment verification / dummy testing record',
                'source' => null,
            ];
        }

        // Standalone existing catalog product (legitimate pre-existing Laijau showroom item)
        if ($product->id <= 820) {
            return [
                'level' => 4,
                'status' => 'MISSING_SOURCE_MATCH',
                'confidence' => 'RETAINED',
                'reason' => 'Pre-existing Laijau showroom catalog item (not present in incoming Jan 2026 stock batch)',
                'source' => null,
            ];
        }

        // If newly created product > 1060
        return [
            'level' => 4,
            'status' => 'NEW_SOURCE_PRODUCT',
            'confidence' => 'CONFIRMED',
            'reason' => 'Newly imported product from Shoes/Clothes Stock batches',
            'source' => null,
        ];
    }

    /**
     * Build source lookup indexes for high-speed deterministic matching.
     */
    public function buildSourceIndexes(array $sourceData): array
    {
        $bySku = [];
        $byCode = [];
        $clothesByName = [];

        foreach ($sourceData['shoes'] as $row) {
            $code = strtoupper(trim((string)($row['code'] ?? '')));
            $color = strtoupper(trim((string)($row['colour'] ?? $row['color'] ?? $row['name'] ?? '')));
            $cleanColor = preg_replace('/[^A-Za-z0-9]/', '', $color);
            $sku = "{$code}{$cleanColor}";

            $entry = array_merge($row, ['source_type' => 'shoes_stock', 'clean_sku' => $sku]);
            $bySku[$sku] = $entry;
            $byCode[$code][] = $entry;
        }

        foreach ($sourceData['clothes'] as $row) {
            $rawCode = strtoupper(trim((string)($row['code'] ?? '')));
            $code = preg_replace('/[^A-Za-z0-9]/', '', $rawCode);
            $nameClean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($row['item_name'] ?? $row['name'] ?? '')));
            $sku = !empty($code) ? "CLO-{$code}" : "CLO-{$nameClean}";

            $entry = array_merge($row, ['source_type' => 'clothes_stock', 'clean_sku' => $sku]);
            $bySku[$sku] = $entry;
            if (!empty($code)) {
                $byCode[$code][] = $entry;
            }
            $clothesByName[$nameClean] = $entry;
        }

        foreach ($sourceData['purchases'] as $row) {
            $code = strtoupper(trim((string)($row['code'] ?? '')));
            $color = strtoupper(trim((string)($row['colour'] ?? $row['color'] ?? '')));
            $cleanColor = preg_replace('/[^A-Za-z0-9]/', '', $color);
            $sku = "{$code}{$cleanColor}";

            $entry = array_merge($row, ['source_type' => 'purchases_jan_2026', 'clean_sku' => $sku]);
            if (!isset($bySku[$sku])) {
                $bySku[$sku] = $entry;
            }
            $byCode[$code][] = $entry;
        }

        return [
            'by_sku' => $bySku,
            'by_code' => $byCode,
            'clothes_by_name' => $clothesByName,
        ];
    }

    /**
     * Audit catalog and return comprehensive 19-phase report without database modifications.
     */
    public function audit(): array
    {
        $sourceData = $this->loadSourceData();
        $sourceIndexes = $this->buildSourceIndexes($sourceData);

        $products = Product::with(['variants', 'categories'])->get();
        $totalProducts = $products->count();
        $totalVariants = ProductVariant::count();

        // Warehouse resolution
        $showroomWh = Warehouse::where('code', 'STORE-KTM-01')->first()
            ?? Warehouse::where('type', 'showroom_pos')->first()
            ?? Warehouse::first();

        // Stock and transaction counts
        $stockMap = DB::table('inventory_stock_levels')
            ->select('product_id', DB::raw('sum(quantity_on_hand) as total_stock'))
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        $offlineSalesMap = DB::table('offline_sale_items')
            ->select('product_id', DB::raw('count(*) as count'), DB::raw('sum(quantity) as total_qty'))
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        $onlineOrdersMap = DB::table('order_items')
            ->select('product_id', DB::raw('count(*) as count'), DB::raw('sum(quantity) as total_qty'))
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        $purchasesMap = DB::table('inventory_purchase_order_items')
            ->select('product_id', DB::raw('count(*) as count'), DB::raw('sum(quantity_ordered) as total_qty'))
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        $matchedCount = 0;
        $newSourceCount = 0;
        $missingSourceCount = 0;
        $manualReviewCount = 0;
        $stagingCount = 0;
        $duplicateCandidates = [];

        $readyToPublish = [];
        $missingPhotos = [];
        $missingData = [];
        $priceReview = [];
        $codeReview = [];

        $skuMap = [];

        foreach ($products as $p) {
            $sku = trim((string)$p->sku);
            $name = (string)$p->name;
            $price = (float)$p->price;
            $cost = (float)$p->cost_price;
            $hasPhoto = $this->hasPhysicalPhoto($p);

            if (!empty($sku)) {
                $skuMap[$sku][] = $p->id;
            }

            // Match classification
            $matchResult = $this->matchProductToSource($p, $sourceIndexes);
            match ($matchResult['status']) {
                'MATCHED' => $matchedCount++,
                'NEW_SOURCE_PRODUCT' => $newSourceCount++,
                'MISSING_SOURCE_MATCH' => $missingSourceCount++,
                'STAGING' => $stagingCount++,
                default => $manualReviewCount++,
            };

            // Publication validation
            $val = $this->validatePublicationEligibility($p);
            if ($val['eligible']) {
                $readyToPublish[] = $p->id;
            } elseif (!$hasPhoto) {
                $missingPhotos[] = $p->id;
            } else {
                $missingData[] = [
                    'id' => $p->id,
                    'sku' => $sku,
                    'name' => $name,
                    'missing' => $val['missing'],
                ];
            }

            // Price anomalies
            if ($price <= 0 || ($cost > 0 && $price < $cost) || $price > 100000) {
                $priceReview[] = [
                    'id' => $p->id,
                    'sku' => $sku,
                    'name' => $name,
                    'price' => $price,
                    'cost_price' => $cost,
                    'reason' => $price <= 0 ? 'Zero price' : ($price < $cost ? 'Selling price lower than cost' : 'Abnormal price > 100k'),
                ];
            }

            // Code review
            if (empty($sku) || preg_match('/^(NA-PRD-|TEMP-|UNKNOWN)/i', $sku)) {
                $codeReview[] = [
                    'id' => $p->id,
                    'sku' => $sku,
                    'name' => $name,
                    'reason' => empty($sku) ? 'Missing SKU' : 'Placeholder SKU requires manual assignment',
                ];
            }
        }

        // Duplicate SKUs
        foreach ($skuMap as $s => $ids) {
            if (count($ids) > 1) {
                $duplicateCandidates[] = ['sku' => $s, 'product_ids' => $ids, 'count' => count($ids)];
            }
        }

        return [
            'timestamp' => now()->toIso8601String(),
            'total_erp_products' => $totalProducts,
            'total_variants' => $totalVariants,
            'matched_count' => $matchedCount,
            'new_source_count' => $newSourceCount,
            'missing_source_count' => $missingSourceCount,
            'staging_count' => $stagingCount,
            'manual_review_count' => $manualReviewCount,
            'ready_to_publish_count' => count($readyToPublish),
            'missing_photos_count' => count($missingPhotos),
            'missing_data_count' => count($missingData),
            'price_review_count' => count($priceReview),
            'code_review_count' => count($codeReview),
            'duplicate_candidates_count' => count($duplicateCandidates),
            'categories' => [
                'ready_to_publish' => $readyToPublish,
                'missing_photos' => $missingPhotos,
                'missing_data' => $missingData,
                'price_review' => $priceReview,
                'code_review' => $codeReview,
                'duplicate_candidates' => $duplicateCandidates,
            ],
        ];
    }

    /**
     * Execute full reconciliation, variant stock sync, title normalization, and matrix generation.
     */
    public function sync(bool $dryRun = false, bool $exportMatrix = true): array
    {
        $sourceData = $this->loadSourceData();
        $sourceIndexes = $this->buildSourceIndexes($sourceData);

        // Pre-aggregate shoe stock by code, color, and size
        $shoesStockMatrix = [];
        foreach ($sourceData['shoes'] as $r) {
            $code = strtoupper(trim((string)($r['code'] ?? '')));
            $color = strtoupper(trim((string)($r['colour'] ?? $r['color'] ?? $r['name'] ?? '')));
            $cleanColor = preg_replace('/[^A-Za-z0-9]/', '', $color);
            $sku = "{$code}{$cleanColor}";
            $size = (string)($r['size'] ?? '');
            $qty = (int)($r['quantity'] ?? 0);

            $shoesStockMatrix[$sku][$size] = ($shoesStockMatrix[$sku][$size] ?? 0) + $qty;
        }

        // Pre-aggregate clothes stock by code, size, cost, price
        $clothesStockMatrix = [];
        foreach ($sourceData['clothes'] as $r) {
            $rawCode = strtoupper(trim((string)($r['code'] ?? '')));
            $code = preg_replace('/[^A-Za-z0-9]/', '', $rawCode);
            $nameClean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($r['item_name'] ?? $r['name'] ?? '')));
            $sku = !empty($code) ? "CLO-{$code}" : "CLO-{$nameClean}";
            $size = (string)($r['size'] ?? '');
            $qty = (int)($r['quantity'] ?? 0);

            if (!isset($clothesStockMatrix[$sku])) {
                $clothesStockMatrix[$sku] = [
                    'item_name' => $r['item_name'] ?? $r['name'] ?? 'Clothes Item',
                    'cost_price' => (float)($r['cost_price'] ?? $r['cost'] ?? 500.00),
                    'selling_price' => (float)($r['selling_price'] ?? $r['price'] ?? 1000.00),
                    'sizes' => [],
                ];
            }
            $clothesStockMatrix[$sku]['sizes'][$size] = ($clothesStockMatrix[$sku]['sizes'][$size] ?? 0) + $qty;
        }

        $categoryService = new CategoryReconciliationService();
        $categoryHierarchy = $categoryService->ensureHierarchy();

        $metrics = [
            'products_evaluated' => 0,
            'products_matched' => 0,
            'products_updated' => 0,
            'products_unchanged' => 0,
            'products_newly_created' => 0,
            'products_requiring_review' => 0,
            'products_marked_unpublished' => 0,
            'products_activated' => 0,
            'photos_verified' => 0,
            'photos_missing' => 0,
            'stock_records_changed' => 0,
            'variant_stock_records_changed' => 0,
            'prices_changed' => 0,
            'titles_changed' => 0,
            'descriptions_normalized' => 0,
            'categories_assigned' => 0,
            'types_corrected' => 0,
            'duplicates_reconciled' => 0,
            'historical_sales_relinked' => 0,
        ];

        $reconciliationMatrix = [];
        $manualReviewRequired = [];
        $stockReconciliationData = [];

        DB::beginTransaction();
        try {
            $products = Product::with(['variants', 'categories'])->get();
            $metrics['products_evaluated'] = $products->count();

            // Load initial stock snapshot
            $initialStockMap = DB::table('inventory_stock_levels')
                ->select('product_id', DB::raw('sum(quantity_on_hand) as total_stock'))
                ->groupBy('product_id')
                ->get()
                ->keyBy('product_id');

            // Load sales, orders, adjustments, and purchases
            $offlineSalesMap = DB::table('offline_sale_items')
                ->select('product_id', DB::raw('count(*) as count'), DB::raw('sum(quantity) as total_qty'))
                ->groupBy('product_id')
                ->get()
                ->keyBy('product_id');

            $onlineOrdersMap = DB::table('order_items')
                ->select('product_id', DB::raw('count(*) as count'), DB::raw('sum(quantity) as total_qty'))
                ->groupBy('product_id')
                ->get()
                ->keyBy('product_id');

            $purchasesMap = DB::table('inventory_purchase_order_items')
                ->select('product_id', DB::raw('count(*) as count'), DB::raw('sum(quantity_ordered) as total_qty'))
                ->groupBy('product_id')
                ->get()
                ->keyBy('product_id');

            $adjustmentsMap = DB::table('inventory_adjustments')
                ->where('status', 'approved')
                ->select('product_id', DB::raw("sum(CASE WHEN type = 'increase' THEN quantity ELSE -quantity END) as total_delta"))
                ->groupBy('product_id')
                ->get()
                ->keyBy('product_id');

            // Build product lookup by clean SKU and code
            $productsByCleanSku = [];
            foreach ($products as $p) {
                $s = strtoupper(trim((string)$p->sku));
                if (!empty($s)) {
                    $productsByCleanSku[$s] = $p;
                }
            }

            foreach ($products as $product) {
                $sku = trim((string)$product->sku);
                $oldTitle = (string)$product->name;
                $cleanTitle = $this->cleanCustomerFacingTitle($oldTitle);
                $oldPrice = (float)$product->price;
                $oldCost = (float)$product->cost_price;
                $oldStock = (int)($initialStockMap[$product->id]->total_stock ?? 0);

                $hasPhoto = $this->hasPhysicalPhoto($product);
                if ($hasPhoto) {
                    $metrics['photos_verified']++;
                } else {
                    $metrics['photos_missing']++;
                }

                // 1. Multi-level matching against source
                $match = $this->matchProductToSource($product, $sourceIndexes);
                $sourceRow = $match['source'] ?? null;
                $sourceCode = $sourceRow['code'] ?? '';
                $sourceFile = $sourceRow['source_file'] ?? '';
                $sourceSheet = $sourceRow['source_sheet'] ?? '';
                $sourceRowNum = $sourceRow['source_row'] ?? '';

                $updateData = [];
                $productWasUpdated = false;

                // 2. Title Normalization & Brand Safety
                if ($cleanTitle !== $oldTitle) {
                    $updateData['name'] = $cleanTitle;
                    $metrics['titles_changed']++;
                    $productWasUpdated = true;
                }

                // 2b. Category Classification & Product Type Correction (Section 6)
                $classification = $categoryService->classifyProduct($product);
                $expectedType = match ($classification['root']) {
                    'accessories' => 'accessory',
                    default => str_contains($classification['department'], 'footwear') ? 'footwear' : 'apparel',
                };
                if ($product->type !== $expectedType) {
                    $updateData['type'] = $expectedType;
                    $metrics['types_corrected']++;
                    $productWasUpdated = true;
                }

                $catIds = [];
                if (isset($categoryHierarchy[$classification['root']])) $catIds[] = $categoryHierarchy[$classification['root']]->id;
                if (isset($categoryHierarchy[$classification['department']])) $catIds[] = $categoryHierarchy[$classification['department']]->id;
                if (isset($categoryHierarchy[$classification['leaf']])) $catIds[] = $categoryHierarchy[$classification['leaf']]->id;
                $catIds = array_unique(array_filter($catIds));

                if (!$dryRun) {
                    $product->categories()->sync($catIds);
                }
                $metrics['categories_assigned']++;

                // 2c. Factual Description Generation (Section 12)
                $factualDescription = $this->generateFactualDescription($product, $classification);
                if ($product->description !== $factualDescription) {
                    $updateData['description'] = $factualDescription;
                    $metrics['descriptions_normalized']++;
                    $productWasUpdated = true;
                }

                // 3. Price & Cost updates from verified source where available
                $newPrice = $oldPrice;
                $newCost = $oldCost;
                if ($sourceRow) {
                    $srcSell = (float)($sourceRow['selling_price'] ?? 0);
                    $srcCost = (float)($sourceRow['cost_price'] ?? ($sourceRow['unit_cost'] ?? 0));

                    if ($srcSell > 0 && ($oldPrice <= 0 || $oldPrice === 2500.00 && $srcSell !== 2500.00)) {
                        $updateData['price'] = $srcSell;
                        $newPrice = $srcSell;
                        $metrics['prices_changed']++;
                        $productWasUpdated = true;
                    }
                    if ($srcCost > 0 && $oldCost <= 0) {
                        $updateData['cost_price'] = $srcCost;
                        $newCost = $srcCost;
                        $metrics['prices_changed']++;
                        $productWasUpdated = true;
                    }
                    if (!empty($sourceRow['factory']) && empty($product->brand)) {
                        $updateData['brand'] = $sourceRow['factory'];
                        $productWasUpdated = true;
                    }
                }

                // 4. Duplicate Consolidation (Phase 6: One Real Product = One Master Product)
                // If this is a newly created product (ID > 1060) whose source code is already represented on an older master product
                $isMergedDuplicate = false;
                if ($product->id > 1060 && $match['status'] === 'MATCHED' && !empty($match['source']['clean_sku'])) {
                    $candidateMaster = $productsByCleanSku[$match['source']['clean_sku']] ?? null;
                    if ($candidateMaster && $candidateMaster->id <= 820 && $candidateMaster->id !== $product->id) {
                        // Consolidate duplicate into master
                        $isMergedDuplicate = true;
                        $ref = "Merged into Master Product ID {$candidateMaster->id}";
                        if ((bool)$product->is_active || (bool)$product->is_published || $product->internal_reference !== $ref) {
                            $updateData['is_active'] = false;
                            $updateData['is_published'] = false;
                            $updateData['internal_reference'] = $ref;
                            $metrics['duplicates_reconciled']++;
                            $productWasUpdated = true;
                        }
                    }
                }

                // 5. Publication Rule
                $val = $this->validatePublicationEligibility($product);
                $isEligible = $val['eligible'];

                if ($match['status'] === 'STAGING' || $isMergedDuplicate) {
                    if ((bool)$product->is_active || (bool)$product->is_published) {
                        $updateData['is_active'] = false;
                        $updateData['is_published'] = false;
                        $productWasUpdated = true;
                    }
                } elseif ($isEligible) {
                    if (!(bool)$product->is_active || !(bool)$product->is_published) {
                        $updateData['is_active'] = true;
                        $updateData['is_published'] = true;
                        $productWasUpdated = true;
                    }
                    $metrics['products_activated']++;
                } else {
                    if (!(bool)$product->is_active || (bool)$product->is_published) {
                        $updateData['is_active'] = true;
                        $updateData['is_published'] = false;
                        $productWasUpdated = true;
                    }
                    $metrics['products_marked_unpublished']++;
                }

                if (!$dryRun && !empty($updateData)) {
                    $product->update($updateData);
                }

                $showroomWh = Warehouse::where('code', 'STORE-KTM-01')->first()
                    ?? Warehouse::where('type', 'showroom_pos')->first()
                    ?? Warehouse::first();

                // 6. Variant-Level Stock Reconciliation & Stock Formula (Section 13)
                $newStock = $oldStock;
                $cleanSkuKey = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $sku));
                $sourceVariantStock = $shoesStockMatrix[$cleanSkuKey]
                    ?? ($clothesStockMatrix[$cleanSkuKey]['sizes'] ?? null);

                if ($sourceVariantStock && !$isMergedDuplicate) {
                    $totalReconciledStock = 0;
                    foreach ($sourceVariantStock as $size => $qty) {
                        $totalReconciledStock += (int)$qty;
                        $varSku = "{$sku}-{$size}";

                        if (!$dryRun) {
                            $variant = ProductVariant::firstOrCreate(
                                ['product_id' => $product->id, 'sku' => $varSku],
                                [
                                    'size' => (string)$size,
                                    'color' => $product->variants->first()?->color ?: 'Standard',
                                    'price' => $newPrice,
                                    'cost_price' => $newCost,
                                    'stock_quantity' => (int)$qty,
                                    'is_active' => true,
                                ]
                            );

                            if ($variant->stock_quantity !== (int)$qty) {
                                $variant->updateQuietly(['stock_quantity' => (int)$qty]);
                                $metrics['variant_stock_records_changed']++;
                            }

                            $sl = StockLevel::firstOrCreate(
                                [
                                    'warehouse_id' => $showroomWh->id,
                                    'product_id' => $product->id,
                                    'variant_id' => $variant->id,
                                ],
                                [
                                    'quantity_on_hand' => (int)$qty,
                                    'unit_cost_npr' => $newCost,
                                ]
                            );

                            if ($sl->quantity_on_hand !== (int)$qty) {
                                $sl->update(['quantity_on_hand' => (int)$qty]);
                                $metrics['stock_records_changed']++;
                            }
                        }
                    }

                    $newStock = (int)StockLevel::where('product_id', $product->id)->sum('quantity_on_hand');
                }

                // Synchronize legacy scalar attributes from authoritative StockLevel records
                if (!$dryRun) {
                    app(\App\Services\Inventory\InventoryService::class)->syncLegacyStockAttributes($product->id);
                }

                // Section 13: Stock Synchronization Formula:
                // Expected Stock = Opening Stock + Purchases + Adjustments - Offline Sales - Online Fulfilled Sales
                $purchasesQty = (int)($purchasesMap[$product->id]->total_qty ?? 0);
                $offlineSalesQty = (int)($offlineSalesMap[$product->id]->total_qty ?? 0);
                $onlineOrdersQty = (int)($onlineOrdersMap[$product->id]->total_qty ?? 0);
                $adjQty = (int)($adjustmentsMap[$product->id]->total_delta ?? 0);
                $openingStock = $sourceRow ? (int)($sourceRow['quantity'] ?? 0) : $oldStock;
                $expectedStock = max(0, $openingStock + $purchasesQty + $adjQty - $offlineSalesQty - $onlineOrdersQty);
                $stockDifference = $newStock - $expectedStock;
                $stockConfidence = $sourceRow ? 'SOURCE_VERIFIED' : 'ERP_AUTHORITATIVE';

                $stockReconciliationData[] = [
                    'product_id' => $product->id,
                    'sku' => $sku,
                    'name' => $cleanTitle,
                    'opening_stock' => $openingStock,
                    'purchases' => $purchasesQty,
                    'adjustments' => $adjQty,
                    'offline_sales' => $offlineSalesQty,
                    'online_sales' => $onlineOrdersQty,
                    'expected_stock' => $expectedStock,
                    'actual_stock' => $newStock,
                    'difference' => $stockDifference,
                    'confidence' => $stockConfidence,
                ];

                if ($productWasUpdated) {
                    $metrics['products_updated']++;
                } else {
                    $metrics['products_unchanged']++;
                }

                // 7. Populate Reconciliation Matrix Row
                $stockDelta = $newStock - $oldStock;
                $catStatus = $product->categories()->exists() ? 'VALID' : 'MISSING';
                $skuStatus = (!empty($sku) && !preg_match('/^(NA-PRD-|TEMP-|UNKNOWN)/i', $sku)) ? 'RESOLVED' : 'REVIEW_REQUIRED';
                $photoStatus = $hasPhoto ? 'HAS_REAL_PHOTO' : 'MISSING_PHOTO';
                $pubStatus = ($updateData['is_published'] ?? $product->is_published) ? 'PUBLISHED' : 'UNPUBLISHED';
                $categoryPath = "{$classification['root']} → {$classification['department']} → {$classification['leaf']}";

                $matrixRow = [
                    'product_id' => $product->id,
                    'existing_sku' => $sku,
                    'source_code' => $sourceCode ?: '—',
                    'match_status' => $match['status'],
                    'match_confidence' => $match['confidence'],
                    'match_reason' => $match['reason'],
                    'old_title' => $oldTitle,
                    'new_title' => $cleanTitle,
                    'category_path' => $categoryPath,
                    'old_price' => number_format($oldPrice, 2, '.', ''),
                    'new_price' => number_format($newPrice, 2, '.', ''),
                    'old_cost' => number_format($oldCost, 2, '.', ''),
                    'new_cost' => number_format($newCost, 2, '.', ''),
                    'old_stock' => $oldStock,
                    'new_stock' => $newStock,
                    'expected_stock' => $expectedStock,
                    'stock_difference' => $stockDifference,
                    'stock_delta' => $stockDelta,
                    'variant_count' => $product->variants()->count(),
                    'photo_status' => $photoStatus,
                    'category_status' => $catStatus,
                    'sku_status' => $skuStatus,
                    'publication_status' => $pubStatus,
                    'missing_fields' => implode('; ', $val['missing']),
                    'source_file' => $sourceFile ?: '—',
                    'source_sheet' => $sourceSheet ?: '—',
                    'source_row' => $sourceRowNum ?: '—',
                ];

                $reconciliationMatrix[] = $matrixRow;

                if (in_array($match['status'], ['MANUAL_REVIEW', 'STAGING']) || !empty($val['missing']) || $oldPrice <= 0 || ($oldCost > 0 && $oldPrice < $oldCost)) {
                    $manualReviewRequired[] = $matrixRow;
                    $metrics['products_requiring_review']++;
                }
            }

            // 8. Re-link historical offline sales items from product 130 to real products
            $salesItems = OfflineSaleItem::where('product_id', 130)->get(['id', 'sku', 'product_name']);
            foreach ($salesItems as $item) {
                $itemClean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$item->sku));
                if (isset($productsByCleanSku[$itemClean])) {
                    $targetProduct = $productsByCleanSku[$itemClean];
                    if (!$dryRun && $targetProduct->id !== 130) {
                        $item->update(['product_id' => $targetProduct->id]);
                    }
                    $metrics['historical_sales_relinked']++;
                }
            }

            if (!$dryRun) {
                DB::commit();
                Log::info('Master catalog reconciliation applied successfully.', $metrics);
            } else {
                DB::rollBack();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Master catalog reconciliation failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            throw $e;
        }

        // Export reports
        $isTesting = app()->environment('testing');
        $reportFile = $isTesting ? 'product_reconciliation_report_test.json' : 'product_reconciliation_report.json';
        File::put("{$this->migrationDir}/{$reportFile}", json_encode([
            'timestamp' => now()->toIso8601String(),
            'dry_run' => $dryRun,
            'metrics' => $metrics,
        ], JSON_PRETTY_PRINT));

        // Export category tree report
        $categoryTree = $categoryService->generateCategoryTreeReport();
        File::put("{$this->migrationDir}/category_tree_report.json", json_encode($categoryTree, JSON_PRETTY_PRINT));

        // Export stock reconciliation report
        File::put("{$this->migrationDir}/stock_reconciliation_report.json", json_encode([
            'timestamp' => now()->toIso8601String(),
            'formula' => 'Expected Stock = Opening Stock + Purchases + Adjustments - Offline Sales - Online Fulfilled Sales',
            'total_evaluated' => count($stockReconciliationData),
            'records' => $stockReconciliationData,
        ], JSON_PRETTY_PRINT));

        if ($exportMatrix && !$isTesting) {
            // Write 27-column reconciliation matrix
            $matrixPath = "{$this->migrationDir}/product_reconciliation_matrix.csv";
            $handle = fopen($matrixPath, 'w');
            if ($handle) {
                fputcsv($handle, array_keys($reconciliationMatrix[0] ?? []));
                foreach ($reconciliationMatrix as $row) {
                    fputcsv($handle, $row);
                }
                fclose($handle);
            }

            // Write manual review required CSV
            $reviewPath = "{$this->migrationDir}/manual_review_required.csv";
            $rHandle = fopen($reviewPath, 'w');
            if ($rHandle) {
                fputcsv($rHandle, array_keys($manualReviewRequired[0] ?? []));
                foreach ($manualReviewRequired as $row) {
                    fputcsv($rHandle, $row);
                }
                fclose($rHandle);
            }
        }

        return [
            'metrics' => $metrics,
            'dry_run' => $dryRun,
            'files_generated' => [
                'json_report' => "storage/app/migration/{$reportFile}",
                'csv_matrix' => 'storage/app/migration/product_reconciliation_matrix.csv',
                'manual_review' => 'storage/app/migration/manual_review_required.csv',
                'category_tree' => 'storage/app/migration/category_tree_report.json',
                'stock_report' => 'storage/app/migration/stock_reconciliation_report.json',
            ],
        ];
    }
}
