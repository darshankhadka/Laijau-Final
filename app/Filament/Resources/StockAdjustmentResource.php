<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockAdjustmentResource\Pages;
use App\Models\Inventory\StockAdjustment;
use App\Models\Inventory\Warehouse;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Inventory\InventoryService;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StockAdjustmentResource extends Resource
{
    protected static ?string $model = StockAdjustment::class;

    protected static bool $shouldRegisterNavigation = false;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-scale';
    protected static string | \UnitEnum | null $navigationGroup = 'Inventory';
    protected static ?string $navigationLabel = 'Adjustments';
    protected static ?int $navigationSort = 90;

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Adjustment Identification')
                    ->schema([
                        TextInput::make('adjustment_number')
                            ->label('Adjustment Number')
                            ->default(fn() => StockAdjustment::generateNextAdjustmentNumber())
                            ->disabled()
                            ->dehydrated(),

                        Select::make('warehouse_id')
                            ->label('Warehouse Location')
                            ->relationship('warehouse', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),

                        Select::make('product_id')
                            ->label('Product')
                            ->relationship('product', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $cost = app(InventoryService::class)->resolveProductUnitCostnpr($state);
                                    $set('unit_cost_npr', $cost);
                                }
                            }),

                        Select::make('variant_id')
                            ->label('Variant (SKU)')
                            ->options(function (callable $get) {
                                $productId = $get('product_id');
                                if (!$productId) {
                                    return [];
                                }
                                return ProductVariant::where('product_id', $productId)
                                    ->get()
                                    ->mapWithKeys(fn($v) => [$v->id => "{$v->sku} ({$v->color}/{$v->size})"]);
                            })
                            ->searchable()
                            ->nullable()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                $productId = $get('product_id');
                                if ($productId) {
                                    $cost = app(InventoryService::class)->resolveProductUnitCostnpr($productId, $state);
                                    $set('unit_cost_npr', $cost);
                                }
                            }),

                        Select::make('type')
                            ->label('Adjustment Reason Type')
                            ->options([
                                'damage' => 'Damaged Inventory (Write-off)',
                                'loss' => 'Shrinkage / Theft / Unaccounted Loss',
                                'found' => 'Found Stock (Surplus Gain)',
                                'correction' => 'Count Correction',
                            ])
                            ->required()
                            ->default('correction'),
                    ])->columns(3),

                Section::make('Quantities & Financial Value')
                    ->schema([
                        TextInput::make('quantity')
                            ->label('Delta Quantity (+ / -)')
                            ->numeric()
                            ->required()
                            ->helperText('Positive to add stock, negative to reduce.')
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                $unitCost = (float)$get('unit_cost_npr');
                                $set('total_value_npr', round(abs((int)$state) * $unitCost, 2));
                            }),

                        TextInput::make('unit_cost_npr')
                            ->label('Unit Landed Cost (Rs.)')
                            ->numeric()
                            ->prefix('Rs.')
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                $qty = (int)$get('quantity');
                                $set('total_value_npr', round(abs($qty) * (float)$state, 2));
                            }),

                        TextInput::make('total_value_npr')
                            ->label('Total Value (Rs.)')
                            ->numeric()
                            ->prefix('Rs.')
                            ->disabled()
                            ->dehydrated(),

                        TextInput::make('reason')
                            ->label('Detailed Audit Reason')
                            ->required()
                            ->placeholder('e.g. Water damage during warehouse roof inspection'),

                        Select::make('status')
                            ->label('Approval Status')
                            ->options([
                                'pending' => 'Pending Approval',
                                'approved' => 'Approved & Applied',
                                'rejected' => 'Rejected',
                            ])
                            ->default('approved')
                            ->required(),
                    ])->columns(3),

                Section::make('Notes')
                    ->schema([
                        Textarea::make('notes')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('No Stock Adjustments Found')
            ->emptyStateDescription('Create a new stock adjustment for write-offs, found inventory, shrinkage, or spot count corrections.')
            ->columns([
                TextColumn::make('adjustment_number')
                    ->label('Adjustment #')
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('warehouse.code')
                    ->label('Location')
                    ->badge()
                    ->color('primary')
                    ->searchable(),

                TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'damage', 'loss' => 'danger',
                        'found' => 'success',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn($state) => ucfirst($state)),

                TextColumn::make('quantity')
                    ->label('Delta')
                    ->alignRight()
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->color(fn(int $state): string => $state > 0 ? 'success' : 'danger')
                    ->formatStateUsing(fn(int $state) => $state > 0 ? "+{$state}" : (string)$state)
                    ->sortable(),

                TextColumn::make('total_value_npr')
                    ->label('Value (Rs.)')
                    ->alignRight()
                    ->formatStateUsing(fn($state) => 'Rs. ' . number_format((float)$state, 2))
                    ->sortable(),

                TextColumn::make('reason')
                    ->label('Reason')
                    ->searchable()
                    ->limit(25),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn($state) => ucfirst($state)),
            ])
            ->filters([
                SelectFilter::make('warehouse_id')
                    ->label('Warehouse')
                    ->relationship('warehouse', 'name'),

                SelectFilter::make('type')
                    ->options([
                        'damage' => 'Damage',
                        'loss' => 'Loss',
                        'found' => 'Found',
                        'correction' => 'Correction',
                    ]),

                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->actions([
                \Filament\Actions\Action::make('approve')
                    ->label('Apply Adjustment')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->visible(fn(StockAdjustment $record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading(fn(StockAdjustment $record) => "Apply Adjustment: #{$record->adjustment_number}")
                    ->modalDescription(fn(StockAdjustment $record) => "This will update physical inventory for {$record->product?->name} and post the loss/gain journal entry to accounting.")
                    ->action(function (StockAdjustment $record): void {
                        app(InventoryService::class)->applyAdjustment($record);

                        Notification::make()
                            ->title('Adjustment Applied')
                            ->body("Adjustment #{$record->adjustment_number} approved, stock updated and accounting journal posted.")
                            ->success()
                            ->send();
                    }),

                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockAdjustments::route('/'),
            'create' => Pages\CreateStockAdjustment::route('/create'),
            'edit' => Pages\EditStockAdjustment::route('/{record}/edit'),
        ];
    }
}
