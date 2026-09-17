<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\TdsRecordResource\Pages;
use App\Models\Accounting\Account;
use App\Models\Accounting\AccountingFiscalYear;
use App\Models\Accounting\TdsRecord;
use App\Services\Accounting\AccountingService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TdsRecordResource extends Resource
{
    protected static ?string $model = TdsRecord::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-check';
    protected static string | \UnitEnum | null $navigationGroup = 'Finance';
    protected static ?string $navigationLabel = 'TDS Withholding Register';
    protected static ?int $navigationSort = 100;
    protected static ?string $title = 'Nepal TDS Withholding Register (IRD Directives)';
    protected static ?string $slug = 'tds-records';

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('TDS Withholding Details')
                    ->description('Nepal Income Tax Act 2058 withholding at source particulars.')
                    ->schema([
                        TextInput::make('tds_number')
                            ->label('TDS Voucher #')
                            ->disabled()
                            ->placeholder('Auto-generated (e.g. TDS-208384-0001)'),

                        Select::make('fiscal_year')
                            ->label('Fiscal Year')
                            ->options(AccountingFiscalYear::orderBy('start_date', 'desc')->pluck('fiscal_year', 'fiscal_year'))
                            ->default(fn() => AccountingFiscalYear::getCurrent()?->fiscal_year ?? '2083/84')
                            ->required(),

                        DatePicker::make('transaction_date')
                            ->label('Withholding Date (Miti)')
                            ->required()
                            ->default(now()),

                        TextInput::make('payee_name')
                            ->label('Payee / Vendor / Landlord Name')
                            ->required(),

                        TextInput::make('payee_pan')
                            ->label('Payee PAN Number')
                            ->required()
                            ->placeholder('9-digit PAN')
                            ->maxLength(20),

                        Select::make('payment_type')
                            ->label('Withholding Category')
                            ->options([
                                'contract_goods' => 'Procurement / Contract for Goods (1.5%)',
                                'rent' => 'House / Office Rent (10.0%)',
                                'consultancy_service' => 'Professional / Technical Consultancy (15.0%)',
                                'transport' => 'Vehicle Hire / Transport Freight (2.5%)',
                                'commission' => 'Agency / Sales Commission (15.0%)',
                            ])
                            ->default('contract_goods')
                            ->required(),
                    ])->columns(3),

                Section::make('Taxable Sum & Withholding Calculation')
                    ->schema([
                        TextInput::make('gross_amount')
                            ->label('Gross Payment Amount (Rs.)')
                            ->numeric()
                            ->prefix('Rs.')
                            ->required(),

                        TextInput::make('tds_rate')
                            ->label('Statutory TDS Rate %')
                            ->numeric()
                            ->suffix('%')
                            ->default(1.50)
                            ->required(),

                        TextInput::make('tds_amount')
                            ->label('TDS Withheld Amount (Rs.)')
                            ->numeric()
                            ->prefix('Rs.')
                            ->required(),
                    ])->columns(3),

                Section::make('IRD Deposit Particulars')
                    ->schema([
                        Select::make('deposit_status')
                            ->label('Deposit Status')
                            ->options([
                                'pending' => 'Pending IRD Deposit',
                                'deposited' => 'Deposited to IRD Revenue Account',
                                'filed' => 'ETDS Return Filed (Annex 1)',
                            ])
                            ->default('pending'),

                        TextInput::make('ird_challan_no')
                            ->label('IRD Challan / Voucher #')
                            ->placeholder('e.g. CH-2083-9941'),

                        DatePicker::make('deposited_at')
                            ->label('Deposit Date'),

                        Textarea::make('notes')
                            ->label('Remarks / Reference')
                            ->columnSpanFull(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('transaction_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('tds_number')
                    ->label('TDS #')
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('transaction_date')
                    ->label('Miti / Date')
                    ->date('Y-m-d')
                    ->sortable(),

                Tables\Columns\TextColumn::make('payee_name')
                    ->label('Payee')
                    ->searchable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('payee_pan')
                    ->label('Payee PAN')
                    ->searchable()
                    ->fontFamily('mono')
                    ->color('gray'),

                Tables\Columns\TextColumn::make('payment_type')
                    ->label('Category')
                    ->badge()
                    ->formatStateUsing(fn($state) => match ($state) {
                        'contract_goods' => 'Goods Contract (1.5%)',
                        'rent' => 'Rent (10%)',
                        'consultancy_service' => 'Consultancy (15%)',
                        'transport' => 'Transport (2.5%)',
                        default => ucfirst(str_replace('_', ' ', (string)$state)),
                    }),

                Tables\Columns\TextColumn::make('gross_amount')
                    ->label('Gross (Rs.)')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('tds_rate')
                    ->label('Rate %')
                    ->suffix('%')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('tds_amount')
                    ->label('TDS (Rs.)')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->alignEnd()
                    ->weight('bold')
                    ->color('danger'),

                Tables\Columns\TextColumn::make('deposit_status')
                    ->label('Deposit')
                    ->badge()
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'deposited',
                        'info' => 'filed',
                    ]),

                Tables\Columns\TextColumn::make('ird_challan_no')
                    ->label('IRD Challan')
                    ->placeholder('Pending')
                    ->fontFamily('mono'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('fiscal_year')
                    ->label('Fiscal Year')
                    ->options(fn() => AccountingFiscalYear::orderBy('start_date', 'desc')->pluck('fiscal_year', 'fiscal_year')->toArray())
                    ->default(fn() => AccountingFiscalYear::getCurrent()?->fiscal_year ?? '2083/84'),

                Tables\Filters\SelectFilter::make('deposit_status')
                    ->label('Deposit Status')
                    ->options([
                        'pending' => 'Pending IRD Deposit',
                        'deposited' => 'Deposited',
                        'filed' => 'Filed',
                    ]),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make(),

                \Filament\Actions\Action::make('record_deposit')
                    ->label('Deposit to IRD')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn(TdsRecord $record) => $record->deposit_status === 'pending')
                    ->form([
                        TextInput::make('ird_challan_no')
                            ->label('IRD Revenue Challan / Voucher #')
                            ->required()
                            ->placeholder('e.g. CH-2083-8491'),

                        DatePicker::make('deposited_at')
                            ->label('Deposit Date')
                            ->required()
                            ->default(now()),
                    ])
                    ->action(function (TdsRecord $record, array $data) {
                        $accTds = Account::where('account_number', '2140')->first() ?: Account::where('account_number', '2720')->firstOrFail();
                        $accBank = Account::where('account_number', '1120')->first() ?: Account::where('account_number', '2410')->firstOrFail();

                        // Post double-entry voucher: Dr TDS Payable, Cr Bank
                        $lines = [
                            [
                                'account_id' => $accTds->id,
                                'account_number' => $accTds->account_number,
                                'description' => "Deposit TDS to IRD for {$record->payee_name} (Challan #{$data['ird_challan_no']})",
                                'debit' => (float)$record->tds_amount,
                                'credit' => 0.00,
                            ],
                            [
                                'account_id' => $accBank->id,
                                'account_number' => $accBank->account_number,
                                'description' => "TDS Tax remittance to IRD Revenue Account",
                                'debit' => 0.00,
                                'credit' => (float)$record->tds_amount,
                            ],
                        ];

                        $entry = app(AccountingService::class)->postJournalEntry([
                            'voucher_date' => $data['deposited_at'],
                            'entry_type' => 'bank',
                            'reference_type' => 'tds_deposit',
                            'reference_id' => $record->id,
                            'description' => "TDS Tax Deposit to IRD Challan #{$data['ird_challan_no']} ({$record->payee_name})",
                            'currency' => 'NPR',
                        ], $lines, auth()->user());

                        $record->update([
                            'deposit_status' => 'deposited',
                            'ird_challan_no' => $data['ird_challan_no'],
                            'deposited_at' => $data['deposited_at'],
                            'journal_entry_id' => $entry->id,
                        ]);

                        Notification::make()
                            ->title('TDS Deposited to IRD')
                            ->body("Challan #{$data['ird_challan_no']} recorded and General Ledger updated.")
                            ->success()
                            ->send();
                    }),
            ])
            ->headerActions([
                \Filament\Actions\Action::make('view_rules')
                    ->label('TDS Statutory Rate Slabs (IRD)')
                    ->icon('heroicon-o-table-cells')
                    ->color('info')
                    ->modalHeading('Statutory TDS Withholding Rates (Nepal Income Tax Act 2058)')
                    ->modalDescription('Configurable rates by fiscal year and transaction category under Sections 87, 88, 88Ka, 89.')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(fn() => view('filament.components.tds-rules-modal', [
                        'rules' => \App\Models\Accounting\TdsRule::orderBy('fiscal_year', 'desc')->orderBy('section')->get(),
                    ])),

                \Filament\Actions\Action::make('export_tds_csv')
                    ->label('Export TDS Register (CSV)')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(fn() => static::exportTdsCsv()),
            ])
            ->emptyStateHeading('No TDS Withholding Records')
            ->emptyStateDescription('TDS records will automatically accumulate when recording supplier bills (Section 89), rental payments (Section 88), or consulting vouchers.')
            ->emptyStateIcon('heroicon-o-receipt-percent')
            ->emptyStateActions([
                \Filament\Actions\Action::make('create_tds_record')
                    ->label('New TDS Withholding Voucher')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->url(fn() => static::getUrl('create')),
            ]);
    }

    public static function exportTdsCsv(): StreamedResponse
    {
        $records = TdsRecord::orderBy('transaction_date', 'asc')->get();

        return response()->streamDownload(function () use ($records) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'TDS Voucher #',
                'Fiscal Year',
                'Date',
                'Payee Name',
                'Payee PAN',
                'Category',
                'Gross Amount (Rs.)',
                'TDS Rate %',
                'TDS Withheld (Rs.)',
                'Deposit Status',
                'IRD Challan #',
                'Deposited Date',
            ]);

            foreach ($records as $r) {
                fputcsv($handle, [
                    $r->tds_number,
                    $r->fiscal_year,
                    $r->transaction_date ? $r->transaction_date->format('Y-m-d') : '',
                    $r->payee_name,
                    $r->payee_pan,
                    $r->payment_type,
                    number_format((float)$r->gross_amount, 2, '.', ''),
                    number_format((float)$r->tds_rate, 2, '.', ''),
                    number_format((float)$r->tds_amount, 2, '.', ''),
                    $r->deposit_status,
                    $r->ird_challan_no ?: 'Pending',
                    $r->deposited_at ? $r->deposited_at->format('Y-m-d') : '',
                ]);
            }

            fclose($handle);
        }, 'Nepal_TDS_Register_' . date('Y_m_d') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTdsRecords::route('/'),
            'create' => Pages\CreateTdsRecord::route('/create'),
        ];
    }
}
