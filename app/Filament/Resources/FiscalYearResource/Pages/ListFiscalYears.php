<?php

declare(strict_types=1);

namespace App\Filament\Resources\FiscalYearResource\Pages;

use App\Filament\Resources\FiscalYearResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFiscalYears extends ListRecords
{
    protected static string $resource = FiscalYearResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('+ Add Nepali Fiscal Year'),
        ];
    }
}
