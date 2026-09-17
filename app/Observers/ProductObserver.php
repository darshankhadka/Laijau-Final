<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\StorefrontRevalidationService;

class ProductObserver
{
    /**
     * Handle the Product "saved" event.
     */
    public function saved(Product $product): void
    {
        $oldSlug = $product->getOriginal('slug');
        $previousState = [];
        if ($oldSlug && $oldSlug !== $product->slug) {
            $previousState['slug'] = $oldSlug;
        }

        app(StorefrontRevalidationService::class)->revalidateProduct($product, $previousState, async: true);
    }

    /**
     * Handle the Product "deleted" event.
     */
    public function deleted(Product $product): void
    {
        $previousState = [];
        if ($product->slug) {
            $previousState['slug'] = $product->slug;
        }

        app(StorefrontRevalidationService::class)->revalidateProduct($product, $previousState, async: true);
    }
}
