<?php

namespace App\Services\Hrm;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Hrm\Employee;
use App\Models\Hrm\ExpenseClaim;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use Illuminate\Support\Facades\DB;

class ExpenseService
{
    public function __construct(
        protected AccountingService $accountingService
    ) {}

    /**
     * Submit a new employee expense claim.
     */
    public function submitExpenseClaim(
        Employee $employee,
        array $data,
        ?string $receiptPath = null
    ): ExpenseClaim {
        return DB::transaction(function () use ($employee, $data, $receiptPath) {
            $claimNumber = ExpenseClaim::generateNextClaimNumber();
            $category = $data['category'] ?? 'office';

            $mileageKm = isset($data['mileage_km']) ? (float)$data['mileage_km'] : null;
            $settingsService = app(\App\Services\Settings\SettingsService::class);
            $mileageRate = $settingsService->getDecimal('hrm', 'statutory_mileage_rate_npr_per_km', 3.79);

            if ($category === 'transport_mileage' && $mileageKm > 0) {
                $grossAmount = round($mileageKm * $mileageRate, 2);
                $vatRate = 0.00; // Tax-free statutory mileage has 0% VAT
                $vatAmount = 0.00;
                $netAmount = $grossAmount;
            } else {
                $grossAmount = (float)($data['gross_amount_npr'] ?? 0.00);
                $defaultVat = $settingsService->getDecimal('accounting', 'standard_vat_rate', 13.00);
                $vatRate = isset($data['vat_rate']) ? (float)$data['vat_rate'] : $defaultVat;
                if ($vatRate > 0) {
                    $netAmount = round($grossAmount / (1 + ($vatRate / 100)), 2);
                    $vatAmount = round($grossAmount - $netAmount, 2);
                } else {
                    $vatAmount = 0.00;
                    $netAmount = $grossAmount;
                }
            }

            // Map default account if not selected
            $ledgerAccountId = $data['ledger_account_id'] ?? null;
            if (!$ledgerAccountId) {
                $accNumber = match ($category) {
                    'travel', 'transport_mileage' => '6150',
                    'packaging_supplies' => '6160',
                    'utilities' => '6140',
                    'office' => '6180',
                    'legal' => '6210',
                    'meals_representation', 'maintenance' => '6200',
                    'other' => '6200',
                    default => '6200',
                };
                $acc = Account::where('account_number', $accNumber)->first();
                $ledgerAccountId = $acc?->id;
            }

            return ExpenseClaim::create([
                'claim_number' => $claimNumber,
                'employee_id' => $employee->id,
                'title' => $data['title'] ?? 'Expense Claim',
                'expense_date' => $data['expense_date'] ?? date('Y-m-d'),
                'category' => $category,
                'ledger_account_id' => $ledgerAccountId,
                'gross_amount_npr' => $grossAmount,
                'vat_rate' => $vatRate,
                'vat_amount_npr' => $vatAmount,
                'net_amount_npr' => $netAmount,
                'receipt_path' => $receiptPath,
                'mileage_km' => $mileageKm,
                'mileage_rate_npr' => $mileageKm ? $mileageRate : null,
                'status' => 'submitted',
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }

    /**
     * Approve and reimburse an expense claim, posting a balanced double-entry voucher.
     */
    public function approveAndReimburse(
        ExpenseClaim $claim,
        User $approver,
        string $paymentMethod = 'bank' // bank (1120) or payable (2110)
    ): JournalEntry {
        return DB::transaction(function () use ($claim, $approver, $paymentMethod) {
            if ($claim->journal_entry_id) {
                return JournalEntry::findOrFail($claim->journal_entry_id);
            }

            $this->accountingService->ensureDefaultChartOfAccounts();

            $expenseAccount = $claim->ledgerAccount ?? Account::where('account_number', '6110')->first()
                ?: Account::where('category', 'expense')->firstOrFail();
            $accVatInput = Account::where('account_number', '2130')->firstOrFail(); // 2130 Input VAT Credit 13%
            $creditAccount = ($paymentMethod === 'bank')
                ? Account::where('account_number', '1120')->firstOrFail() // 1120 Nabil Bank
                : Account::where('account_number', '2110')->firstOrFail(); // 2110 Accounts Payable

            $gross = (float)$claim->gross_amount_npr;
            $vat = (float)$claim->vat_amount_npr;
            $net = (float)$claim->net_amount_npr;

            $lines = [];

            // Debit 1: Net Expense
            $lines[] = [
                'account_id' => $expenseAccount->id,
                'account_number' => $expenseAccount->account_number,
                'description' => "Expense: {$claim->title} ({$claim->claim_number})",
                'debit' => $net,
                'credit' => 0.00,
            ];

            // Debit 2: Input VAT (if applicable)
            if ($vat > 0.00) {
                $lines[] = [
                    'account_id' => $accVatInput->id,
                    'account_number' => $accVatInput->account_number,
                    'description' => "Input VAT Credit (13%) for claim ({$claim->claim_number})",
                    'debit' => $vat,
                    'credit' => 0.00,
                ];
            }

            // Credit: Bank or Payable
            $lines[] = [
                'account_id' => $creditAccount->id,
                'account_number' => $creditAccount->account_number,
                'description' => "Reimbursement to {$claim->employee->full_name} ({$claim->claim_number})",
                'debit' => 0.00,
                'credit' => $gross,
            ];

            $entry = $this->accountingService->postJournalEntry([
                'voucher_date' => $claim->expense_date->format('Y-m-d'),
                'entry_type' => 'manual',
                'reference_type' => 'hrm_expense_claim',
                'reference_id' => $claim->id,
                'description' => "Employee expense claim {$claim->claim_number} ({$claim->employee->full_name})",
                'notes' => "Reimbursement of approved expense claim for {$claim->employee->full_name}.",
            ], $lines, $approver);

            $claim->update([
                'status' => 'reimbursed',
                'journal_entry_id' => $entry->id,
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'reimbursed_at' => now(),
            ]);

            return $entry;
        });
    }
}
