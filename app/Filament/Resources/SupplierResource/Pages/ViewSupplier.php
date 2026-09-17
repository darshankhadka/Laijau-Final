<?php

namespace App\Filament\Resources\SupplierResource\Pages;

use App\Filament\Resources\SupplierResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSupplier extends ViewRecord
{
    protected static string $resource = SupplierResource::class;

    protected string $view = 'filament.resources.suppliers.view-supplier';

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('create_po')
                ->label('New Purchase Order')
                ->icon('heroicon-m-plus')
                ->color('primary')
                ->url(route('filament.admin.resources.purchase-orders.create') . '?supplier_id=' . $this->record->id),

            Actions\EditAction::make(),
        ];
    }
}
