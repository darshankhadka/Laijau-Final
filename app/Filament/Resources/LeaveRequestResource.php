<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LeaveRequestResource\Pages;
use App\Models\Hrm\Employee;
use App\Models\Hrm\HolidayBalance;
use App\Models\Hrm\LeaveRequest;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LeaveRequestResource extends Resource
{
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $model = LeaveRequest::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-calendar-days';
    protected static string | \UnitEnum | null $navigationGroup = 'HRM';
    protected static ?string $navigationLabel = 'Leave & Absence';
    protected static ?int $navigationSort = 40;

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Leave & Absence Details')
                    ->description('Nepal Labour Act (श्रम ऐन, २०७४) statutory leave categories')
                    ->schema([
                        Select::make('employee_id')
                            ->label('Employee')
                            ->options(Employee::where('status', 'active')->get()->pluck('full_name', 'id'))
                            ->searchable()
                            ->required(),

                        Select::make('leave_type')
                            ->label('Leave Type')
                            ->options([
                                'annual' => 'Annual Home Leave (घर बिदा - Labour Act)',
                                'casual' => 'Casual Leave (भइपरी बिदा)',
                                'sickness' => 'Sick Leave (बिरामी बिदा)',
                                'maternity' => 'Maternity / Paternity Leave (प्रसूति बिदा)',
                                'bereavement' => 'Mourning / Bereavement Leave (किरिया बिदा)',
                                'unpaid' => 'Unpaid Leave (अवैतनिक बिदा)',
                                'other' => 'Other Absence',
                            ])
                            ->default('annual')
                            ->required(),

                        DatePicker::make('start_date')
                            ->label('Start Date')
                            ->required(),

                        DatePicker::make('end_date')
                            ->label('End Date')
                            ->required(),

                        TextInput::make('days_count')
                            ->label('Work Days Requested')
                            ->numeric()
                            ->step(0.5)
                            ->default(1.0)
                            ->required(),

                        Toggle::make('is_paid')
                            ->label('Paid Leave')
                            ->default(true),

                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'pending' => 'Pending Review',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('pending')
                            ->required(),

                        Textarea::make('reason')
                            ->label('Reason / Notes')
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
                    ->sortable(),

                TextColumn::make('leave_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn($state) => match ($state) {
                        'annual', 'annual_holiday', 'home_leave' => 'Home Leave (घर बिदा)',
                        'casual' => 'Casual Leave (भइपरी)',
                        'sickness', 'sick' => 'Sick Leave (बिरामी)',
                        'maternity' => 'Maternity/Paternity (प्रसूति)',
                        'bereavement' => 'Mourning Leave (किरिया)',
                        'unpaid' => 'Unpaid Leave',
                        'other' => 'Other',
                        default => ucfirst($state ?? ''),
                    })
                    ->color(fn($state) => match ($state) {
                        'annual', 'annual_holiday', 'home_leave' => 'success',
                        'sickness', 'sick' => 'danger',
                        'casual' => 'warning',
                        'maternity' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('start_date')
                    ->label('From')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label('To')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('days_count')
                    ->label('Days')
                    ->numeric(1)
                    ->suffix(' days')
                    ->weight('bold'),

                IconColumn::make('is_paid')
                    ->label('Paid')
                    ->boolean(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        'rejected' => 'danger',
                        'cancelled' => 'gray',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('start_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('employee_id')
                    ->label('Employee')
                    ->relationship('employee', 'first_name'),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending Review',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->actions([
                \Filament\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn($record) => $record->status === 'pending')
                    ->action(function (LeaveRequest $record) {
                        $record->update([
                            'status' => 'approved',
                            'approved_by' => \Illuminate\Support\Facades\Auth::id(),
                            'approved_at' => now(),
                        ]);

                        // Deduct from holiday balance if annual / annual_holiday
                        if (in_array($record->leave_type, ['annual', 'annual_holiday', 'home_leave'])) {
                            $year = $record->start_date ? (int)$record->start_date->format('Y') : (int)date('Y');
                            $balance = HolidayBalance::where('employee_id', $record->employee_id)
                                ->where('holiday_year', $year)
                                ->first();

                            if ($balance) {
                                $balance->used_days += (float)$record->days_count;
                                $balance->recalculate();
                                $balance->save();
                            }
                        }

                        Notification::make()
                            ->title('Leave Request Approved')
                            ->body("Leave approved for {$record->employee->full_name}.")
                            ->success()
                            ->send();
                    }),

                \Filament\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn($record) => $record->status === 'pending')
                    ->action(function (LeaveRequest $record) {
                        $record->update([
                            'status' => 'rejected',
                            'approved_by' => \Illuminate\Support\Facades\Auth::id(),
                            'approved_at' => now(),
                        ]);
                    }),

                \Filament\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeaveRequests::route('/'),
            'create' => Pages\CreateLeaveRequest::route('/create'),
            'edit' => Pages\EditLeaveRequest::route('/{record}/edit'),
        ];
    }
}
