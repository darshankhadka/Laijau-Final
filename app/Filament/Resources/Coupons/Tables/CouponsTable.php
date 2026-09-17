<?php

namespace App\Filament\Resources\Coupons\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;

class CouponsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->weight('bold')
                    ->copyable(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'percentage' => 'success',
                        'fixed' => 'primary',
                        default => 'gray',
                    }),
                TextColumn::make('value')
                    ->formatStateUsing(fn($record) => $record->type === 'percentage' ? $record->value . '%' : $record->value),
                TextColumn::make('min_spend')
                    ->label('Min Spend')
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('None'),
                TextColumn::make('used_count')
                    ->label('Usage')
                    ->formatStateUsing(fn($record) => $record->used_count . ($record->usage_limit ? ' / ' . $record->usage_limit : '')),
                ToggleColumn::make('is_active')
                    ->label('Active'),
                TextColumn::make('expires_at')
                    ->dateTime()
                    ->placeholder('No Expiry')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
