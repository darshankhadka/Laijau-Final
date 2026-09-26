<?php

namespace App\Filament\Resources\TimesheetResource\Pages;

use App\Filament\Resources\TimesheetResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTimesheets extends ListRecords
{
    protected static string $resource = TimesheetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('employee_summary_report')
                ->label('Employee Summary Report')
                ->icon('heroicon-o-chart-bar')
                ->color('info')
                ->modalHeading('Employee Attendance & Timesheet Summary')
                ->modalDescription('View aggregated attendance totals for daily, weekly, monthly, or custom periods.')
                ->modalWidth('5xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->modalContent(fn() => view('filament.components.timesheet-summary-report-picker', [
                    'employees' => \App\Models\Hrm\Employee::where('status', 'active')->orderBy('first_name')->get(),
                ])),

            Actions\CreateAction::make(),
        ];
    }
}
