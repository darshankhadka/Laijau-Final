<?php

declare(strict_types=1);

namespace App\Filament\Resources\BikriKhataResource\Pages;

use App\Filament\Resources\BikriKhataResource;
use Filament\Resources\Pages\ListRecords;

class ListBikriKhataEntries extends ListRecords
{
    protected static string $resource = BikriKhataResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
