<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;
    protected Width | string | null $maxWidth = 'full';

    public function getSubheading(): ?string
    {
        return 'Omnichannel Order Operations: Track sales orders, courier dispatch, customer invoices and settlement.';
    }

    public function getTabs(): array
    {
        $pendingCount = Order::whereIn('status', ['pending', 'pending_payment', 'customer_confirmed', 'payment_verified'])->count();
        $processingCount = Order::whereIn('status', ['processing', 'packing', 'ready_for_delivery'])->count();
        $inTransitCount = Order::whereIn('status', ['handed_to_courier', 'in_transit'])->count();
        $deliveredCount = Order::where('status', 'delivered')->count();
        $cancelledCount = Order::whereIn('status', ['cancelled', 'returned', 'failed_delivery'])->count();

        return [
            'all' => Tab::make('All Orders')
                ->icon('heroicon-m-shopping-bag')
                ->badge(Order::count()),

            'pending' => Tab::make('Pending Action')
                ->icon('heroicon-m-clock')
                ->badge($pendingCount > 0 ? $pendingCount : null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['pending', 'pending_payment', 'customer_confirmed', 'payment_verified'])),

            'processing' => Tab::make('Fulfillment & Packing')
                ->icon('heroicon-m-archive-box')
                ->badge($processingCount > 0 ? $processingCount : null)
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['processing', 'packing', 'ready_for_delivery'])),

            'in_transit' => Tab::make('In Transit')
                ->icon('heroicon-m-truck')
                ->badge($inTransitCount > 0 ? $inTransitCount : null)
                ->badgeColor('purple')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['handed_to_courier', 'in_transit'])),

            'delivered' => Tab::make('Delivered')
                ->icon('heroicon-m-check-badge')
                ->badge($deliveredCount)
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'delivered')),

            'cancelled' => Tab::make('Cancelled / Returned')
                ->icon('heroicon-m-x-circle')
                ->badge($cancelledCount)
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['cancelled', 'returned', 'failed_delivery'])),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('fulfillment_hub')
                ->label('Fulfillment Hub')
                ->icon('heroicon-o-truck')
                ->color('success')
                ->url(route('filament.admin.pages.fulfillment-hub')),

            Actions\Action::make('pos')
                ->label('Point of Sale')
                ->icon('heroicon-o-computer-desktop')
                ->color('primary')
                ->url(url('/intadmin/offline-sales/POS'))
                ->openUrlInNewTab(true),
        ];
    }
}
