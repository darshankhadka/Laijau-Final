<?php

namespace App\Filament\Resources\PurchaseOrderResource\Pages;

use App\Filament\Resources\PurchaseOrderResource;
use App\Models\Inventory\PurchaseOrder;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPurchaseOrders extends ListRecords
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('+ New Purchase Order'),
            Actions\Action::make('stock_levels')
                ->label('Stock Overview')
                ->icon('heroicon-m-cube')
                ->color('gray')
                ->url(route('filament.admin.resources.stock-levels.index')),
        ];
    }

    public function getTabs(): array
    {
        $allCount = PurchaseOrder::count();
        $receivedCount = PurchaseOrder::where('status', 'received')->count();
        $inboundCount = PurchaseOrder::whereIn('status', ['ordered', 'in_transit', 'partially_received', 'approved', 'submitted'])->count();
        $draftCount = PurchaseOrder::where('status', 'draft')->count();

        return [
            'all' => Tab::make('All Purchase Orders')
                ->icon('heroicon-m-clipboard-document-list')
                ->badge((string) $allCount),

            'received' => Tab::make('Received & Stocked')
                ->icon('heroicon-m-check-badge')
                ->badge((string) $receivedCount)
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'received')),

            'inbound' => Tab::make('Inbound / In Transit')
                ->icon('heroicon-m-truck')
                ->badge((string) $inboundCount)
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['ordered', 'in_transit', 'partially_received', 'approved', 'submitted'])),

            'draft' => Tab::make('Drafts')
                ->icon('heroicon-m-pencil-square')
                ->badge((string) $draftCount)
                ->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'draft')),
        ];
    }
}
