<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockMovementResource\Pages;
use App\Models\Inventory\StockMovement;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static bool $shouldRegisterNavigation = false;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-arrows-right-left';
    protected static string | \UnitEnum | null $navigationGroup = 'Inventory';
    protected static ?string $navigationLabel = 'Stock Movements';
    protected static ?int $navigationSort = 30;

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Stock Ledger Transaction')
                    ->schema([
                        TextInput::make('movement_number')
                            ->label('Movement Number')
                            ->disabled(),

                        Select::make('movement_type')
                            ->label('Movement Classification')
                            ->options([
                                'opening_stock' => 'Opening Stock',
                                'purchase_receive' => 'Purchase Order Receiving',
                                'sale_order' => 'E-Commerce Order Fulfillment',
                                'sale_pos' => 'POS / Showroom Sale',
                                'return_customer' => 'Customer Return',
                                'return_supplier' => 'Supplier Return',
                                'transfer_out' => 'Transfer Outbound',
                                'transfer_in' => 'Transfer Inbound',
                                'adjustment_gain' => 'Adjustment Gain (Found)',
                                'adjustment_loss' => 'Adjustment Loss',
                                'damage' => 'Damaged Inventory',
                                'loss' => 'Loss / Shrinkage',
                                'correction' => 'Count Correction',
                                'count_reconciliation' => 'Stock Count Reconciliation',
                            ])
                            ->disabled(),

                        Select::make('warehouse_id')
                            ->relationship('warehouse', 'name')
                            ->disabled(),

                        Select::make('product_id')
                            ->relationship('product', 'name')
                            ->disabled(),

                        Select::make('variant_id')
                            ->relationship('variant', 'sku')
                            ->disabled(),

                        TextInput::make('quantity')
                            ->label('Quantity Delta')
                            ->disabled(),

                        TextInput::make('quantity_before')
                            ->label('Balance Before')
                            ->disabled(),

                        TextInput::make('quantity_after')
                            ->label('Balance After')
                            ->disabled(),

                        TextInput::make('unit_cost_npr')
                            ->label('Unit Cost (Rs.)')
                            ->disabled(),

                        TextInput::make('total_cost_npr')
                            ->label('Total Value Delta (Rs.)')
                            ->disabled(),

                        TextInput::make('reference_number')
                            ->label('Reference Doc #')
                            ->disabled(),

                        TextInput::make('reason')
                            ->label('Reason & Purpose')
                            ->disabled()
                            ->columnSpanFull(),

                        Textarea::make('notes')
                            ->disabled()
                            ->columnSpanFull(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('No Stock Movements Recorded')
            ->emptyStateDescription('Every stock mutation across E-Commerce, POS, POs, Transfers, and Adjustments is automatically recorded here.')
            ->columns([
                TextColumn::make('movement_number')
                    ->label('Movement #')
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Timestamp')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),

                TextColumn::make('warehouse.code')
                    ->label('Warehouse')
                    ->badge()
                    ->color('primary')
                    ->searchable(),

                TextColumn::make('movement_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'purchase_receive', 'adjustment_gain', 'return_customer', 'transfer_in', 'opening_stock' => 'success',
                        'sale_order', 'sale_pos', 'transfer_out' => 'info',
                        'damage', 'loss', 'adjustment_loss' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn($state) => ucfirst(str_replace('_', ' ', $state)))
                    ->searchable(),

                TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable()
                    ->description(function (StockMovement $record) {
                        if ($record->variant) {
                            return "SKU: {$record->variant->sku} ({$record->variant->color}/{$record->variant->size})";
                        }
                        return null;
                    }),

                TextColumn::make('quantity')
                    ->label('Delta')
                    ->alignRight()
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->color(fn(int $state): string => $state > 0 ? 'success' : 'danger')
                    ->formatStateUsing(fn(int $state) => $state > 0 ? "+{$state}" : (string)$state)
                    ->sortable(),

                TextColumn::make('quantity_after')
                    ->label('Balance')
                    ->alignRight()
                    ->fontFamily('mono')
                    ->weight('medium')
                    ->sortable(),

                TextColumn::make('unit_cost_npr')
                    ->label('Unit Cost')
                    ->alignRight()
                    ->formatStateUsing(fn($state) => 'Rs. ' . number_format((float)$state, 2))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('total_cost_npr')
                    ->label('Total (Rs.)')
                    ->alignRight()
                    ->formatStateUsing(fn($state) => 'Rs. ' . number_format((float)$state, 2))
                    ->sortable(),

                TextColumn::make('reference_number')
                    ->label('Reference')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('reason')
                    ->label('Reason')
                    ->searchable()
                    ->limit(30),

                TextColumn::make('user.name')
                    ->label('User')
                    ->placeholder('System')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('warehouse_id')
                    ->label('Warehouse')
                    ->relationship('warehouse', 'name'),

                SelectFilter::make('movement_type')
                    ->label('Movement Type')
                    ->options([
                        'opening_stock' => 'Opening Stock',
                        'purchase_receive' => 'Purchase Receive',
                        'sale_order' => 'Sale Order',
                        'sale_pos' => 'POS Sale',
                        'return_customer' => 'Customer Return',
                        'transfer_out' => 'Transfer Out',
                        'transfer_in' => 'Transfer In',
                        'adjustment_gain' => 'Adjustment Gain',
                        'adjustment_loss' => 'Adjustment Loss',
                        'damage' => 'Damage',
                        'loss' => 'Loss / Shrinkage',
                        'count_reconciliation' => 'Count Reconciliation',
                    ]),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make(),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with(['product', 'variant', 'warehouse', 'user']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockMovements::route('/'),
            'view' => Pages\ViewStockMovement::route('/{record}'),
        ];
    }
}
