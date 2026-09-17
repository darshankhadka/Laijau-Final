<?php

declare(strict_types=1);

namespace App\Filament\Resources\KharidKhataResource\Pages;

use App\Filament\Resources\KharidKhataResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListKharidKhataEntries extends ListRecords
{
    protected static string $resource = KharidKhataResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('+ Record Supplier Bill (Kharid)'),
        ];
    }
}
