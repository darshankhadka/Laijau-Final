<?php

declare(strict_types=1);

namespace App\Services\Operational;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ForensicProductReconciliationService
{
    protected Collection $products;
    protected Collection $variants;
    protected array $procurementCosts = [];
    protected array $productLookupBySku = [];
    protected array $productLookupByName = [];
    protected array $variantLookupBySku = [];

    public function __construct()
    {
        $this->loadMasterData();
    }

    /**
     * Load catalog products, variants, and build fast lookup indexes.
     */
    public function loadMasterData(): void
    {
        $this->products = Product::with(['variants', 'categories'])->get();
        $this->variants = ProductVariant::all();

        // Index products
        foreach ($this->products as $p) {
            $sku = trim((string)$p->sku);
            $name = trim((string)$p->name);

            if (!empty($sku)) {
                $this->productLookupBySku[$sku] = $p;
                $this->productLookupBySku[strtolower($sku)] = $p;
                $norm = $this->normalizeCode($sku);
                if (!isset($this->productLookupBySku[$norm])) {
                    $this->productLookupBySku[$norm] = $p;
                }
            }

            if (!empty($name)) {
                $this->productLookupByName[strtolower($name)] = $p;
                $normName = $this->normalizeName($name);
                if (!isset($this->productLookupByName[$normName])) {
                    $this->productLookupByName[$normName] = $p;
                }
            }
        }

        // Index variants
        foreach ($this->variants as $v) {
            $vSku = trim((string)$v->sku);
            if (!empty($vSku)) {
                $this->variantLookupBySku[$vSku] = $v;
                $this->variantLookupBySku[strtolower($vSku)] = $v;
                $this->variantLookupBySku[$this->normalizeCode($vSku)] = $v;
            }
        }

        // Build procurement cost dictionary from verified historical batches & authoritative specs
        $this->buildProcurementCostDictionary();
    }

    /**
     * Normalize code for robust matching while preserving distinct variant suffixes like -D, -T, (2).
     */
    public function normalizeCode(string|int $code): string
    {
        $c = trim((string)$code);
        $c = preg_replace('/^\#/', '', $c);
        $c = preg_replace('/\.0+$/', '', $c); // Remove Excel floating zero like 1221.0 -> 1221
        $c = preg_replace('/\s+/', '', $c);
        return strtolower($c);
    }

    /**
     * Normalize text name.
     */
    public function normalizeName(string|int $name): string
    {
        $n = trim((string)$name);
        $n = preg_replace('/[\-_]+/', ' ', $n);
        $n = preg_replace('/\s+/', ' ', $n);
        return strtolower($n);
    }

    /**
     * Normalize size, removing trailing float zeros (e.g. 40.0 -> 40).
     */
    public function normalizeSize(string|int|null $size): string
    {
        if ($size === null) {
            return '';
        }
        $s = trim((string)$size);
        $s = preg_replace('/\.0+$/', '', $s);
        return strtolower($s);
    }

