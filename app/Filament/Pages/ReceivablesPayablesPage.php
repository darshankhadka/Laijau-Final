<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountingInvoice;
use App\Models\Accounting\KharidKhataEntry;
use App\Services\Accounting\AccountingService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;

class ReceivablesPayablesPage extends Page
{
    protected string $view = 'filament.pages.receivables-payables';
    protected Width | string | null $maxWidth = 'full';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-banknotes';
    protected static string | \UnitEnum | null $navigationGroup = 'Finance';
    protected static ?string $navigationLabel = 'Receivables & Payables';
    protected static ?int $navigationSort = 40;
    protected static ?string $title = 'Accounts Receivable & Payable (Aging & Settlement)';
    protected static ?string $slug = 'receivables-payables';

    public string $activeTab = 'receivables'; // 'receivables' | 'payables'

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
            || $user->role === 'accountant';
    }

    public function getAgingData(): array
    {
        return app(AccountingService::class)->generateReceivablesPayablesAging();
    }

    public function markInvoicePaid(int $invoiceId): void
    {
        $invoice = AccountingInvoice::findOrFail($invoiceId);
        $due = (float)($invoice->total_amount - $invoice->paid_amount);

        if ($due <= 0) {
            return;
        }

        // Post GL Receipt: Dr Operating Bank / Cash, Cr Accounts Receivable
        $accBank = Account::where('account_number', '1120')->first() ?: Account::where('category', 'cash_bank')->firstOrFail();
        $accAr = Account::where('account_number', '1150')->first() ?: Account::where('category', 'receivables')->firstOrFail();

        $lines = [
            [
                'account_id' => $accBank->id,
                'account_number' => $accBank->account_number,
                'description' => "Customer receipt for invoice #{$invoice->invoice_number} ({$invoice->contact_name})",
                'debit' => $due,
                'credit' => 0.00,
            ],
            [
                'account_id' => $accAr->id,
                'account_number' => $accAr->account_number,
                'description' => "Clear Accounts Receivable #{$invoice->invoice_number}",
                'debit' => 0.00,
                'credit' => $due,
            ],
        ];

        app(AccountingService::class)->postJournalEntry([
            'voucher_date' => date('Y-m-d'),
            'entry_type' => 'bank',
            'reference_type' => 'invoice_payment',
            'reference_id' => $invoice->id,
            'description' => "Customer payment received for #{$invoice->invoice_number}",
            'currency' => 'NPR',
        ], $lines, auth()->user());

        $invoice->update([
            'paid_amount' => $invoice->total_amount,
            'payment_status' => 'paid',
        ]);

        Notification::make()
            ->title('Customer Payment Recorded')
            ->body("Invoice #{$invoice->invoice_number} marked as fully paid and posted to GL.")
            ->success()
            ->send();
    }

    public function markBillPaid(int $billId): void
    {
        $bill = KharidKhataEntry::findOrFail($billId);
        $due = (float)($bill->total_amount - $bill->paid_amount);

        if ($due <= 0) {
            return;
        }

        // Post GL Payment: Dr Accounts Payable, Cr Operating Bank
        $accAp = Account::where('account_number', '2110')->first() ?: Account::where('category', 'payables')->firstOrFail();
        $accBank = Account::where('account_number', '1120')->first() ?: Account::where('category', 'cash_bank')->firstOrFail();

        $lines = [
            [
                'account_id' => $accAp->id,
                'account_number' => $accAp->account_number,
                'description' => "Supplier settlement: {$bill->contact_name} Bill #{$bill->invoice_number}",
                'debit' => $due,
                'credit' => 0.00,
            ],
            [
                'account_id' => $accBank->id,
                'account_number' => $accBank->account_number,
                'description' => "Bank payment for bill #{$bill->invoice_number}",
                'debit' => 0.00,
                'credit' => $due,
            ],
        ];

        app(AccountingService::class)->postJournalEntry([
            'voucher_date' => date('Y-m-d'),
            'entry_type' => 'bank',
            'reference_type' => 'bill_payment',
            'reference_id' => $bill->id,
            'description' => "Supplier bill settlement for #{$bill->invoice_number}",
            'currency' => 'NPR',
        ], $lines, auth()->user());

        $bill->update([
            'paid_amount' => $bill->total_amount,
            'payment_status' => 'paid',
        ]);

        Notification::make()
            ->title('Supplier Bill Settled')
            ->body("Bill #{$bill->invoice_number} marked as paid and posted to GL.")
            ->success()
            ->send();
    }
}
