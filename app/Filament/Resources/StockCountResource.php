<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockCountResource\Pages;
use App\Models\Inventory\StockCount;
use App\Models\Inventory\StockCountItem;
use App\Models\Inventory\Warehouse;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Inventory\InventoryService;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
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

class StockCountResource extends Resource
{
    protected static ?string $model = StockCount::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-check-badge';
    protected static string | \UnitEnum | null $navigationGroup = 'Inventory';
    protected static ?string $navigationLabel = 'Stock Counts';
    protected static ?int $navigationSort = 40;

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Audit / Physical Count Details')
                    ->schema([
                        TextInput::make('count_number')
                            ->label('Count Audit Reference')
                            ->default(fn() => StockCount::generateNextCountNumber())
                            ->disabled()
                            ->dehydrated(),

                        Select::make('warehouse_id')
                            ->label('Warehouse Audited')
                            ->relationship('warehouse', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),

                        DatePicker::make('count_date')
                            ->label('Audit Date')
                            ->default(now()->toDateString())
                            ->required(),

                        Select::make('status')
                            ->label('Audit Status')
                            ->options([
                                'draft' => 'Draft (Preparing)',
                                'in_progress' => 'In Progress (Counting)',
                                'completed' => 'Completed (Pending Reconcile)',
                                'reconciled' => 'Reconciled & Ledger Synced',
                            ])
                            ->default('draft')
                            ->required(),
                    ])->columns(4),

