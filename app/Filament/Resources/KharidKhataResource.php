<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\KharidKhataResource\Pages;
use App\Models\Accounting\AccountingFiscalYear;
use App\Models\Accounting\KharidKhataEntry;
use App\Models\Inventory\Supplier;
use App\Models\Product;
use App\Services\Accounting\AccountingService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KharidKhataResource extends Resource
{
    protected static ?string $model = KharidKhataEntry::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-shopping-bag';
    protected static string | \UnitEnum | null $navigationGroup = 'Finance';
    protected static ?string $navigationLabel = 'Kharid Khata (Purchase Book)';
    protected static ?int $navigationSort = 30;
    protected static ?string $title = 'Kharid Khata — Statutory Purchase Book (IRD Annex 8)';
    protected static ?string $slug = 'kharid-khata';

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Supplier Invoice & Tax Particulars (IRD Annex 8)')
                    ->description('Record procurement bills with Nepal Inland Revenue Department compliance.')
                    ->schema([
                        TextInput::make('invoice_number')
                            ->label('Supplier Tax Invoice #')
                            ->required()
                            ->placeholder('e.g. TI-84920'),

                        DatePicker::make('issue_date')
                            ->label('Bill Date (Miti)')
                            ->required()
                            ->default(now()),

                        DatePicker::make('due_date')
                            ->label('Payment Due Date')
                            ->default(now()->addDays(30)),

                        Select::make('fiscal_year')
                            ->label('Fiscal Year (Bikram Sambat)')
                            ->options(
                                AccountingFiscalYear::orderBy('start_date', 'desc')->pluck('fiscal_year', 'fiscal_year')
                            )
                            ->default(fn() => AccountingFiscalYear::getCurrent()?->fiscal_year ?? '2083/84')
                            ->required(),

                        TextInput::make('contact_name')
                            ->label('Supplier / Vendor Name')
                            ->required()
                            ->placeholder('e.g. Pashmina Crafts Nepal Pvt. Ltd.'),

                        TextInput::make('seller_pan')
                            ->label('Supplier PAN / VAT Number')
                            ->placeholder('9-digit statutory PAN')
                            ->maxLength(20),

                        Select::make('purchase_type')
                            ->label('Purchase Classification')
                            ->options([
                                'merchandise' => 'Merchandise Procurement (Stock / Raw Material)',
                                'local_taxable_13' => 'Local Taxable Purchase (13% VAT)',
                                'local_exempt' => 'Local Exempt Purchase (0% VAT)',
                                'import_customs' => 'Overseas Import Consignment (Customs)',
                                'capital_fixed_asset' => 'Capital / Fixed Asset Purchase',
                                'administrative_service' => 'Service / Operational Procurement',
                            ])
                            ->default('merchandise')
                            ->required(),

                        Select::make('payment_status')
                            ->label('Settlement / Payment Status')
                            ->options([
                                'paid' => 'Settled / Paid',
                                'unpaid' => 'Unpaid / Outstanding Payable',
                                'partial' => 'Partially Paid',
                            ])
                            ->default('paid'),

                        Toggle::make('is_credit')
                            ->label('Credit Purchase (Accounts Payable)')
                            ->default(false),

                        TextInput::make('branch')
                            ->label('Receiving Warehouse / Branch')
                            ->default('Laijau Showroom'),

                        TextInput::make('reference_purchase_order_id')
                            ->label('Reference PO #')
                            ->disabled()
                            ->visible(fn(?KharidKhataEntry $record) => filled($record?->reference_purchase_order_id)),
                    ])->columns(3),

                Section::make('Statutory Amount & Input Tax Credit (13%)')
                    ->schema([
                        TextInput::make('taxable_amount')
                            ->label('Taxable Amount (Rs.)')
                            ->numeric()
                            ->prefix('Rs.')
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $taxData = \App\Services\TaxCalculatorService::calcExclusive((float)$state);
                                $set('vat_amount', $taxData['vat_amount']);
                                $set('total_amount', $taxData['gross_amount']);
                            }),

                        TextInput::make('vat_amount')
                            ->label('13% Input VAT Credit (Rs.)')
                            ->numeric()
                            ->prefix('Rs.')
                            ->required()
                            ->helperText('Deductible statutory VAT credit against output sales tax'),

                        TextInput::make('exempt_amount')
                            ->label('Exempt Amount (Rs.)')
                            ->numeric()
                            ->prefix('Rs.')
                            ->default(0.00),

                        TextInput::make('total_amount')
                            ->label('Gross Bill Total (Rs.)')
                            ->numeric()
                            ->prefix('Rs.')
                            ->required(),
                    ])->columns(4),

                Section::make('Nepal TDS (Tax Withholding at Source)')
                    ->description('Statutory deduction under Nepal Income Tax Act 2058.')
                    ->schema([
                        Toggle::make('tds_applicable')
                            ->label('Apply Statutory TDS')
                            ->reactive(),

                        Select::make('tds_rate')
                            ->label('TDS Withholding Rate')
                            ->options([
                                '1.50' => '1.5% — Supply of Goods / Procurement Contract',
                                '10.00' => '10.0% — Premises Rental Lease',
                                '15.00' => '15.0% — Professional / Consultancy Services',
                            ])
                            ->default('1.50')
                            ->visible(fn(callable $get) => (bool)$get('tds_applicable'))
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                $taxable = (float)$get('taxable_amount');
                                $rate = (float)$state;
                                $set('tds_amount', round($taxable * ($rate / 100), 2));
                            }),

                        TextInput::make('tds_amount')
                            ->label('TDS Amount Withheld (Rs.)')
                            ->numeric()
                            ->prefix('Rs.')
                            ->visible(fn(callable $get) => (bool)$get('tds_applicable')),
                    ])->columns(3),

                Section::make('Audit Trail & Bill Remarks')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Bill Remarks / Purchase Audit Notes')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])->collapsible(),

                Section::make('Line Items & Bill Breakdown')
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Select::make('product_id')
                                    ->label('Product / Item')
                                    ->searchable()
                                    ->getSearchResultsUsing(fn(string $search): array => Product::where('name', 'like', "%{$search}%")
                                        ->orWhere('sku', 'like', "%{$search}%")
                                        ->orWhere('barcode', 'like', "%{$search}%")
                                        ->limit(50)
                                        ->pluck('name', 'id')
                                        ->toArray()
                                    )
                                    ->getOptionLabelUsing(fn($value): ?string => Product::find($value)?->name)
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        if ($state) {
                                            $prod = Product::find($state);
                                            if ($prod) {
                                                $set('description', $prod->name . ($prod->sku ? " ({$prod->sku})" : ''));
                                                $costPrice = (float)$prod->cost_price;
                                                $set('unit_price', $costPrice);
                                                $qty = (float)($get('quantity') ?? 1.00);
                                                $vatRate = (float)($get('vat_rate') ?? 0.00);
                                                $taxable = round($qty * $costPrice, 2);
                                                $vatAmt = round($taxable * ($vatRate / 100), 2);
                                                $set('vat_amount', $vatAmt);
                                                $set('total_amount', round($taxable + $vatAmt, 2));
                                            }
                                        }
                                    })
                                    ->columnSpan(3),

                                TextInput::make('description')
                                    ->label('Item Description')
                                    ->required()
                                    ->columnSpan(3),

                                TextInput::make('quantity')
                                    ->label('Quantity')
                                    ->numeric()
                                    ->default(1.00)
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        $qty = (float)($state ?? 1.00);
                                        $unitPrice = (float)($get('unit_price') ?? 0.00);
                                        $vatRate = (float)($get('vat_rate') ?? 0.00);
                                        $taxable = round($qty * $unitPrice, 2);
                                        $vatAmt = round($taxable * ($vatRate / 100), 2);
                                        $set('vat_amount', $vatAmt);
                                        $set('total_amount', round($taxable + $vatAmt, 2));
                                    })
                                    ->columnSpan(1),

                                TextInput::make('unit_price')
                                    ->label('Unit Cost (Rs.)')
                                    ->numeric()
                                    ->prefix('Rs.')
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        $unitPrice = (float)($state ?? 0.00);
                                        $qty = (float)($get('quantity') ?? 1.00);
                                        $vatRate = (float)($get('vat_rate') ?? 0.00);
                                        $taxable = round($qty * $unitPrice, 2);
                                        $vatAmt = round($taxable * ($vatRate / 100), 2);
                                        $set('vat_amount', $vatAmt);
                                        $set('total_amount', round($taxable + $vatAmt, 2));
                                    })
                                    ->columnSpan(1),

                                TextInput::make('vat_rate')
                                    ->label('VAT %')
                                    ->numeric()
                                    ->default(0.00)
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        $vatRate = (float)($state ?? 0.00);
                                        $qty = (float)($get('quantity') ?? 1.00);
                                        $unitPrice = (float)($get('unit_price') ?? 0.00);
                                        $taxable = round($qty * $unitPrice, 2);
                                        $vatAmt = round($taxable * ($vatRate / 100), 2);
                                        $set('vat_amount', $vatAmt);
                                        $set('total_amount', round($taxable + $vatAmt, 2));
                                    })
                                    ->columnSpan(1),

                                TextInput::make('vat_amount')
                                    ->label('VAT (Rs.)')
                                    ->numeric()
                                    ->prefix('Rs.')
                                    ->columnSpan(1),

                                TextInput::make('total_amount')
                                    ->label('Line Total (Rs.)')
                                    ->numeric()
                                    ->prefix('Rs.')
                                    ->required()
                                    ->columnSpan(1),
                            ])
                            ->columns(11)
                            ->columnSpanFull()
                            ->addable(fn(string $context) => $context !== 'view')
                            ->deletable(fn(string $context) => $context !== 'view')
                            ->reorderable(false),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('issue_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('issue_date')
                    ->label('Miti / Date')
                    ->date('Y-m-d')
                    ->sortable(),

                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Bill #')
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->color('primary')
                    ->copyable(),

                Tables\Columns\TextColumn::make('contact_name')
                    ->label('Supplier / Vendor')
                    ->searchable()
                    ->limit(25)
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('seller_pan')
                    ->label('Supplier PAN')
                    ->searchable()
                    ->fontFamily('mono')
                    ->color('gray'),

                Tables\Columns\TextColumn::make('purchase_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn(KharidKhataEntry $record) => $record->purchase_type_label),

                Tables\Columns\TextColumn::make('taxable_amount')
                    ->label('Taxable (Rs.)')
                    ->formatStateUsing(fn($state) => \App\Helpers\NepaliNumberHelper::format((float)$state, 2))
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('vat_amount')
                    ->label('Input VAT 13%')
                    ->formatStateUsing(fn($state) => \App\Helpers\NepaliNumberHelper::format((float)$state, 2))
                    ->sortable()
                    ->alignEnd()
                    ->color('info')
                    ->weight('semibold')
                    ->tooltip('Statutory Input Tax Credit for Nepal VAT Return'),

                Tables\Columns\TextColumn::make('tds_amount')
                    ->label('TDS Withheld')
                    ->formatStateUsing(fn($state) => \App\Helpers\NepaliNumberHelper::format((float)$state, 2))
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Gross Total (Rs.)')
                    ->formatStateUsing(fn($state) => \App\Helpers\NepaliNumberHelper::format((float)$state, 2))
                    ->sortable()
                    ->alignEnd()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->colors([
                        'success' => 'paid',
                        'warning' => 'unpaid',
                    ]),

                Tables\Columns\IconColumn::make('posted_to_gl')
                    ->label('GL')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('fiscal_year')
                    ->label('Fiscal Year')
                    ->options(fn() => AccountingFiscalYear::orderBy('start_date', 'desc')->pluck('fiscal_year', 'fiscal_year')->toArray()),

                Tables\Filters\Filter::make('period_filter')
                    ->label('Time Period')
                    ->form([
                        Select::make('period')
                            ->label('Period')
                            ->options([
                                'all_time' => 'All Time',
                                'today' => 'Today',
                                'yesterday' => 'Yesterday',
                                'this_month' => 'This Month',
                                'last_month' => 'Last Month',
                                'jan_2026' => 'January 2026',
                                'feb_2026' => 'February 2026',
                                'mar_2026' => 'March 2026',
                                'apr_2026' => 'April 2026',
                                'may_2026' => 'May 2026',
                                'jun_2026' => 'June 2026',
                                'jul_2026' => 'July 2026',
                                'aug_2026' => 'August 2026',
                                'sep_2026' => 'September 2026',
                            ])
                            ->default('all_time'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['period'] ?? null) {
                            'today' => $query->whereDate('issue_date', now()->toDateString()),
                            'yesterday' => $query->whereDate('issue_date', now()->subDay()->toDateString()),
                            'this_month' => $query->whereBetween('issue_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()]),
                            'last_month' => $query->whereBetween('issue_date', [now()->subMonth()->startOfMonth()->toDateString(), now()->subMonth()->endOfMonth()->toDateString()]),
                            'jan_2026' => $query->whereBetween('issue_date', ['2026-01-01', '2026-01-31']),
                            'feb_2026' => $query->whereBetween('issue_date', ['2026-02-01', '2026-02-28']),
                            'mar_2026' => $query->whereBetween('issue_date', ['2026-03-01', '2026-03-31']),
                            'apr_2026' => $query->whereBetween('issue_date', ['2026-04-01', '2026-04-30']),
                            'may_2026' => $query->whereBetween('issue_date', ['2026-05-01', '2026-05-31']),
                            'jun_2026' => $query->whereBetween('issue_date', ['2026-06-01', '2026-06-30']),
                            'jul_2026' => $query->whereBetween('issue_date', ['2026-07-01', '2026-07-31']),
                            'aug_2026' => $query->whereBetween('issue_date', ['2026-08-01', '2026-08-31']),
                            'sep_2026' => $query->whereBetween('issue_date', ['2026-09-01', '2026-09-30']),
                            default => $query,
                        };
                    }),

                Tables\Filters\SelectFilter::make('purchase_type')
                    ->label('Purchase Type')
                    ->options([
                        'merchandise' => 'Merchandise Procurement',
                        'local_taxable_13' => 'Local Taxable (13%)',
                        'local_exempt' => 'Local Exempt',
                        'import_customs' => 'Customs Import',
                        'capital_fixed_asset' => 'Capital Asset',
                        'administrative_service' => 'Service',
                    ]),

                Tables\Filters\Filter::make('date_range')
                    ->form([
                        DatePicker::make('from_date')->label('From Date'),
                        DatePicker::make('to_date')->label('To Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from_date'] ?? null, fn($q, $date) => $q->whereDate('issue_date', '>=', $date))
                            ->when($data['to_date'] ?? null, fn($q, $date) => $q->whereDate('issue_date', '<=', $date));
                    }),
            ])
            ->actions([
                \Filament\Actions\Action::make('inspect')
                    ->label('Inspect')
                    ->icon('heroicon-m-eye')
                    ->color('primary')
                    ->modalHeading(fn(KharidKhataEntry $record) => "Kharid Khata Bill #{$record->invoice_number} ({$record->contact_name})")
                    ->modalDescription(fn(KharidKhataEntry $record) => "Statutory Nepal Purchase Book (IRD Annex 8) • Fiscal Year {$record->fiscal_year}")
                    ->modalContent(fn(KharidKhataEntry $record) => view('filament.components.accounting-inspect-modal', [
                        'record' => $record,
                        'context' => 'purchase',
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalWidth('5xl'),

                \Filament\Actions\Action::make('issue_debit_note')
                    ->label('Purchase Return')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->visible(fn(KharidKhataEntry $record) => $record->type === 'supplier_bill')
                    ->form([
                        TextInput::make('return_amount')
                            ->label('Gross Return Amount (Rs.)')
                            ->numeric()
                            ->prefix('Rs.')
                            ->required()
                            ->maxValue(fn(KharidKhataEntry $record) => (float)$record->total_amount)
                            ->default(fn(KharidKhataEntry $record) => (float)$record->total_amount),

                        Textarea::make('reason')
                            ->label('Reason for Debit Note / Return')
                            ->required()
                            ->placeholder('e.g. Returned rejected shipment, fabric flaw, supplier discount adjustment'),
                    ])
                    ->action(function (KharidKhataEntry $record, array $data) {
                        try {
                            /** @var \App\Models\User|null $currentUser */
                            $currentUser = \Illuminate\Support\Facades\Auth::user();
                            $debitNote = app(AccountingService::class)->recordDebitNote(
                                $record,
                                (float)$data['return_amount'],
                                (string)$data['reason'],
                                $currentUser
                            );

                            Notification::make()
                                ->title('Debit Note Issued & Posted to GL')
                                ->body("Debit Note #{$debitNote->invoice_number} successfully recorded in Kharid Khata.")
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Debit Note Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->headerActions([
                \Filament\Actions\Action::make('export_annex_8')
                    ->label('Export IRD Annex 8 (CSV)')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(fn() => static::exportAnnex8Csv()),
            ])
            ->emptyStateHeading('No Purchase Bills in Kharid Khata')
            ->emptyStateDescription('Record vendor bills, raw cashmere purchases, packaging supplies, or import invoices with 13% Input VAT.')
            ->emptyStateIcon('heroicon-o-shopping-bag')
            ->emptyStateActions([
                \Filament\Actions\Action::make('create_purchase_bill')
                    ->label('Record Purchase Bill')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->url(fn() => static::getUrl('create')),
            ]);
    }

    public static function exportAnnex8Csv(): StreamedResponse
    {
        $entries = KharidKhataEntry::orderBy('issue_date', 'asc')->get();

        return response()->streamDownload(function () use ($entries) {
            $handle = fopen('php://output', 'w');

            // Official IRD Annex 8 Headers
            fputcsv($handle, [
                'Date (Miti)',
                'Bill Number',
                'Supplier Name',
                'Supplier PAN',
                'Purchase Type',
                'Taxable Purchase (Rs.)',
                '13% Input VAT Credit (Rs.)',
                'Exempt Amount (Rs.)',
                'TDS Withheld (Rs.)',
                'Total Amount (Rs.)',
                'Doc Type',
                'GL Status',
            ]);

            foreach ($entries as $e) {
                fputcsv($handle, [
                    $e->issue_date ? $e->issue_date->format('Y-m-d') : '',
                    $e->invoice_number,
                    $e->contact_name,
                    $e->seller_pan ?: 'N/A',
                    $e->purchase_type_label,
                    number_format((float)$e->taxable_amount, 2, '.', ''),
                    number_format((float)$e->vat_amount, 2, '.', ''),
                    number_format((float)$e->exempt_amount, 2, '.', ''),
                    number_format((float)$e->tds_amount, 2, '.', ''),
                    number_format((float)$e->total_amount, 2, '.', ''),
                    $e->type,
                    $e->posted_to_gl ? 'Posted' : 'Unposted',
                ]);
            }

            fclose($handle);
        }, 'Kharid_Khata_Annex_8_' . date('Y_m_d') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListKharidKhataEntries::route('/'),
            'create' => Pages\CreateKharidKhataEntry::route('/create'),
        ];
    }
}
