<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\FiscalYearResource\Pages;
use App\Models\Accounting\AccountingFiscalYear;
use App\Models\Accounting\AccountingPeriod;
use App\Services\Accounting\AccountingService;
use Filament\Forms\Components\DatePicker;
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

class FiscalYearResource extends Resource
{
    protected static ?string $model = AccountingFiscalYear::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-calendar';
    protected static string | \UnitEnum | null $navigationGroup = 'Finance';
    protected static ?string $navigationLabel = 'Fiscal Years & Periods';
    protected static ?int $navigationSort = 120;
    protected static ?string $title = 'Nepali Fiscal Years & Statutory Periods (Bikram Sambat)';
    protected static ?string $slug = 'fiscal-years';

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Nepali Fiscal Year (Bikram Sambat)')
                    ->description('Bikram Sambat cycle runs from Shrawan 1 to Ashadh 32.')
                    ->schema([
                        TextInput::make('fiscal_year')
                            ->label('Fiscal Year (e.g. 2083/84)')
                            ->required()
                            ->maxLength(15),

                        DatePicker::make('start_date')
                            ->label('Gregorian Start Date (approx July 16)')
                            ->required(),

                        DatePicker::make('end_date')
                            ->label('Gregorian End Date (approx July 15 next year)')
                            ->required(),

                        Select::make('status')
                            ->label('Filing & Period Status')
                            ->options([
                                'open' => 'Active & Open for Vouchers',
                                'locked' => 'Locked (Auditing / Management Review)',
                                'closed' => 'Closed (Statutory Audit Completed)',
                            ])
                            ->default('open')
                            ->required(),

                        Toggle::make('is_current')
                            ->label('Set as Active Current Fiscal Year')
                            ->default(false),

                        Textarea::make('notes')
                            ->label('Statutory / Finance Act Notes')
                            ->columnSpanFull(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('start_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('fiscal_year')
                    ->label('Fiscal Year')
                    ->weight('bold')
                    ->color('primary')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('Starts (Shrawan 1)')
                    ->date('Y-m-d')
                    ->sortable(),

                Tables\Columns\TextColumn::make('end_date')
                    ->label('Ends (Ashadh 32)')
                    ->date('Y-m-d')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_current')
                    ->label('Current')
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'success' => 'open',
                        'warning' => 'locked',
                        'danger' => 'closed',
                    ])
                    ->formatStateUsing(fn($state) => match ($state) {
                        'open' => '🟢 Open',
                        'locked' => '🔒 Locked',
                        'closed' => '📕 Closed & Audited',
                        default => ucfirst((string)$state),
                    }),

                Tables\Columns\TextColumn::make('periods_count')
                    ->label('Nepali Periods')
                    ->counts('periods')
                    ->suffix(' Months')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('notes')
                    ->label('Notes')
                    ->limit(35)
                    ->color('gray'),
            ])
            ->actions([
                \Filament\Actions\Action::make('lock_period')
                    ->label('Lock Period')
                    ->icon('heroicon-o-lock-closed')
                    ->color('warning')
                    ->visible(fn(AccountingFiscalYear $record) => $record->status === 'open')
                    ->requiresConfirmation()
                    ->modalDescription('Locking this fiscal year prevents ordinary users from modifying historical financial records.')
                    ->action(function (AccountingFiscalYear $record) {
                        $record->update(['status' => 'locked']);
                        AccountingPeriod::where('fiscal_year', $record->fiscal_year)->update(['status' => 'locked']);

                        Notification::make()
                            ->title('Fiscal Year Locked')
                            ->body("FY {$record->fiscal_year} is now locked against historical posting.")
                            ->warning()
                            ->send();
                    }),

                \Filament\Actions\Action::make('unlock_period')
                    ->label('Unlock')
                    ->icon('heroicon-o-lock-open')
                    ->color('success')
                    ->visible(fn(AccountingFiscalYear $record) => $record->status === 'locked')
                    ->requiresConfirmation()
                    ->action(function (AccountingFiscalYear $record) {
                        $record->update(['status' => 'open']);
                        AccountingPeriod::where('fiscal_year', $record->fiscal_year)->update(['status' => 'open']);

                        Notification::make()
                            ->title('Fiscal Year Unlocked')
                            ->body("FY {$record->fiscal_year} is now open for postings.")
                            ->success()
                            ->send();
                    }),

                \Filament\Actions\Action::make('year_end_close')
                    ->label('Year-End Close (NAS)')
                    ->icon('heroicon-o-check-badge')
                    ->color('danger')
                    ->visible(fn(AccountingFiscalYear $record) => $record->status !== 'closed')
                    ->requiresConfirmation()
                    ->modalHeading(fn(AccountingFiscalYear $record) => "Perform Year-End Closing for FY {$record->fiscal_year}")
                    ->modalDescription('Zeroes all nominal Revenue & Expense accounts, transfers Net Profit/Loss to Retained Earnings (Account 3200), and closes the fiscal year.')
                    ->action(function (AccountingFiscalYear $record) {
                        /** @var \App\Models\User|null $currentUser */
                        $currentUser = \Illuminate\Support\Facades\Auth::user();
                        $closingVoucher = app(AccountingService::class)->performYearEndClosing($record->fiscal_year, $currentUser);

                        Notification::make()
                            ->title('Year-End Closing Completed')
                            ->body("Voucher #{$closingVoucher->entry_number} posted. Nominal accounts zeroed and FY {$record->fiscal_year} closed.")
                            ->success()
                            ->send();
                    }),

                \Filament\Actions\Action::make('roll_opening_balances')
                    ->label('Roll Opening Balances →')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('info')
                    ->form([
                        Select::make('target_fiscal_year')
                            ->label('Target Fiscal Year to Open')
                            ->options(fn(AccountingFiscalYear $record) => AccountingFiscalYear::where('fiscal_year', '!=', $record->fiscal_year)->pluck('fiscal_year', 'fiscal_year'))
                            ->required(),
                    ])
                    ->action(function (AccountingFiscalYear $record, array $data) {
                        $targetFy = $data['target_fiscal_year'];
                        /** @var \App\Models\User|null $currentUser */
                        $currentUser = \Illuminate\Support\Facades\Auth::user();
                        $openingVoucher = app(AccountingService::class)->generateOpeningBalances($targetFy, $record->fiscal_year, $currentUser);

                        Notification::make()
                            ->title('Opening Balances Rolled Forward')
                            ->body("Opening balance voucher #{$openingVoucher->entry_number} posted for FY {$targetFy}.")
                            ->success()
                            ->send();
                    }),
            ])
            ->headerActions([
                \Filament\Actions\Action::make('seed_statutory_years')
                    ->label('Seed Statutory Periods (2081–2084)')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->action(function () {
                        app(AccountingService::class)->seedNepalFiscalYearsAndPeriods();
                        Notification::make()
                            ->title('Statutory Nepali Periods Synchronized')
                            ->body('Fiscal Years 2081/82, 2082/83, 2083/84 and 36 monthly statutory periods verified.')
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('No Fiscal Years Configured')
            ->emptyStateDescription('Manage statutory Nepali Bikram Sambat fiscal years (Shrawan 1 to Ashadh 32).')
            ->emptyStateIcon('heroicon-o-calendar')
            ->emptyStateActions([
                \Filament\Actions\Action::make('seed_statutory_years_empty')
                    ->label('Seed Statutory Periods (2081–2084)')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->action(function () {
                        app(AccountingService::class)->seedNepalFiscalYearsAndPeriods();
                        Notification::make()
                            ->title('Statutory Nepali Periods Synchronized')
                            ->body('Fiscal Years 2081/82, 2082/83, 2083/84 and 36 monthly statutory periods verified.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFiscalYears::route('/'),
        ];
    }
}
