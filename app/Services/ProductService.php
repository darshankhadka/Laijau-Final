<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductService
{
    public function __construct(
        protected ProductSkuService $skuService
    ) {}

    /**
     * Safely duplicate a product as a draft template.
     * Copies catalog metadata, descriptions, pricing, attributes and variant structure.
     * Does NOT copy stock, stock movements, historical orders, or customer transactions.
     * Generates brand-new, guaranteed unique SKUs and barcodes for product and all variants.
     */
    public function duplicate(Product $product): Product
    {
        return DB::transaction(function () use ($product) {
            $newProduct = $product->replicate([
                'created_at',
                'updated_at',
            ]);

            $newProduct->name = $product->name . ' (Copy)';
            $newProduct->slug = Str::slug($product->name . '-copy-' . rand(100, 999));
            $newProduct->sku = null; // Will auto-generate fresh unique SKU
            $newProduct->barcode = null; // Will auto-generate fresh unique EAN-13 barcode
            $newProduct->is_published = false;
            $newProduct->is_active = true;
            $newProduct->quantity = 0; // Fresh stock count starts at 0
            $newProduct->save();

            // Clone Category and Collection associations
            if ($product->categories()->exists()) {
                $newProduct->categories()->sync($product->categories->pluck('id'));
            }
            if ($product->collections()->exists()) {
                $newProduct->collections()->sync($product->collections->pluck('id'));
            }

            // Clone variants with brand-new SKUs, barcodes, and zero stock
            if ($product->variants()->exists()) {
                foreach ($product->variants as $variant) {
                    $newVariant = $variant->replicate([
                        'created_at',
                        'updated_at',
                    ]);

                    $newVariant->product_id = $newProduct->id;
                    $newVariant->sku = null; // Fresh SKU allocated by ProductSkuService
                    $newVariant->barcode = null; // Fresh barcode allocated by ProductSkuService
                    $newVariant->stock_quantity = 0;
                    $newVariant->reserved_quantity = 0;
                    $newVariant->save();
                }
            }

            return $newProduct;
        });
    }

    /**
     * Archive product safely.
     * Prevents selling on storefront and new POS sales without deleting historical data.
     */
    public function archive(Product $product): void
    {
        $product->update([
            'is_active' => false,
            'is_published' => false,
        ]);

        // Also deactivate variants
        $product->variants()->update(['is_active' => false]);
    }

    /**
     * Unarchive / restore product.
     */
    public function unarchive(Product $product): void
    {
        $product->update([
            'is_active' => true,
            'is_published' => true,
        ]);

        $product->variants()->update(['is_active' => true]);
    }

    /**
     * Bulk update product retail, cost, or wholesale prices.
     */
    public function bulkUpdatePrices(
        array $productIds,
        float $adjustmentValue,
        bool $isPercentage = false,
        string $priceField = 'price'
    ): int {
        if (!in_array($priceField, ['price', 'cost_price', 'wholesale_price', 'compare_at_price'], true)) {
            $priceField = 'price';
        }

        $updatedCount = 0;
        $products = Product::whereIn('id', $productIds)->get();

        DB::transaction(function () use ($products, $adjustmentValue, $isPercentage, $priceField, &$updatedCount) {
            foreach ($products as $product) {
                $current = (float) ($product->{$priceField} ?? 0);
                if ($current <= 0 && $priceField !== 'compare_at_price') {
                    continue;
                }

                $newPrice = $isPercentage
                    ? round($current * (1 + ($adjustmentValue / 100)), 2)
                    : round(max(0, $current + $adjustmentValue), 2);

                $product->update([$priceField => $newPrice]);

                // Propagate to variants if applicable
                if ($product->variants()->exists() && in_array($priceField, ['price', 'cost_price', 'wholesale_price'], true)) {
                    $product->variants()->update([$priceField => $newPrice]);
                }

                $updatedCount++;
            }
        });

        return $updatedCount;
    }

    /**
     * Bulk generate missing EAN-13 barcodes for selected products and their variants.
     */
    public function bulkGenerateMissingBarcodes(array $productIds): int
    {
        $generated = 0;
        $products = Product::with('variants')->whereIn('id', $productIds)->get();

        foreach ($products as $product) {
            if (empty($product->barcode) || !$this->skuService->validateEan13($product->barcode)) {
                $product->barcode = $this->skuService->generateBarcodeForProduct($product);
                $product->saveQuietly();
                $generated++;
            }

            foreach ($product->variants as $variant) {
                if (empty($variant->barcode) || !$this->skuService->validateEan13($variant->barcode)) {
                    $variant->barcode = $this->skuService->generateBarcodeForVariant($variant);
                    $variant->saveQuietly();
                    $generated++;
                }
            }
        }

        return $generated;
    }
}
