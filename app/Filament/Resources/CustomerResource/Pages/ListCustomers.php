<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('+ New Customer'),
            Actions\Action::make('crm_pipeline')
                ->label('CRM Pipeline & Follow-ups')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('info')
                ->url(route('filament.admin.pages.crm-kanban')),
        ];
    }

    public function getTabs(): array
    {
        $allCount = User::where('role', 'customer')->count();
        $activeBuyersCount = User::where('role', 'customer')
            ->where(fn ($q) => $q->has('orders')->orHas('offlineSales'))
            ->count();
        $vipCount = User::where('role', 'customer')
            ->where(fn ($q) => $q->has('orders', '>=', 2)->orWhereHas('orders', fn ($sub) => $sub->whereRaw('total_amount >= 50000')))
            ->count();
        $newCount = User::where('role', 'customer')
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
        $prospectCount = User::where('role', 'customer')
            ->doesntHave('orders')
            ->doesntHave('offlineSales')
            ->count();

        return [
            'all' => Tab::make('All Customers')
                ->icon('heroicon-m-user-group')
                ->badge((string) $allCount),

            'active_buyers' => Tab::make('Active Buyers')
                ->icon('heroicon-m-shopping-bag')
                ->badge((string) $activeBuyersCount)
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->where(fn ($q) => $q->has('orders')->orHas('offlineSales'))),

            'vip' => Tab::make('VIP Clients')
                ->icon('heroicon-m-star')
                ->badge((string) $vipCount)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where(fn ($q) => $q->has('orders', '>=', 2)->orWhereHas('orders', fn ($sub) => $sub->whereRaw('total_amount >= 50000')))),

            'new_30d' => Tab::make('New Registrations (30d)')
                ->icon('heroicon-m-sparkles')
                ->badge((string) $newCount)
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('created_at', '>=', now()->subDays(30))),

            'prospects' => Tab::make('Prospects / No Orders')
                ->icon('heroicon-m-clock')
                ->badge((string) $prospectCount)
                ->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $query) => $query->doesntHave('orders')->doesntHave('offlineSales')),
        ];
    }
}
