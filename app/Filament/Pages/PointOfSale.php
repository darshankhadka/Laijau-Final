<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;

class PointOfSale extends OfflineSales
{
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $slug = 'point-of-sale';

    public static function getRelativeRouteName(\Filament\Panel $panel): string
    {
        return 'point-of-sale';
    }

    public function mount(): void
    {
        parent::mount();
    }
}
