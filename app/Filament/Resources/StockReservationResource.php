<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockReservationResource\Pages;
use App\Models\Inventory\StockReservation;
use App\Models\Inventory\Warehouse;
use App\Models\Product;
use App\Services\Inventory\InventoryService;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
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

class StockReservationResource extends Resource
{
    protected static ?string $model = StockReservation::class;

    protected static bool $shouldRegisterNavigation = false;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-lock-closed';
    protected static string | \UnitEnum | null $navigationGroup = 'Inventory';
    protected static ?string $navigationLabel = 'Reservations';
    protected static ?int $navigationSort = 40;

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Reservation Details')
                    ->schema([
                        TextInput::make('reservation_number')
                            ->label('Reservation Number')
                            ->disabled()
                            ->placeholder('Auto-generated (RES-YYYYMMDD-XXXX)'),

                        Select::make('warehouse_id')
                            ->label('Warehouse')
                            ->relationship('warehouse', 'name')
                            ->required(),

                        Select::make('product_id')
                            ->label('Product')
                            ->relationship('product', 'name')
                            ->required()
                            ->searchable(),

                        Select::make('variant_id')
                            ->label('Variant (SKU)')
                            ->relationship('variant', 'sku')
                            ->nullable()
                            ->searchable(),

                        TextInput::make('quantity')
                            ->label('Reserved Quantity')
                            ->numeric()
                            ->default(1)
                            ->required(),

                        Select::make('reference_type')
                            ->label('Reference Type')
                            ->options([
                                'cart' => 'Cart Session Hold',
                                'order' => 'E-Commerce Order',
                                'pos_hold' => 'Showroom POS Hold',
                                'manual' => 'Manual Reservation',
                            ])
                            ->default('manual')
                            ->required(),

                        TextInput::make('cart_token')
                            ->label('Cart Token / Session')
                            ->nullable(),

                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'active' => 'Active',
                                'converted' => 'Converted (Fulfilled)',
                                'released' => 'Released',
                                'expired' => 'Expired',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('active')
                            ->required(),

                        DateTimePicker::make('expires_at')
                            ->label('Expires At')
                            ->default(now()->addMinutes(30)),

                        DateTimePicker::make('released_at')
                            ->label('Released At')
                            ->disabled(),

                        Textarea::make('notes')
                            ->label('Notes')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('No Stock Reservations')
            ->emptyStateDescription('Active cart reservations, customer pre-orders, and POS holds will appear here.')
            ->columns([
                TextColumn::make('reservation_number')
                    ->label('Reservation #')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->sortable(),

                TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->limit(25),

                TextColumn::make('variant.sku')
                    ->label('SKU')
                    ->placeholder('Base SKU')
                    ->searchable(),

                TextColumn::make('quantity')
                    ->label('Qty')
                    ->sortable()
                    ->alignRight(),

                TextColumn::make('reference_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'order' => 'primary',
                        'cart' => 'warning',
                        'pos_hold' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'converted' => 'primary',
                        'released' => 'gray',
                        'expired' => 'danger',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'converted' => 'Converted',
                        'released' => 'Released',
                        'expired' => 'Expired',
                        'cancelled' => 'Cancelled',
                    ]),

                SelectFilter::make('warehouse_id')
                    ->label('Warehouse')
                    ->relationship('warehouse', 'name'),

                SelectFilter::make('reference_type')
                    ->options([
                        'cart' => 'Cart Session',
                        'order' => 'Order',
                        'pos_hold' => 'POS Hold',
                        'manual' => 'Manual',
                    ]),
            ])
            ->actions([
                \Filament\Actions\Action::make('release')
                    ->label('Release')
                    ->icon('heroicon-o-lock-open')
                    ->color('danger')
                    ->visible(fn (StockReservation $record) => $record->status === 'active')
                    ->requiresConfirmation()
                    ->modalHeading(fn (StockReservation $record) => "Release Reservation: #{$record->reservation_number}")
                    ->modalDescription(fn (StockReservation $record) => "Are you sure you want to release {$record->quantity} pcs of {$record->product?->name} back to available inventory?")
                    ->action(function (StockReservation $record) {
                        app(InventoryService::class)->releaseReservation($record, 'manually_released');
                        Notification::make()
                            ->title('Reservation Released')
                            ->body("Stock for reservation #{$record->reservation_number} has been released back to available inventory.")
                            ->success()
                            ->send();
                    }),

                \Filament\Actions\EditAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkAction::make('release_all')
                    ->label('Release Selected')
                    ->icon('heroicon-o-lock-open')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Release Selected Reservations')
                    ->modalDescription('Release all selected active reservations back to available stock?')
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                        $service = app(InventoryService::class);
                        $count = 0;
                        foreach ($records as $record) {
                            if ($record->status === 'active') {
                                $service->releaseReservation($record, 'bulk_released');
                                $count++;
                            }
                        }
                        Notification::make()
                            ->title('Reservations Released')
                            ->body("{$count} active reservations released.")
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockReservations::route('/'),
            'create' => Pages\CreateStockReservation::route('/create'),
            'edit' => Pages\EditStockReservation::route('/{record}/edit'),
        ];
    }
}
