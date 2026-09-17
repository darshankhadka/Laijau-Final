<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccountingInvoiceResource\Pages;
use App\Models\Accounting\Account;
use App\Models\Accounting\AccountingInvoice;
use App\Models\Product;
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

class AccountingInvoiceResource extends Resource
{
    protected static ?string $model = AccountingInvoice::class;

    protected static bool $shouldRegisterNavigation = false;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-receipt-percent';
    protected static string | \UnitEnum | null $navigationGroup = 'Finance';
    protected static ?string $navigationLabel = 'Invoices & Supplier Bills';
    protected static ?int $navigationSort = 40;

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Invoice & Bill Details')
                    ->schema([
                        Select::make('type')
                            ->label('Invoice Type')
                            ->required()
                            ->default('sales_invoice')
                            ->options([
                                'sales_invoice' => 'Sales Invoice (Customer)',
                                'supplier_bill' => 'Supplier Bill (Purchase / Expense)',
                                'credit_note' => 'Credit Note',
                            ]),

                        TextInput::make('invoice_number')
                            ->label('Invoice / Bill #')
                            ->disabled()
                            ->placeholder('Auto-generated (e.g. INV-2026-0001)'),

                        DatePicker::make('issue_date')
                            ->label('Issue Date')
                            ->required()
                            ->default(now()),

                        DatePicker::make('due_date')
                            ->label('Due Date')
                            ->default(now()->addDays(14)),

                        TextInput::make('contact_name')
                            ->label('Customer / Supplier Name')
                            ->required(),

                        TextInput::make('contact_cvr')
                            ->label('PAN / VAT Number (Nepal)')
                            ->placeholder('e.g. 601234567'),

                        TextInput::make('contact_email')
                            ->label('Email')
                            ->email(),

                        TextInput::make('currency')
                            ->label('Currency')
                            ->default('NPR')
                            ->maxLength(3),

                        Select::make('payment_status')
                            ->label('Payment Status')
                            ->required()
                            ->default('unpaid')
                            ->options([
                                'unpaid' => 'Unpaid',
                                'partial' => 'Partially Paid',
                                'paid' => 'Paid',
                                'overdue' => 'Overdue',
                                'cancelled' => 'Cancelled',
                            ]),
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

                                Select::make('account_id')
                                    ->label('Ledger Account')
                                    ->searchable()
                                    ->options(Account::where('is_active', true)->orderBy('account_number')->get()->pluck('display_name', 'id'))
                                    ->columnSpan(2),

                                TextInput::make('description')
                                    ->label('Description')
                                    ->required()
                                    ->columnSpan(3),

                                TextInput::make('quantity')
                                    ->label('Quantity')
                                    ->numeric()
                                    ->default(1.00)
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
                                    ->label('Unit Price (excl. VAT)')
                                    ->numeric()
                                    ->default(0.00)
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
                                    ->label('VAT Rate %')
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
                                    ->label('VAT Amount (Rs.)')
                                    ->numeric()
                                    ->columnSpan(1),

                                TextInput::make('total_amount')
                                    ->label('Line Total (Rs.)')
                                    ->numeric()
                                    ->required()
                                    ->columnSpan(1),
                            ])
                            ->columns(13)
                            ->defaultItems(1)
                            ->columnSpanFull()
                            ->reorderable(false),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('issue_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Invoice #')
                    ->sortable()
                    ->searchable()
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->colors([
                        'success' => 'sales_invoice',
                        'warning' => 'supplier_bill',
                        'danger' => 'credit_note',
                    ])
                    ->formatStateUsing(fn($state) => match ($state) {
                        'sales_invoice' => 'Sales',
                        'supplier_bill' => 'Supplier Bill',
                        'credit_note' => 'Credit Note',
                        default => ucfirst((string)$state),
                    }),

                Tables\Columns\TextColumn::make('contact_name')
                    ->label('Party')
                    ->searchable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('contact_cvr')
                    ->label('PAN/VAT')
                    ->searchable()
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('issue_date')
                    ->label('Issue Date')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('due_date')
                    ->label('Due Date')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total (incl. VAT)')
                    ->sortable()
                    ->weight('bold')
                    ->formatStateUsing(fn($record) => number_format((float)$record->total_amount, 2, '.', ',') . ' ' . $record->currency),

                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'success' => 'paid',
                        'danger' => fn($state) => in_array($state, ['unpaid', 'overdue']),
                        'warning' => 'partial',
                        'gray' => 'cancelled',
                    ])
                    ->formatStateUsing(fn($state) => match ($state) {
                        'paid' => 'Paid',
                        'unpaid' => 'Unpaid',
                        'partial' => 'Partially Paid',
                        'overdue' => 'Overdue',
                        'cancelled' => 'Cancelled',
                        default => ucfirst((string)$state),
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Type')
                    ->options([
                        'sales_invoice' => 'Sales Invoice',
                        'supplier_bill' => 'Supplier Bill',
                        'credit_note' => 'Credit Note',
                    ]),

                Tables\Filters\SelectFilter::make('payment_status')
                    ->label('Payment Status')
                    ->options([
                        'unpaid' => 'Unpaid',
                        'paid' => 'Paid',
                        'partial' => 'Partially Paid',
                        'overdue' => 'Overdue',
                    ]),
            ])
            ->actions([
                \Filament\Actions\Action::make('inspect')
                    ->label('Inspect')
                    ->icon('heroicon-m-eye')
                    ->color('primary')
                    ->modalHeading(fn(AccountingInvoice $record) => "Invoice #{$record->invoice_number} ({$record->contact_name})")
                    ->modalDescription(fn(AccountingInvoice $record) => "Tax Document • Fiscal Year {$record->fiscal_year}")
                    ->modalContent(fn(AccountingInvoice $record) => view('filament.components.accounting-inspect-modal', [
                        'record' => $record,
                        'context' => $record->type === 'supplier_bill' ? 'purchase' : 'sales',
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalWidth('5xl'),

                \Filament\Actions\Action::make('mark_paid')
                    ->label('Mark as Paid')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn($record) => $record->payment_status !== 'paid')
                    ->requiresConfirmation()
                    ->action(function (AccountingInvoice $record) {
                        $record->update([
                            'payment_status' => 'paid',
                            'paid_amount' => $record->total_amount,
                        ]);
                        Notification::make()
                            ->title('Payment Registered')
                            ->body("Invoice {$record->invoice_number} has been marked as paid.")
                            ->success()
                            ->send();
                    }),

                \Filament\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccountingInvoices::route('/'),
            'create' => Pages\CreateAccountingInvoice::route('/create'),
            'edit' => Pages\EditAccountingInvoice::route('/{record}/edit'),
        ];
    }
}
