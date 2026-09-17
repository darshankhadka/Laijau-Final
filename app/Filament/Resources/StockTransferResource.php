<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockTransferResource\Pages;
use App\Models\Inventory\StockTransfer;
use App\Models\Inventory\StockTransferItem;
use App\Models\Inventory\Warehouse;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Inventory\InventoryService;
use Filament\Actions;
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

class StockTransferResource extends Resource
{
    protected static ?string $model = StockTransfer::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-truck';
    protected static string | \UnitEnum | null $navigationGroup = 'Inventory';
    protected static ?string $navigationLabel = 'Transfers';
    protected static ?int $navigationSort = 30;

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Transfer Routing & Logistics')
                    ->schema([
                        TextInput::make('transfer_number')
                            ->label('Transfer Number')
                            ->default(fn() => StockTransfer::generateNextTransferNumber())
                            ->disabled()
                            ->dehydrated(),

                        Select::make('source_warehouse_id')
                            ->label('Origin Warehouse')
                            ->relationship('sourceWarehouse', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),

                        Select::make('destination_warehouse_id')
                            ->label('Destination Warehouse')
                            ->relationship('destinationWarehouse', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->different('source_warehouse_id'),

                        Select::make('status')
                            ->label('Transfer Status')
                            ->options([
                                'draft' => 'Draft (Preparing)',
                                'in_transit' => 'In Transit (Dispatched)',
                                'completed' => 'Completed (Delivered & Checked)',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('draft')
                            ->required(),

                        TextInput::make('tracking_reference')
                            ->label('Waybill / Tracking Reference')
                            ->placeholder('e.g. LJ-VAN-01 or NCM-TRF-9812'),
                    ])->columns(3),

                Section::make('Transfer Line Items')
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
                                    ->live(),

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

                                TextInput::make('quantity_sent')
                                    ->label('Qty Sent')
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(1)
                                    ->required(),

                                TextInput::make('quantity_received')
                                    ->label('Qty Received')
                                    ->numeric()
                                    ->default(0)
                                    ->disabled(),
                            ])
                            ->columns(4)
                            ->columnSpanFull(),
                    ]),

                Section::make('Logistics Notes')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Special Handling / Notes')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->with(['sourceWarehouse', 'destinationWarehouse', 'items.product', 'items.variant']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('No Transfers Found')
            ->emptyStateDescription('Create a new stock transfer to route inventory between warehouses, retail showrooms, or production workshops.')
            ->columns([
                TextColumn::make('transfer_number')
                    ->label('Transfer #')
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('sourceWarehouse.code')
                    ->label('From')
                    ->badge()
                    ->color('danger')
                    ->searchable(),

                TextColumn::make('destinationWarehouse.code')
                    ->label('To')
                    ->badge()
                    ->color('success')
                    ->searchable(),

                TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->alignRight()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'completed' => 'success',
                        'in_transit' => 'warning',
                        'draft' => 'gray',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn($state) => ucfirst(str_replace('_', ' ', $state))),

                TextColumn::make('sent_at')
                    ->label('Dispatched')
                    ->dateTime('M d, H:i')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('received_at')
                    ->label('Received')
                    ->dateTime('M d, H:i')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('tracking_reference')
                    ->label('Tracking Ref')
                    ->placeholder('—')
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('source_warehouse_id')
                    ->label('Origin')
                    ->relationship('sourceWarehouse', 'name'),

                SelectFilter::make('destination_warehouse_id')
                    ->label('Destination')
                    ->relationship('destinationWarehouse', 'name'),

                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'in_transit' => 'In Transit',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->actions([
                \Filament\Actions\Action::make('dispatch')
                    ->label('Dispatch')
                    ->icon('heroicon-m-paper-airplane')
                    ->color('warning')
                    ->visible(fn(StockTransfer $record) => $record->status === 'draft')
                    ->requiresConfirmation()
                    ->modalHeading(fn(StockTransfer $record) => "Dispatch Transfer: #{$record->transfer_number}")
                    ->modalDescription(fn(StockTransfer $record) => "Deduct stock from {$record->sourceWarehouse?->name} and mark transfer as In Transit.")
                    ->action(function (StockTransfer $record): void {
                        try {
                            app(InventoryService::class)->dispatchTransfer($record, auth()->user());

                            Notification::make()
                                ->title('Transfer Dispatched')
                                ->body("Stock dispatched from {$record->sourceWarehouse?->name}.")
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Dispatch Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),

                \Filament\Actions\Action::make('receive')
                    ->label('Receive')
                    ->icon('heroicon-m-check-badge')
                    ->color('success')
                    ->visible(fn(StockTransfer $record) => $record->status === 'in_transit')
                    ->requiresConfirmation()
                    ->modalHeading(fn(StockTransfer $record) => "Receive Transfer: #{$record->transfer_number}")
                    ->modalDescription(fn(StockTransfer $record) => "Credit stock to {$record->destinationWarehouse?->name} and complete this transfer.")
                    ->action(function (StockTransfer $record): void {
                        try {
                            app(InventoryService::class)->receiveTransfer($record, [], auth()->user());

                            Notification::make()
                                ->title('Transfer Completed')
                                ->body("Stock received and verified at {$record->destinationWarehouse?->name}.")
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Receive Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                \Filament\Actions\Action::make('cancel_transfer')
                    ->label('Cancel')
                    ->icon('heroicon-m-x-circle')
                    ->color('danger')
                    ->visible(fn(StockTransfer $record) => in_array($record->status, ['draft', 'in_transit']))
                    ->requiresConfirmation()
                    ->modalHeading(fn(StockTransfer $record) => "Cancel Transfer #{$record->transfer_number}")
                    ->modalDescription('Are you sure you want to cancel this transfer? Any in-transit items will be restored to the source warehouse.')
                    ->form([
                        TextInput::make('cancel_reason')
                            ->label('Cancellation Reason')
                            ->placeholder('e.g. Route error, items returned to shelf, or cancelled by logistics')
                            ->required(),
                    ])
                    ->action(function (StockTransfer $record, array $data): void {
                        try {
                            app(InventoryService::class)->cancelTransfer($record, $data['cancel_reason'] ?? '', auth()->user());

                            Notification::make()
                                ->title('Transfer Cancelled')
                                ->body("Transfer #{$record->transfer_number} cancelled successfully.")
                                ->warning()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Cancellation Failed')
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
            'index' => Pages\ListStockTransfers::route('/'),
            'create' => Pages\CreateStockTransfer::route('/create'),
            'view' => Pages\ViewStockTransfer::route('/{record}'),
            'edit' => Pages\EditStockTransfer::route('/{record}/edit'),
        ];
    }
}
