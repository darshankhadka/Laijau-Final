<?php

namespace App\Filament\Resources\JournalEntryResource\Pages;

use App\Filament\Resources\JournalEntryResource;
use App\Models\Accounting\JournalEntry;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListJournalEntries extends ListRecords
{
    protected static string $resource = JournalEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('+ New Journal Voucher (JV)'),
            Actions\Action::make('bikri_khata')
                ->label('Bikri Khata')
                ->icon('heroicon-m-book-open')
                ->color('gray')
                ->url('/intadmin/bikri-khata'),
            Actions\Action::make('accounting_dashboard')
                ->label('Accounting Hub')
                ->icon('heroicon-m-chart-bar')
                ->color('gray')
                ->url('/intadmin/accounting'),
        ];
    }

    public function getTabs(): array
    {
        $allCount = JournalEntry::count();
        $salesCount = JournalEntry::where('entry_type', 'sales')->count();
        $manualCount = JournalEntry::where('entry_type', 'manual')->count();
        $cogsCount = JournalEntry::where('entry_type', 'cogs')->count();
        $bankingCount = JournalEntry::whereIn('entry_type', ['purchase', 'bank', 'settlement'])->count();

        return [
            'all' => Tab::make('All Vouchers')
                ->icon('heroicon-m-document-duplicate')
                ->badge((string) $allCount),

            'sales' => Tab::make('Sales (Bikri)')
                ->icon('heroicon-m-shopping-bag')
                ->badge((string) $salesCount)
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('entry_type', 'sales')),

            'manual' => Tab::make('General (Aam Khata)')
                ->icon('heroicon-m-book-open')
                ->badge((string) $manualCount)
                ->badgeColor('primary')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('entry_type', 'manual')),

            'cogs' => Tab::make('COGS & Inventory')
                ->icon('heroicon-m-cube')
                ->badge((string) $cogsCount)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('entry_type', 'cogs')),

            'banking' => Tab::make('Purchases & Banking')
                ->icon('heroicon-m-building-library')
                ->badge((string) $bankingCount)
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('entry_type', ['purchase', 'bank', 'settlement'])),
        ];
    }
}
