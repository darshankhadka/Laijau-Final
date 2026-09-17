<?php

namespace App\Filament\Pages;

use Filament\Support\Enums\Width;

class CycleCountPage extends FullStockCountPage
{
    protected string $view = 'filament.pages.cycle-count';
    protected Width | string | null $maxWidth = 'full';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-arrow-path';
    protected static string | \UnitEnum | null $navigationGroup = 'Inventory';
    protected static ?string $navigationLabel = 'Cycle Count';
    protected static ?int $navigationSort = 37;
    protected static ?string $title = 'Cycle Count & Stock Audit';
    protected static ?string $slug = 'inventory/cycle-count';

    public function mount(): void
    {
        parent::mount();
        $this->mode = 'cycle';
        $this->cycleView = 'session';
    }
}
