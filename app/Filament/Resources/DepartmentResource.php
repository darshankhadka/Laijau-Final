<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DepartmentResource\Pages;
use App\Models\Hrm\Department;
use App\Models\Hrm\Employee;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DepartmentResource extends Resource
{
    protected static ?string $model = Department::class;

    protected static bool $shouldRegisterNavigation = false;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-building-office';
    protected static string | \UnitEnum | null $navigationGroup = 'People';
    protected static ?string $navigationLabel = 'Organization & Departments';
    protected static ?int $navigationSort = 30;

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Department & Unit Details')
                    ->schema([
                        TextInput::make('name')
                            ->label('Department Name')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('e.g. Kathmandu Showroom'),

                        TextInput::make('code')
                            ->label('Department Code')
                            ->required()
                            ->maxLength(50)
                            ->placeholder('e.g. SHOWROOM')
                            ->unique(ignoreRecord: true),

                        TextInput::make('location')
                            ->label('Location / Facility')
                            ->maxLength(150)
                            ->placeholder('e.g. Durbar Marg, Kathmandu'),

                        Select::make('manager_id')
                            ->label('Department Head / Manager')
                            ->options(Employee::where('status', 'active')->get()->pluck('full_name', 'id'))
                            ->searchable()
                            ->nullable(),

                        Toggle::make('is_active')
                            ->label('Active Department')
                            ->default(true),

                        Textarea::make('description')
                            ->label('Description & Responsibilities')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->searchable(),

                TextColumn::make('name')
                    ->label('Department')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('location')
                    ->label('Location')
                    ->searchable(),

                TextColumn::make('manager.full_name')
                    ->label('Department Head')
                    ->default('—'),

                TextColumn::make('employees_count')
                    ->label('Staff Count')
                    ->counts('employees')
                    ->badge()
                    ->color('success'),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDepartments::route('/'),
            'create' => Pages\CreateDepartment::route('/create'),
            'edit' => Pages\EditDepartment::route('/{record}/edit'),
        ];
    }
}
