<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TimesheetResource\Pages;
use App\Models\Hrm\Employee;
use App\Models\Hrm\Timesheet;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TimesheetResource extends Resource
{
    protected static ?string $model = Timesheet::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clock';
    protected static string | \UnitEnum | null $navigationGroup = 'People';
    protected static ?string $navigationLabel = 'Attendance';
    protected static ?int $navigationSort = 20;

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Work Shift & Attendance Timestamps')
                    ->description('Employee attendance tracking and shift hours for Laijau showroom and warehouse operations')
                    ->schema([
                        Select::make('employee_id')
                            ->label('Employee')
                            ->options(Employee::where('status', 'active')->get()->pluck('full_name', 'id'))
                            ->searchable()
                            ->required(),

                        DatePicker::make('date')
                            ->label('Work Date')
                            ->default(now())
                            ->required(),

                        Select::make('location')
                            ->label('Work Location')
                            ->options([
                                'Laijau Showroom' => 'Laijau Showroom',
                                'Laijau Showroom 2' => 'Laijau Showroom 2',
                                'Laijau Central Fulfillment Hub' => 'Laijau Central Fulfillment Hub',
                                'Remote / Field' => 'Remote / Field',
                            ])
                            ->default('Laijau Showroom')
                            ->required(),

                        TextInput::make('shift_name')
                            ->label('Assigned Shift')
                            ->default('Showroom Retail Shift')
                            ->required(),

                        TimePicker::make('shift_start_time')
                            ->label('Shift Scheduled Start')
                            ->default('08:00:00')
                            ->seconds(false),

                        TimePicker::make('shift_end_time')
                            ->label('Shift Scheduled End')
                            ->default('19:00:00')
                            ->seconds(false),

                        TimePicker::make('clock_in')
                            ->label('Actual Clock In')
                            ->seconds(false)
                            ->default('10:00'),

                        TimePicker::make('clock_out')
                            ->label('Actual Clock Out')
                            ->seconds(false)
                            ->default('19:00'),

                        TextInput::make('break_minutes')
                            ->label('Break (Minutes)')
                            ->numeric()
                            ->default(60),

                        TextInput::make('regular_hours')
                            ->label('Regular Hours')
                            ->numeric()
                            ->step(0.01)
                            ->default(8.0),

                        TextInput::make('overtime_hours')
                            ->label('Overtime Hours')
                            ->numeric()
                            ->step(0.01)
                            ->default(0.0),

                        Select::make('attendance_status')
                            ->label('Attendance Status')
                            ->options([
                                'present' => 'Present',
                                'half_day' => 'Half Day',
                                'absent' => 'Absent',
                                'on_leave' => 'On Approved Leave',
                                'missing_punch' => 'Missing Punch Flag',
                            ])
                            ->default('present')
                            ->required(),

                        Select::make('status')
                            ->label('Manager Approval')
                            ->options([
                                'draft' => 'Draft',
                                'submitted' => 'Submitted',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                            ])
                            ->default('approved')
                            ->required(),

                        Toggle::make('is_late')
                            ->label('Late Arrival Flag')
                            ->default(false),

                        TextInput::make('late_minutes')
                            ->label('Late Minutes')
                            ->numeric()
                            ->default(0),

                        Textarea::make('notes')
                            ->label('Shift Notes / Description')
                            ->columnSpanFull(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee.full_name')
                    ->label('Employee')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('date')
                    ->label('Date')
                    ->date('M d, Y')
                    ->sortable(),

                TextColumn::make('total_sessions')
                    ->label('Sessions')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(function ($state, $record) {
                        $count = $state ?: ($record->attendanceSessions()->count() ?: 1);
                        return $count . ' ' . \Illuminate\Support\Str::plural('sess', $count);
                    })
                    ->sortable(),

                TextColumn::make('clock_in')
                    ->label('First In')
                    ->time('h:i A'),

                TextColumn::make('clock_out')
                    ->label('Last Out')
                    ->time('h:i A')
                    ->placeholder('Active'),

                TextColumn::make('total_worked_hours')
                    ->label('Worked')
                    ->formatStateUsing(function ($state, $record) {
                        $hrs = (float)($state ?: $record->regular_hours);
                        $mins = (int)($record->total_worked_minutes ?: ($hrs * 60));
                        $h = floor($mins / 60);
                        $m = $mins % 60;
                        return "{$h}h {$m}m";
                    })
                    ->sortable(),

                TextColumn::make('regular_hours')
                    ->label('Regular (Hrs)')
                    ->numeric(2)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('overtime_hours')
                    ->label('OT (Hrs)')
                    ->numeric(2)
                    ->badge()
                    ->color(fn($state) => (float)$state > 0 ? 'warning' : 'gray'),

                TextColumn::make('is_late')
                    ->label('Late')
                    ->badge()
                    ->formatStateUsing(fn($record) => $record->is_late ? "{$record->late_minutes}m late" : 'On Time')
                    ->color(fn($state) => $state ? 'danger' : 'success'),

                TextColumn::make('attendance_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'present' => 'success',
                        'half_day' => 'warning',
                        'absent' => 'danger',
                        'missing_punch' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('status')
                    ->label('Approval')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'approved' => 'success',
                        'submitted' => 'warning',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('employee_id')
                    ->label('Employee')
                    ->searchable()
                    ->options(fn() => Employee::orderBy('first_name')->get()->mapWithKeys(function ($emp) {
                        $name = trim($emp->first_name . ' ' . $emp->last_name);
                        return [$emp->id => $emp->employee_number ? "{$name} ({$emp->employee_number})" : $name];
                    })->all()),

                Tables\Filters\Filter::make('date_range')
                    ->form([
                        DatePicker::make('from_date')->label('From Date'),
                        DatePicker::make('until_date')->label('Until Date'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from_date'] ?? null, fn($q, $date) => $q->whereDate('date', '>=', $date))
                            ->when($data['until_date'] ?? null, fn($q, $date) => $q->whereDate('date', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from_date'] ?? null) {
                            $indicators['from_date'] = 'From: ' . \Carbon\Carbon::parse($data['from_date'])->format('M d, Y');
                        }
                        if ($data['until_date'] ?? null) {
                            $indicators['until_date'] = 'Until: ' . \Carbon\Carbon::parse($data['until_date'])->format('M d, Y');
                        }
                        return $indicators;
                    }),

                Tables\Filters\SelectFilter::make('attendance_status')
                    ->options([
                        'present' => 'Present',
                        'half_day' => 'Half Day',
                        'absent' => 'Absent',
                        'missing_punch' => 'Missing Punch',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Approval Status')
                    ->options([
                        'submitted' => 'Pending Approval',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->actions([
                \Filament\Actions\Action::make('view_sessions')
                    ->label('Sessions')
                    ->icon('heroicon-o-queue-list')
                    ->color('info')
                    ->modalHeading(fn(Timesheet $record) => "Attendance Sessions — " . ($record->employee?->full_name ?? 'Employee') . " (" . ($record->date?->format('M d, Y') ?? '') . ")")
                    ->modalWidth('4xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(fn(Timesheet $record) => view('filament.components.timesheet-sessions-modal', [
                        'record' => $record,
                    ])),

                \Filament\Actions\Action::make('employee_summary')
                    ->label('Summary')
                    ->icon('heroicon-o-chart-bar')
                    ->color('gray')
                    ->modalHeading(fn(Timesheet $record) => "Monthly Attendance Summary — " . ($record->employee?->full_name ?? 'Employee'))
                    ->modalWidth('4xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(function (Timesheet $record) {
                        $attendanceService = app(\App\Services\Attendance\AttendanceService::class);
                        $summary = $attendanceService->getEmployeeTimesheetSummary($record->employee, 'monthly');
                        return view('filament.components.timesheet-employee-summary-modal', [
                            'summary' => $summary,
                        ]);
                    }),

                \Filament\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn($record) => $record->status === 'submitted' || $record->status === 'draft')
                    ->action(function (Timesheet $record) {
                        $record->status = 'approved';
                        $record->approved_by = auth()->id();
                        $record->approved_at = now();
                        $record->save();

                        Notification::make()
                            ->title('Timesheet Approved')
                            ->body("Approved attendance for {$record->employee->full_name} on {$record->date->format('M d')}.")
                            ->success()
                            ->send();
                    }),

                \Filament\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTimesheets::route('/'),
            'create' => Pages\CreateTimesheet::route('/create'),
            'edit' => Pages\EditTimesheet::route('/{record}/edit'),
        ];
    }
}
