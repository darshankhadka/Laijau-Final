<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PayrollRunResource\Pages;
use App\Models\Accounting\BankAccount;
use App\Models\Hrm\PayrollRun;
use App\Services\Hrm\PayrollPreparationService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PayrollRunResource extends Resource
{
    protected static ?string $model = PayrollRun::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-banknotes';
    protected static string | \UnitEnum | null $navigationGroup = 'People';
    protected static ?string $navigationLabel = 'Payroll';
    protected static ?int $navigationSort = 30;

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Nepal Monthly Payroll Header')
                    ->description('Monthly salary processing with Basic Salary, Allowances, SSF (Social Security Fund), and TDS deductions.')
                    ->schema([
                        TextInput::make('run_number')
                            ->label('Run Reference #')
                            ->default(fn() => 'PAY-' . date('Y-m'))
                            ->required(),

                        Select::make('fiscal_year')
                            ->label('Nepal Fiscal Year')
                            ->options([
                                '2083/84' => 'FY 2083/84 (2026/27)',
                                '2082/83' => 'FY 2082/83 (2025/26)',
                                '2081/82' => 'FY 2081/82 (2024/25)',
                            ])
                            ->default('2082/83')
                            ->required(),

                        TextInput::make('name')
                            ->label('Payroll Period Name')
                            ->default('Salary — ' . date('F Y'))
                            ->required(),

                        DatePicker::make('period_start')
                            ->label('Period Start')
                            ->default(now()->startOfMonth())
                            ->required(),

                        DatePicker::make('period_end')
                            ->label('Period End')
                            ->default(now()->endOfMonth())
                            ->required(),

                        DatePicker::make('pay_date')
                            ->label('Disbursement Pay Date')
                            ->default(now()->endOfMonth())
                            ->required(),

                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'draft' => 'Draft',
                                'calculated' => 'Calculated',
                                'approved' => 'Approved',
                                'posted_to_accounting' => 'Posted to Accounting',
                                'paid' => 'Disbursed / Paid',
                            ])
                            ->default('draft')
                            ->required(),
                    ])->columns(3),

                Section::make('Statutory Nepal Payroll Summary (NPR / Rs.)')
                    ->schema([
                        TextInput::make('total_basic_salary')
                            ->label('Total Basic Salary (Rs.)')
                            ->numeric()
                            ->prefix('Rs.')
                            ->disabled(),

                        TextInput::make('total_allowances')
                            ->label('Total Allowances (Rs.)')
                            ->numeric()
                            ->prefix('Rs.')
                            ->disabled(),

                        TextInput::make('total_overtime_amount')
                            ->label('Overtime Earnings (Rs.)')
                            ->numeric()
                            ->prefix('Rs.')
                            ->disabled(),

                        TextInput::make('total_ssf_employee')
                            ->label('SSF Deductions (11%) (Rs.)')
                            ->numeric()
                            ->prefix('Rs.')
                            ->disabled(),

                        TextInput::make('total_tds_tax')
                            ->label('TDS Tax Withheld Sec 87 (Rs.)')
                            ->numeric()
                            ->prefix('Rs.')
                            ->disabled(),

                        TextInput::make('total_net_salary')
                            ->label('Net Salary Payable to Staff (Rs.)')
                            ->numeric()
                            ->prefix('Rs.')
                            ->disabled(),

                        TextInput::make('total_ssf_employer')
                            ->label('SSF Employer Contribution (20%) (Rs.)')
                            ->numeric()
                            ->prefix('Rs.')
                            ->disabled(),

                        TextInput::make('total_employer_cost')
                            ->label('Total Cost to Company (CTC) (Rs.)')
                            ->numeric()
                            ->prefix('Rs.')
                            ->disabled(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('run_number')
                    ->label('Run #')
                    ->weight('bold')
                    ->fontFamily('mono')
                    ->searchable(),

                TextColumn::make('fiscal_year')
                    ->label('FY')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Payroll Period')
                    ->searchable(),

                TextColumn::make('pay_date')
                    ->label('Disbursement Date')
                    ->date('M d, Y')
                    ->sortable(),

                TextColumn::make('gross_salary')
                    ->label('Gross Total (Rs.)')
                    ->formatStateUsing(fn($state) => 'Rs. ' . number_format((float)$state, 0))
                    ->sortable(),

                TextColumn::make('net_salary')
                    ->label('Net Payable (Rs.)')
                    ->formatStateUsing(fn($state) => 'Rs. ' . number_format((float)$state, 0))
                    ->weight('bold')
                    ->color('success'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'paid' => 'success',
                        'posted_to_accounting' => 'info',
                        'approved' => 'warning',
                        'calculated' => 'gray',
                        'draft' => 'gray',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('period_start', 'desc')
            ->actions([
                \Filament\Actions\Action::make('calculate')
                    ->label('Calculate')
                    ->icon('heroicon-o-calculator')
                    ->color('info')
                    ->action(function (PayrollRun $record) {
                        app(PayrollPreparationService::class)->calculateRunItems($record);
                        Notification::make()
                            ->title('Payroll Calculated')
                            ->body("Calculated Nepal statutory payroll for {$record->run_number}.")
                            ->success()
                            ->send();
                    }),

                \Filament\Actions\Action::make('post_to_accounting')
                    ->label('Post to Ledger')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Post Payroll Voucher to General Ledger')
                    ->modalDescription('Creates double-entry journal entry debiting salary expense (6120) & employer SSF (6130) and crediting staff salary payable (2150) & TDS (2140).')
                    ->visible(fn($record) => in_array($record->status, ['calculated', 'approved']))
                    ->action(function (PayrollRun $record) {
                        /** @var \App\Models\User|null $currentUser */
                        $currentUser = \Illuminate\Support\Facades\Auth::user();
                        $entry = app(PayrollPreparationService::class)->postPayrollToAccounting($record, $currentUser);
                        Notification::make()
                            ->title('Posted to General Ledger')
                            ->body("Created journal voucher {$entry->entry_number}.")
                            ->success()
                            ->send();
                    }),

                \Filament\Actions\Action::make('disburse_salaries')
                    ->label('Disburse / Pay')
                    ->icon('heroicon-o-banknotes')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Disburse Net Salaries via Bank')
                    ->modalDescription('Creates bank payment voucher (Dr 2150 Salaries Payable, Cr Bank Account) and registers Bank Transaction.')
                    ->visible(fn($record) => $record->status === 'posted_to_accounting')
                    ->form([
                        Select::make('bank_account_id')
                            ->label('Disbursement Bank Account')
                            ->options(BankAccount::pluck('name', 'id'))
                            ->required(),
                        TextInput::make('reference')
                            ->label('Cheque / NCHL / ConnectIPS Reference')
                            ->placeholder('e.g. NCHL-SAL-2083-05')
                            ->required(),
                    ])
                    ->action(function (PayrollRun $record, array $data) {
                        $bankAccount = BankAccount::findOrFail($data['bank_account_id']);
                        /** @var \App\Models\User|null $currentUser */
                        $currentUser = \Illuminate\Support\Facades\Auth::user();
                        $entry = app(PayrollPreparationService::class)->recordSalaryDisbursement(
                            $record,
                            $bankAccount,
                            $currentUser,
                            $data['reference']
                        );

                        Notification::make()
                            ->title('Salaries Disbursed Successfully')
                            ->body("Created bank payout voucher {$entry->entry_number} from {$bankAccount->name}.")
                            ->success()
                            ->send();
                    }),

                \Filament\Actions\Action::make('view_payslips')
                    ->label('Payslips')
                    ->icon('heroicon-o-document-text')
                    ->color('gray')
                    ->visible(fn($record) => $record->items()->count() > 0)
                    ->url(fn($record) => route('hrm.payslip', $record->items()->first()->id))
                    ->openUrlInNewTab(),

                \Filament\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayrollRuns::route('/'),
            'create' => Pages\CreatePayrollRun::route('/create'),
            'edit' => Pages\EditPayrollRun::route('/{record}/edit'),
        ];
    }
}
