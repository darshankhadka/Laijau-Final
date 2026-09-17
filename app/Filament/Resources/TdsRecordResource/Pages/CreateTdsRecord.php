<?php

declare(strict_types=1);

namespace App\Filament\Resources\TdsRecordResource\Pages;

use App\Filament\Resources\TdsRecordResource;
use App\Models\Accounting\TdsRecord;
use Filament\Resources\Pages\CreateRecord;

class CreateTdsRecord extends CreateRecord
{
    protected static string $resource = TdsRecordResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $fy = $data['fiscal_year'] ?? '2083/84';
        $data['tds_number'] = TdsRecord::generateNextTdsNumber($fy);
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
