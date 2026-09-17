<?php

namespace App\Filament\Pages;

use App\Models\Accounting\Account;
use App\Models\Accounting\BankAccount;
use App\Models\Accounting\BankTransaction;
use App\Services\Accounting\AccountingService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;

class BankReconciliationPage extends Page
{
    protected string $view = 'filament.pages.bank-reconciliation-page';
    protected Width | string | null $maxWidth = 'full';

    protected static bool $shouldRegisterNavigation = true;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-building-library';
    protected static string | \UnitEnum | null $navigationGroup = 'Finance';
    protected static ?string $navigationLabel = 'Cash & Bank';
    protected static ?int $navigationSort = 80;
    protected static ?string $title = 'Cash & Bank Liquidity Registers';
    protected static ?string $slug = 'bank-reconciliation';

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
            || $user->can('page_BankReconciliationPage')
            || $user->role === 'accountant';
    }

    // ConnectIPS / Card Reconciliation Form
    public float $connectIpsNetPayout = 0.00;
    public float $connectIpsFee = 0.00;
    public string $connectIpsPayoutId = '';
    public ?string $connectIpsDate = null;

    // eSewa / Digital Wallet Reconciliation Form
    public float $esewaNetPayout = 0.00;
    public float $esewaFee = 0.00;
    public string $esewaBatchRef = '';
    public ?string $esewaDate = null;

    // Internal Transfer / Cash Deposit Form
    public ?int $transferFromAccountId = null;
    public ?int $transferToAccountId = null;
    public float $transferAmount = 0.00;
    public string $transferReference = '';
    public ?string $transferDate = null;
    public bool $showTransferModal = false;

    // Audit Trail Filters
    public string $selectedBankFilter = 'all';
    public string $transactionSearch = '';
    public string $transactionType = 'all';
    public string $dateFilter = '30days';

    public function mount(): void
    {
        $this->connectIpsDate = date('Y-m-d');
        $this->esewaDate = date('Y-m-d');
        $this->transferDate = date('Y-m-d');

        $defaultFrom = BankAccount::where('name', 'like', '%Cash%')->orWhere('name', 'like', '%Showroom%')->first();
        $defaultTo = BankAccount::where('name', 'like', '%Operating%')->orWhere('name', 'like', '%Nabil%')->first();
        if ($defaultFrom && $defaultTo) {
            $this->transferFromAccountId = $defaultFrom->id;
            $this->transferToAccountId = $defaultTo->id;
        }
    }

    public function getBankAccountsProperty()
    {
        return BankAccount::with('ledgerAccount')->where('is_active', true)->get();
    }

    public function getClearingBalancesProperty(): array
    {
        $accEsewa = Account::where('account_number', '1130')->first();
        $accConnectIps = Account::where('account_number', '1140')->first();
        $accCod = Account::where('account_number', '1150')->first();
        $accCard = Account::where('account_number', '1160')->first();

        $esewaBal = (float)($accEsewa?->current_balance ?? 0);
        $connectIpsBal = (float)($accConnectIps?->current_balance ?? 0);
        $codBal = (float)($accCod?->current_balance ?? 0);
        $cardBal = (float)($accCard?->current_balance ?? 0);

        return [
            'esewa' => $esewaBal,
            'connectips' => $connectIpsBal,
            'cod' => $codBal,
            'card' => $cardBal,
            'total' => $esewaBal + $connectIpsBal + $codBal + $cardBal,
        ];
    }

    public function getLiquidityMetricsProperty(): array
    {
        $banks = $this->bankAccounts;
        $totalLiquidNpr = 0.0;

        foreach ($banks as $b) {
            $bal = (float)$b->current_balance;
            $totalLiquidNpr += $bal;
        }

        $clearing = $this->clearingBalances;
        $txCount = BankTransaction::count();
        $reconciledCount = BankTransaction::where('is_reconciled', true)->count();
        $reconciliationRate = $txCount > 0 ? round(($reconciledCount / $txCount) * 100, 1) : 100.0;

        return [
            'total_liquid_npr' => round($totalLiquidNpr, 2),
            'clearing_total_npr' => round($clearing['total'], 2),
            'total_available_npr' => round($totalLiquidNpr + $clearing['total'], 2),
            'total_liquid_npr' => round($totalLiquidNpr, 2),
            'clearing_total_npr' => round($clearing['total'], 2),
            'total_available_npr' => round($totalLiquidNpr + $clearing['total'], 2),
            'reconciled_rate' => $reconciliationRate,
            'total_tx_count' => $txCount,
        ];
    }

    public function getFilteredTransactionsProperty()
    {
        $query = BankTransaction::with(['bankAccount', 'journalEntry'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');

        if ($this->selectedBankFilter !== 'all') {
            $query->where('bank_account_id', (int)$this->selectedBankFilter);
        }

        if ($this->transactionType !== 'all') {
            $query->where('match_type', $this->transactionType);
        }

        if (!empty($this->transactionSearch)) {
            $term = '%' . trim($this->transactionSearch) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('description', 'like', $term)
                    ->orWhere('external_reference', 'like', $term)
                    ->orWhereHas('journalEntry', fn($je) => $je->where('entry_number', 'like', $term));
            });
        }

        if ($this->dateFilter === '7days') {
            $query->where('transaction_date', '>=', now()->subDays(7));
        } elseif ($this->dateFilter === '30days') {
            $query->where('transaction_date', '>=', now()->subDays(30));
        } elseif ($this->dateFilter === '90days') {
            $query->where('transaction_date', '>=', now()->subDays(90));
        } elseif ($this->dateFilter === 'year') {
            $query->where('transaction_date', '>=', now()->startOfYear());
        }

        return $query->limit(60)->get();
    }

    public function prefillConnectIps(): void
    {
        $clearing = $this->clearingBalances['connectips'] ?: $this->clearingBalances['card'];
        if ($clearing <= 0) {
            Notification::make()
                ->title('No Pending Gateway Balance')
                ->body('There is currently Rs. 0.00 pending in the connectIPS / card clearing account (1140/1160).')
                ->info()
                ->send();
            return;
        }

        // Estimate standard banking/gateway fee (~1.4%)
        $estimatedFee = round($clearing * 0.014, 2);
        if ($estimatedFee >= $clearing) {
            $estimatedFee = 0.00;
        }
        $net = round($clearing - $estimatedFee, 2);

        $this->connectIpsNetPayout = $net;
        $this->connectIpsFee = $estimatedFee;
        $this->connectIpsPayoutId = 'CIP_' . date('ymd') . '_' . substr(uniqid(), -4);
        $this->connectIpsDate = date('Y-m-d');

        Notification::make()
            ->title('ConnectIPS / Card Fields Prefilled')
            ->body("Prefilled with pending balance: Rs. {$clearing} (Net: Rs. {$net}, Fee: Rs. {$estimatedFee})")
            ->success()
            ->send();
    }

    public function prefillEsewa(): void
    {
        $clearing = $this->clearingBalances['esewa'];
        if ($clearing <= 0) {
            Notification::make()
                ->title('No Pending eSewa Balance')
                ->body('There is currently Rs. 0.00 pending in the eSewa clearing account (1130).')
                ->info()
                ->send();
            return;
        }

        // Estimate digital wallet fee (~0.99%)
        $estimatedFee = round($clearing * 0.0099, 2);
        if ($estimatedFee >= $clearing) {
            $estimatedFee = 0.00;
        }
        $net = round($clearing - $estimatedFee, 2);

        $this->esewaNetPayout = $net;
        $this->esewaFee = $estimatedFee;
        $this->esewaBatchRef = 'ESEWA-NPR-' . date('ymd') . '-' . substr(uniqid(), -3);
        $this->esewaDate = date('Y-m-d');

        Notification::make()
            ->title('eSewa Fields Prefilled')
            ->body("Prefilled with pending balance: Rs. {$clearing} (Net: Rs. {$net}, Fee: Rs. {$estimatedFee})")
            ->success()
            ->send();
    }

    public function reconcileConnectIpsPayout(): void
    {
        $net = $this->connectIpsNetPayout;
        $fee = $this->connectIpsFee;
        $payoutId = $this->connectIpsPayoutId;
        $date = $this->connectIpsDate;

        if ($net <= 0 || empty($payoutId)) {
            Notification::make()
                ->title('Invalid ConnectIPS Parameters')
                ->body('Please provide valid payout amount and reference ID.')
                ->warning()
                ->send();
            return;
        }

        try {
            $entry = app(AccountingService::class)->reconcileConnectIpsPayout(
                $net,
                $fee,
                $payoutId,
                $date
            );

            Notification::make()
                ->title('ConnectIPS Payout Reconciled')
                ->body("Voucher {$entry->entry_number} posted. Operating Bank Account credited with Rs. " . number_format($net, 2, '.', ',') . ".")
                ->success()
                ->send();

            $this->connectIpsNetPayout = 0.00;
            $this->connectIpsFee = 0.00;
            $this->connectIpsPayoutId = '';
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Error During ConnectIPS Reconciliation')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function reconcileConnectIps(): void
    {
        $this->reconcileConnectIpsPayout();
    }

    public function reconcileEsewaPayout(): void
    {
        $net = $this->esewaNetPayout;
        $fee = $this->esewaFee;
        $batchRef = $this->esewaBatchRef;
        $date = $this->esewaDate;

        if ($net <= 0 || empty($batchRef)) {
            Notification::make()
                ->title('Invalid eSewa Parameters')
                ->body('Please provide valid payout amount and batch reference.')
                ->warning()
                ->send();
            return;
        }

        try {
            $entry = app(AccountingService::class)->reconcileEsewaPayout(
                $net,
                $fee,
                $batchRef,
                $date
            );

            Notification::make()
                ->title('eSewa Settlement Reconciled')
                ->body("Voucher {$entry->entry_number} posted. Operating Bank Account credited with Rs. " . number_format($net, 2, '.', ',') . ".")
                ->success()
                ->send();

            $this->esewaNetPayout = 0.00;
            $this->esewaFee = 0.00;
            $this->esewaBatchRef = '';
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Error During eSewa Reconciliation')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function reconcileEsewa(): void
    {
        $this->reconcileEsewaPayout();
    }

    public function openTransferModal(): void
    {
        $this->showTransferModal = true;
    }

    public function closeTransferModal(): void
    {
        $this->showTransferModal = false;
    }

    public function executeInternalTransfer(): void
    {
        $this->validate([
            'transferFromAccountId' => 'required|integer',
            'transferToAccountId' => 'required|integer|different:transferFromAccountId',
            'transferAmount' => 'required|numeric|min:0.01',
        ]);

        try {
            $entry = app(AccountingService::class)->recordInternalTransfer(
                (int)$this->transferFromAccountId,
                (int)$this->transferToAccountId,
                $this->transferAmount,
                $this->transferReference ?: 'Cash Deposit / Bank Transfer',
                $this->transferDate
            );

            Notification::make()
                ->title('Transfer Completed & Posted')
                ->body("Voucher {$entry->entry_number} posted. Rs. " . number_format($this->transferAmount, 2, '.', ',') . " transferred between accounts.")
                ->success()
                ->send();

            $this->transferAmount = 0.00;
            $this->transferReference = '';
            $this->showTransferModal = false;
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Error During Internal Transfer')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
