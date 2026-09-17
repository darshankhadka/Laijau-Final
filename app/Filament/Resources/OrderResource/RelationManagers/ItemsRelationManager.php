<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';
    protected static ?string $recordTitleAttribute = 'product_name';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product_name')
                    ->label('Product')
                    ->getStateUsing(fn($record) => $record->product_name ?? $record->product?->name ?? 'Standard Item'),
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->copyable(),
                Tables\Columns\TextColumn::make('selected_size')
                    ->label('Size')
                    ->badge(),
                Tables\Columns\TextColumn::make('selected_color')
                    ->label('Color'),
                Tables\Columns\TextColumn::make('unit_price')
                    ->label('Unit Price')
                    ->formatStateUsing(fn($state, $record) => 'Rs. ' . number_format((float) $state, 0)),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Qty'),
                Tables\Columns\TextColumn::make('line_total')
                    ->label('Total')
                    ->formatStateUsing(fn($record) => 'Rs. ' . number_format((float) ($record->unit_price * $record->quantity), 0)),
                Tables\Columns\TextColumn::make('custom_measurements')
                    ->label('Custom Measurements')
                    ->getStateUsing(function ($record) {
                        $m = $record->custom_measurements;
                        if (empty($m)) return 'Standard';
                        if (is_string($m)) $m = json_decode($m, true);
                        if (!is_array($m)) return 'Standard';
                        return collect($m)->map(fn($v, $k) => ucfirst(str_replace('_', ' ', $k)) . ': ' . $v)->join(', ');
                    })
                    ->limit(50)
                    ->tooltip(fn($record) => $record->custom_measurements ? json_encode($record->custom_measurements, JSON_PRETTY_PRINT) : null),
            ])
            ->filters([
                //
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
