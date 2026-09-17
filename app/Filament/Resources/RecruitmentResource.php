<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RecruitmentResource\Pages;
use App\Models\Hrm\Department;
use App\Models\Hrm\Position;
use App\Models\Hrm\RecruitmentJob;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RecruitmentResource extends Resource
{
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $model = RecruitmentJob::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-briefcase';
    protected static string | \UnitEnum | null $navigationGroup = 'HRM';
    protected static ?string $navigationLabel = 'Recruitment & Jobs';
    protected static ?int $navigationSort = 80;

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Job Opening / Vacancy Details')
                    ->description('Manage open job positions, descriptions and candidate recruitment pipelines.')
                    ->schema([
                        TextInput::make('title')
                            ->label('Job Title')
                            ->required()
                            ->maxLength(150)
                            ->placeholder('e.g. Showroom Stylist & Client Advisor'),

                        Select::make('department_id')
                            ->label('Department')
                            ->options(Department::where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        Select::make('position_id')
                            ->label('Position Template')
                            ->options(Position::where('is_active', true)->pluck('title', 'id'))
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        Select::make('employment_type')
                            ->label('Employment Type')
                            ->options([
                                'full_time' => 'Full-time',
                                'part_time' => 'Part-time',
                                'hourly' => 'Hourly',
                            ])
                            ->default('full_time')
                            ->required(),

                        TextInput::make('location')
                            ->label('Location')
                            ->default('Showroom Kathmandu')
                            ->required(),

                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'draft' => 'Draft',
                                'published' => 'Published / Accepting Applications',
                                'closed' => 'Closed / Hired',
                            ])
                            ->default('published')
                            ->required(),

                        DatePicker::make('deadline')
                            ->label('Application Deadline')
                            ->default(now()->addDays(30))
                            ->nullable(),

                        Textarea::make('description')
                            ->label('Job Description & Role Mission')
                            ->columnSpanFull(),

                        Textarea::make('requirements')
                            ->label('Candidate Qualifications & Requirements')
                            ->columnSpanFull(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Job Title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('department.name')
                    ->label('Department')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('position.title')
                    ->label('Position')
                    ->sortable()
                    ->default('—'),

                TextColumn::make('location')
                    ->label('Location')
                    ->searchable(),

                TextColumn::make('employment_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn($state) => match ($state) {
                        'full_time' => 'Full-Time (37h)',
                        'part_time' => 'Part-Time',
                        'hourly' => 'Hourly',
                        default => $state,
                    })
                    ->color('info'),

                TextColumn::make('deadline')
                    ->label('Deadline')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'published' => 'success',
                        'draft' => 'warning',
                        'closed' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('applicants_count')
                    ->label('Applicants')
                    ->counts('applicants')
                    ->badge()
                    ->color('info'),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRecruitments::route('/'),
            'create' => Pages\CreateRecruitment::route('/create'),
            'edit' => Pages\EditRecruitment::route('/{record}/edit'),
        ];
    }
}
