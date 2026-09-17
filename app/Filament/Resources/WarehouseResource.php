<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WarehouseResource\Pages;
use App\Models\Inventory\Warehouse;
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

class WarehouseResource extends Resource
{
    protected static ?string $model = Warehouse::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-building-office-2';
    protected static string | \UnitEnum | null $navigationGroup = 'Inventory';
    protected static ?string $navigationLabel = 'Warehouses';
    protected static ?int $navigationSort = 70;

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Warehouse / Facility Identity')
                    ->schema([
                        TextInput::make('code')
                            ->label('Location Code')
                            ->placeholder('e.g. WH-KTM-MAIN, SHOWROOM-THAMEL')
                            ->required()
                            ->maxLength(30)
                            ->unique(ignoreRecord: true),

                        TextInput::make('name')
                            ->label('Location Name')
                            ->placeholder('e.g. Laijau Central Fulfillment Hub')
                            ->required()
                            ->maxLength(255),

                        Select::make('type')
                            ->label('Location Type')
                            ->options([
                                'warehouse' => 'Main Warehouse / Distribution Center',
                                'showroom_pos' => 'Showroom / Retail Store (POS)',
                                'regional_hub' => 'Regional Distribution Hub',
                                'transit' => 'In-Transit / Logistics Hub',
                                'virtual' => 'Virtual / Dropship Location',
                            ])
                            ->required()
                            ->default('warehouse'),

                        TextInput::make('manager_name')
                            ->label('Facility Manager')
                            ->maxLength(100),
                    ])->columns(2),

                Section::make('Address & Contact')
                    ->schema([
                        TextInput::make('address')
                            ->label('Street Address'),

                        TextInput::make('postal_code')
                            ->label('Postal Code'),

                        TextInput::make('city')
                            ->label('City'),

                        TextInput::make('country')
                            ->label('Country Code (ISO 2)')
                            ->default('NP')
                            ->maxLength(2)
                            ->required(),

                        TextInput::make('contact_email')
                            ->email(),

                        TextInput::make('contact_phone')
                            ->tel(),
                    ])->columns(3),

                Section::make('Configuration & Status')
                    ->schema([
                        Toggle::make('is_default')
                            ->label('Default Fulfillment Warehouse')
                            ->helperText('Used for online e-commerce order routing by default.'),

                        Toggle::make('allow_sales')
                            ->label('Allow Sales & Outward Shipments')
                            ->default(true),

                        Toggle::make('is_active')
                            ->label('Active Facility')
                            ->default(true),

                        Textarea::make('notes')
                            ->label('Operational Notes')
                            ->columnSpanFull(),
                    ])->columns(3),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->withCount(['stockLevels as unique_lines_count' => function ($query) {
                $query->where('quantity_on_hand', '>', 0);
            }])
            ->withSum('stockLevels as total_units_on_hand', 'quantity_on_hand');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name', 'asc')
            ->emptyStateHeading('No Warehouses Found')
            ->emptyStateDescription('Add your central warehouses, retail showrooms, or production workshops to track multi-location physical inventory.')
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Warehouse Name')
                    ->description(fn(Warehouse $record) => $record->manager_name ? "Manager: {$record->manager_name}" : null)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn($state) => ucfirst(str_replace('_', ' ', $state)))
                    ->color(fn($state) => match ($state) {
                        'warehouse' => 'primary',
                        'showroom_pos' => 'success',
                        'regional_hub' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('unique_lines_count')
                    ->label('Active SKUs')
                    ->alignRight()
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('total_units_on_hand')
                    ->label('Units On Hand')
                    ->alignRight()
                    ->formatStateUsing(fn($state) => number_format((int)$state))
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('city')
                    ->label('Location')
                    ->state(fn(Warehouse $record) => $record->city ? "{$record->city}, {$record->country}" : ($record->country ?: '—'))
                    ->searchable(),

                IconColumn::make('allow_sales')
                    ->label('Sales')
                    ->boolean(),

                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make()
                    ->before(function (\Filament\Actions\DeleteAction $action, Warehouse $record) {
                        $hasStock = $record->stockLevels()->where('quantity_on_hand', '>', 0)->exists();
                        if ($hasStock) {
                            \Filament\Notifications\Notification::make()
                                ->title('Deletion Prohibited')
                                ->body("Facility '{$record->name}' contains active stock on hand. Deactivate the warehouse instead to preserve historical integrity.")
                                ->danger()
                                ->send();
                            $action->cancel();
                        }
                        if ($record->is_default) {
                            \Filament\Notifications\Notification::make()
                                ->title('Deletion Prohibited')
                                ->body("Cannot delete the default fulfillment warehouse.")
                                ->danger()
                                ->send();
                            $action->cancel();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWarehouses::route('/'),
            'create' => Pages\CreateWarehouse::route('/create'),
            'view' => Pages\ViewWarehouse::route('/{record}'),
            'edit' => Pages\EditWarehouse::route('/{record}/edit'),
        ];
    }
}
