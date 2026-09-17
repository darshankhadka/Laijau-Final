<?php

namespace App\Filament\Resources\StockAdjustmentResource\Pages;

use App\Filament\Resources\StockAdjustmentResource;
use App\Services\Inventory\InventoryService;
use Filament\Resources\Pages\CreateRecord;

class CreateStockAdjustment extends CreateRecord
{
    protected static string $resource = StockAdjustmentResource::class;

    protected function afterCreate(): void
    {
        /** @var \App\Models\Inventory\StockAdjustment $record */
        $record = $this->record;
        if ($record->status === 'approved') {
            app(InventoryService::class)->applyAdjustment($record);
        }
    }
}
