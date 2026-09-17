<?php

namespace App\Filament\Resources\StockReservationResource\Pages;

use App\Filament\Resources\StockReservationResource;
use App\Models\Inventory\StockReservation;
use App\Services\Inventory\InventoryService;
use Filament\Resources\Pages\CreateRecord;

class CreateStockReservation extends CreateRecord
{
    protected static string $resource = StockReservationResource::class;

    protected function handleRecordCreation(array $data): StockReservation
    {
        return app(InventoryService::class)->createReservation(
            (int)$data['warehouse_id'],
            (int)$data['product_id'],
            !empty($data['variant_id']) ? (int)$data['variant_id'] : null,
            (int)($data['quantity'] ?? 1),
            (string)($data['reference_type'] ?? 'manual'),
            null,
            $data['cart_token'] ?? null,
            30,
            auth()->user()
        );
    }
}
