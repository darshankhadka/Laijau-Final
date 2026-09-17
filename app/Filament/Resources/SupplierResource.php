<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierResource\Pages;
use App\Models\Inventory\Supplier;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';
    protected static string | \UnitEnum | null $navigationGroup = 'Inventory';
    protected static ?string $navigationLabel = 'Suppliers';
    protected static ?int $navigationSort = 60;

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Supplier & Artisan Guild Information')
                    ->schema([
                        TextInput::make('code')
                            ->label('Supplier Code')
                            ->placeholder('e.g. SUP-PASHMINA-KTM')
                            ->required()
                            ->maxLength(30)
                            ->unique(ignoreRecord: true),

                        TextInput::make('name')
                            ->label('Supplier / Cooperative Name')
                            ->placeholder('e.g. Himalayan Cashmere & Pashmina Guild')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('contact_person')
                            ->label('Primary Contact Person'),

                        TextInput::make('email')
                            ->email(),

                        TextInput::make('phone')
                            ->tel(),

                        TextInput::make('tax_vat_number')
                            ->label('Tax / VAT / PAN Number'),
                    ])->columns(3),

                Section::make('Location & Commercial Terms')
                    ->schema([
                        TextInput::make('address')
                            ->label('Address'),

                        TextInput::make('city')
                            ->label('City'),

                        TextInput::make('country')
                            ->label('Country Code')
                            ->default('NP')
                            ->maxLength(2)
                            ->required(),

                        Select::make('currency')
                            ->label('Billing Currency')
                            ->options([
                                'NPR' => 'NPR (Nepalese Rupee)',
                                'INR' => 'INR (Indian Rupee)',
                                'USD' => 'USD (US Dollar)',
                            ])
                            ->default('NPR')
                            ->required(),

                        TextInput::make('payment_terms')
                            ->label('Payment Terms')
                            ->placeholder('e.g. Net 30, 50% Advance')
                            ->default('Net 30'),

                        TextInput::make('lead_time_days')
                            ->label('Standard Lead Time (Days)')
                            ->numeric()
                            ->default(14)
                            ->required(),

                        TextInput::make('due_balance')
                            ->label('Payable Balance (Due Today)')
                            ->prefix('Rs.')
                            ->numeric()
                            ->default(0.00)
                            ->required(),

                        Toggle::make('is_active')
                            ->label('Active Supplier')
                            ->default(true),

                        Textarea::make('notes')
                            ->label('Notes & Craftsmanship Details')
                            ->columnSpanFull(),
                    ])->columns(3),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->withCount('purchaseOrders')
            ->withCount(['purchaseOrders as active_inbound_count' => function ($query) {
                $query->whereIn('status', ['ordered', 'in_transit', 'partially_received']);
            }])
            ->withSum(['purchaseOrders as total_purchases_npr' => function ($query) {
                $query->whereIn('status', ['received', 'partially_received', 'closed']);
            }], 'total_amount_npr');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name', 'asc')
            ->emptyStateHeading('No Suppliers Found')
            ->emptyStateDescription('Register artisan cooperatives, fabric mills, and international vendors to manage procurement orders.')
            ->emptyStateIcon('heroicon-o-truck')
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Supplier Name')
                    ->description(fn(Supplier $record) => $record->contact_person ? "Contact: {$record->contact_person}" : null)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('phone')
                    ->label('Phone')
                    ->placeholder('—')
                    ->copyable()
                    ->searchable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->placeholder('—')
                    ->copyable()
                    ->searchable(),

                TextColumn::make('city')
                    ->label('Location')
                    ->state(fn(Supplier $record) => $record->city ? "{$record->city}, {$record->country}" : ($record->country ?: '—'))
                    ->searchable(),

                TextColumn::make('due_balance')
                    ->label('Payable Balance')
                    ->alignRight()
                    ->formatStateUsing(fn($state) => \App\Helpers\NepaliNumberHelper::formatCurrency((float)$state, 'Rs. ', 2))
                    ->color(fn($state) => (float)$state > 0 ? 'danger' : 'gray')
                    ->weight(fn($state) => (float)$state > 0 ? 'bold' : 'normal')
                    ->badge(fn($state) => (float)$state > 0)
                    ->sortable(),

                TextColumn::make('purchase_orders_count')
                    ->label('POs')
                    ->alignRight()
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('active_inbound_count')
                    ->label('Inbound')
                    ->alignRight()
                    ->badge()
                    ->color(fn(int $state): string => $state > 0 ? 'warning' : 'gray')
                    ->formatStateUsing(fn(int $state): string => $state > 0 ? "{$state} Active" : '—')
                    ->sortable(),

                TextColumn::make('total_purchases_npr')
                    ->label('Procurement Value')
                    ->alignRight()
                    ->formatStateUsing(fn($state) => \App\Helpers\NepaliNumberHelper::formatCurrency((float)$state, 'Rs. ', 2))
                    ->weight('bold')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make()
                    ->before(function (\Filament\Actions\DeleteAction $action, Supplier $record) {
                        if ($record->purchaseOrders()->count() > 0) {
                            \Filament\Notifications\Notification::make()
                                ->title('Cannot Delete Supplier')
                                ->body("Supplier '{$record->name}' has associated purchase orders. Deactivate the supplier instead to maintain audit trails.")
                                ->danger()
                                ->send();
                            $action->halt();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'view' => Pages\ViewSupplier::route('/{record}'),
            'edit' => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }
}
