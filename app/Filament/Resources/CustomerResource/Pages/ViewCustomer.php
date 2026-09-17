<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Models\User;
use App\Services\Customer\CustomerService;
use Filament\Actions;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    protected string $view = 'filament.resources.customers.view-customer';

    public string $internalNotes = '';

    public function mount(int | string $record): void
    {
        parent::mount($record);
        $this->internalNotes = (string)($this->record->notes ?? '');
    }

    public function saveNotes(): void
    {
        $this->record->update([
            'notes' => $this->internalNotes,
        ]);

        Notification::make()
            ->title('Customer internal notes saved.')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            // WhatsApp Direct Contact Action
            Actions\Action::make('whatsapp')
                ->label('Chat on WhatsApp')
                ->icon('heroicon-o-chat-bubble-left-ellipsis')
                ->color('success')
                ->visible(fn (User $record) => !empty($record->phone))
                ->url(function (User $record): string {
                    $cleanPhone = preg_replace('/[^0-9]/', '', (string)$record->phone);
                    if (strlen($cleanPhone) === 10) {
                        $cleanPhone = '977' . $cleanPhone;
                    }
                    $name = $record->name ?: 'Customer';
                    $msg = "Namaste {$name}! Laijau Support here. How can we assist you today?";
                    return "https://wa.me/{$cleanPhone}?text=" . urlencode($msg);
                })
                ->openUrlInNewTab(),

            // Add/Update Internal Note Action Modal
            Actions\Action::make('add_note')
                ->label('Edit Staff Note')
                ->icon('heroicon-o-pencil-square')
                ->color('gray')
                ->form([
                    Textarea::make('notes')
                        ->label('Internal Operational Notes')
                        ->placeholder('e.g. Prefers WhatsApp communication, VIP customer, call before dispatch.')
                        ->default(fn (User $record) => $record->notes)
                        ->rows(4)
                        ->required(),
                ])
                ->action(function (array $data, User $record): void {
                    $record->update(['notes' => $data['notes']]);
                    $this->internalNotes = $data['notes'];
                    Notification::make()->title('Internal notes updated successfully.')->success()->send();
                }),

            Actions\EditAction::make(),
        ];
    }
}
