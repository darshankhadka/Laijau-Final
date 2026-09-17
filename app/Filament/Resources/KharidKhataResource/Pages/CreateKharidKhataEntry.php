<?php

declare(strict_types=1);

namespace App\Filament\Resources\KharidKhataResource\Pages;

use App\Filament\Resources\KharidKhataResource;
use App\Services\Accounting\AccountingService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateKharidKhataEntry extends CreateRecord
{
    protected static string $resource = KharidKhataResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $items = $data['items'] ?? [];
        unset($data['items']);

        return app(AccountingService::class)->recordPurchaseBillKharidKhata($data, $items);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
