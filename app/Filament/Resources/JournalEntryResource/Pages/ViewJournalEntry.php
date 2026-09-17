<?php

namespace App\Filament\Resources\JournalEntryResource\Pages;

use App\Filament\Resources\JournalEntryResource;
use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\AccountingService;
use Filament\Actions;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewJournalEntry extends ViewRecord
{
    protected static string $resource = JournalEntryResource::class;

    protected string $view = 'filament.resources.journal-entries.view-journal-entry';

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('reverse')
                ->label('Reverse Voucher')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->visible(fn () => $this->record->status === 'posted')
                ->requiresConfirmation()
                ->modalHeading('Reverse Posted Journal Voucher')
                ->modalDescription(fn () => "Under Nepal Accounting Standards (NAS), a posted voucher ({$this->record->entry_number}) cannot be deleted. A balanced reversal entry will be posted.")
                ->form([
                    Textarea::make('reason')
                        ->label('Reason for Reversal')
                        ->required()
                        ->placeholder('e.g. Incorrect account debited or posting error'),
                ])
                ->action(function (array $data): void {
                    try {
                        $reversal = app(AccountingService::class)->reverseJournalEntry($this->record, $data['reason']);
                        $this->record->refresh();
                        Notification::make()
                            ->title('Voucher Reversed')
                            ->body("Reversal voucher {$reversal->entry_number} has been posted.")
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Reversal Error')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\Action::make('all_entries')
                ->label('All Vouchers')
                ->icon('heroicon-m-arrow-left')
                ->color('gray')
                ->url(route('filament.admin.resources.journal-entries.index')),
        ];
    }
}