                Section::make('Physical Count Line Items')
                    ->schema([
                        Repeater::make('items')
                            ->relationship('items')
                            ->schema([
                                Select::make('product_id')
                                    ->label('Product')
                                    ->searchable()
                                    ->getSearchResultsUsing(
                                        fn(string $search): array => Product::where('name', 'like', "%{$search}%")
                                            ->orWhere('sku', 'like', "%{$search}%")
                                            ->orWhere('barcode', 'like', "%{$search}%")
                                            ->limit(50)
                                            ->pluck('name', 'id')
                                            ->toArray()
                                    )
                                    ->getOptionLabelUsing(fn($value): ?string => Product::find($value)?->name)
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        if ($state) {
                                            $cost = app(InventoryService::class)->resolveProductUnitCostNpr($state);
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
                                            ->mapWithKeys(fn($v) => [$v->id => "{$v->sku}" . ($v->color || $v->size ? " ({$v->color}/{$v->size})" : "")]);
                                    })
                                    ->searchable()
                                    ->nullable(),

                                TextInput::make('expected_quantity')
                                    ->label('System Expected')
                                    ->numeric()
                                    ->default(0)
                                    ->required(),

                                TextInput::make('counted_quantity')
                                    ->label('Physical Counted')
                                    ->numeric()
                                    ->default(0)
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                        $expected = (int)$get('expected_quantity');
                                        $variance = (int)$state - $expected;
                                        $unitCost = (float)$get('unit_cost_npr');
                                        $set('variance_quantity', $variance);
                                        $set('variance_value_npr', round($variance * $unitCost, 2));
                                    }),

                                TextInput::make('variance_quantity')
                                    ->label('Variance (+ / -)')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(),

                                TextInput::make('unit_cost_npr')
                                    ->label('Unit Cost (Rs.)')
                                    ->numeric()
                                    ->prefix('Rs.')
                                    ->required(),

                                TextInput::make('variance_value_npr')
                                    ->label('Variance Value (Rs.)')
                                    ->numeric()
                                    ->prefix('Rs.')
                                    ->disabled()
                                    ->dehydrated(),
                            ])
                            ->columns(4)
                            ->columnSpanFull(),
                    ]),

                Section::make('Audit Notes')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Observations & Discrepancy Findings')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->with(['warehouse', 'items.product', 'items.variant']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('No Stock Counts Found')
            ->emptyStateDescription('Initiate a physical stock count audit to verify warehouse balances and synchronize accounting ledgers.')
            ->columns([
                TextColumn::make('count_number')
                    ->label('Count #')
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->badge()
                    ->color('gray')
                    ->searchable(),

                TextColumn::make('count_date')
                    ->label('Date')
                    ->date('M d, Y')
                    ->sortable(),

                TextColumn::make('total_expected_items')
                    ->label('Expected')
                    ->alignRight()
                    ->sortable(),

                TextColumn::make('total_counted_items')
                    ->label('Counted')
                    ->alignRight()
                    ->sortable(),

                TextColumn::make('total_variance_items')
                    ->label('Variance')
                    ->alignRight()
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->color(fn(int $state): string => match (true) {
                        $state === 0 => 'success',
                        $state > 0 => 'info',
                        default => 'danger',
                    })
                    ->formatStateUsing(fn(int $state) => $state > 0 ? "+{$state}" : (string)$state)
                    ->sortable(),

                TextColumn::make('total_variance_value_npr')
                    ->label('Variance Value')
                    ->alignRight()
                    ->formatStateUsing(fn($state) => 'Rs. ' . number_format((float)$state, 2))
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'reconciled' => 'success',
                        'completed' => 'warning',
                        'in_progress' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn($state) => ucfirst(str_replace('_', ' ', $state))),
            ])
            ->filters([
                SelectFilter::make('warehouse_id')
                    ->label('Warehouse')
                    ->relationship('warehouse', 'name'),

                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'reconciled' => 'Reconciled',
                    ]),
            ])
            ->actions([
                \Filament\Actions\Action::make('populate_items')
                    ->label('Populate')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn(StockCount $record) => in_array($record->status, ['draft', 'in_progress']) && $record->items()->count() === 0)
                    ->requiresConfirmation()
                    ->modalHeading(fn(StockCount $record) => "Snapshot {$record->warehouse?->name} Stock")
                    ->modalDescription('Pre-fill this audit with all current on-hand inventory balances from this warehouse.')
                    ->action(function (StockCount $record): void {
                        $stockLevels = \App\Models\Inventory\StockLevel::where('warehouse_id', $record->warehouse_id)
                            ->where('quantity_on_hand', '>', 0)
                            ->with(['product', 'variant'])
                            ->get();

                        foreach ($stockLevels as $level) {
                            $record->items()->create([
                                'product_id' => $level->product_id,
                                'variant_id' => $level->variant_id,
                                'expected_quantity' => $level->quantity_on_hand,
                                'counted_quantity' => $level->quantity_on_hand,
                                'variance_quantity' => 0,
                                'unit_cost_npr' => (float)$level->unit_cost_npr,
                                'variance_value_npr' => 0.00,
                                'is_reconciled' => false,
                            ]);
                        }
                        $record->recalculateTotals();

                        Notification::make()
                            ->title('Warehouse Stock Populated')
                            ->body("Loaded {$stockLevels->count()} stock lines into count #{$record->count_number}.")
                            ->success()
                            ->send();
                    }),

                \Filament\Actions\Action::make('reconcile')
                    ->label('Reconcile')
                    ->icon('heroicon-m-check-badge')
                    ->color('success')
                    ->visible(fn(StockCount $record) => in_array($record->status, ['completed', 'draft', 'in_progress']))
                    ->requiresConfirmation()
                    ->modalHeading(fn(StockCount $record) => "Reconcile Count: #{$record->count_number}")
                    ->modalDescription(fn(StockCount $record) => "This will adjust physical stock balances to match counted quantities and auto-post variance journal entries to accounting.")
                    ->action(function (StockCount $record): void {
                        try {
                            app(InventoryService::class)->reconcileStockCount($record, auth()->user());

                            Notification::make()
                                ->title('Count Reconciled')
                                ->body("Count #{$record->count_number} variances reconciled and stock balances updated.")
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Reconciliation Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockCounts::route('/'),
            'create' => Pages\CreateStockCount::route('/create'),
            'view' => Pages\ViewStockCount::route('/{record}'),
            'edit' => Pages\EditStockCount::route('/{record}/edit'),
        ];
    }
}
