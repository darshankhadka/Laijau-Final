<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductSkuService
{
    /**
     * Standard category code mappings.
     * Includes traditional apparel as well as footwear, streetwear, and accessory lines.
     */
    protected const CATEGORY_MAP = [
        'shoe' => 'SHO',
        'shoes' => 'SHO',
        'footwear' => 'SHO',
        'sneaker' => 'SHO',
        'sneakers' => 'SHO',
        'boot' => 'SHO',
        'boots' => 'SHO',
        'sandal' => 'SHO',
        'sandals' => 'SHO',
        'heel' => 'SHO',
        'heels' => 'SHO',
        'loafer' => 'SHO',
        'loafers' => 'SHO',
        'shirt' => 'SHR',
        'shirts' => 'SHR',
        'tshirt' => 'TSH',
        't-shirt' => 'TSH',
        'tee' => 'TSH',
        'top' => 'TOP',
        'tops' => 'TOP',
        'pant' => 'PNT',
        'pants' => 'PNT',
        'trouser' => 'PNT',
        'trousers' => 'PNT',
        'jeans' => 'JNS',
        'denim' => 'JNS',
        'jacket' => 'JKT',
        'jackets' => 'JKT',
        'hoodie' => 'HOD',
        'hoodies' => 'HOD',
        'dress' => 'DRS',
        'dresses' => 'DRS',
        'jewellery' => 'JWL',
        'jewelry' => 'JWL',
        'accessory' => 'ACC',
        'accessories' => 'ACC',
        'handbag' => 'HAN',
        'bag' => 'BAG',
        'bags' => 'BAG',
        'belt' => 'BLT',
        'belts' => 'BLT',
        'wallet' => 'WLT',
        'wallets' => 'WLT',
        'cap' => 'CAP',
        'caps' => 'CAP',
        'shawl' => 'SHW',
        'shawls' => 'SHW',
        'wrap' => 'SHW',
        'wraps' => 'SHW',
    ];

    /**
     * Get the default SKU prefix (defaults to LJ for Laijau).
     */
    public function getDefaultPrefix(): string
    {
        return config('app.sku_prefix', env('SKU_PREFIX', 'LJ'));
    }

    /**
     * Resolve a clean 3-character uppercase category abbreviation.
     */
    public function resolveCategoryCode(?Category $category = null, ?string $productName = null): string
    {
        // 1. Check category slug or name against mapped dictionary
        if ($category) {
            $catSlug = strtolower(trim($category->slug ?? ''));
            $catName = strtolower(trim($category->name ?? ''));

            foreach (self::CATEGORY_MAP as $keyword => $code) {
                if ($catSlug === $keyword || str_contains($catSlug, $keyword) || str_contains($catName, $keyword)) {
                    return $code;
                }
            }

            // Fallback from category name: extract first 3 alphanumeric characters
            $cleaned = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $category->name ?? ''));
            if (strlen($cleaned) >= 3) {
                return substr($cleaned, 0, 3);
            }
        }

        // 2. Check product name keywords
        if ($productName) {
            $lowerName = strtolower($productName);
            foreach (self::CATEGORY_MAP as $keyword => $code) {
                if (str_contains($lowerName, $keyword)) {
                    return $code;
                }
            }
        }

        // 3. Universal fallback
        return 'PRD';
    }

    /**
     * Generate a unique, deterministic, collision-safe product SKU.
     * Format: LJ-{CATEGORY}-{SEQUENCE} e.g. LJ-SAR-00001
     */
    public function generate(?Product $product = null, ?Category $category = null, ?string $name = null, ?string $prefixOverride = null): string
    {
        // Determine category if not explicitly provided
        if (!$category && $product) {
            if ($product->relationLoaded('categories') && $product->categories->isNotEmpty()) {
                $category = $product->categories->first();
            } elseif ($product->exists) {
                $category = $product->categories()->first();
            }
        }

        $prodName = $name ?: ($product?->name ?? null);
        $categoryCode = $this->resolveCategoryCode($category, $prodName);
        $sysPrefix = $prefixOverride ?: $this->getDefaultPrefix();
        $prefix = "{$sysPrefix}-{$categoryCode}";

        return $this->allocateNextSkuForPrefix($prefix);
    }

    /**
     * Generate SKU for a Product model instance.
     */
    public function generateForProduct(Product $product): string
    {
        return $this->generate($product, null, $product->name);
    }

    /**
     * Generate a unique SKU for a ProductVariant.
     * Format: {PARENT_SKU}-{COLOR_CODE}-{SIZE_CODE} e.g. LJ-SAR-00001-EME-STD or LJ-SHO-00001-BLK-42
     */
    public function generateForVariant(ProductVariant $variant): string
    {
        $parent = $variant->product ?? Product::find($variant->product_id);
        $parentSku = $parent?->sku;

        if (empty($parentSku)) {
            $parentSku = $parent ? $this->generateForProduct($parent) : ($this->getDefaultPrefix() . '-PRD-00001');
        }

        $colorRaw = $variant->color ?? 'STD';
        $colorClean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $colorRaw));
        $colorCode = strlen($colorClean) >= 3 ? substr($colorClean, 0, 3) : str_pad($colorClean, 3, 'X');

        $sizeRaw = $variant->size ?? 'STD';
        $sizeClean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $sizeRaw));
        $sizeCode = strlen($sizeClean) >= 3 ? substr($sizeClean, 0, 3) : str_pad($sizeClean, 3, 'X');

        $baseCandidate = "{$parentSku}-{$colorCode}-{$sizeCode}";
        $candidate = $baseCandidate;
        $suffix = 1;

        // Ensure global uniqueness in product_variants table
        while (DB::table('product_variants')->where('sku', $candidate)->where('id', '!=', $variant->id ?? 0)->exists()) {
            $candidate = "{$baseCandidate}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }

    /**
     * Atomically allocate the next unique SKU for a given prefix.
     */
    protected function allocateNextSkuForPrefix(string $prefix): string
    {
        $maxAttempts = 50;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $seqNumber = DB::transaction(function () use ($prefix) {
                $row = DB::table('sku_sequences')
                    ->where('prefix', $prefix)
                    ->lockForUpdate()
                    ->first();

                if (!$row) {
                    $maxExisting = $this->determineMaxExistingSequence($prefix);
                    $currentNumber = max(1, $maxExisting + 1);

                    DB::table('sku_sequences')->insert([
                        'prefix' => $prefix,
                        'next_number' => $currentNumber + 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    return $currentNumber;
                }

                $currentNumber = (int) $row->next_number;

                DB::table('sku_sequences')
                    ->where('prefix', $prefix)
                    ->update([
                        'next_number' => $currentNumber + 1,
                        'updated_at' => now(),
                    ]);

                return $currentNumber;
            });

            $candidateSku = sprintf('%s-%05d', $prefix, $seqNumber);

            // Double check against products and product_variants tables
            $existsInProducts = DB::table('products')->where('sku', $candidateSku)->exists();
            $existsInVariants = DB::table('product_variants')->where('sku', $candidateSku)->exists();

            if (!$existsInProducts && !$existsInVariants) {
                return $candidateSku;
            }
        }

        throw new \RuntimeException("Failed to allocate a unique SKU for prefix [{$prefix}] after {$maxAttempts} attempts.");
    }

    /**
     * Inspect existing database records to find the highest sequence integer matching the prefix.
     */
    protected function determineMaxExistingSequence(string $prefix): int
    {
        $existingSkus = DB::table('products')
            ->where('sku', 'LIKE', "{$prefix}-%")
            ->pluck('sku');

        $max = 0;
        foreach ($existingSkus as $sku) {
            if (preg_match('/-(\d+)$/', $sku, $matches)) {
                $num = (int) $matches[1];
                if ($num > $max) {
                    $max = $num;
                }
            }
        }

        return $max;
    }

    /**
     * Compute EAN-13 Check Digit using standard modulo 10 algorithm.
     */
    public function calculateEan13CheckDigit(string $digits12): int
    {
        $digits12 = preg_replace('/\D/', '', $digits12);
        if (strlen($digits12) < 12) {
            $digits12 = str_pad($digits12, 12, '0', STR_PAD_RIGHT);
        }

        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $digit = (int) $digits12[$i];
            $weight = ($i % 2 === 0) ? 1 : 3;
            $sum += $digit * $weight;
        }

        return (10 - ($sum % 10)) % 10;
    }

    /**
     * Validate an EAN-13 barcode check digit and length.
     */
    public function validateEan13(string $barcode): bool
    {
        $cleaned = preg_replace('/\D/', '', $barcode);
        if (strlen($cleaned) !== 13) {
            return false;
        }

        $expectedCheck = $this->calculateEan13CheckDigit(substr($cleaned, 0, 12));
        return (int) $cleaned[12] === $expectedCheck;
    }

    /**
     * Generate an authoritative, compliant GS1 in-store EAN-13 barcode for a product.
     * Uses prefix 200 (GS1 restricted distribution for internal retail operations).
     */
    public function generateBarcodeForProduct(Product $product): string
    {
        do {
            $id = (int) ($product->id ?: rand(100, 9999));
            $seed = sprintf('%06d', ($id * 17 + rand(1, 9999)) % 1000000);
            $tail = sprintf('%03d', rand(100, 999));
            $prefix12 = '200' . $seed . $tail;
            $checkDigit = $this->calculateEan13CheckDigit($prefix12);
            $candidate = $prefix12 . $checkDigit;
        } while (
            DB::table('products')->where('barcode', $candidate)->where('id', '!=', $product->id ?? 0)->exists() ||
            DB::table('product_variants')->where('barcode', $candidate)->exists()
        );

        return $candidate;
    }

    /**
     * Generate an authoritative, compliant GS1 in-store EAN-13 barcode for a product variant.
     * Uses prefix 200 (GS1 restricted distribution for internal retail operations).
     */
    public function generateBarcodeForVariant(ProductVariant $variant): string
    {
        do {
            $parentId = (int) ($variant->product_id ?: rand(10, 999));
            $sizeHash = abs(crc32($variant->size ?? 'STD')) % 1000;
            $randSeq = rand(10, 99);
            $prefix12 = sprintf('200%04d%03d%02d', ($parentId * 13 + rand(1, 999)) % 10000, $sizeHash, $randSeq);
            $checkDigit = $this->calculateEan13CheckDigit($prefix12);
            $candidate = $prefix12 . $checkDigit;
        } while (
            DB::table('product_variants')->where('barcode', $candidate)->where('id', '!=', $variant->id ?? 0)->exists() ||
            DB::table('products')->where('barcode', $candidate)->exists()
        );

        return $candidate;
    }

    /**
     * Omnichannel Barcode & SKU Lookup.
     * Finds active product or variant by scanning or typing barcode / SKU.
     */
    public function lookupBarcode(string $term): ?array
    {
        $term = trim($term);
        if (empty($term)) {
            return null;
        }

        // 1. Variant match by barcode or SKU
        $variant = ProductVariant::with('product')
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('barcode', $term)->orWhere('sku', $term))
            ->first();

        if ($variant) {
            return [
                'type' => 'variant',
                'variant' => $variant,
                'product' => $variant->product,
                'sku' => $variant->sku,
                'barcode' => $variant->barcode,
                'price' => (float) ($variant->price ?: $variant->product?->price ?: 0),
                'stock' => (int) $variant->stock_quantity,
                'label' => ($variant->product?->name ?? 'Product') . ' (' . ($variant->color ?? '') . ' / ' . ($variant->size ?? '') . ')',
            ];
        }

        // 2. Product match by barcode or SKU
        $product = Product::with('variants')
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('barcode', $term)->orWhere('sku', $term))
            ->first();

        if ($product) {
            return [
                'type' => 'product',
                'variant' => null,
                'product' => $product,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'price' => (float) ($product->price ?: 0),
                'stock' => (int) $product->quantity,
                'label' => $product->name,
            ];
        }

        return null;
    }

    /**
     * Generate pure SVG vector barcode (EAN-13 format) at high resolution.
     * Perfect for thermal retail printers (50x30mm) and A4 label sheets.
     */
    public function generateBarcodeSvg(string $barcode, int $height = 48, int $moduleWidth = 2): string
    {
        $digits = preg_replace('/\D/', '', $barcode);
        if (strlen($digits) !== 13) {
            $digits = str_pad(substr($digits, 0, 12), 12, '0', STR_PAD_RIGHT);
            $digits .= $this->calculateEan13CheckDigit($digits);
        }

        // EAN-13 Parity structures for the first digit
        $parityMap = [
            '0' => ['A', 'A', 'A', 'A', 'A', 'A'],
            '1' => ['A', 'A', 'B', 'A', 'B', 'B'],
            '2' => ['A', 'A', 'B', 'B', 'A', 'B'],
            '3' => ['A', 'A', 'B', 'B', 'B', 'A'],
            '4' => ['A', 'B', 'A', 'A', 'B', 'B'],
            '5' => ['A', 'B', 'B', 'A', 'A', 'B'],
            '6' => ['A', 'B', 'B', 'B', 'A', 'A'],
            '7' => ['A', 'B', 'A', 'B', 'A', 'B'],
            '8' => ['A', 'B', 'A', 'B', 'B', 'A'],
            '9' => ['A', 'B', 'B', 'A', 'B', 'A'],
        ];

        // Encoding tables
        $tableL = [
            '0' => '0001101', '1' => '0011001', '2' => '0010011', '3' => '0111101', '4' => '0100011',
            '5' => '0110001', '6' => '0101111', '7' => '0111011', '8' => '0110111', '9' => '0001011',
        ];
        $tableG = [
            '0' => '0100111', '1' => '0110011', '2' => '0011011', '3' => '0100001', '4' => '0011101',
            '5' => '0111001', '6' => '0000101', '7' => '0010001', '8' => '0001001', '9' => '0010111',
        ];
        $tableR = [
            '0' => '1110010', '1' => '1100110', '2' => '1101100', '3' => '1000010', '4' => '1011100',
            '5' => '1001110', '6' => '1010000', '7' => '1000100', '8' => '1001000', '9' => '1110100',
        ];

        $firstDigit = $digits[0];
        $parities = $parityMap[$firstDigit] ?? $parityMap['0'];

        // Build 95-bit binary string
        $binary = '101'; // Left guard

        // Left 6 digits
        for ($i = 1; $i <= 6; $i++) {
            $d = $digits[$i];
            $type = $parities[$i - 1];
            $binary .= ($type === 'A') ? $tableL[$d] : $tableG[$d];
        }

        $binary .= '01010'; // Center guard

        // Right 6 digits
        for ($i = 7; $i <= 12; $i++) {
            $d = $digits[$i];
            $binary .= $tableR[$d];
        }

        $binary .= '101'; // Right guard

        $totalBits = strlen($binary);
        $svgWidth = $totalBits * $moduleWidth;

        $rects = '';
        for ($b = 0; $b < $totalBits; $b++) {
            if ($binary[$b] === '1') {
                $x = $b * $moduleWidth;
                $rects .= "<rect x=\"{$x}\" y=\"0\" width=\"{$moduleWidth}\" height=\"{$height}\" fill=\"#000000\" />";
            }
        }

        return "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 {$svgWidth} {$height}\" width=\"100%\" height=\"{$height}\" preserveAspectRatio=\"none\" style=\"display:block;\">{$rects}</svg>";
    }
}
