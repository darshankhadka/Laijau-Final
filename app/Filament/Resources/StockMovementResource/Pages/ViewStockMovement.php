<?php

namespace App\Filament\Resources\StockMovementResource\Pages;

use App\Filament\Resources\StockMovementResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewStockMovement extends ViewRecord
{
    protected static string $resource = StockMovementResource::class;

    protected string $view = 'filament.resources.stock-movements.view-stock-movement';

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('stock_levels')
                ->label('Stock Levels')
                ->icon('heroicon-m-archive-box')
                ->color('primary')
                ->url(route('filament.admin.resources.stock-levels.index')),

            Actions\Action::make('product')
                ->label('View Product')
                ->icon('heroicon-m-cube')
                ->color('gray')
                ->visible(fn () => !empty($this->record->product_id))
                ->url(fn () => route('filament.admin.resources.products.view', $this->record->product_id)),

            Actions\Action::make('all_movements')
                ->label('All Movements')
                ->icon('heroicon-m-arrow-left')
                ->color('gray')
                ->url(route('filament.admin.resources.stock-movements.index')),
        ];
    }
}
