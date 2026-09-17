<?php

namespace App\Filament\Resources\AccountResource\Pages;

use App\Filament\Resources\AccountResource;
use App\Models\Accounting\Account;
use App\Services\Accounting\AccountingService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListAccounts extends ListRecords
{
    protected static string $resource = AccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('recalc')
                ->label('Genberegn Kontosaldi')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function () {
                    $count = 0;
                    foreach (Account::all() as $acc) {
                        $acc->recalculateBalance();
                        $count++;
                    }
                    Notification::make()
                        ->title('Kontosaldi opdateret')
                        ->body("Genberegnede saldi for {$count} konti fra hovedbogen.")
                        ->success()
                        ->send();
                }),

            Actions\Action::make('seed')
                ->label('Indlæs Dansk Standard Kontoplan')
                ->icon('heroicon-o-sparkles')
                ->color('primary')
                ->requiresConfirmation()
                ->action(function () {
                    app(AccountingService::class)->seedDefaultChartOfAccounts();
                    Notification::make()
                        ->title('Kontoplan indlæst')
                        ->body('Standard dansk kontoplan er fuldt synkroniseret.')
                        ->success()
                        ->send();
                }),

            Actions\CreateAction::make()->label('+ Opret Konto'),
        ];
    }
}
