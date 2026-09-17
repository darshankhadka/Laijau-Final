<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->before(function (Actions\DeleteAction $action, \App\Models\User $record) {
                    if ($record->orders()->count() > 0 || $record->offlineSales()->count() > 0) {
                        \Filament\Notifications\Notification::make()
                            ->title('Cannot Delete Customer')
                            ->body("Customer '{$record->name}' has existing historical sales/orders. Deleting them would violate audit trail requirements.")
                            ->danger()
                            ->send();
                        $action->halt();
                    }
                }),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
