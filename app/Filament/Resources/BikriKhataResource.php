<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\BikriKhataResource\Pages;
use App\Models\Accounting\AccountingFiscalYear;
use App\Models\Accounting\BikriKhataEntry;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use App\Models\Product;
use App\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BikriKhataResource extends Resource
{
    protected static ?string $model = BikriKhataEntry::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static string | \UnitEnum | null $navigationGroup = 'Finance';
    protected static ?string $navigationLabel = 'Bikri Khata (Sales Book)';
    protected static ?int $navigationSort = 20;
    protected static ?string $title = 'Bikri Khata — Statutory Sales Book (IRD Annex 7)';
    protected static ?string $slug = 'bikri-khata';

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Statutory Tax Invoice (Bikri Khata — IRD Annex 7)')
                    ->description('Nepal Inland Revenue Department VAT Act 2052 statutory sales record.')
                    ->schema([
                        TextInput::make('invoice_number')
                            ->label('Invoice #')
                            ->disabled(),

                        DatePicker::make('issue_date')
                            ->label('Invoice Date (Miti)')
                            ->required(),

                        Select::make('fiscal_year')
                            ->label('Fiscal Year (Bikram Sambat)')
                            ->options(
                                AccountingFiscalYear::orderBy('start_date', 'desc')->pluck('fiscal_year', 'fiscal_year')
                            )
                            ->required(),

                        TextInput::make('contact_name')
                            ->label('Buyer / Customer Name')
                            ->required(),

                        TextInput::make('buyer_pan')
                            ->label('Buyer PAN / VAT Number')
                            ->placeholder('9-digit IRD PAN')
                            ->maxLength(20),

                        Select::make('customer_type')
                            ->label('Customer Classification')
                            ->options([
                                'b2c_retail' => 'Retail / Consumer (B2C)',
                                'b2b_corporate' => 'Corporate / Registered Entity (B2B)',
                                'walk_in_pos' => 'Showroom Walk-in',
                                'export_overseas' => 'Overseas Export Client',
                            ])
                            ->default('b2c_retail'),

                        Select::make('sales_channel')
                            ->label('Sales Channel')
                            ->options([
                                'pos_showroom' => '🏬 Showroom POS (Kathmandu)',
                                'web_storefront' => '🌐 E-Commerce Webshop',
                                'concierge_whatsapp' => '💬 WhatsApp Clienteling',
                                'wholesale' => '📦 Wholesale / Bulk Sales',
                            ])
                            ->default('web_storefront'),

                        TextInput::make('branch')
                            ->label('Branch / Location')
                            ->default('Laijau Showroom'),
                    ])->columns(3),

                Section::make('Statutory VAT Breakdown (NAS / IRD)')
                    ->schema([
                        TextInput::make('taxable_amount')
                            ->label('Taxable Amount (NPR)')
                            ->numeric()
                            ->prefix('Rs.')
                            ->default(0.00),

                        TextInput::make('vat_amount')
                            ->label('Output VAT 13% (NPR)')
                            ->numeric()
                            ->prefix('Rs.')
                            ->default(0.00),

                        TextInput::make('exempt_amount')
                            ->label('Exempt Sales (NPR)')
                            ->numeric()
                            ->prefix('Rs.')
                            ->default(0.00),

                        TextInput::make('export_amount')
                            ->label('Export / Zero-Rated (NPR)')
                            ->numeric()
                            ->prefix('Rs.')
                            ->default(0.00),

                        TextInput::make('discount_amount')
                            ->label('Discount (NPR)')
                            ->numeric()
                            ->prefix('Rs.')
                            ->default(0.00),

                        TextInput::make('total_amount')
                            ->label('Total Invoice Amount (NPR)')
                            ->numeric()
                            ->prefix('Rs.')
                            ->required(),
                    ])->columns(3),

                Section::make('Invoice Line Items & Merchandise Breakdown')
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
                                                $grossPrice = (float)$prod->price;
                                                $vatRate = (float)($get('vat_rate') ?? 13.00);
                                                $rateExcl = $vatRate > 0 ? round($grossPrice / (1 + ($vatRate / 100)), 4) : $grossPrice;
                                                $qty = (float)($get('quantity') ?? 1.00);
                                                $lineGross = round($grossPrice * $qty, 2);
                                                $vatAmt = round($lineGross - ($rateExcl * $qty), 2);
                                                $set('unit_price', $rateExcl);
                                                $set('vat_amount', $vatAmt);
                                                $set('total_amount', $lineGross);
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
                                        $vatRate = (float)($get('vat_rate') ?? 13.00);
                                        $taxable = round($qty * $unitPrice, 2);
                                        $vatAmt = round($taxable * ($vatRate / 100), 2);
                                        $total = round($taxable + $vatAmt, 2);
                                        $set('vat_amount', $vatAmt);
                                        $set('total_amount', $total);
                                    })
                                    ->columnSpan(1),

                                TextInput::make('unit_price')
                                    ->label('Rate (Excl. VAT)')
                                    ->numeric()
                                    ->prefix('Rs.')
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        $unitPrice = (float)($state ?? 0.00);
                                        $qty = (float)($get('quantity') ?? 1.00);
                                        $vatRate = (float)($get('vat_rate') ?? 13.00);
                                        $taxable = round($qty * $unitPrice, 2);
                                        $vatAmt = round($taxable * ($vatRate / 100), 2);
                                        $total = round($taxable + $vatAmt, 2);
                                        $set('vat_amount', $vatAmt);
                                        $set('total_amount', $total);
                                    })
                                    ->columnSpan(1),

                                TextInput::make('vat_rate')
                                    ->label('VAT %')
                                    ->numeric()
                                    ->default(13.00)
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        $vatRate = (float)($state ?? 0.00);
                                        $qty = (float)($get('quantity') ?? 1.00);
                                        $unitPrice = (float)($get('unit_price') ?? 0.00);
                                        $taxable = round($qty * $unitPrice, 2);
                                        $vatAmt = round($taxable * ($vatRate / 100), 2);
                                        $total = round($taxable + $vatAmt, 2);
                                        $set('vat_amount', $vatAmt);
                                        $set('total_amount', $total);
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
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Invoice #')
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->color('primary')
                    ->copyable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->colors([
                        'success' => 'sales_invoice',
                        'danger' => 'credit_note',
                    ])
                    ->formatStateUsing(fn($state) => $state === 'credit_note' ? 'Credit Note' : 'Tax Invoice'),

                Tables\Columns\TextColumn::make('contact_name')
                    ->label('Buyer / Customer')
                    ->searchable()
                    ->limit(25)
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('buyer_pan')
                    ->label('Buyer PAN')
                    ->searchable()
                    ->placeholder('Retail B2C')
                    ->fontFamily('mono')
                    ->color('gray'),

                Tables\Columns\TextColumn::make('sales_channel')
                    ->label('Channel')
                    ->badge()
                    ->formatStateUsing(fn(BikriKhataEntry $record) => $record->sales_channel_label),

                Tables\Columns\TextColumn::make('taxable_amount')
                    ->label('Taxable (Rs.)')
                    ->formatStateUsing(function ($state, BikriKhataEntry $record) {
                        $val = (float)$state;
                        if ($val <= 0 && (float)$record->total_amount > 0 && (float)$record->exempt_amount <= 0 && (float)$record->export_amount <= 0) {
                            $val = round((float)$record->total_amount / 1.13, 2);
                        }
                        return \App\Helpers\NepaliNumberHelper::format($val, 2);
                    })
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('vat_amount')
                    ->label('Output VAT 13%')
                    ->formatStateUsing(function ($state, BikriKhataEntry $record) {
                        $val = (float)$state;
                        if ($val <= 0 && (float)$record->total_amount > 0 && (float)$record->exempt_amount <= 0 && (float)$record->export_amount <= 0) {
                            $taxable = round((float)$record->total_amount / 1.13, 2);
                            $val = round((float)$record->total_amount - $taxable, 2);
                        }
                        return \App\Helpers\NepaliNumberHelper::format($val, 2);
                    })
                    ->sortable()
                    ->alignEnd()
                    ->color('warning')
                    ->weight('semibold'),

                Tables\Columns\TextColumn::make('exempt_amount')
                    ->label('Exempt')
                    ->formatStateUsing(fn($state) => \App\Helpers\NepaliNumberHelper::format((float)$state, 2))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('export_amount')
                    ->label('Export')
                    ->formatStateUsing(fn($state) => \App\Helpers\NepaliNumberHelper::format((float)$state, 2))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total (Rs.)')
                    ->formatStateUsing(fn($state) => \App\Helpers\NepaliNumberHelper::format((float)$state, 2))
                    ->sortable()
                    ->alignEnd()
                    ->weight('bold'),

                Tables\Columns\IconColumn::make('posted_to_gl')
                    ->label('GL')
                    ->boolean()
                    ->alignCenter()
                    ->tooltip('Posted to Double-Entry General Ledger'),
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

                Tables\Filters\SelectFilter::make('sales_channel')
                    ->label('Sales Channel')
                    ->options([
                        'pos_showroom' => 'Showroom POS',
                        'web_storefront' => 'E-Commerce Storefront',
                        'concierge_whatsapp' => 'WhatsApp Clienteling',
                        'wholesale' => 'Wholesale',
                    ]),

                Tables\Filters\SelectFilter::make('customer_type')
                    ->label('Customer Type')
                    ->options([
                        'b2c_retail' => 'Retail (B2C)',
                        'b2b_corporate' => 'Corporate (B2B PAN)',
                        'walk_in_pos' => 'Showroom Walk-in',
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
                    ->modalHeading(fn(BikriKhataEntry $record) => "Tax Invoice #{$record->invoice_number} ({$record->contact_name})")
                    ->modalDescription(fn(BikriKhataEntry $record) => "Statutory Nepal Sales Book (IRD Annex 5) • Fiscal Year {$record->fiscal_year}")
                    ->modalContent(fn(BikriKhataEntry $record) => view('filament.components.accounting-inspect-modal', [
                        'record' => $record,
                        'context' => 'sales',
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalWidth('5xl'),

                \Filament\Actions\Action::make('print_receipt')
                    ->label('Receipt')
                    ->icon('heroicon-m-printer')
                    ->color('primary')
                    ->url(function (BikriKhataEntry $record) {
                        if ($record->reference_order_id) {
                            return route('order.pos_receipt', ['order' => $record->reference_order_id]);
                        }
                        if ($record->reference_offline_sale_id) {
                            return route('offline_sales.receipt', ['offlineSale' => $record->reference_offline_sale_id]);
                        }
                        return null;
                    })
                    ->visible(fn(BikriKhataEntry $record) => !empty($record->reference_order_id) || !empty($record->reference_offline_sale_id))
                    ->openUrlInNewTab(),

                \Filament\Actions\Action::make('issue_credit_note')
                    ->label('Sales Return')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->visible(fn(BikriKhataEntry $record) => $record->type === 'sales_invoice')
                    ->form([
                        TextInput::make('return_amount')
                            ->label('Gross Return Amount (Rs.)')
                            ->numeric()
                            ->prefix('Rs.')
                            ->required()
                            ->maxValue(fn(BikriKhataEntry $record) => (float)$record->total_amount)
                            ->default(fn(BikriKhataEntry $record) => (float)$record->total_amount),

                        Textarea::make('reason')
                            ->label('Reason for Credit Note / Return')
                            ->required()
                            ->placeholder('e.g. Customer sizing exchange, damaged luxury box, order return'),
                    ])
                    ->action(function (BikriKhataEntry $record, array $data) {
                        try {
                            /** @var User|null $currentUser */
                            $currentUser = Auth::user();

                            $creditNote = app(AccountingService::class)->recordSalesReturn(
                                $record,
                                (float)$data['return_amount'],
                                (string)$data['reason'],
                                $currentUser
                            );

                            Notification::make()
                                ->title('Credit Note Issued & Posted to GL')
                                ->body("Credit Note #{$creditNote->invoice_number} successfully recorded in Bikri Khata.")
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Credit Note Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->headerActions([
                \Filament\Actions\Action::make('export_annex_7')
                    ->label('Export IRD Annex 7 (CSV)')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(fn() => static::exportAnnex7Csv()),

                \Filament\Actions\Action::make('sync_unposted_sales')
                    ->label('Sync Commerce to Bikri Khata')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->action(function () {
                        $service = app(AccountingService::class);
                        $orders = \App\Models\Order::whereNotIn('status', ['cancelled', 'failed_delivery'])->get();
                        $synced = 0;
                        foreach ($orders as $order) {
                            if (!BikriKhataEntry::where('reference_order_id', $order->id)->exists()) {
                                $service->recordOrderSale($order);
                                $synced++;
                            }
                        }

                        $offlineSales = \App\Models\OfflineSale::where('status', '!=', 'voided')->get();
                        foreach ($offlineSales as $sale) {
                            if (!BikriKhataEntry::where('reference_offline_sale_id', $sale->id)->exists()) {
                                $service->recordOfflineSale($sale);
                                $synced++;
                            }
                        }

                        \Illuminate\Support\Facades\Artisan::call('laijau:reconcile-bikri-khata', ['--live' => true]);

                        Notification::make()
                            ->title('Bikri Khata Synchronized & Reconciled')
                            ->body("{$synced} commerce transactions posted; all statutory VAT and channel mappings reconciled.")
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('No Sales Invoices in Bikri Khata')
            ->emptyStateDescription('No VAT sales entries match your filter. Sync online orders and POS sales or adjust the fiscal year filter.')
            ->emptyStateIcon('heroicon-o-book-open')
            ->emptyStateActions([
                \Filament\Actions\Action::make('sync_sales_empty')
                    ->label('Sync Commerce to Bikri Khata')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->action(function () {
                        $service = app(AccountingService::class);
                        $orders = \App\Models\Order::whereNotIn('status', ['cancelled', 'failed_delivery'])->get();
                        $synced = 0;
                        foreach ($orders as $order) {
                            if (!BikriKhataEntry::where('reference_order_id', $order->id)->exists()) {
                                $service->recordOrderSale($order);
                                $synced++;
                            }
                        }
                        $offlineSales = \App\Models\OfflineSale::where('status', '!=', 'voided')->get();
                        foreach ($offlineSales as $sale) {
                            if (!BikriKhataEntry::where('reference_offline_sale_id', $sale->id)->exists()) {
                                $service->recordOfflineSale($sale);
                                $synced++;
                            }
                        }

                        \Illuminate\Support\Facades\Artisan::call('laijau:reconcile-bikri-khata', ['--live' => true]);

                        Notification::make()
                            ->title('Bikri Khata Synchronized & Reconciled')
                            ->body("{$synced} commerce transactions posted; all statutory VAT and channel mappings reconciled.")
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function exportAnnex7Csv(): StreamedResponse
    {
        $entries = BikriKhataEntry::orderBy('issue_date', 'asc')->get();

        return response()->streamDownload(function () use ($entries) {
            $handle = fopen('php://output', 'w');

            // Official IRD Annex 7 Headers
            fputcsv($handle, [
                'Date (Miti)',
                'Invoice Number',
                'Buyer Name',
                'Buyer PAN',
                'Customer Type',
                'Sales Channel',
                'Taxable Amount (Rs.)',
                '13% Output VAT (Rs.)',
                'Exempt Amount (Rs.)',
                'Export Amount (Rs.)',
                'Total Amount (Rs.)',
                'Doc Type',
                'GL Status',
            ]);

            foreach ($entries as $e) {
                $total = (float)$e->total_amount;
                $taxable = (float)$e->taxable_amount;
                $vat = (float)$e->vat_amount;
                if ($taxable <= 0 && $total > 0 && (float)$e->exempt_amount <= 0 && (float)$e->export_amount <= 0) {
                    $taxable = round($total / 1.13, 2);
                    $vat = round($total - $taxable, 2);
                }

                fputcsv($handle, [
                    $e->issue_date ? $e->issue_date->format('Y-m-d') : '',
                    $e->invoice_number,
                    $e->contact_name,
                    $e->buyer_pan ?: 'N/A',
                    $e->customer_type_label,
                    $e->sales_channel_label,
                    number_format($taxable, 2, '.', ''),
                    number_format($vat, 2, '.', ''),
                    number_format((float)$e->exempt_amount, 2, '.', ''),
                    number_format((float)$e->export_amount, 2, '.', ''),
                    number_format($total, 2, '.', ''),
                    $e->type,
                    $e->posted_to_gl ? 'Posted' : 'Unposted',
                ]);
            }

            fclose($handle);
        }, 'Bikri_Khata_Annex_7_' . date('Y_m_d') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBikriKhataEntries::route('/'),
        ];
    }
}
