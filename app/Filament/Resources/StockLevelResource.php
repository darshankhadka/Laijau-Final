<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockLevelResource\Pages;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\Warehouse;
use App\Models\Product;
use App\Services\Inventory\InventoryService;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StockLevelResource extends Resource
{
    protected static ?string $model = StockLevel::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-archive-box';
    protected static string | \UnitEnum | null $navigationGroup = 'Inventory';
    protected static ?string $navigationLabel = 'Stock Overview';
    protected static ?int $navigationSort = 10;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['warehouse', 'product.categories', 'variant']);
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Stock Location & Product Identification')
                    ->schema([
                        Select::make('warehouse_id')
                            ->label('Warehouse / Location')
                            ->relationship('warehouse', 'name')
                            ->required()
                            ->disabled(fn($record) => $record !== null),

                        Select::make('product_id')
                            ->label('Product')
                            ->relationship('product', 'name')
                            ->required()
                            ->disabled(fn($record) => $record !== null),

                        Select::make('variant_id')
                            ->label('Variant (SKU)')
                            ->relationship('variant', 'sku')
                            ->nullable()
                            ->disabled(fn($record) => $record !== null),

                        TextInput::make('bin_location')
                            ->label('Bin / Shelf Location')
                            ->placeholder('e.g. Aisle 2 - Shelf B4')
                            ->maxLength(50),
                    ])->columns(2),

                Section::make('Quantities & Reserves')
                    ->schema([
                        TextInput::make('quantity_on_hand')
                            ->label('Physical Quantity On Hand')
                            ->numeric()
                            ->disabled()
                            ->helperText('Physical balance must be modified through audited stock movements or adjustments.'),

                        TextInput::make('quantity_reserved')
                            ->label('Quantity Reserved (Orders / Holds)')
                            ->numeric()
                            ->disabled(),

                        TextInput::make('quantity_incoming')
                            ->label('Incoming Quantity (Open POs)')
                            ->numeric()
                            ->disabled(),

                        TextInput::make('damaged_quantity')
                            ->label('Damaged / Defective Stock')
                            ->numeric()
                            ->default(0)
                            ->disabled(),

                        TextInput::make('quarantined_quantity')
                            ->label('Quarantined / QC Hold')
                            ->numeric()
                            ->default(0)
                            ->disabled(),

                        TextInput::make('unit_cost_npr')
                            ->label('Unit Landed Cost (Rs.)')
                            ->numeric()
                            ->prefix('Rs.')
                            ->required()
                            ->visible(fn() => auth()->user()?->hasRole(['super_admin', 'super-admin', 'Store Manager', 'Warehouse Manager']) ?? false),
                    ])->columns(3),

                Section::make('Safety Thresholds & Reorder Automation')
                    ->schema([
                        TextInput::make('reorder_point')
                            ->label('Reorder Point Threshold')
                            ->numeric()
                            ->default(3)
                            ->required(),

                        TextInput::make('reorder_quantity')
                            ->label('Standard Reorder Batch')
                            ->numeric()
                            ->default(10)
                            ->required(),

                        TextInput::make('safety_stock')
                            ->label('Safety Buffer Stock')
                            ->numeric()
                            ->default(2),

                        TextInput::make('maximum_stock')
                            ->label('Maximum Stock Cap')
                            ->numeric()
                            ->default(100),
                    ])->columns(4),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('No Stock Levels Found')
            ->emptyStateDescription('Stock levels will automatically initialize when goods are received, adjusted, or catalog products are synchronized.')
            ->columns([
                ImageColumn::make('product.featured_image')
                    ->label('')
                    ->disk('public')
                    ->square()
                    ->size(40)
                    ->defaultImageUrl(fn() => 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="%23cbd5e1" stroke-width="1.5"><rect width="18" height="18" x="3" y="3" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>'),

                TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn(StockLevel $record) => $record->product?->category?->name ?? $record->product?->categories?->first()?->name),

                TextColumn::make('variant_desc')
                    ->label('Variant / Spec')
                    ->state(function (StockLevel $record) {
                        if ($record->variant) {
                            $parts = array_filter([$record->variant->color, $record->variant->size]);
                            return !empty($parts) ? implode(' / ', $parts) : ($record->variant->sku ?: 'Variant');
                        }
                        return 'Standard';
                    })
                    ->badge()
                    ->color('gray'),

                TextColumn::make('sku')
                    ->label('SKU')
                    ->fontFamily('mono')
                    ->state(fn(StockLevel $record) => $record->variant?->sku ?: ($record->product?->sku ?: '—'))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $q) use ($search) {
                            $q->whereHas('variant', fn(Builder $v) => $v->where('sku', 'like', "%{$search}%"))
                                ->orWhereHas('product', fn(Builder $p) => $p->where('sku', 'like', "%{$search}%"));
                        });
                    })
                    ->sortable(),

                TextColumn::make('barcode')
                    ->label('Barcode')
                    ->fontFamily('mono')
                    ->state(fn(StockLevel $record) => $record->variant?->barcode ?: ($record->product?->barcode ?: '—'))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $q) use ($search) {
                            $q->whereHas('variant', fn(Builder $v) => $v->where('barcode', 'like', "%{$search}%"))
                                ->orWhereHas('product', fn(Builder $p) => $p->where('barcode', 'like', "%{$search}%"));
                        });
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('warehouse.code')
                    ->label('Warehouse')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'STORE-KTM-01' => 'warning',
                        'STORE-KTM-02' => 'info',
                        default => 'primary',
                    })
                    ->sortable()
                    ->searchable(),

                TextColumn::make('quantity_on_hand')
                    ->label('On Hand')
                    ->alignRight()
                    ->badge()
                    ->color(fn(int $state, StockLevel $record): string => match (true) {
                        $state <= 0 => 'danger',
                        $state <= $record->reorder_point => 'warning',
                        default => 'success',
                    })
                    ->sortable(),

                TextColumn::make('quantity_reserved')
                    ->label('Reserved')
                    ->alignRight()
                    ->badge()
                    ->color(fn(int $state): string => $state > 0 ? 'warning' : 'gray')
                    ->sortable(),

                TextColumn::make('available')
                    ->label('Available')
                    ->state(fn(StockLevel $record) => $record->available_quantity)
                    ->alignRight()
                    ->badge()
                    ->color(fn(int $state): string => match (true) {
                        $state <= 0 => 'danger',
                        $state <= 3 => 'warning',
                        default => 'success',
                    })
                    ->weight('bold')
                    ->tooltip('Available = On Hand - Reserved'),

                TextColumn::make('quantity_incoming')
                    ->label('Incoming')
                    ->alignRight()
                    ->badge()
                    ->color(fn(int $state): string => $state > 0 ? 'info' : 'gray')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('damaged_quantity')
                    ->label('Damaged')
                    ->alignRight()
                    ->badge()
                    ->color(fn(int $state): string => $state > 0 ? 'danger' : 'gray')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('stock_status')
                    ->label('Status')
                    ->badge()
                    ->state(function (StockLevel $record) {
                        if ($record->quantity_on_hand <= 0) {
                            return 'Out of Stock';
                        }
                        if ($record->isLowStock()) {
                            return 'Low Stock';
                        }
                        if ($record->isOverStock()) {
                            return 'Overstocked';
                        }
                        return 'In Stock';
                    })
                    ->color(fn($state) => match ($state) {
                        'Out of Stock' => 'danger',
                        'Low Stock' => 'warning',
                        'Overstocked' => 'info',
                        default => 'success',
                    }),

                TextColumn::make('reorder_point')
                    ->label('Reorder Pt')
                    ->alignRight()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('bin_location')
                    ->label('Bin')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('unit_cost_npr')
                    ->label('Landed Cost')
                    ->alignRight()
                    ->formatStateUsing(fn($state) => 'Rs. ' . number_format((float)$state, 2))
                    ->visible(fn() => auth()->user()?->hasRole(['super_admin', 'super-admin', 'Store Manager', 'Warehouse Manager']) ?? false)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime('M d, Y H:i')
                    ->timezone('Asia/Kathmandu')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('warehouse_id')
                    ->label('Warehouse')
                    ->relationship('warehouse', 'name')
                    ->preload(),

                SelectFilter::make('stock_status')
                    ->label('Stock Status')
                    ->options([
                        'in_stock' => 'In Stock (Healthy)',
                        'low_stock' => 'Low Stock (≤ Reorder Point)',
                        'out_of_stock' => 'Out of Stock (0)',
                        'overstocked' => 'Overstocked (Exceeds Max)',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'in_stock' => $query->whereRaw('quantity_on_hand > reorder_point'),
                            'low_stock' => $query->whereRaw('quantity_on_hand <= reorder_point AND quantity_on_hand > 0'),
                            'out_of_stock' => $query->where('quantity_on_hand', '<=', 0),
                            'overstocked' => $query->whereRaw('maximum_stock > 0 AND quantity_on_hand > maximum_stock'),
                            default => $query,
                        };
                    }),

                SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship('product.categories', 'name')
                    ->preload(),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make(),

                \Filament\Actions\Action::make('quick_adjust')
                    ->label('Adjust')
                    ->icon('heroicon-m-arrows-up-down')
                    ->color('warning')
                    ->modalHeading(fn(StockLevel $record) => "Audited Stock Adjustment: {$record->product?->name}")
                    ->modalDescription(fn(StockLevel $record) => "Current balance: {$record->quantity_on_hand} pcs at {$record->warehouse?->name}")
                    ->form([
                        Select::make('movement_type')
                            ->label('Adjustment Type')
                            ->options([
                                'correction' => 'Correction (Inventory Count Difference)',
                                'damage' => 'Damage (Defective / Damaged Item)',
                                'loss' => 'Loss / Shrinkage',
                                'adjustment_gain' => 'Found Stock (Physical Surplus)',
                            ])
                            ->required()
                            ->default('correction'),

                        TextInput::make('delta_quantity')
                            ->label('Quantity Delta (+ / -)')
                            ->numeric()
                            ->required()
                            ->helperText('Use positive number to increase stock, negative number to decrease.'),

                        TextInput::make('reason')
                            ->label('Reason for adjustment')
                            ->required()
                            ->placeholder('e.g. Spot count reconciliation or damaged during transit'),
                    ])
                    ->action(function (StockLevel $record, array $data): void {
                        $delta = (int)$data['delta_quantity'];
                        if ($delta === 0) {
                            return;
                        }

                        app(InventoryService::class)->recordStockMovement([
                            'warehouse_id' => $record->warehouse_id,
                            'product_id' => $record->product_id,
                            'variant_id' => $record->variant_id,
                            'movement_type' => $data['movement_type'],
                            'quantity' => $delta,
                            'unit_cost_npr' => (float)$record->unit_cost_npr,
                            'reference_type' => 'manual_adjustment',
                            'reason' => $data['reason'],
                        ], auth()->user());

                        Notification::make()
                            ->title('Stock Adjusted')
                            ->body("Updated stock for {$record->product?->name}. New balance: {$record->fresh()->quantity_on_hand} pcs.")
                            ->success()
                            ->send();
                    }),

                \Filament\Actions\EditAction::make(),
            ])
            ->emptyStateHeading('No Stock Records Found')
            ->emptyStateDescription('No inventory records match the current warehouse or status filter.')
            ->emptyStateIcon('heroicon-o-cube');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockLevels::route('/'),
            'view' => Pages\ViewStockLevel::route('/{record}'),
            'edit' => Pages\EditStockLevel::route('/{record}/edit'),
        ];
    }
}
