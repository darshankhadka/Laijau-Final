<?php

namespace App\Filament\Resources\WarehouseResource\Pages;

use App\Filament\Resources\WarehouseResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewWarehouse extends ViewRecord
{
    protected static string $resource = WarehouseResource::class;

    protected string $view = 'filament.resources.warehouses.view-warehouse';

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('quick_entry')
                ->label('Receive Stock')
                ->icon('heroicon-m-bolt')
                ->color('primary')
                ->url(route('filament.admin.pages.quick-stock-entry') . '?warehouse_id=' . $this->record->id),

            Actions\Action::make('transfer')
                ->label('New Transfer')
                ->icon('heroicon-m-truck')
                ->color('gray')
                ->url(route('filament.admin.resources.stock-transfers.create') . '?source_id=' . $this->record->id),

            Actions\Action::make('count')
                ->label('Start Count')
                ->icon('heroicon-m-check-badge')
                ->color('gray')
                ->url(route('filament.admin.resources.stock-counts.create') . '?warehouse_id=' . $this->record->id),

            Actions\EditAction::make(),
        ];
    }
}
