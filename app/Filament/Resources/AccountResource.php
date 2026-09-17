<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccountResource\Pages;
use App\Models\Accounting\Account;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class AccountResource extends Resource
{
    protected static ?string $model = Account::class;

    protected static bool $shouldRegisterNavigation = true;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-book-open';
    protected static string | \UnitEnum | null $navigationGroup = 'Finance';
    protected static ?string $navigationLabel = 'Chart of Accounts';
    protected static ?int $navigationSort = 110;

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Nepal Chart of Accounts (NAS)')
                    ->description('Standard 4-digit Nepal account numbering compliant with Nepal Accounting Standards (NAS)')
                    ->schema([
                        TextInput::make('account_number')
                            ->label('Account Number (4 digits)')
                            ->required()
                            ->maxLength(10)
                            ->unique(ignoreRecord: true)
                            ->placeholder('e.g. 1110 or 2120'),

                        TextInput::make('name')
                            ->label('Account Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g. Retail Sales Revenue 13% VAT'),

                        Select::make('category')
                            ->label('Account Category')
                            ->required()
                            ->options([
                                'cash_bank' => '1110-1129: Cash on Hand & Operating Bank (Asset)',
                                'receivables' => '1130-1199: Receivables & Payment Clearing (Asset)',
                                'inventory' => '1200-1299: Merchandise Inventory Stock (Asset)',
                                'fixed_assets' => '1300-1399: Fixed Assets & Equipment (Asset)',
                                'payables' => '2110-2119: Accounts Payable & Trade Debt (Liability)',
                                'vat_tax' => '2120-2149: VAT & TDS Statutory Taxes (Liability/Asset)',
                                'equity' => '3100-3199: Capital & Retained Earnings (Equity)',
                                'revenue' => '4100-4199: Sales & Commerce Revenue (Revenue)',
                                'cogs' => '5100-5199: Cost of Goods Sold (COGS)',
                                'opex' => '6100-6199: Operating Expenses (OPEX)',
                                'personnel' => '6120-6139: Personnel & SSF Expenses (Expense)',
                                'financial' => '6190-6199: Bank & Processing Fees (Expense)',
                            ]),

                        Select::make('account_type')
                            ->label('Account Type (Balance Sheet / Income Statement)')
                            ->required()
                            ->options([
                                'income' => 'Income / Revenue (P&L)',
                                'expense' => 'Expense / Cost (P&L)',
                                'asset' => 'Asset (Balance Sheet)',
                                'liability' => 'Liability (Balance Sheet)',
                                'equity' => 'Equity (Balance Sheet)',
                            ]),

                        Select::make('normal_balance')
                            ->label('Normal Balance (Debit / Credit)')
                            ->required()
                            ->options([
                                'debit' => 'Debit (Asset / Expense)',
                                'credit' => 'Credit (Liability / Revenue / Equity)',
                            ]),

                        TextInput::make('default_vat_rate')
                            ->label('Default VAT Rate')
                            ->numeric()
                            ->default(13.00)
                            ->suffix('%')
                            ->placeholder('13.00'),

                        TextInput::make('currency')
                            ->label('Currency')
                            ->default('NPR')
                            ->maxLength(3),

                        Toggle::make('is_active')
                            ->label('Active Account')
                            ->default(true),

                        Textarea::make('description')
                            ->label('Purpose & Notes')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('account_number')
            ->columns([
                Tables\Columns\TextColumn::make('account_number')
                    ->label('Account #')
                    ->sortable()
                    ->searchable()
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Account Name')
                    ->sortable()
                    ->searchable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('category')
                    ->label('Category')
                    ->badge()
                    ->colors([
                        'success' => fn($state) => in_array($state, ['revenue', 'cash_bank']),
                        'warning' => fn($state) => in_array($state, ['cogs', 'payment_fees']),
                        'info' => fn($state) => in_array($state, ['opex', 'receivables', 'fixed_assets', 'inventory']),
                        'danger' => fn($state) => in_array($state, ['vat_tax', 'payables']),
                        'primary' => fn($state) => in_array($state, ['equity']),
                    ])
                    ->formatStateUsing(fn($state) => match ($state) {
                        'revenue' => 'Revenue',
                        'cogs' => 'COGS',
                        'payment_fees' => 'Payment Fees',
                        'opex' => 'OPEX',
                        'personnel' => 'Personnel',
                        'financial' => 'Financial',
                        'fixed_assets' => 'Fixed Assets',
                        'inventory' => 'Inventory',
                        'receivables' => 'Receivables',
                        'cash_bank' => 'Cash & Bank',
                        'equity' => 'Equity',
                        'vat_tax' => 'VAT & Tax',
                        'payables' => 'Payables',
                        default => ucfirst((string)$state),
                    }),

                Tables\Columns\TextColumn::make('account_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn($state) => match ($state) {
                        'income' => 'Income',
                        'expense' => 'Expense',
                        'asset' => 'Asset',
                        'liability' => 'Liability',
                        'equity' => 'Equity',
                        default => ucfirst((string)$state),
                    }),

                Tables\Columns\TextColumn::make('normal_balance')
                    ->label('Normal Balance')
                    ->badge()
                    ->color(fn($state) => $state === 'debit' ? 'info' : 'primary')
                    ->formatStateUsing(fn($state) => strtoupper((string)$state)),

                Tables\Columns\TextColumn::make('current_balance')
                    ->label('Current Balance (NPR)')
                    ->sortable()
                    ->weight('bold')
                    ->color(fn($record) => (float)$record->current_balance < 0 ? 'danger' : null)
                    ->formatStateUsing(fn($state) => 'Rs. ' . number_format((float)$state, 2, '.', ',')),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('Category')
                    ->options([
                        'revenue' => 'Revenue',
                        'cogs' => 'Cost of Goods Sold (COGS)',
                        'payment_fees' => 'Payment Fees',
                        'opex' => 'Operating Expenses (OPEX)',
                        'personnel' => 'Personnel',
                        'financial' => 'Financial',
                        'fixed_assets' => 'Fixed Assets',
                        'inventory' => 'Inventory',
                        'receivables' => 'Receivables',
                        'cash_bank' => 'Cash & Bank',
                        'equity' => 'Equity',
                        'vat_tax' => 'VAT & Tax',
                        'payables' => 'Payables',
                    ]),

                Tables\Filters\SelectFilter::make('account_type')
                    ->label('Account Type')
                    ->options([
                        'income' => 'Income',
                        'expense' => 'Expense',
                        'asset' => 'Asset',
                        'liability' => 'Liability',
                        'equity' => 'Equity',
                    ]),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
            ])
            ->emptyStateHeading('No Ledger Accounts Found')
            ->emptyStateDescription('No accounts matched your search or category filter.')
            ->emptyStateIcon('heroicon-o-numbered-list')
            ->emptyStateActions([
                \Filament\Actions\Action::make('create_account')
                    ->label('Create New Account')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->url(fn() => static::getUrl('create')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccounts::route('/'),
            'create' => Pages\CreateAccount::route('/create'),
            'edit' => Pages\EditAccount::route('/{record}/edit'),
        ];
    }
}
