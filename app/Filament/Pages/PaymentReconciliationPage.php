<?php

namespace App\Filament\Pages;

use App\Models\Accounting\Account;
use App\Models\Accounting\BankAccount;
use App\Models\Accounting\BankTransaction;
use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\AccountingService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;

class PaymentReconciliationPage extends Page
{
    protected string $view = 'filament.pages.payment-reconciliation-page';
    protected Width | string | null $maxWidth = 'full';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-scale';
    protected static string | \UnitEnum | null $navigationGroup = 'Finance';
    protected static ?string $navigationLabel = 'Payment Reconciliation';
    protected static ?int $navigationSort = 50;
    protected static ?string $title = 'Payment & Bank Reconciliation';
    protected static ?string $slug = 'payment-reconciliation';

    public string $activeTab = 'esewa'; // 'esewa' | 'connectips' | 'cod' | 'cash_deposit' | 'audit'

    // eSewa Form
    public float $esewaNetPayout = 0.00;
    public float $esewaFee = 0.00;
    public string $esewaRef = '';
    public string $esewaDate = '';

    // ConnectIPS Form
    public float $connectIpsNetPayout = 0.00;
    public float $connectIpsFee = 0.00;
    public string $connectIpsRef = '';
    public string $connectIpsDate = '';

    // COD Remittance Form
    public float $codRemitted = 0.00;
    public float $codCourierFee = 0.00;
    public string $codCourierName = 'Nepal Can Move (NCM)';
    public string $codBatchRef = '';
    public string $codDate = '';

    // Cash Deposit Form
    public float $cashDepositAmount = 0.00;
    public string $cashDepositSlipRef = '';
    public string $cashDepositDate = '';

    public function mount(): void
    {
        $today = date('Y-m-d');
        $this->esewaDate = $today;
        $this->connectIpsDate = $today;
        $this->codDate = $today;
        $this->cashDepositDate = $today;
    }

    public function reconcileEsewa(): void
    {
        if ($this->esewaNetPayout <= 0) {
            Notification::make()->title('Invalid Amount')->body('Please enter a net remittance amount greater than 0.')->warning()->send();
            return;
        }

        try {
            $ref = $this->esewaRef ?: ('ESEWA-' . date('Ymd'));
            app(AccountingService::class)->reconcileEsewaSettlement(
                netAmount: $this->esewaNetPayout,
                commissionFee: $this->esewaFee,
                reference: $ref,
                date: $this->esewaDate
            );

            Notification::make()->title('eSewa Remittance Reconciled')->body("Rs. " . number_format($this->esewaNetPayout) . " credited to Bank account.")->success()->send();
            $this->esewaNetPayout = 0.00;
            $this->esewaFee = 0.00;
            $this->esewaRef = '';
        } catch (\Throwable $e) {
            Notification::make()->title('Reconciliation Failed')->body($e->getMessage())->danger()->send();
        }
    }

    public function reconcileConnectIps(): void
    {
        if ($this->connectIpsNetPayout <= 0) {
            Notification::make()->title('Invalid Amount')->body('Please enter a net payout amount greater than 0.')->warning()->send();
            return;
        }

        try {
            $ref = $this->connectIpsRef ?: ('CIPS-' . date('Ymd'));
            app(AccountingService::class)->reconcileConnectIpsSettlement(
                netAmount: $this->connectIpsNetPayout,
                fee: $this->connectIpsFee,
                reference: $ref,
                date: $this->connectIpsDate
            );

            Notification::make()->title('ConnectIPS Settlement Reconciled')->body("Rs. " . number_format($this->connectIpsNetPayout) . " credited to Bank account.")->success()->send();
            $this->connectIpsNetPayout = 0.00;
            $this->connectIpsFee = 0.00;
            $this->connectIpsRef = '';
        } catch (\Throwable $e) {
            Notification::make()->title('Reconciliation Failed')->body($e->getMessage())->danger()->send();
        }
    }

    public function reconcileCod(): void
    {
        if ($this->codRemitted <= 0) {
            Notification::make()->title('Invalid Amount')->body('Please enter a remitted amount greater than 0.')->warning()->send();
            return;
        }

        try {
            $ref = $this->codBatchRef ?: ('COD-REMIT-' . date('Ymd'));
            app(AccountingService::class)->reconcileCodCourierSettlement(
                remittedAmount: $this->codRemitted,
                courierFee: $this->codCourierFee,
                courierName: $this->codCourierName,
                batchRef: $ref,
                date: $this->codDate
            );

            Notification::make()->title('COD Remittance Reconciled')->body("Rs. " . number_format($this->codRemitted) . " deposited to Bank from {$this->codCourierName}.")->success()->send();
            $this->codRemitted = 0.00;
            $this->codCourierFee = 0.00;
            $this->codBatchRef = '';
        } catch (\Throwable $e) {
            Notification::make()->title('Reconciliation Failed')->body($e->getMessage())->danger()->send();
        }
    }

    public function recordCashDeposit(): void
    {
        if ($this->cashDepositAmount <= 0) {
            Notification::make()->title('Invalid Amount')->body('Please enter a cash deposit amount greater than 0.')->warning()->send();
            return;
        }

        try {
            $ref = $this->cashDepositSlipRef ?: ('DEP-SLIP-' . date('Ymd'));
            app(AccountingService::class)->reconcileCashDeposit(
                amount: $this->cashDepositAmount,
                depositSlipRef: $ref,
                date: $this->cashDepositDate
            );

            Notification::make()->title('Cash Deposit Recorded')->body("Rs. " . number_format($this->cashDepositAmount) . " transferred from POS drawer to Bank.")->success()->send();
            $this->cashDepositAmount = 0.00;
            $this->cashDepositSlipRef = '';
        } catch (\Throwable $e) {
            Notification::make()->title('Deposit Failed')->body($e->getMessage())->danger()->send();
        }
    }

    public function getBalancesProperty(): array
    {
        $accCash = Account::where('account_number', '1110')->first() ?: Account::where('account_number', '2430')->first();
        $accBank = Account::where('account_number', '1120')->first() ?: Account::where('account_number', '2410')->first();
        $accEsewa = Account::where('account_number', '1130')->first() ?: Account::where('account_number', '2320')->first();
        $accConnectIps = Account::where('account_number', '1140')->first() ?: Account::where('account_number', '2330')->first();
        $accCod = Account::where('account_number', '1150')->first() ?: Account::where('account_number', '2340')->first();

        return [
            'cash' => (float) ($accCash?->current_balance ?? 0),
            'bank' => (float) ($accBank?->current_balance ?? 0),
            'esewa' => (float) ($accEsewa?->current_balance ?? 0),
            'connectips' => (float) ($accConnectIps?->current_balance ?? 0),
            'cod' => (float) ($accCod?->current_balance ?? 0),
        ];
    }

    public function getRecentSettlementsProperty()
    {
        return JournalEntry::with('lines.account')
            ->whereIn('entry_type', ['settlement', 'internal_transfer'])
            ->latest('voucher_date')
            ->take(20)
            ->get();
    }
}
