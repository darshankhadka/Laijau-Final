<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpenseClaimResource\Pages;
use App\Models\Accounting\Account;
use App\Models\Hrm\Employee;
use App\Models\Hrm\ExpenseClaim;
use App\Services\Hrm\ExpenseService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExpenseClaimResource extends Resource
{
    protected static ?string $model = ExpenseClaim::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-receipt-percent';
    protected static string | \UnitEnum | null $navigationGroup = 'Finance';
    protected static ?string $navigationLabel = 'Expenses';
    protected static ?int $navigationSort = 60;

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Expense Claim & Reimbursement')
                    ->description('Nepal Inland Revenue Department (IRD) compliance, VAT deduction & expense calculation')
                    ->schema([
                        TextInput::make('claim_number')
                            ->label('Claim Reference')
                            ->default(fn() => ExpenseClaim::generateNextClaimNumber())
                            ->disabled(),

                        Select::make('employee_id')
                            ->label('Employee')
                            ->options(Employee::where('status', 'active')->get()->pluck('full_name', 'id'))
                            ->searchable()
                            ->required(),

                        TextInput::make('title')
                            ->label('Description / Title')
                            ->placeholder('e.g. Packaging, workshop and showroom supplies')
                            ->required(),

                        DatePicker::make('expense_date')
                            ->label('Date of Expense')
                            ->default(now())
                            ->required(),

                        Select::make('category')
                            ->label('Expense Category')
                            ->options([
                                'office' => 'Office Supplies, Cloud & Telecom (Account 6180)',
                                'packaging_supplies' => 'Packaging, Bags & Brand Boxes (Account 6160)',
                                'travel' => 'Courier, Delivery & Transport (Account 6150)',
                                'transport_mileage' => 'Local Travel & Mileage Reimbursement (Account 6150)',
                                'meals_representation' => 'Showroom Operations & Refreshments (Account 6200)',
                                'utilities' => 'Electricity & Water Utilities (Account 6140)',
                                'maintenance' => 'Showroom Cleaning & Maintenance (Account 6200)',
                                'legal' => 'Legal, Audit & Professional Fees (Account 6210)',
                                'other' => 'Other Operating Expenses (Account 6200)',
                            ])
                            ->default('office')
                            ->required(),

                        Select::make('ledger_account_id')
                            ->label('Chart of Accounts Placement')
                            ->options(fn() => Account::where('account_type', 'expense')->orderBy('account_number')->get()->mapWithKeys(fn($acc) => [$acc->id => "{$acc->account_number} - {$acc->name}"]))
                            ->searchable()
                            ->nullable(),

                        TextInput::make('gross_amount_npr')
                            ->label('Gross Amount (Incl. 13% VAT)')
                            ->numeric()
                            ->prefix('Rs.')
                            ->required(),

                        TextInput::make('vat_rate')
                            ->label('VAT Rate %')
                            ->numeric()
                            ->default(13.0),

                        TextInput::make('mileage_km')
                            ->label('Mileage (KM traveled)')
                            ->numeric()
                            ->placeholder('e.g. 45'),

                        FileUpload::make('receipt_path')
                            ->label('Receipt / Document Upload (Bill/Invoice)')
                            ->disk('public')
                            ->directory('hrm/receipts')
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(10240)
                            ->columnSpanFull(),

                        Textarea::make('notes')
                            ->label('Purpose & Internal Notes')
                            ->columnSpanFull(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('claim_number')
                    ->label('Claim #')
                    ->weight('bold')
                    ->fontFamily('mono')
                    ->searchable(),

                TextColumn::make('employee.full_name')
                    ->label('Employee')
                    ->searchable(),

                TextColumn::make('title')
                    ->label('Description')
                    ->limit(30),

                TextColumn::make('category')
                    ->label('Category')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('expense_date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('gross_amount_npr')
                    ->label('Gross Amount')
                    ->money('NPR')
                    ->sortable(),

                TextColumn::make('vat_amount_npr')
                    ->label('VAT (13%)')
                    ->money('NPR'),

                TextColumn::make('net_amount_npr')
                    ->label('Net Amount')
                    ->money('NPR'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'reimbursed' => 'success',
                        'approved' => 'info',
                        'submitted' => 'warning',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('expense_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('employee_id')
                    ->label('Employee')
                    ->relationship('employee', 'first_name'),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'submitted' => 'Submitted / Pending',
                        'approved' => 'Approved',
                        'reimbursed' => 'Reimbursed',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->actions([
                \Filament\Actions\Action::make('approve_reimburse')
                    ->label('Approve & Post')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve & Reimburse Expense Claim')
                    ->modalDescription('This will create an official accounting journal voucher (JV-XXXX) with Input VAT debit (Account 2130) and bank disbursement credit (Account 1120).')
                    ->visible(fn($record) => in_array($record->status, ['submitted', 'approved']))
                    ->action(function (ExpenseClaim $record) {
                        /** @var \App\Models\User|null $currentUser */
                        $currentUser = \Illuminate\Support\Facades\Auth::user();
                        $entry = app(ExpenseService::class)->approveAndReimburse($record, $currentUser);
                        Notification::make()
                            ->title('Expense Claim Reimbursed')
                            ->body("Created voucher {$entry->entry_number}.")
                            ->success()
                            ->send();
                    }),

                \Filament\Actions\EditAction::make(),
            ])
            ->emptyStateHeading('No Employee Expense Claims')
            ->emptyStateDescription('Track employee out-of-pocket expenses, showroom maintenance, local travel, or packaging supplies.')
            ->emptyStateIcon('heroicon-o-document-text')
            ->emptyStateActions([
                \Filament\Actions\Action::make('new_expense_claim')
                    ->label('Submit Expense Claim')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->url(fn() => static::getUrl('create')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExpenseClaims::route('/'),
            'create' => Pages\CreateExpenseClaim::route('/create'),
            'edit' => Pages\EditExpenseClaim::route('/{record}/edit'),
        ];
    }
}
