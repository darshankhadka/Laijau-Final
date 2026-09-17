<?php

namespace App\Filament\Resources\AccountingInvoiceResource\Pages;

use App\Filament\Resources\AccountingInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAccountingInvoices extends ListRecords
{
    protected static string $resource = AccountingInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('+ New Invoice / Bill'),
        ];
    }
}
