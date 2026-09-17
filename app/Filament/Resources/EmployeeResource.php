<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeResource\Pages;
use App\Models\Hrm\Department;
use App\Models\Hrm\Employee;
use App\Models\Hrm\Position;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-identification';
    protected static string | \UnitEnum | null $navigationGroup = 'People';
    protected static ?string $navigationLabel = 'Employees';
    protected static ?int $navigationSort = 10;

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Employee Identity & Nepal Statutory Profile')
                    ->description('Personal identification, PAN, and statutory profile under Nepal Labour Act')
                    ->schema([
                        TextInput::make('employee_number')
                            ->label('Employee ID / Code')
                            ->default(fn() => Employee::generateNextEmployeeNumber())
                            ->required()
                            ->maxLength(30)
                            ->unique(ignoreRecord: true),

                        TextInput::make('first_name')
                            ->label('First Name')
                            ->required()
                            ->maxLength(100),

                        TextInput::make('last_name')
                            ->label('Last Name')
                            ->required()
                            ->maxLength(100),

                        TextInput::make('pan_number')
                            ->label('Nepal PAN (Permanent Account Number)')
                            ->placeholder('e.g. 102938475 (9 digits)')
                            ->maxLength(20)
                            ->required(),

                        TextInput::make('citizenship_number')
                            ->label('Citizenship / National ID #')
                            ->placeholder('e.g. 27-01-78-12345')
                            ->maxLength(50),

                        Select::make('marital_status')
                            ->label('Tax Marital Status')
                            ->options([
                                'single' => 'Single (Unmarried / Individual Slab)',
                                'married' => 'Married (Couple / Joint Slab)',
                            ])
                            ->default('single')
                            ->helperText('Determines Section 87 Income Tax Slabs')
                            ->required(),

                        TextInput::make('email')
                            ->label('Work Email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(150),

                        TextInput::make('phone')
                            ->label('Mobile Phone (+977)')
                            ->tel()
                            ->maxLength(30)
                            ->placeholder('+977 98XXXXXXXX'),

                        Select::make('branch_location')
                            ->label('Branch / Operational Location')
                            ->options([
                                'Laijau Showroom' => 'Laijau Showroom',
                                'Laijau Showroom 2' => 'Laijau Showroom 2',
                                'Laijau Central Fulfillment Hub' => 'Laijau Central Fulfillment Hub',
                                'Remote / Field' => 'Remote / Field',
                            ])
                            ->default('Laijau Showroom')
                            ->required(),
                    ])->columns(3),

                Section::make('Organizational Placement & Employment Terms')
                    ->schema([
                        Select::make('department_id')
                            ->label('Department')
                            ->options(Department::where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->preload(),

                        Select::make('position_id')
                            ->label('Position / Designation')
                            ->options(Position::where('is_active', true)->pluck('title', 'id'))
                            ->searchable()
                            ->preload(),

                        Select::make('manager_id')
                            ->label('Reporting Manager')
                            ->options(Employee::where('status', 'active')->get()->pluck('full_name', 'id'))
                            ->searchable(),

                        Select::make('employment_type')
                            ->label('Employment Type')
                            ->options([
                                'full_time' => 'Full-time (Showroom / Office)',
                                'part_time' => 'Part-time',
                                'hourly' => 'Contract / Hourly',
                            ])
                            ->default('full_time')
                            ->required(),

                        Select::make('status')
                            ->label('Employment Status')
                            ->options([
                                'active' => 'Active',
                                'probation' => 'Probation',
                                'on_leave' => 'On Leave',
                                'terminated' => 'Terminated',
                            ])
                            ->default('active')
                            ->required(),

                        DatePicker::make('hire_date')
                            ->label('Joining Date')
                            ->default(now())
                            ->required(),

                        DatePicker::make('probation_end_date')
                            ->label('Probation End Date')
                            ->nullable(),

                        DatePicker::make('termination_date')
                            ->label('Termination Date')
                            ->nullable(),
                    ])->columns(3),

                Section::make('Nepal Salary Structure & Statutory Contributions')
                    ->description('Monthly basic salary, allowances, and Social Security Fund (SSF) configuration')
                    ->schema([
                        TextInput::make('basic_salary')
                            ->label('Basic Salary (NPR / Rs.)')
                            ->numeric()
                            ->prefix('Rs.')
                            ->default(30000.00)
                            ->required(),

                        TextInput::make('allowance_amount')
                            ->label('Dearness & Other Allowances (NPR / Rs.)')
                            ->numeric()
                            ->prefix('Rs.')
                            ->default(15000.00)
                            ->helperText('House rent, transport, communication, meals'),

                        Toggle::make('ssf_enrolled')
                            ->label('Enrolled in Social Security Fund (SSF)')
                            ->default(true)
                            ->helperText('11% Employee deduction + 20% Employer contribution on Basic Salary'),

                        TextInput::make('ssf_number')
                            ->label('SSF Registration Number')
                            ->placeholder('e.g. SSF-10293847')
                            ->maxLength(50),

                        TextInput::make('annual_leave_quota')
                            ->label('Annual Leave Entitlement (Days)')
                            ->numeric()
                            ->default(18.0)
                            ->helperText('1 day per 20 days worked under Labour Act'),

                        TextInput::make('sick_leave_quota')
                            ->label('Sick Leave Quota (Days)')
                            ->numeric()
                            ->default(12.0),
                    ])->columns(3),

                Section::make('Nepal Banking & Remittance Details')
                    ->schema([
                        TextInput::make('bank_name')
                            ->label('Bank Name')
                            ->placeholder('e.g. Nabil Bank Ltd.')
                            ->maxLength(100),

                        TextInput::make('bank_branch')
                            ->label('Bank Branch')
                            ->placeholder('e.g. Teendhara / Durbar Marg')
                            ->maxLength(100),

                        TextInput::make('bank_account_number')
                            ->label('Bank Account Number')
                            ->maxLength(30)
                            ->placeholder('e.g. 01201017500011'),

                        TextInput::make('bank_account_name')
                            ->label('Account Holder Name')
                            ->placeholder('Name as per bank passbook')
                            ->maxLength(150),

                        TextInput::make('iban')
                            ->label('eSewa / Digital Wallet ID')
                            ->placeholder('e.g. 98XXXXXXXX')
                            ->maxLength(50),
                    ])->columns(3),

                Section::make('Emergency Contacts & Notes')
                    ->schema([
                        TextInput::make('emergency_contact_name')
                            ->label('Contact Person')
                            ->maxLength(150),

                        TextInput::make('emergency_contact_phone')
                            ->label('Emergency Phone')
                            ->maxLength(50),

                        TextInput::make('emergency_contact_relation')
                            ->label('Relationship')
                            ->placeholder('e.g. Spouse / Parent / Sibling')
                            ->maxLength(100),

                        Textarea::make('notes')
                            ->label('Administrative Notes / History')
                            ->columnSpanFull(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee_number')
                    ->label('ID')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->fontFamily('mono'),

                TextColumn::make('full_name')
                    ->label('Employee Name')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),

                TextColumn::make('pan_number')
                    ->label('PAN #')
                    ->fontFamily('mono')
                    ->searchable(),

                TextColumn::make('department.name')
                    ->label('Department')
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('position.title')
                    ->label('Designation')
                    ->sortable(),

                TextColumn::make('branch_location')
                    ->label('Branch')
                    ->limit(20)
                    ->color('gray'),

                TextColumn::make('basic_salary')
                    ->label('Basic (Rs.)')
                    ->formatStateUsing(fn($state) => 'Rs. ' . number_format((float)$state, 0))
                    ->sortable(),

                TextColumn::make('marital_status')
                    ->label('Marital')
                    ->badge()
                    ->color(fn($state) => $state === 'married' ? 'info' : 'gray'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'active' => 'success',
                        'probation' => 'warning',
                        'on_leave' => 'info',
                        'terminated' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('hire_date')
                    ->label('Joined')
                    ->date('M d, Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('department_id')
                    ->label('Department')
                    ->relationship('department', 'name'),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'probation' => 'Probation',
                        'on_leave' => 'On Leave',
                        'terminated' => 'Terminated',
                    ]),

                Tables\Filters\SelectFilter::make('marital_status')
                    ->options([
                        'single' => 'Single',
                        'married' => 'Married',
                    ]),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'edit' => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }
}
