<?php

namespace App\Filament\Resources\CrmLeadResource\Pages;

use App\Filament\Resources\CrmLeadResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCrmLead extends CreateRecord
{
    protected static string $resource = CrmLeadResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
