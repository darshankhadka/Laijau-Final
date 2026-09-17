<?php

namespace App\Filament\Resources\StockTransferResource\Pages;

use App\Filament\Resources\StockTransferResource;
use App\Models\Inventory\StockTransfer;
use App\Services\Inventory\InventoryService;
use Filament\Actions;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewStockTransfer extends ViewRecord
{
    protected static string $resource = StockTransferResource::class;

    protected string $view = 'filament.resources.stock-transfers.view-stock-transfer';

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('dispatch')
                ->label('Dispatch Transfer')
                ->icon('heroicon-m-paper-airplane')
                ->color('warning')
                ->visible(fn () => $this->record->status === 'draft')
                ->requiresConfirmation()
                ->modalHeading("Dispatch Transfer #{$this->record->transfer_number}")
                ->modalDescription("Deduct physical stock from {$this->record->sourceWarehouse?->name} and mark inventory as In Transit.")
                ->action(function (): void {
                    try {
                        app(InventoryService::class)->dispatchTransfer($this->record, auth()->user());
                        $this->record->refresh();

                        Notification::make()
                            ->title('Transfer Dispatched')
                            ->body("Inventory dispatched from {$this->record->sourceWarehouse?->name}. Marked In Transit.")
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Dispatch Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),

            Actions\Action::make('receive')
                ->label('Receive Goods')
                ->icon('heroicon-m-check-badge')
                ->color('success')
                ->visible(fn () => $this->record->status === 'in_transit')
                ->requiresConfirmation()
                ->modalHeading("Receive Transfer: #{$this->record->transfer_number}")
                ->modalDescription("Credit inventory to {$this->record->destinationWarehouse?->name} and complete this transfer.")
                ->action(function (): void {
                    try {
                        app(InventoryService::class)->receiveTransfer($this->record, [], auth()->user());
                        $this->record->refresh();

                        Notification::make()
                            ->title('Transfer Completed')
                            ->body("Stock received and verified at {$this->record->destinationWarehouse?->name}.")
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Receive Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\Action::make('cancel_transfer')
                ->label('Cancel Transfer')
                ->icon('heroicon-m-x-circle')
                ->color('danger')
                ->visible(fn () => in_array($this->record->status, ['draft', 'in_transit']))
                ->requiresConfirmation()
                ->modalHeading("Cancel Transfer #{$this->record->transfer_number}")
                ->modalDescription('Are you sure you want to cancel this transfer? Any in-transit items will be restored to the source warehouse.')
                ->form([
                    TextInput::make('cancel_reason')
                        ->label('Cancellation Reason')
                        ->placeholder('e.g. Route error, items returned to shelf, or logistics cancelled')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    try {
                        app(InventoryService::class)->cancelTransfer($this->record, $data['cancel_reason'], auth()->user());
                        $this->record->refresh();

                        Notification::make()
                            ->title('Transfer Cancelled')
                            ->body("Transfer #{$this->record->transfer_number} was cancelled.")
                            ->warning()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Cancellation Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\EditAction::make()
                ->visible(fn () => $this->record->status === 'draft'),
        ];
    }
}
