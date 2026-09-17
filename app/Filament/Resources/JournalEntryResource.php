<?php

namespace App\Filament\Resources;

use App\Filament\Resources\JournalEntryResource\Pages;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\AccountingService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class JournalEntryResource extends Resource
{
    protected static ?string $model = JournalEntry::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-text';
    protected static string | \UnitEnum | null $navigationGroup = 'Finance';
    protected static ?string $navigationLabel = 'Journal Entries';
    protected static ?int $navigationSort = 70;

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Voucher Information')
                    ->description('Double-entry general ledger voucher compliant with Nepal Accounting Standards (NAS)')
                    ->schema([
                        TextInput::make('entry_number')
                            ->label('Voucher #')
                            ->disabled()
                            ->placeholder('Auto-generated (e.g. JV-2026-0001)'),

                        DatePicker::make('voucher_date')
                            ->label('Voucher Date')
                            ->required()
                            ->default(now()),

                        Select::make('entry_type')
                            ->label('Entry Type')
                            ->required()
                            ->default('manual')
                            ->options([
                                'manual' => 'Manual Journal Entry',
                                'sales' => 'Sales Revenue (POS / Webshop)',
                                'purchase' => 'Purchases / Supplier Bill',
                                'bank' => 'Bank Transaction',
                                'settlement' => 'Payment Gateway / Settlement',
                                'cogs' => 'Cost of Goods Sold (COGS)',
                                'depreciation' => 'Depreciation',
                                'closing' => 'Year-End Closing',
                            ]),

                        TextInput::make('currency')
                            ->label('Currency')
                            ->default('NPR')
                            ->disabled(),

                        TextInput::make('description')
                            ->label('Voucher Description')
                            ->required()
                            ->columnSpanFull()
                            ->placeholder('e.g. Monthly showroom rent Kathmandu'),
                    ])->columns(3),

                Section::make('Journal Lines (Debit & Credit)')
                    ->description('Total debits must strictly equal total credits')
                    ->schema([
                        Repeater::make('lines')
                            ->relationship()
                            ->schema([
                                Select::make('account_id')
                                    ->label('Account')
                                    ->required()
                                    ->searchable()
                                    ->options(Account::where('is_active', true)->orderBy('account_number')->get()->pluck('display_name', 'id')),

                                TextInput::make('description')
                                    ->label('Line Description')
                                    ->placeholder('Optional specific text'),

                                TextInput::make('debit')
                                    ->label('Debit (NPR)')
                                    ->numeric()
                                    ->default(0.00)
                                    ->prefix('Rs.'),

                                TextInput::make('credit')
                                    ->label('Credit (NPR)')
                                    ->numeric()
                                    ->default(0.00)
                                    ->prefix('Rs.'),
                            ])
                            ->columns(4)
                            ->defaultItems(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('voucher_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('entry_number')
                    ->label('Voucher #')
                    ->sortable()
                    ->searchable()
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('voucher_date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->wrap()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('entry_type')
                    ->label('Type')
                    ->badge()
                    ->colors([
                        'success' => 'sales',
                        'info' => 'bank',
                        'purple' => 'settlement',
                        'warning' => 'cogs',
                        'danger' => 'reversal',
                        'gray' => 'manual',
                        'primary' => 'closing',
                    ])
                    ->formatStateUsing(fn($state) => match ($state) {
                        'sales' => 'Sales',
                        'purchase' => 'Purchase',
                        'bank' => 'Bank',
                        'settlement' => 'Settlement',
                        'cogs' => 'COGS',
                        'depreciation' => 'Depreciation',
                        'closing' => 'Closing',
                        'reversal' => 'Reversal',
                        default => 'Manual',
                    }),

                Tables\Columns\TextColumn::make('total_debit')
                    ->label('Debit (NPR)')
                    ->sortable()
                    ->weight('bold')
                    ->formatStateUsing(fn($state) => \App\Helpers\NepaliNumberHelper::formatCurrency((float)$state, 'Rs. ', 2)),

                Tables\Columns\TextColumn::make('total_credit')
                    ->label('Credit (NPR)')
                    ->sortable()
                    ->weight('bold')
                    ->formatStateUsing(fn($state) => \App\Helpers\NepaliNumberHelper::formatCurrency((float)$state, 'Rs. ', 2)),

                Tables\Columns\IconColumn::make('is_balanced')
                    ->label('Balanced')
                    ->boolean(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'success' => 'posted',
                        'danger' => 'reversed',
                        'warning' => 'draft',
                    ])
                    ->formatStateUsing(fn($state) => match ($state) {
                        'posted' => 'Posted',
                        'reversed' => 'Reversed',
                        'draft' => 'Draft',
                        default => ucfirst((string)$state),
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('entry_type')
                    ->label('Entry Type')
                    ->options([
                        'sales' => 'Sales',
                        'purchase' => 'Purchase',
                        'bank' => 'Bank',
                        'settlement' => 'Settlement',
                        'cogs' => 'Cost of Goods Sold (COGS)',
                        'reversal' => 'Reversal',
                        'manual' => 'Manual',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'posted' => 'Posted',
                        'reversed' => 'Reversed',
                        'draft' => 'Draft',
                    ]),
            ])
            ->actions([
                \Filament\Actions\Action::make('inspect')
                    ->label('Inspect Voucher')
                    ->icon('heroicon-m-eye')
                    ->color('primary')
                    ->modalHeading(fn(JournalEntry $record) => "Journal Voucher #{$record->entry_number}")
                    ->modalDescription(fn(JournalEntry $record) => "Double-Entry General Ledger Voucher • " . ($record->voucher_date ? $record->voucher_date->format('d M Y') : ''))
                    ->modalContent(fn(JournalEntry $record) => view('filament.components.journal-entry-inspect-modal', [
                        'record' => $record,
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalWidth('5xl'),

                // NAS / IRD Compliant Reversal Action
                \Filament\Actions\Action::make('reverse')
                    ->label('Reverse Voucher')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->visible(fn($record) => $record->status === 'posted')
                    ->requiresConfirmation()
                    ->modalHeading('Reverse Posted Voucher')
                    ->modalDescription(fn($record) => "Under Nepal Accounting Standards (NAS), a posted voucher ({$record->entry_number}) cannot be deleted. A balanced reversal entry will be posted.")
                    ->form([
                        Textarea::make('reason')
                            ->label('Reason for Reversal')
                            ->required()
                            ->placeholder('e.g. Incorrect account debited or posting error'),
                    ])
                    ->action(function (JournalEntry $record, array $data) {
                        try {
                            $reversal = app(AccountingService::class)->reverseJournalEntry($record, $data['reason']);
                            Notification::make()
                                ->title('Voucher Reversed')
                                ->body("Reversal voucher {$reversal->entry_number} has been posted.")
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Reversal Error')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->emptyStateHeading('No Journal Vouchers Found')
            ->emptyStateDescription('All double-entry transactions from sales, purchases, payroll, and settlements will appear here with complete audit trails.')
            ->emptyStateIcon('heroicon-o-clipboard-document-list')
            ->emptyStateActions([
                \Filament\Actions\Action::make('create_journal_voucher')
                    ->label('Post Manual Journal Voucher')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->url(fn() => static::getUrl('create')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJournalEntries::route('/'),
            'create' => Pages\CreateJournalEntry::route('/create'),
            'view' => Pages\ViewJournalEntry::route('/{record}'),
        ];
    }
}
