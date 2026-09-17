<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New System User')
                ->icon('heroicon-o-plus'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all_staff' => Tab::make('All Staff')
                ->badge(User::where('role', '!=', 'customer')->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('role', '!=', 'customer')),

            'admins' => Tab::make('Administrators')
                ->badge(User::whereIn('role', ['admin', 'super_admin', 'workspace_admin'])->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('role', ['admin', 'super_admin', 'workspace_admin'])),

            'finance_hr' => Tab::make('Finance & HR')
                ->badge(User::whereIn('role', ['accountant', 'hr_manager'])->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('role', ['accountant', 'hr_manager'])),

            'operations' => Tab::make('Operations')
                ->badge(User::whereIn('role', ['store_manager', 'warehouse_manager', 'cashier', 'support_agent'])->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('role', ['store_manager', 'warehouse_manager', 'cashier', 'support_agent'])),

            'inactive' => Tab::make('Deactivated')
                ->badge(User::where('role', '!=', 'customer')->where('is_active', false)->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('role', '!=', 'customer')->where('is_active', false)),
        ];
    }
}
