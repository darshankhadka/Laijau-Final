<?php

namespace App\Filament\Resources\ExpenseClaimResource\Pages;

use App\Filament\Resources\ExpenseClaimResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateExpenseClaim extends CreateRecord
{
    protected static string $resource = ExpenseClaimResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $employee = \App\Models\Hrm\Employee::findOrFail($data['employee_id']);
        $receiptPath = $data['receipt_path'] ?? null;
        return app(\App\Services\Hrm\ExpenseService::class)->submitExpenseClaim($employee, $data, $receiptPath);
    }
}
