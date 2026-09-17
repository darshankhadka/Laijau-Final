<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttributeResource\Pages;
use App\Models\ProductAttribute;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Components\Section;

class AttributeResource extends Resource
{
    protected static ?string $model = ProductAttribute::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-adjustments-horizontal';
    protected static string | \UnitEnum | null $navigationGroup = 'Products';
    protected static ?string $navigationLabel = 'Attributes & Variants';
    protected static ?string $modelLabel = 'Attribute / Size';
    protected static ?string $pluralModelLabel = 'Attributes & Sizes';
    protected static ?string $slug = 'attributes-sizes';
    protected static ?int $navigationSort = 30;

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Attribute Definition & Classification')
                    ->description('Define standalone attribute presets, size options, and color palettes.')
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->label('Attribute Type')
                            ->options(ProductAttribute::$types)
                            ->default('size')
                            ->live()
                            ->required(),

                        Forms\Components\TextInput::make('name')
                            ->label('Attribute Name')
                            ->placeholder('e.g. M (Medium), 42 (Shoe Size), Midnight Black')
                            ->required()
                            ->maxLength(150),

                        Forms\Components\TextInput::make('code')
                            ->label('Code / SKU Identifier')
                            ->placeholder('e.g. SZ-M, SZ-42, CLR-BLK')
                            ->maxLength(50)
                            ->helperText('Auto-generated if left blank.'),

                        Forms\Components\Select::make('category_group')
                            ->label('Category / Grouping')
                            ->options(ProductAttribute::$categoryGroups)
                            ->searchable()
                            ->nullable(),
                    ])->columns(2),

                Section::make('Values, Palette & Sizing Notes')
                    ->description('Configure measurements, hex codes, or drape guidelines.')
                    ->schema([
                        Forms\Components\TextInput::make('value')
                            ->label('Value / Measurement / Hex')
                            ->placeholder('e.g. Bust: 36-38 in / 91-97 cm, #0A2E23, or 5.5m x 1.15m')
                            ->maxLength(255)
                            ->live(),

                        Forms\Components\ColorPicker::make('color_picker_helper')
                            ->label('Color Palette Swatch')
                            ->visible(fn($get) => $get('type') === 'color')
                            ->afterStateHydrated(function ($component, $state, $record) {
                                if ($record && $record->type === 'color' && !empty($record->value) && str_starts_with($record->value, '#')) {
                                    $component->state($record->value);
                                }
                            })
                            ->live()
                            ->afterStateUpdated(function ($state, $set) {
                                if (!empty($state)) {
                                    $set('value', $state);
                                }
                            }),

                        Forms\Components\Textarea::make('description')
                            ->label('Detailed Sizing Notes / Care Guide')
                            ->placeholder('e.g. Recommended for height 5\'2" - 5\'8". Standard 6-yard drape.')
                            ->rows(3)
                            ->columnSpanFull()
                            ->nullable(),
                    ])->columns(2),

                Section::make('Ordering & Display')
                    ->schema([
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Display Priority / Sort Order')
                            ->numeric()
                            ->default(0)
                            ->helperText('Lower values appear first in size selectors.'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active & Available for Product Configuration')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'size' => 'primary',
                        'shoe_size' => 'info',
                        'dimension' => 'gray',
                        'color' => 'warning',
                        'material' => 'success',
                        'fit' => 'danger',
                        'style' => 'secondary',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => ProductAttribute::$types[$state] ?? ucfirst($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Attribute / Size Name')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->badge()
                    ->fontFamily('mono')
                    ->copyable()
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('value')
                    ->label('Specification / Measurement')
                    ->searchable()
                    ->limit(40)
                    ->placeholder('—'),

                Tables\Columns\ColorColumn::make('color_hex')
                    ->label('Swatch')
                    ->getStateUsing(fn($record) => ($record->type === 'color' && !empty($record->value) && str_starts_with($record->value, '#')) ? $record->value : null),

                Tables\Columns\TextColumn::make('category_group')
                    ->label('Group')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Sort')
                    ->sortable(),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Filter by Type')
                    ->options(ProductAttribute::$types),

                Tables\Filters\SelectFilter::make('category_group')
                    ->label('Filter by Group')
                    ->options(ProductAttribute::$categoryGroups),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->toolbarActions([
                \Filament\Actions\CreateAction::make()->label('+ Add Attribute / Size'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAttributes::route('/'),
            'create' => Pages\CreateAttribute::route('/create'),
            'edit' => Pages\EditAttribute::route('/{record}/edit'),
        ];
    }
}
