<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LogisticsEventsRelationManager extends RelationManager
{
    protected static string $relationship = 'logisticsEvents';
    protected static ?string $recordTitleAttribute = 'event';
    protected static ?string $title = 'Logistics & Webhook Audit Log';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('provider')
                    ->label('Courier')
                    ->badge()
                    ->formatStateUsing(fn($state) => strtoupper($state))
                    ->color(fn($state) => strtolower($state) === 'ncm' ? 'info' : 'warning'),
                Tables\Columns\TextColumn::make('external_order_id')
                    ->label('Courier Order / Consignment #')
                    ->copyable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('event')
                    ->label('Event')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Courier Status')
                    ->badge()
                    ->color(fn($state) => match (strtolower((string) $state)) {
                        'delivered', 'delivery completed' => 'success',
                        'cancelled', 'failed' => 'danger',
                        'in transit', 'sent for delivery', 'out for delivery' => 'primary',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('processing_status')
                    ->label('System Processing')
                    ->badge()
                    ->color(fn($state) => $state === 'processed' ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('received_at')
                    ->label('Received At')
                    ->dateTime('M d, Y H:i:s')
                    ->timezone('Asia/Kathmandu')
                    ->sortable(),
                Tables\Columns\TextColumn::make('error')
                    ->label('Error')
                    ->limit(40)
                    ->color('danger')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
