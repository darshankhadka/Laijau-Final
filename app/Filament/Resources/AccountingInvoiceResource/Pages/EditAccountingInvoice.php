<?php

namespace App\Filament\Resources\AccountingInvoiceResource\Pages;

use App\Filament\Resources\AccountingInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAccountingInvoice extends EditRecord
{
    protected static string $resource = AccountingInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $this->record->recalculateTotals();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
