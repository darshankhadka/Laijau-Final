<?php

namespace App\Observers;

use App\Models\Category;
use App\Services\StorefrontRevalidationService;

class CategoryObserver
{
    /**
     * Handle the Category "saved" event.
     */
    public function saved(Category $category): void
    {
        $oldSlug = $category->getOriginal('slug');
        app(StorefrontRevalidationService::class)->revalidateCategory($category, $oldSlug, async: true);
    }

    /**
     * Handle the Category "deleted" event.
     */
    public function deleted(Category $category): void
    {
        app(StorefrontRevalidationService::class)->revalidateCategory($category, $category->slug, async: true);
    }
}
