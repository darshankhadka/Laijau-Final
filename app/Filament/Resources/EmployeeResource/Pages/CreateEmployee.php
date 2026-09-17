<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateEmployee extends CreateRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $cprRaw = $data['cpr_raw'] ?? null;
        unset($data['cpr_raw']);

        $record = static::getModel()::create($data);

        if ($cprRaw) {
            $record->setCpr($cprRaw);
            $record->save();
        }

        // Ensure holiday balance
        app(\App\Services\Hrm\HrmService::class)->ensureHolidayBalance($record, (int)date('Y'));

        return $record;
    }
}
