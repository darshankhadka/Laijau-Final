<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PreorderResource\Pages;
use App\Models\OrderItem;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PreorderResource extends Resource
{
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $model = OrderItem::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clock';
    protected static string | \UnitEnum | null $navigationGroup = 'Commerce';
    protected static ?string $navigationLabel = 'Pre-orders';
    protected static ?int $navigationSort = 70;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('is_preorder', true)
            ->with(['order', 'product']);
    }

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            TextInput::make('product_name')->disabled(),
            TextInput::make('quantity')->disabled(),
            TextInput::make('preorder_dispatch_note')->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('product.featured_image')
                    ->label('Photo')
                    ->circular()
                    ->size(40),

                TextColumn::make('order.order_number')
                    ->label('Order Reference')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->url(fn(OrderItem $record): ?string => $record->order ? OrderResource::getUrl('edit', ['record' => $record->order]) : null),

                TextColumn::make('product_name')
                    ->label('Pre-order Piece')
                    ->searchable()
                    ->sortable()
                    ->description(fn(OrderItem $record): string => ($record->selected_size ?: 'Standard') . ' / ' . ($record->selected_color ?: 'Default')),

                TextColumn::make('quantity')
                    ->label('Qty')
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                TextColumn::make('preorder_dispatch_note')
                    ->label('Expected Dispatch')
                    ->badge()
                    ->color('warning')
                    ->default('15–20 days'),

                TextColumn::make('order.customer_full_name')
                    ->label('Customer')
                    ->state(fn(OrderItem $record): string => $record->order ? trim(($record->order->first_name ?? '') . ' ' . ($record->order->last_name ?? '')) : 'Guest')
                    ->description(fn(OrderItem $record): string => $record->order?->email ?? ''),

                TextColumn::make('order.status')
                    ->label('Fulfillment Status')
                    ->badge()
                    ->formatStateUsing(fn(?string $state): string => match ($state) {
                        'processing' => '● In Production',
                        'shipped' => '✈ Dispatched',
                        'delivered', 'completed' => '✓ Fulfilled',
                        'cancelled' => '✕ Cancelled',
                        default => '● Pending Payment',
                    })
                    ->color(fn(?string $state): string => match ($state) {
                        'processing' => 'warning',
                        'shipped' => 'info',
                        'delivered', 'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('Ordered On')
                    ->dateTime('M d, Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('order_status')
                    ->label('Order Status')
                    ->relationship('order', 'status')
                    ->options([
                        'processing' => 'In Production / Processing',
                        'shipped' => 'Dispatched',
                        'delivered' => 'Fulfilled',
                        'pending_payment' => 'Pending Payment',
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPreorders::route('/'),
        ];
    }
}
