<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $cprRaw = $data['cpr_raw'] ?? null;
        unset($data['cpr_raw']);

        $record->update($data);

        if ($cprRaw && !str_starts_with($cprRaw, '******')) {
            $record->setCpr($cprRaw);
            $record->save();
        }

        return $record;
    }
}
