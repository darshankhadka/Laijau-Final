<?php

namespace App\Observers;

use App\Models\Collection;
use App\Services\StorefrontRevalidationService;

class CollectionObserver
{
    /**
     * Handle the Collection "saved" event.
     */
    public function saved(Collection $collection): void
    {
        $oldSlug = $collection->getOriginal('slug');
        app(StorefrontRevalidationService::class)->revalidateCollection($collection, $oldSlug, async: true);
    }

    /**
     * Handle the Collection "deleted" event.
     */
    public function deleted(Collection $collection): void
    {
        app(StorefrontRevalidationService::class)->revalidateCollection($collection, $collection->slug, async: true);
    }
}
