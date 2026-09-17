<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $recordTitleAttribute = 'id';

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('id')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')->label('Order #')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->formatStateUsing(fn($state) => 'Rs. ' . number_format((float)$state, 0))
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        \App\Models\Order::STATUS_PENDING_PAYMENT => 'warning',
                        \App\Models\Order::STATUS_PAID, \App\Models\Order::STATUS_PROCESSING => 'primary',
                        \App\Models\Order::STATUS_PACKING, \App\Models\Order::STATUS_QUALITY_CHECK => 'info',
                        \App\Models\Order::STATUS_READY_TO_SHIP, \App\Models\Order::STATUS_SHIPPED, \App\Models\Order::STATUS_DELIVERED => 'success',
                        \App\Models\Order::STATUS_CANCELLED, \App\Models\Order::STATUS_PAYMENT_FAILED => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state) => \App\Models\Order::getStatuses()[$state] ?? ucfirst(str_replace('_', ' ', $state))),
                Tables\Columns\TextColumn::make('created_at')->label('Ordered At')->dateTime('M d, Y H:i')->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([])
            ->actions([
                \Filament\Actions\Action::make('view_order')
                    ->label('View Order')
                    ->icon('heroicon-o-eye')
                    ->url(fn($record): string => route('filament.admin.resources.orders.view', ['record' => $record]))
            ])
            ->bulkActions([]);
    }
}
