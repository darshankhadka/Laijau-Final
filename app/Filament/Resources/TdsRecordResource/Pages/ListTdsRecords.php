<?php

declare(strict_types=1);

namespace App\Filament\Resources\TdsRecordResource\Pages;

use App\Filament\Resources\TdsRecordResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTdsRecords extends ListRecords
{
    protected static string $resource = TdsRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('+ Record TDS Withholding'),
        ];
    }
}
