<?php

namespace App\Filament\Resources\SupplierResource\Pages;

use App\Filament\Resources\SupplierResource;
use App\Models\Inventory\Supplier;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListSuppliers extends ListRecords
{
    protected static string $resource = SupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('+ New Supplier'),
            Actions\Action::make('purchase_orders')
                ->label('Purchasing Hub')
                ->icon('heroicon-m-clipboard-document-list')
                ->color('gray')
                ->url(route('filament.admin.resources.purchase-orders.index')),
        ];
    }

    public function getTabs(): array
    {
        $allCount = Supplier::count();
        $activeCount = Supplier::where('is_active', true)->count();
        $withPosCount = Supplier::has('purchaseOrders')->count();
        $newCount = Supplier::doesntHave('purchaseOrders')->count();

        return [
            'all' => Tab::make('All Suppliers')
                ->icon('heroicon-m-building-office-2')
                ->badge((string) $allCount),

            'active' => Tab::make('Active Vendors')
                ->icon('heroicon-m-check-circle')
                ->badge((string) $activeCount)
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_active', true)),

            'with_pos' => Tab::make('With PO History')
                ->icon('heroicon-m-document-text')
                ->badge((string) $withPosCount)
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $query) => $query->has('purchaseOrders')),

            'no_pos' => Tab::make('New / Pending PO')
                ->icon('heroicon-m-clock')
                ->badge((string) $newCount)
                ->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $query) => $query->doesntHave('purchaseOrders')),
        ];
    }
}
