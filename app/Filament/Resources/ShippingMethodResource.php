<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShippingMethodResource\Pages;
use App\Models\Order;
use App\Models\ShippingMethod;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ShippingMethodResource extends Resource
{
    protected static ?string $model = ShippingMethod::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-truck';
    protected static string | \UnitEnum | null $navigationGroup = 'Fulfillment';
    protected static ?string $navigationLabel = 'Delivery Zones & Rates';
    protected static ?string $modelLabel = 'Shipping Method';
    protected static ?string $pluralModelLabel = 'Delivery Zones & Rates';
    protected static ?int $navigationSort = 20;

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Method Identity')
                    ->description('Public customer title and logistics partner branding')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Method Display Name')
                            ->placeholder('e.g. Kathmandu Valley Standard Delivery')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('code')
                            ->label('System Code')
                            ->placeholder('e.g. inside_valley_standard')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->extraInputAttributes(['class' => 'font-mono text-sm']),
                        Forms\Components\TextInput::make('carrier')
                            ->label('Logistics Partner / Carrier')
                            ->placeholder('e.g. Pathao Courier, Nepal Can Move (NCM), In-House Rider')
                            ->nullable(),
                        Forms\Components\Textarea::make('description')
                            ->label('Customer Instructions / Notes')
                            ->placeholder('e.g. Door-to-door courier service with SMS updates and real-time tracking')
                            ->rows(2)
                            ->columnSpanFull()
                            ->nullable(),
                    ])->columns(3),

                Forms\Components\Section::make('Coverage & Delivery SLA')
                    ->description('Geographic delivery zone and expected transit timeframe')
                    ->schema([
                        Forms\Components\Select::make('zone')
                            ->label('Delivery Zone')
                            ->options([
                                'kathmandu_valley' => 'Kathmandu Valley (Kathmandu, Lalitpur, Bhaktapur)',
                                'outside_valley' => 'Outside Valley (All Other 74 Districts of Nepal)',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('estimated_delivery')
                            ->label('Estimated Transit Time')
                            ->placeholder('e.g. 24–48 hours, 2–4 business days')
                            ->required(),
                    ])->columns(2),

                Forms\Components\Section::make('Delivery Rates & Free Shipping Threshold')
                    ->description('Pricing in Nepalese Rupees (NPR / Rs.)')
                    ->schema([
                        Forms\Components\TextInput::make('price_npr')
                            ->label('Standard Delivery Fee (Rs.)')
                            ->numeric()
                            ->prefix('Rs.')
                            ->placeholder('100')
                            ->required(),
                        Forms\Components\TextInput::make('free_shipping_threshold_npr')
                            ->label('Free Delivery Threshold (Rs.)')
                            ->numeric()
                            ->prefix('Rs.')
                            ->placeholder('Leave empty if no free shipping threshold')
                            ->helperText('Cart subtotal in Rs. above which delivery becomes free of charge'),
                    ])->columns(2),

                Forms\Components\Section::make('Operational Controls')
                    ->description('Display priority and active availability in checkout')
                    ->schema([
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Display Priority')
                            ->helperText('Lower numbers appear first at checkout (e.g. 0, 10, 20)')
                            ->numeric()
                            ->default(0),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Available at Checkout')
                            ->helperText('When toggled off, customers cannot select this method')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Method & Code')
                    ->description(fn(ShippingMethod $record): string => $record->code ?? '')
                    ->searchable(['name', 'code'])
                    ->weight('bold')
                    ->sortable(),

                Tables\Columns\TextColumn::make('carrier')
                    ->label('Carrier')
                    ->badge()
                    ->color('gray')
                    ->placeholder('In-House')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('zone')
                    ->label('Zone')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'kathmandu_valley' => 'success',
                        'outside_valley' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'kathmandu_valley' => 'Kathmandu Valley',
                        'outside_valley' => 'Outside Valley',
                        default => ucfirst(str_replace('_', ' ', $state)),
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('price_npr')
                    ->label('Delivery Fee')
                    ->formatStateUsing(fn($state) => ((float)$state === 0.0) ? 'Free (Rs. 0)' : 'Rs. ' . number_format((float)$state, 0))
                    ->weight('bold')
                    ->color(fn($state) => ((float)$state === 0.0) ? 'success' : 'gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('free_shipping_threshold_npr')
                    ->label('Free Above')
                    ->formatStateUsing(fn($state) => $state ? 'Rs. ' . number_format((float)$state, 0) : '—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('estimated_delivery')
                    ->label('Transit Time')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Priority')
                    ->sortable(),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active Status')
                    ->afterStateUpdated(function ($record, $state) {
                        if (!$state) {
                            $activeOrders = Order::where('shipping_method', $record->code)
                                ->whereNotIn('status', [Order::STATUS_DELIVERED, Order::STATUS_CANCELLED, Order::STATUS_RETURNED])
                                ->count();

                            if ($activeOrders > 0) {
                                Notification::make()
                                    ->title('Method Deactivated')
                                    ->body("Note: {$activeOrders} active orders currently reference this method.")
                                    ->warning()
                                    ->send();
                            }
                        }
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('zone')
                    ->label('Delivery Zone')
                    ->options([
                        'kathmandu_valley' => 'Kathmandu Valley',
                        'outside_valley' => 'Outside Valley',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive Only'),
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make()
                    ->before(function (\Filament\Actions\DeleteAction $action, ShippingMethod $record) {
                        $activeOrderCount = Order::where('shipping_method', $record->code)
                            ->whereNotIn('status', [Order::STATUS_DELIVERED, Order::STATUS_CANCELLED, Order::STATUS_RETURNED])
                            ->count();

                        if ($activeOrderCount > 0) {
                            Notification::make()
                                ->title('Cannot Delete Shipping Method')
                                ->body("This method is currently referenced by {$activeOrderCount} active orders. Deactivate it instead to preserve order integrity.")
                                ->danger()
                                ->send();
                            $action->cancel();
                        }
                    }),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageShippingMethods::route('/'),
        ];
    }
}
