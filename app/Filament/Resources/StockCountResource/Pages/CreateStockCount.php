<?php

namespace App\Filament\Resources\StockCountResource\Pages;

use App\Filament\Resources\StockCountResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStockCount extends CreateRecord
{
    protected static string $resource = StockCountResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['conducted_by'] = auth()->id();
        return $data;
    }
}
