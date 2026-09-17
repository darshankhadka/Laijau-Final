<?php

namespace App\Filament\Resources\Coupons\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Toggle;

class CouponForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->extraInputAttributes(['style' => 'text-transform: uppercase']),
                Select::make('type')
                    ->options([
                        'percentage' => 'Percentage Discount (%)',
                        'fixed' => 'Fixed Amount Discount',
                    ])
                    ->required()
                    ->default('percentage'),
                TextInput::make('value')
                    ->label('Discount Value')
                    ->required()
                    ->numeric()
                    ->minValue(0.01),
                TextInput::make('min_spend')
                    ->label('Minimum Order Amount')
                    ->numeric()
                    ->nullable(),
                TextInput::make('max_discount')
                    ->label('Maximum Discount Cap')
                    ->numeric()
                    ->nullable(),
                DateTimePicker::make('starts_at')
                    ->label('Valid From')
                    ->nullable(),
                DateTimePicker::make('expires_at')
                    ->label('Expires At')
                    ->nullable(),
                TextInput::make('usage_limit')
                    ->numeric()
                    ->nullable()
                    ->label('Usage Limit (Total globally)'),
                TextInput::make('used_count')
                    ->numeric()
                    ->disabled()
                    ->default(0),
                Toggle::make('is_active')
                    ->label('Active for Customer Checkout')
                    ->default(true),
            ]);
    }
}