    /**
     * Build authentic procurement cost lookup.
     */
    protected function buildProcurementCostDictionary(): void
    {
        // 1. Explicit verified ground truth from specifications & raw sheets
        $authoritativeCosts = [
            '2242' => 800.0,
            '2242-t' => 1000.0,
            '2255' => 850.0,
            '2257-d' => 850.0,
            '2244' => 700.0,
            '2244-d' => 750.0,
            '0021' => 1800.0,
            '1315' => 1800.0,
            '1327' => 1900.0,
            '1327(2)' => 2200.0,
            '1314' => 1800.0,
            'sm824a' => 975.0,
            'sm1188b' => 800.0,
            'sm1080' => 950.0,
            'sm508a' => 825.0,
            'sm10643' => 925.0,
            'diesel open trouser' => 375.0,
            'cotton half pant' => 550.0,
            '26-20-b' => 450.0,
            '532' => 1250.0,
            '3245' => 950.0,
            '2013' => 1450.0,
            '2529' => 900.0,
            '7026' => 2000.0,
            '7025' => 1600.0,
            '111' => 3400.0,
            '1320' => 1500.0,
            '1130' => 2600.0,
            '6264' => 600.0,
            '1635' => 2500.0,
        ];

        foreach ($authoritativeCosts as $k => $c) {
            $this->procurementCosts[$this->normalizeCode($k)] = $c;
        }

        // 2. Load extracted purchases if available
        $extractedDir = storage_path('app/migration/real_data_extracted');
        $shoeFile = $extractedDir . '/purchases_jan_2026.json';
        if (File::exists($shoeFile)) {
            $shoePurchases = json_decode(File::get($shoeFile), true) ?: [];
            foreach ($shoePurchases as $p) {
                $code = $this->normalizeCode((string)($p['code'] ?? ''));
                $cost = (float)($p['unit_cost'] ?? 0);
                if (!empty($code) && $cost > 0 && !isset($this->procurementCosts[$code])) {
                    $this->procurementCosts[$code] = $cost;
                }
                if (!empty($p['colour'])) {
                    $codeCol = $this->normalizeCode($code . '-' . (string)$p['colour']);
                    if (!isset($this->procurementCosts[$codeCol]) && $cost > 0) {
                        $this->procurementCosts[$codeCol] = $cost;
                    }
                }
            }
        }

        $clothesFile = $extractedDir . '/clothes_purchases_2026.json';
        if (File::exists($clothesFile)) {
            $clothesPurchases = json_decode(File::get($clothesFile), true) ?: [];
            foreach ($clothesPurchases as $p) {
                $code = $this->normalizeCode((string)($p['code'] ?? ''));
                $cost = (float)($p['unit_cost'] ?? 0);
                if (!empty($code) && $cost > 0 && !isset($this->procurementCosts[$code])) {
                    $this->procurementCosts[$code] = $cost;
                }
                $art = $this->normalizeName((string)($p['article'] ?? ''));
                if (!empty($art) && $cost > 0 && !isset($this->procurementCosts[$art])) {
                    $this->procurementCosts[$art] = $cost;
                }
            }
        }
    }

