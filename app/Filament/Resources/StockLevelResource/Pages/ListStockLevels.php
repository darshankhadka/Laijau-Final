<?php

namespace App\Filament\Resources\StockLevelResource\Pages;

use App\Filament\Resources\StockLevelResource;
use App\Models\Inventory\StockLevel;
use App\Services\Inventory\InventoryService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListStockLevels extends ListRecords
{
    protected static string $resource = StockLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('quick_entry')
                ->label('Quick Stock Entry')
                ->icon('heroicon-m-bolt')
                ->color('primary')
                ->url(route('filament.admin.pages.quick-stock-entry')),

            Actions\Action::make('transfer_stock')
                ->label('New Transfer')
                ->icon('heroicon-m-truck')
                ->color('gray')
                ->url(route('filament.admin.resources.stock-transfers.create')),

            Actions\Action::make('sync_catalog')
                ->label('Sync Products')
                ->icon('heroicon-m-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->action(function () {
                    $count = app(InventoryService::class)->backfillExistingProductsToStockLevels();
                    Notification::make()
                        ->title('Catalog Synchronized')
                        ->body("Synchronized {$count} new catalog stock items to inventory engine.")
                        ->success()
                        ->send();
                }),
        ];
    }

    public function getTabs(): array
    {
        $allCount = StockLevel::count();
        $healthyCount = StockLevel::whereRaw('quantity_on_hand > reorder_point')->count();
        $lowCount = StockLevel::whereRaw('quantity_on_hand <= reorder_point AND quantity_on_hand > 0')->count();
        $depletedCount = StockLevel::where('quantity_on_hand', '<=', 0)->count();
        $surplusCount = StockLevel::where('quantity_on_hand', '>', 10)->count();

        return [
            'all' => Tab::make('All Inventory')
                ->icon('heroicon-m-building-storefront')
                ->badge((string) $allCount),

            'healthy' => Tab::make('Healthy Stock')
                ->icon('heroicon-m-check-badge')
                ->badge((string) $healthyCount)
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereRaw('quantity_on_hand > reorder_point')),

            'low' => Tab::make('Low Stock Alert')
                ->icon('heroicon-m-exclamation-triangle')
                ->badge((string) $lowCount)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereRaw('quantity_on_hand <= reorder_point AND quantity_on_hand > 0')),

            'depleted' => Tab::make('Depleted / Zero')
                ->icon('heroicon-m-x-circle')
                ->badge((string) $depletedCount)
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('quantity_on_hand', '<=', 0)),

            'surplus' => Tab::make('High Stock (>10 pcs)')
                ->icon('heroicon-m-archive-box')
                ->badge((string) $surplusCount)
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('quantity_on_hand', '>', 10)),
        ];
    }
}
