<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseOrderResource\Pages;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Inventory\PurchaseOrderItem;
use App\Models\Inventory\Supplier;
use App\Models\Inventory\Warehouse;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Inventory\InventoryService;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
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

class PurchaseOrderResource extends Resource
{
    protected static ?string $model = PurchaseOrder::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static string | \UnitEnum | null $navigationGroup = 'Inventory';
    protected static ?string $navigationLabel = 'Purchasing';
    protected static ?int $navigationSort = 50;

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Purchase Order Overview')
                    ->schema([
                        TextInput::make('po_number')
                            ->label('PO Number')
                            ->default(fn() => PurchaseOrder::generateNextPoNumber())
                            ->disabled()
                            ->dehydrated(),

                        Select::make('supplier_id')
                            ->label('Supplier / Artisan Guild')
                            ->relationship('supplier', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),

                        Select::make('warehouse_id')
                            ->label('Destination Warehouse')
                            ->relationship('warehouse', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->default(fn() => Warehouse::where('is_default', true)->first()?->id),

                        DatePicker::make('order_date')
                            ->label('Order Date')
                            ->default(now()->toDateString())
                            ->required(),

                        DatePicker::make('expected_delivery_date')
                            ->label('Expected Delivery Date'),

                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'draft' => 'Draft',
                                'ordered' => 'Ordered (Pending Shipment)',
                                'in_transit' => 'In Transit (Shipping)',
                                'partially_received' => 'Partially Received',
                                'received' => 'Fully Received & Closed',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('draft')
                            ->required(),
                    ])->columns(3),

                Section::make('Financials & Logistics Costing')
                    ->schema([
                        Select::make('currency')
                            ->label('Billing Currency')
                            ->options([
                                'NPR' => 'NPR (Nepalese Rupee)',
                            ])
                            ->default('NPR')
                            ->disabled()
                            ->dehydrated()
                            ->required(),

                        TextInput::make('exchange_rate_to_npr')
                            ->label('Exchange Rate to NPR')
                            ->numeric()
                            ->default(1.000000)
                            ->disabled()
                            ->dehydrated()
                            ->required(),

                        TextInput::make('shipping_cost_npr')
                            ->label('Inbound Freight / Logistics (Rs.)')
                            ->numeric()
                            ->prefix('Rs.')
                            ->default(0.00),

                        TextInput::make('customs_duty_npr')
                            ->label('Customs & Import Tariff (Rs.)')
                            ->numeric()
                            ->prefix('Rs.')
                            ->default(0.00),

                        TextInput::make('total_amount_npr')
                            ->label('Total Landed Amount (Rs.)')
                            ->numeric()
                            ->prefix('Rs.')
                            ->disabled()
                            ->dehydrated(),
                    ])->columns(3),

                Section::make('Purchase Order Line Items')
                    ->schema([
                        Repeater::make('items')
                            ->relationship('items')
                            ->schema([
                                Select::make('product_id')
                                    ->label('Product')
                                    ->searchable()
                                    ->getSearchResultsUsing(function (string $search): array {
                                        return Product::searchRetail($search)
                                            ->limit(40)
                                            ->get()
                                            ->mapWithKeys(function (Product $p) {
                                                $sku = $p->sku ? "[{$p->sku}] " : '';
                                                $cost = (float)($p->cost_price ?: 0);
                                                $costLabel = $cost > 0 ? ' — Cost: Rs. ' . number_format($cost, 2) : '';
                                                $stock = " (Stock: {$p->quantity} pcs)";
                                                return [$p->id => "{$sku}{$p->name}{$costLabel}{$stock}"];
                                            })
                                            ->toArray();
                                    })
                                    ->getOptionLabelUsing(function ($value): ?string {
                                        if (!$value) {
                                            return null;
                                        }
                                        $p = Product::find($value);
                                        if (!$p) {
                                            return null;
                                        }
                                        $sku = $p->sku ? "[{$p->sku}] " : '';
                                        return "{$sku}{$p->name}";
                                    })
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        if ($state) {
                                            $cost = app(InventoryService::class)->resolveProductUnitCostNpr($state);
                                            $set('unit_cost_npr', $cost);
                                            $set('unit_cost_currency', $cost);
                                        }
                                    }),

                                Select::make('variant_id')
                                    ->label('Variant (SKU & Spec)')
                                    ->options(function (callable $get) {
                                        $productId = $get('product_id');
                                        if (!$productId) {
                                            return [];
                                        }
                                        return ProductVariant::where('product_id', $productId)
                                            ->get()
                                            ->mapWithKeys(function (ProductVariant $v) {
                                                $spec = implode(' / ', array_filter([$v->color, $v->size])) ?: 'Standard';
                                                return [$v->id => "{$v->sku} ({$spec}) — Stock: {$v->stock_quantity} pcs"];
                                            });
                                    })
                                    ->searchable()
                                    ->nullable(),

                                TextInput::make('quantity_ordered')
                                    ->label('Qty Ordered')
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(1)
                                    ->live()
                                    ->required(),

                                TextInput::make('quantity_received')
                                    ->label('Qty Received')
                                    ->numeric()
                                    ->default(0)
                                    ->disabled(),

                                TextInput::make('unit_cost_currency')
                                    ->label('Unit Cost (Billing Currency)')
                                    ->numeric()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                        $rate = (float)($get('../../exchange_rate_to_npr') ?? 1.0);
                                        $set('unit_cost_npr', round((float)$state * $rate, 2));
                                    }),

                                TextInput::make('unit_cost_npr')
                                    ->label('Unit Cost (Rs.)')
                                    ->numeric()
                                    ->prefix('Rs.')
                                    ->live()
                                    ->required(),

                                Placeholder::make('line_total')
                                    ->label('Estimated Line Total (Rs.)')
                                    ->content(function (callable $get): string {
                                        $qty = (int)($get('quantity_ordered') ?: 1);
                                        $cost = (float)($get('unit_cost_npr') ?: 0);
                                        return 'Rs. ' . number_format($qty * $cost, 2);
                                    }),
                            ])
                            ->columns(4)
                            ->columnSpanFull(),
                    ]),

                Section::make('Notes')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Internal & Commercial Notes')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->with(['supplier', 'warehouse', 'items.product', 'items.variant']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('No Purchase Orders Found')
            ->emptyStateDescription('Create a new purchase order to initiate inbound procurement from artisans and international suppliers.')
            ->emptyStateIcon('heroicon-o-clipboard-document-list')
            ->columns([
                TextColumn::make('po_number')
                    ->label('PO #')
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('warehouse.code')
                    ->label('Dest WH')
                    ->badge()
                    ->color('primary')
                    ->searchable(),

                TextColumn::make('order_date')
                    ->label('Order Date')
                    ->date('M d, Y')
                    ->sortable(),

                TextColumn::make('expected_delivery_date')
                    ->label('Expected')
                    ->date('M d, Y')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('items_count')
                    ->label('Lines')
                    ->counts('items')
                    ->alignRight()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('total_amount_npr')
                    ->label('Landed Cost')
                    ->formatStateUsing(fn($state) => \App\Helpers\NepaliNumberHelper::formatCurrency((float)$state, 'Rs. ', 2))
                    ->alignRight()
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'received' => 'success',
                        'approved' => 'info',
                        'submitted' => 'warning',
                        'partially_received' => 'warning',
                        'ordered', 'in_transit' => 'info',
                        'rejected', 'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn($state) => ucfirst(str_replace('_', ' ', $state))),
            ])
            ->filters([
                SelectFilter::make('supplier_id')
                    ->label('Supplier')
                    ->relationship('supplier', 'name'),

                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'submitted' => 'Submitted (Pending Approval)',
                        'approved' => 'Approved',
                        'ordered' => 'Ordered',
                        'in_transit' => 'In Transit',
                        'partially_received' => 'Partially Received',
                        'received' => 'Fully Received',
                        'rejected' => 'Rejected',
                        'cancelled' => 'Cancelled',
                    ]),

                \Filament\Tables\Filters\Filter::make('order_date')
                    ->label('Order Date')
                    ->form([
                        Select::make('period')
                            ->label('Time Period')
                            ->options([
                                'all_time' => 'All Time',
                                'today' => 'Today',
                                'yesterday' => 'Yesterday',
                                'this_month' => 'This Month',
                                'last_month' => 'Last Month',
                                'jan_2026' => 'January 2026',
                                'feb_2026' => 'February 2026',
                                'mar_2026' => 'March 2026',
                                'apr_2026' => 'April 2026',
                                'may_2026' => 'May 2026',
                                'jun_2026' => 'June 2026',
                                'jul_2026' => 'July 2026',
                                'aug_2026' => 'August 2026',
                                'sep_2026' => 'September 2026',
                                'custom' => 'Custom Range',
                            ])
                            ->default('all_time'),
                        \Filament\Forms\Components\DatePicker::make('from_date')
                            ->label('From Date')
                            ->visible(fn($get) => $get('period') === 'custom'),
                        \Filament\Forms\Components\DatePicker::make('to_date')
                            ->label('To Date')
                            ->visible(fn($get) => $get('period') === 'custom'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['period'] ?? null) {
                            'today' => $query->whereDate('order_date', now()->toDateString()),
                            'yesterday' => $query->whereDate('order_date', now()->subDay()->toDateString()),
                            'this_month' => $query->whereBetween('order_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()]),
                            'last_month' => $query->whereBetween('order_date', [now()->subMonth()->startOfMonth()->toDateString(), now()->subMonth()->endOfMonth()->toDateString()]),
                            'jan_2026' => $query->whereBetween('order_date', ['2026-01-01', '2026-01-31']),
                            'feb_2026' => $query->whereBetween('order_date', ['2026-02-01', '2026-02-28']),
                            'mar_2026' => $query->whereBetween('order_date', ['2026-03-01', '2026-03-31']),
                            'apr_2026' => $query->whereBetween('order_date', ['2026-04-01', '2026-04-30']),
                            'may_2026' => $query->whereBetween('order_date', ['2026-05-01', '2026-05-31']),
                            'jun_2026' => $query->whereBetween('order_date', ['2026-06-01', '2026-06-30']),
                            'jul_2026' => $query->whereBetween('order_date', ['2026-07-01', '2026-07-31']),
                            'aug_2026' => $query->whereBetween('order_date', ['2026-08-01', '2026-08-31']),
                            'sep_2026' => $query->whereBetween('order_date', ['2026-09-01', '2026-09-30']),
                            'custom' => $query
                                ->when($data['from_date'] ?? null, fn($q, $d) => $q->whereDate('order_date', '>=', $d))
                                ->when($data['to_date'] ?? null, fn($q, $d) => $q->whereDate('order_date', '<=', $d)),
                            default => $query,
                        };
                    }),
            ])
            ->actions([
                \Filament\Actions\Action::make('submit_po')
                    ->label('Submit')
                    ->icon('heroicon-m-paper-airplane')
                    ->color('primary')
                    ->visible(fn(PurchaseOrder $record) => $record->status === 'draft')
                    ->requiresConfirmation()
                    ->action(function (PurchaseOrder $record): void {
                        try {
                            app(InventoryService::class)->submitPurchaseOrder($record, auth()->user());
                            $record->refresh();
                            Notification::make()
                                ->title($record->status === 'approved' ? 'PO Auto-Approved' : 'PO Submitted for Approval')
                                ->body("PO #{$record->po_number} is now {$record->status}.")
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Submission Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                \Filament\Actions\Action::make('approve_po')
                    ->label('Approve')
                    ->icon('heroicon-m-check-badge')
                    ->color('info')
                    ->visible(fn(PurchaseOrder $record) => in_array($record->status, ['draft', 'submitted']))
                    ->requiresConfirmation()
                    ->action(function (PurchaseOrder $record): void {
                        try {
                            app(InventoryService::class)->approvePurchaseOrder($record, auth()->user());
                            Notification::make()
                                ->title('Purchase Order Approved')
                                ->body("PO #{$record->po_number} approved for procurement.")
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Approval Denied')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                \Filament\Actions\Action::make('reject_po')
                    ->label('Reject')
                    ->icon('heroicon-m-x-mark')
                    ->color('danger')
                    ->visible(fn(PurchaseOrder $record) => in_array($record->status, ['draft', 'submitted']))
                    ->form([
                        TextInput::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->action(function (PurchaseOrder $record, array $data): void {
                        try {
                            app(InventoryService::class)->rejectPurchaseOrder($record, $data['rejection_reason'], auth()->user());
                            $record->refresh();
                            Notification::make()
                                ->title('Purchase Order Rejected')
                                ->body("PO #{$record->po_number} status is now {$record->status}.")
                                ->warning()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Rejection Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                \Filament\Actions\Action::make('receive_goods')
                    ->label('Receive Goods')
                    ->icon('heroicon-m-inbox-arrow-down')
                    ->color('success')
                    ->visible(fn(PurchaseOrder $record) => in_array($record->status, ['approved', 'ordered', 'in_transit', 'partially_received']))
                    ->modalHeading(fn(PurchaseOrder $record) => "Receive Goods: PO #{$record->po_number}")
                    ->modalDescription(fn(PurchaseOrder $record) => "Enter quantities physically verified at {$record->warehouse?->name}. This will update stock levels and ledger movements.")
                    ->form(function (PurchaseOrder $record): array {
                        $fields = [];
                        $items = $record->items()->with(['product', 'variant'])->get();
                        foreach ($items as $item) {
                            $name = $item->product?->name ?? 'Product';
                            $sku = $item->variant?->sku ?? $item->product?->sku ?? 'SKU';
                            $remaining = max(0, (int)$item->quantity_ordered - (int)$item->quantity_received);
                            $fields[] = TextInput::make("received_qty_{$item->id}")
                                ->label("{$name} ({$sku})")
                                ->helperText("Ordered: {$item->quantity_ordered} | Received so far: {$item->quantity_received} | Remaining: {$remaining}")
                                ->numeric()
                                ->minValue(0)
                                ->default($remaining)
                                ->required();
                        }
                        return $fields;
                    })
                    ->action(function (PurchaseOrder $record, array $data): void {
                        try {
                            $receivedMap = [];
                            foreach ($data as $key => $val) {
                                if (str_starts_with($key, 'received_qty_')) {
                                    $itemId = (int)str_replace('received_qty_', '', $key);
                                    $receivedMap[$itemId] = (int)$val;
                                }
                            }

                            app(InventoryService::class)->receivePurchaseOrder($record, $receivedMap, auth()->user());
                            $record->refresh();

                            Notification::make()
                                ->title('Goods Received')
                                ->body("PO #{$record->po_number} receiving processed. Current status: " . ucfirst(str_replace('_', ' ', $record->status)) . ".")
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Goods Receiving Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                \Filament\Actions\Action::make('cancel_po')
                    ->label('Cancel')
                    ->icon('heroicon-m-x-circle')
                    ->color('danger')
                    ->visible(fn(PurchaseOrder $record) => !in_array($record->status, ['received', 'cancelled']))
                    ->requiresConfirmation()
                    ->action(function (PurchaseOrder $record): void {
                        app(InventoryService::class)->cancelPurchaseOrder($record, 'Cancelled from admin UI', auth()->user());
                        Notification::make()
                            ->title('Purchase Order Cancelled')
                            ->body("PO #{$record->po_number} cancelled.")
                            ->warning()
                            ->send();
                    }),

                \Filament\Actions\Action::make('inspect_po')
                    ->label('Inspect')
                    ->icon('heroicon-m-eye')
                    ->color('gray')
                    ->modalHeading(fn(PurchaseOrder $record) => "Purchase Order Inspection: {$record->po_number}")
                    ->modalContent(fn(PurchaseOrder $record) => view('filament.components.purchase-order-inspect-modal', ['record' => $record]))
                    ->modalWidth('5xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

                \Filament\Actions\Action::make('print_po')
                    ->label('Print')
                    ->icon('heroicon-m-printer')
                    ->color('gray')
                    ->url(fn(PurchaseOrder $record) => route('admin.purchase-orders.print', ['purchaseOrder' => $record->id]), shouldOpenInNewTab: true),

                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchaseOrders::route('/'),
            'create' => Pages\CreatePurchaseOrder::route('/create'),
            'view' => Pages\ViewPurchaseOrder::route('/{record}'),
            'edit' => Pages\EditPurchaseOrder::route('/{record}/edit'),
        ];
    }
}
