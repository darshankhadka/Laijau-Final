<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PriceHistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'priceHistories';

    protected static ?string $title = '30-Day Price History (Omnibus Directive)';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Read-only, no form needed
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('changed_at')
            ->columns([
                TextColumn::make('prices')
                    ->label('Prices Recorded')
                    ->formatStateUsing(function ($state) {
                        if (is_array($state)) {
                            return collect($state)->map(fn($price, $curr) => "$curr: $price")->implode(', ');
                        }
                        return $state;
                    }),
                TextColumn::make('changed_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // Read-only
            ])
            ->recordActions([
                // Read-only
            ])
            ->toolbarActions([
                // Read-only
            ])
            ->defaultSort('changed_at', 'desc');
    }
}
