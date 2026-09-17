<?php

namespace App\Filament\Pages;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountingPeriod;
use App\Services\Accounting\AccountingService;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountingDashboard extends Page
{
    protected string $view = 'filament.pages.accounting-dashboard';
    protected Width | string | null $maxWidth = 'full';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static string | \UnitEnum | null $navigationGroup = 'Finance';
    protected static ?string $navigationLabel = 'Accounting Dashboard';
    protected static ?int $navigationSort = 10;
    protected static ?string $title = 'Accounting & Financial Reporting';
    protected static ?string $slug = 'accounting';

    public function getHeading(): string | Htmlable
    {
        return '';
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user() ?? auth('web')->user();
        if (!$user) {
            return false;
        }

        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return true;
        }

        return $user->hasRole('Accountant', 'admin')
            || $user->hasRole('Accountant', 'web')
            || $user->hasRole('Workspace Admin', 'admin')
            || $user->hasRole('Workspace Admin', 'web')
            || $user->can('page_AccountingDashboard')
            || $user->role === 'accountant';
    }

    // Active Navigation Tab: 'pnl' | 'balance' | 'trial_balance' | 'ledger' | 'periods'
    public string $activeTab = 'pnl';

    // Date range filter
    public string $periodPreset = 'ytd';
    public ?string $startDate = null;
    public ?string $endDate = null;

    // General ledger selected account
    public ?int $selectedAccountId = null;

    // Trial balance search and filtering
    public string $trialBalanceSearch = '';
    public string $trialBalanceCategory = 'all';

    // Memoized computation caches
    protected ?array $cachedPnl = null;
    protected ?array $cachedBalance = null;
    protected ?array $cachedTrialBalance = null;
    protected ?array $cachedLedger = null;
    protected ?array $cachedVat = null;
    protected ?array $cachedRevenueSplit = null;

    public function mount(): void
    {
        $this->applyPreset('ytd');

        $defaultAcc = Account::where('account_number', '1110')->first() ?: Account::first();
        $this->selectedAccountId = $defaultAcc?->id;
    }

    public function clearCache(): void
    {
        $this->cachedPnl = null;
        $this->cachedBalance = null;
        $this->cachedTrialBalance = null;
        $this->cachedLedger = null;
        $this->cachedVat = null;
        $this->cachedRevenueSplit = null;
    }

    public function setCustomDates(string $start, string $end): void
    {
        $this->periodPreset = 'custom';
        $this->startDate = $start;
        $this->endDate = $end;
        $this->clearCache();
    }

    public function getRevenueSplitProperty(): array
    {
        if ($this->cachedRevenueSplit !== null) {
            return $this->cachedRevenueSplit;
        }

        $pnl = $this->pnlData;
        $accounts = collect($pnl['accounts_breakdown'] ?? [])->keyBy('account_number');

        // Nepal Standard Chart of Accounts (NAS):
        // 4120: Online Storefront Sales Revenue (Laijau.com)
        // 4110: Retail Showroom / POS Sales Revenue
        // 4130: Delivery & Courier Shipping Revenue
        $online = (float)($accounts->get('4120')['amount'] ?? 0);
        $showroom = (float)($accounts->get('4110')['amount'] ?? 0);
        $shipping = (float)($accounts->get('4130')['amount'] ?? 0);
        $discounts = 0.0;
        $totalGross = $online + $showroom + $shipping;

        return $this->cachedRevenueSplit = [
            'online' => round($online, 2),
            'online_percent' => $totalGross > 0 ? round(($online / $totalGross) * 100, 1) : 0,
            'showroom' => round($showroom, 2),
            'showroom_percent' => $totalGross > 0 ? round(($showroom / $totalGross) * 100, 1) : 0,
            'shipping' => round($shipping, 2),
            'discounts' => round($discounts, 2),
            'total' => round($pnl['revenue'], 2),
        ];
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function updatedPeriodPreset(string $val): void
    {
        $this->applyPreset($val);
    }

    public function updatedSelectedAccountId(): void
    {
        $this->cachedLedger = null;
    }

    public function applyPreset(string $preset): void
    {
        $this->periodPreset = $preset;
        $now = Carbon::now();
        $year = $now->year;
        $this->clearCache();

        switch ($preset) {
            case 'this_month':
                $this->startDate = $now->copy()->startOfMonth()->toDateString();
                $this->endDate = $now->copy()->endOfMonth()->toDateString();
                break;
            case 'last_month':
                $this->startDate = $now->copy()->subMonth()->startOfMonth()->toDateString();
                $this->endDate = $now->copy()->subMonth()->endOfMonth()->toDateString();
                break;
            case 'q1':
                $this->startDate = "{$year}-01-01";
                $this->endDate = "{$year}-03-31";
                break;
            case 'q2':
                $this->startDate = "{$year}-04-01";
                $this->endDate = "{$year}-06-30";
                break;
            case 'q3':
                $this->startDate = "{$year}-07-01";
                $this->endDate = "{$year}-09-30";
                break;
            case 'q4':
                $this->startDate = "{$year}-10-01";
                $this->endDate = "{$year}-12-31";
                break;
            case 'full_year':
            case 'fy_2026':
                $this->startDate = "{$year}-01-01";
                $this->endDate = "{$year}-12-31";
                break;
            case 'ytd':
            default:
                $this->startDate = "{$year}-01-01";
                $this->endDate = $now->toDateString();
                break;
        }
    }

    public function getPnlDataProperty(): array
    {
        if ($this->cachedPnl !== null) {
            return $this->cachedPnl;
        }
        return $this->cachedPnl = app(AccountingService::class)->generateProfitAndLoss($this->startDate, $this->endDate);
    }

    public function getBalanceDataProperty(): array
    {
        if ($this->cachedBalance !== null) {
            return $this->cachedBalance;
        }
        return $this->cachedBalance = app(AccountingService::class)->generateBalanceSheet($this->endDate);
    }

    public function getTrialBalanceDataProperty(): array
    {
        if ($this->cachedTrialBalance !== null) {
            return $this->cachedTrialBalance;
        }
        return $this->cachedTrialBalance = app(AccountingService::class)->generateTrialBalance($this->startDate, $this->endDate);
    }

    public function getLedgerDataProperty(): ?array
    {
        if (!$this->selectedAccountId) {
            return null;
        }
        if ($this->cachedLedger !== null) {
            return $this->cachedLedger;
        }
        return $this->cachedLedger = app(AccountingService::class)->generateGeneralLedger($this->selectedAccountId, $this->startDate, $this->endDate);
    }

    public function getVatDataProperty(): array
    {
        if ($this->cachedVat !== null) {
            return $this->cachedVat;
        }
        return $this->cachedVat = app(AccountingService::class)->generateVatReturn($this->startDate, $this->endDate);
    }

    public function exportCsv(): StreamedResponse
    {
        $csv = app(AccountingService::class)->exportTrialBalanceCsv($this->startDate, $this->endDate);
        $filename = "Trial_Balance_{$this->startDate}_to_{$this->endDate}.csv";

        return response()->streamDownload(function () use ($csv) {
            echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel
            echo $csv;
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function lockPeriod(int $periodId): void
    {
        $period = AccountingPeriod::findOrFail($periodId);
        $period->lock(auth()->user());

        Notification::make()
            ->title('Accounting Period Locked')
            ->body("Period {$period->name} is now locked against new postings.")
            ->warning()
            ->send();
    }

    public function unlockPeriod(int $periodId): void
    {
        $period = AccountingPeriod::findOrFail($periodId);
        $period->unlock();

        Notification::make()
            ->title('Accounting Period Reopened')
            ->body("Period {$period->name} is now open for postings.")
            ->success()
            ->send();
    }

    public function togglePeriodLock(int $periodId): void
    {
        $period = AccountingPeriod::findOrFail($periodId);
        if ($period->is_closed) {
            $this->unlockPeriod($periodId);
        } else {
            $this->lockPeriod($periodId);
        }
    }

    public function executeYearEndClosing(?int $year = null): void
    {
        $this->closeYear();
    }

    public function closeYear(): void
    {
        $year = Carbon::parse($this->endDate)->year;

        try {
            $closingEntry = app(AccountingService::class)->performYearEndClosing($year, auth()->user());
            Notification::make()
                ->title('Fiscal Year-End Closing Completed')
                ->body("The net profit for fiscal year {$year} has been transferred to equity via voucher {$closingEntry->entry_number}.")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Year-End Closing Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
