<?php

namespace App\Filament\Resources\StockReservationResource\Pages;

use App\Filament\Resources\StockReservationResource;
use App\Services\Inventory\InventoryService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListStockReservations extends ListRecords
{
    protected static string $resource = StockReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('cleanup_expired')
                ->label('Cleanup Expired')
                ->icon('heroicon-o-trash')
                ->color('warning')
                ->action(function () {
                    $cleaned = app(InventoryService::class)->cleanupExpiredReservations();
                    Notification::make()
                        ->title('Expired Reservations Cleaned')
                        ->body("{$cleaned} expired reservations swept and stock restored.")
                        ->success()
                        ->send();
                }),

            Actions\CreateAction::make(),
        ];
    }
}