    /**
     * Match product and derive authentic cost using priority:
     * 1. exact product code
     * 2. normalized product code
     * 3. code + colour
     * 4. code + size/variant
     * 5. exact article/product name
     * 6. normalized product name
     * 7. controlled fuzzy matching
     */
    public function matchProduct(string $rawCode, ?string $rawName = null, ?string $size = null, ?string $colour = null, float $salePrice = 0.0): array
    {
        $code = trim($rawCode);
        $normCode = $this->normalizeCode($code);
        $normName = $rawName ? $this->normalizeName($rawName) : '';

        // Extract base code and colour suffix if formatted like "2244-Black", "0021-Bl", "#0085-Green"
        $baseCode = $normCode;
        $extractedColour = $colour ? strtolower(trim($colour)) : null;

        // Check if there is a dash or space separating code and colour/description
        // e.g. "2244-black" -> base: 2244, col: black; but keep "2244-d" as distinct variant!
        if (preg_match('/^([a-z0-9]+)[\s\-]+(black|brown|white|tan|broshof|blue|green|red|grey|gray|cherry|yellow|orange|navy|bk|br|wt|wh|coffe|coffee|pocket)\b/i', $normCode, $cm)) {
            $baseCode = $cm[1];
            if (!$extractedColour) {
                $extractedColour = strtolower($cm[2]);
            }
        } elseif (preg_match('/^(\d+)/', $normCode, $nm)) {
            $baseCode = $nm[1];
        }

        $matchedProduct = null;
        $matchedVariant = null;
        $matchType = 'none';

        // 1. Exact SKU match
        if (isset($this->productLookupBySku[$code])) {
            $matchedProduct = $this->productLookupBySku[$code];
            $matchType = 'exact_code';
        } elseif (isset($this->productLookupBySku[$normCode])) {
            $matchedProduct = $this->productLookupBySku[$normCode];
            $matchType = 'normalized_code';
        } elseif (!empty($baseCode) && isset($this->productLookupBySku[$baseCode])) {
            $matchedProduct = $this->productLookupBySku[$baseCode];
            $matchType = 'base_code';
        }

        // 2. Code + Size variant match
        if (!$matchedProduct && !empty($size)) {
            $normSize = $this->normalizeSize($size);
            $candidateVarSku = $normCode . '-' . $normSize;
            if (isset($this->variantLookupBySku[$candidateVarSku])) {
                $matchedVariant = $this->variantLookupBySku[$candidateVarSku];
                $matchedProduct = $this->products->firstWhere('id', $matchedVariant->product_id);
                $matchType = 'code_size';
            }
        }

        // 3. Exact Name match
        if (!$matchedProduct && !empty($rawName) && isset($this->productLookupByName[strtolower(trim($rawName))])) {
            $matchedProduct = $this->productLookupByName[strtolower(trim($rawName))];
            $matchType = 'exact_name';
        } elseif (!$matchedProduct && !empty($normName) && isset($this->productLookupByName[$normName])) {
            $matchedProduct = $this->productLookupByName[$normName];
            $matchType = 'normalized_name';
        }

        // 4. Code substring in catalog product SKU or Name
        if (!$matchedProduct && strlen($baseCode) >= 3 && !in_array($baseCode, ['black', 'brown', 'white', 'pant', 'shoe', '100', '200'])) {
            foreach ($this->products as $p) {
                $pSkuNorm = $this->normalizeCode((string)$p->sku);
                $pNameNorm = $this->normalizeCode((string)$p->name);
                if ($pSkuNorm === $baseCode || str_contains($pSkuNorm, $baseCode) || str_contains($pNameNorm, $baseCode)) {
                    $matchedProduct = $p;
                    $matchType = 'code_in_catalog';
                    break;
                }
            }
        }

        // 5. Variant match if product found
        if ($matchedProduct && !$matchedVariant && !empty($size)) {
            $normTargetSize = $this->normalizeSize($size);
            $matchedVariant = $matchedProduct->variants->first(function ($v) use ($size, $normTargetSize) {
                $vSize = strtolower(trim((string)$v->size));
                return $vSize === strtolower(trim($size)) || $this->normalizeSize($vSize) === $normTargetSize;
            });
        }

        // 6. Cost derivation
        $derivedCost = 0.0;
        $costSource = 'none';

        // Check full normCode first (preserves 2244-d, 2242-t, 1327(2))
        if (isset($this->procurementCosts[$normCode])) {
            $derivedCost = (float)$this->procurementCosts[$normCode];
            $costSource = 'exact_purchase_cost';
        } elseif (!empty($extractedColour) && isset($this->procurementCosts[$this->normalizeCode($baseCode . '-' . $extractedColour)])) {
            $derivedCost = (float)$this->procurementCosts[$this->normalizeCode($baseCode . '-' . $extractedColour)];
            $costSource = 'code_colour_purchase_cost';
        } elseif (!empty($baseCode) && isset($this->procurementCosts[$baseCode])) {
            $derivedCost = (float)$this->procurementCosts[$baseCode];
            $costSource = 'base_code_purchase_cost';
        } elseif (!empty($normName) && isset($this->procurementCosts[$normName])) {
            $derivedCost = (float)$this->procurementCosts[$normName];
            $costSource = 'article_purchase_cost';
        } elseif ($matchedProduct && (float)$matchedProduct->cost_price > 0) {
            $derivedCost = (float)$matchedProduct->cost_price;
            $costSource = 'catalog_cost_price';
        } elseif ($matchedVariant && (float)$matchedVariant->cost_price > 0) {
            $derivedCost = (float)$matchedVariant->cost_price;
            $costSource = 'variant_cost_price';
        } else {
            // Fallback rule: If sale price is known, realistic apparel/footwear retail cost is ~ 55% of sale price (min Rs. 500)
            if ($salePrice > 0) {
                $derivedCost = max(500.0, round($salePrice * 0.55, 2));
                $costSource = 'margin_fallback_rule';
            } else {
                $derivedCost = 500.0;
                $costSource = 'minimum_fallback_rule';
            }
        }

        return [
            'product' => $matchedProduct,
            'variant' => $matchedVariant,
            'product_id' => $matchedProduct?->id ?? null,
            'variant_id' => $matchedVariant?->id ?? null,
            'product_name' => $matchedProduct?->name ?? $rawCode,
            'sku' => $matchedProduct?->sku ?? $rawCode,
            'match_type' => $matchType,
            'unit_cost_npr' => $derivedCost,
            'cost_source' => $costSource,
        ];
    }
}
