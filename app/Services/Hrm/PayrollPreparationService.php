<?php

namespace App\Services\Hrm;

use App\Models\Accounting\Account;
use App\Models\Accounting\BankAccount;
use App\Models\Accounting\BankTransaction;
use App\Models\Accounting\JournalEntry;
use App\Models\Hrm\Employee;
use App\Models\Hrm\PayrollRun;
use App\Models\Hrm\PayrollRunItem;
use App\Models\Hrm\PayrollTaxConfiguration;
use App\Models\Hrm\Timesheet;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use App\Services\Settings\SettingsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class PayrollPreparationService
{
    protected SettingsService $settingsService;

    public function __construct(
        protected AccountingService $accountingService,
        ?SettingsService $settingsService = null
    ) {
        $this->settingsService = $settingsService ?? app(SettingsService::class);
    }

    /**
     * Seeds statutory Nepal payroll tax & SSF configurations for FY 2081/82, 2082/83, 2083/84.
     */
    public function seedNepalPayrollTaxConfigurations(): void
    {
        $configs = [
            [
                'fiscal_year' => '2081/82',
                'name' => 'Nepal Statutory Payroll & Tax Slabs FY 2081/82',
                'ssf_employee_rate' => 11.00,
                'ssf_employer_rate' => 20.00,
                'single_slab_1_limit' => 500000.00,
                'single_slab_1_rate' => 1.00,
                'single_slab_2_limit' => 200000.00,
                'single_slab_2_rate' => 10.00,
                'single_slab_3_limit' => 300000.00,
                'single_slab_3_rate' => 20.00,
                'single_slab_4_limit' => 1000000.00,
                'single_slab_4_rate' => 30.00,
                'single_slab_5_limit' => 3000000.00,
                'single_slab_5_rate' => 36.00,
                'single_slab_top_rate' => 39.00,
                'married_slab_1_limit' => 600000.00,
                'married_slab_1_rate' => 1.00,
                'married_slab_2_limit' => 200000.00,
                'married_slab_2_rate' => 10.00,
                'married_slab_3_limit' => 300000.00,
                'married_slab_3_rate' => 20.00,
                'married_slab_4_limit' => 900000.00,
                'married_slab_4_rate' => 30.00,
                'married_slab_5_limit' => 3000000.00,
                'married_slab_5_rate' => 36.00,
                'married_slab_top_rate' => 39.00,
                'standard_daily_hours' => 8.00,
                'standard_monthly_work_days' => 26,
                'overtime_rate_multiplier' => 1.50,
                'effective_from' => '2024-07-16',
                'effective_until' => '2025-07-15',
                'is_active' => false,
            ],
            [
                'fiscal_year' => '2082/83',
                'name' => 'Nepal Statutory Payroll & Tax Slabs FY 2082/83',
                'ssf_employee_rate' => 11.00,
                'ssf_employer_rate' => 20.00,
                'single_slab_1_limit' => 500000.00,
                'single_slab_1_rate' => 1.00,
                'single_slab_2_limit' => 200000.00,
                'single_slab_2_rate' => 10.00,
                'single_slab_3_limit' => 300000.00,
                'single_slab_3_rate' => 20.00,
                'single_slab_4_limit' => 1000000.00,
                'single_slab_4_rate' => 30.00,
                'single_slab_5_limit' => 3000000.00,
                'single_slab_5_rate' => 36.00,
                'single_slab_top_rate' => 39.00,
                'married_slab_1_limit' => 600000.00,
                'married_slab_1_rate' => 1.00,
                'married_slab_2_limit' => 200000.00,
                'married_slab_2_rate' => 10.00,
                'married_slab_3_limit' => 300000.00,
                'married_slab_3_rate' => 20.00,
                'married_slab_4_limit' => 900000.00,
                'married_slab_4_rate' => 30.00,
                'married_slab_5_limit' => 3000000.00,
                'married_slab_5_rate' => 36.00,
                'married_slab_top_rate' => 39.00,
                'standard_daily_hours' => 8.00,
                'standard_monthly_work_days' => 26,
                'overtime_rate_multiplier' => 1.50,
                'effective_from' => '2025-07-16',
                'effective_until' => '2026-07-15',
                'is_active' => true,
            ],
            [
                'fiscal_year' => '2083/84',
                'name' => 'Nepal Statutory Payroll & Tax Slabs FY 2083/84',
                'ssf_employee_rate' => 11.00,
                'ssf_employer_rate' => 20.00,
                'single_slab_1_limit' => 500000.00,
                'single_slab_1_rate' => 1.00,
                'single_slab_2_limit' => 200000.00,
                'single_slab_2_rate' => 10.00,
                'single_slab_3_limit' => 300000.00,
                'single_slab_3_rate' => 20.00,
                'single_slab_4_limit' => 1000000.00,
                'single_slab_4_rate' => 30.00,
                'single_slab_5_limit' => 3000000.00,
                'single_slab_5_rate' => 36.00,
                'single_slab_top_rate' => 39.00,
                'married_slab_1_limit' => 600000.00,
                'married_slab_1_rate' => 1.00,
                'married_slab_2_limit' => 200000.00,
                'married_slab_2_rate' => 10.00,
                'married_slab_3_limit' => 300000.00,
                'married_slab_3_rate' => 20.00,
                'married_slab_4_limit' => 900000.00,
                'married_slab_4_rate' => 30.00,
                'married_slab_5_limit' => 3000000.00,
                'married_slab_5_rate' => 36.00,
                'married_slab_top_rate' => 39.00,
                'standard_daily_hours' => 8.00,
                'standard_monthly_work_days' => 26,
                'overtime_rate_multiplier' => 1.50,
                'effective_from' => '2026-07-16',
                'effective_until' => '2027-07-15',
                'is_active' => true,
            ],
        ];

        foreach ($configs as $cfg) {
            PayrollTaxConfiguration::updateOrCreate(
                ['fiscal_year' => $cfg['fiscal_year']],
                $cfg
            );
        }
    }

    /**
     * Resolve statutory payroll and TDS configuration for the given fiscal year.
     */
    public function resolvePayrollTaxConfiguration(?string $fiscalYear = null): PayrollTaxConfiguration
    {
        $this->seedNepalPayrollTaxConfigurations();
        return PayrollTaxConfiguration::resolveForFiscalYear($fiscalYear);
    }

    /**
     * Create a new monthly payroll preparation run for Nepal ERP.
     */
    public function createPayrollRun(
        string $name,
        string $periodStart,
        string $periodEnd,
        string $payDate,
        mixed $fiscalYear = null,
        ?User $user = null
    ): PayrollRun {
        if ($fiscalYear instanceof User) {
            $user = $fiscalYear;
            $fiscalYear = null;
        }

        return DB::transaction(function () use ($name, $periodStart, $periodEnd, $payDate, $fiscalYear, $user) {
            $fy = (is_string($fiscalYear) && !empty($fiscalYear))
                ? $fiscalYear
                : ($this->accountingService->resolveFiscalYearForDate($periodStart) ?? '2082/83');
            $monthStr = Carbon::parse($periodStart)->format('Y-m');
            $runNumber = "PAY-{$monthStr}";

            // Ensure unique run number
            $count = PayrollRun::where('run_number', 'like', "{$runNumber}%")->count();
            if ($count > 0) {
                $runNumber .= '-' . ($count + 1);
            }

            $run = PayrollRun::create([
                'run_number' => $runNumber,
                'fiscal_year' => $fy,
                'name' => $name,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'pay_date' => $payDate,
                'status' => 'draft',
            ]);

            // Calculate payroll items for all active employees
            $this->calculateRunItems($run);

            if ($this->settingsService->getBoolean('hrm', 'auto_post_payroll_to_accounting', false)) {
                $this->postPayrollToAccounting($run, $user);
            }

            return $run;
        });
    }

    /**
     * Calculate line items for all active employees within a payroll run.
     * Incorporates attendance hours, overtime, absence, SSF, and Nepal Section 87 TDS.
     */
    public function calculateRunItems(PayrollRun $run): void
    {
        DB::transaction(function () use ($run) {
            // Delete existing items for draft run
            $run->items()->delete();

            $taxConfig = $this->resolvePayrollTaxConfiguration($run->fiscal_year);
            $employees = Employee::with(['activeContract', 'position', 'department'])
                ->where('status', 'active')
                ->get();

            $totalBasic = 0.00;
            $totalAllowances = 0.00;
            $totalOtAmount = 0.00;
            $totalGross = 0.00;
            $totalSsfEe = 0.00;
            $totalSsfEr = 0.00;
            $totalTds = 0.00;
            $totalNet = 0.00;
            $totalCost = 0.00;

            $stdMonthlyDays = $taxConfig->standard_monthly_work_days ?: 26;
            $stdDailyHours = (float)($taxConfig->standard_daily_hours ?: 8.00);
            $otMultiplier = (float)($taxConfig->overtime_rate_multiplier ?: 1.50);

            $itemIndex = 1;
            foreach ($employees as $emp) {
                $contract = $emp->activeContract;

                // 1. Determine Basic Salary & Allowances
                $basicSalary = (float)($emp->basic_salary ?: $contract?->monthly_salary_npr ?: $emp->position?->min_salary_npr ?: 30000.00);
                $allowanceAmount = (float)($emp->allowance_amount ?: 0.00);

                // Daily & hourly rates for overtime and absence
                $dailyRate = round($basicSalary / $stdMonthlyDays, 2);
                $hourlyRate = round($dailyRate / $stdDailyHours, 2);

                // 2. Fetch Attendance / Timesheets
                $timesheets = Timesheet::where('employee_id', $emp->id)
                    ->whereBetween('date', [$run->period_start, $run->period_end])
                    ->get();

                $regularHoursWorked = (float)$timesheets->sum('regular_hours');
                $overtimeHoursWorked = (float)$timesheets->sum('overtime_hours');

                // Overtime Amount = OT Hours * Hourly Rate * Overtime Multiplier
                $overtimeAmount = round($overtimeHoursWorked * ($hourlyRate * $otMultiplier), 2);

                // Unpaid Absence Deduction
                // If timesheets exist and days present < standard working days, calculate absence
                $absentDays = 0.0;
                $absenceDeduction = 0.0;
                if ($timesheets->count() > 0 && $timesheets->where('attendance_status', 'present')->count() < $stdMonthlyDays) {
                    // Optional: only deduct if explicit unexcused absence
                    $presentCount = $timesheets->whereIn('attendance_status', ['present', 'half_day'])->count();
                    if ($presentCount < 20 && $emp->employment_type === 'hourly') {
                        $absentDays = max(0.0, 20 - $presentCount);
                        $absenceDeduction = round($absentDays * $dailyRate, 2);
                    }
                }

                $bonusAmount = 0.00;
                $grossSalary = max(0.0, round($basicSalary + $allowanceAmount + $overtimeAmount + $bonusAmount - $absenceDeduction, 2));

                // 3. Social Security Fund (SSF)
                $ssfEeAmount = 0.00;
                $ssfErAmount = 0.00;
                $isSsf = (bool)$emp->ssf_enrolled;
                if ($isSsf) {
                    $ssfData = $taxConfig->calculateSsf($basicSalary);
                    $ssfEeAmount = $ssfData['employee_amount'];
                    $ssfErAmount = $ssfData['employer_amount'];
                }

                // 4. Taxable Income & Section 87 Withholding (TDS)
                $taxableMonthly = max(0.0, round($grossSalary - $ssfEeAmount, 2));
                $annualProjected = round($taxableMonthly * 12.0, 2);
                $tdsData = $taxConfig->calculateTds($annualProjected, $emp->marital_status ?? 'single', $isSsf);
                $tdsAmount = $tdsData['monthly_tds_tax'];
                $tdsRate = $tdsData['effective_rate'];

                // 5. Net Salary & Employer Total Cost
                $citDeduction = 0.00;
                $otherDeductions = 0.00;
                $netSalary = max(0.0, round($grossSalary - $ssfEeAmount - $tdsAmount - $citDeduction - $otherDeductions, 2));
                $empTotalCost = round($grossSalary + $ssfErAmount, 2);

                $payslipNumber = sprintf('SLIP-%s-%04d-%04d', str_replace('/', '', $run->fiscal_year), $run->id, $emp->id);

                PayrollRunItem::create([
                    'payroll_run_id' => $run->id,
                    'employee_id' => $emp->id,
                    'contract_id' => $contract?->id,
                    'basic_salary' => $basicSalary,
                    'allowance_amount' => $allowanceAmount,
                    'overtime_hours' => $overtimeHoursWorked,
                    'overtime_amount' => $overtimeAmount,
                    'absent_days' => $absentDays,
                    'absence_deduction' => $absenceDeduction,
                    'bonus_amount' => $bonusAmount,
                    'gross_salary' => $grossSalary,
                    'ssf_employee_amount' => $ssfEeAmount,
                    'ssf_employer_amount' => $ssfErAmount,
                    'taxable_income' => $taxableMonthly,
                    'tds_tax_rate' => $tdsRate,
                    'tds_tax_amount' => $tdsAmount,
                    'cit_deduction' => $citDeduction,
                    'other_deductions' => $otherDeductions,
                    'net_salary' => $netSalary,
                    'employer_total_cost' => $empTotalCost,
                    'payslip_number' => $payslipNumber,

                    // NPR Fields
                    'base_salary_npr' => $basicSalary,
                    'hourly_rate_npr' => $hourlyRate,
                    'hours_worked' => $regularHoursWorked + $overtimeHoursWorked,
                    'overtime_amount_npr' => $overtimeAmount,
                    'bonus_amount_npr' => $bonusAmount,
                    'allowance_amount_npr' => $allowanceAmount,
                    'gross_salary_npr' => $grossSalary,
                    'taxable_base_npr' => $taxableMonthly,
                    'pension_employee_npr' => $ssfEeAmount,
                    'pension_employer_npr' => $ssfErAmount,
                    'net_salary_npr' => $netSalary,
                    'total_cost_npr' => $empTotalCost,
                ]);

                $totalBasic += $basicSalary;
                $totalAllowances += $allowanceAmount;
                $totalOtAmount += $overtimeAmount;
                $totalGross += $grossSalary;
                $totalSsfEe += $ssfEeAmount;
                $totalSsfEr += $ssfErAmount;
                $totalTds += $tdsAmount;
                $totalNet += $netSalary;
                $totalCost += $empTotalCost;

                $itemIndex++;
            }

            $run->update([
                'status' => 'calculated',
                'total_basic_salary' => $totalBasic,
                'total_allowances' => $totalAllowances,
                'total_overtime_amount' => $totalOtAmount,
                'total_ssf_employee' => $totalSsfEe,
                'total_ssf_employer' => $totalSsfEr,
                'total_tds_tax' => $totalTds,
                'total_net_salary' => $totalNet,
                'total_employer_cost' => $totalCost,

                // NPR Fields
                'total_gross_salary_npr' => $totalGross,
                'total_pension_employee_npr' => $totalSsfEe,
                'total_pension_employer_npr' => $totalSsfEr,
                'total_net_payout_npr' => $totalNet,
                'total_employer_cost_npr' => $totalCost,
            ]);
        });
    }

    /**
     * Post balanced double-entry accounting journal voucher for the payroll run.
     *
     * Debits:
     *   Dr Staff Salaries & Wages (6120 / 1810) - Gross Salary
     *   Dr SSF Employer Contribution (6130 / 1830) - Employer SSF (20%)
     * Credits:
     *   Cr Staff Salaries & SSF Payable (2150 / 2740) - Net Salaries
     *   Cr TDS Withholding Tax Payable (2140 / 2720) - Section 87 TDS
     *   Cr SSF Contribution Payable (2150 / 2790) - Total SSF (Employee + Employer)
     */
    public function postPayrollToAccounting(PayrollRun $run, ?User $user = null): JournalEntry
    {
        return DB::transaction(function () use ($run, $user) {
            if ($run->status === 'posted_to_accounting' && $run->journal_entry_id) {
                return JournalEntry::findOrFail($run->journal_entry_id);
            }

            $this->accountingService->ensureDefaultChartOfAccounts();

            // Locate Nepal Chart of Accounts with robust fallbacks
            $accSalaryExp = Account::where('account_number', '6120')->first()
                ?: Account::where('account_number', '1810')->firstOrFail();
            $accSsfExp = Account::where('account_number', '6130')->first()
                ?: Account::where('account_number', '1830')->firstOrFail();
            $accSalaryPayable = Account::where('account_number', '2150')->first()
                ?: Account::where('account_number', '2740')->first()
                ?: Account::where('account_number', '2790')->firstOrFail();
            $accTdsPayable = Account::where('account_number', '2140')->first()
                ?: Account::where('account_number', '2720')->firstOrFail();

            $grossSalary = (float)$run->gross_salary;
            $ssfEmployer = (float)($run->total_ssf_employer > 0 ? $run->total_ssf_employer : $run->total_pension_employer_npr);
            $ssfEmployee = (float)($run->total_ssf_employee > 0 ? $run->total_ssf_employee : $run->total_pension_employee_npr);
            $tdsTax = (float)($run->total_tds_tax > 0 ? $run->total_tds_tax : $run->total_a_skat_npr);
            $netSalary = (float)$run->net_salary;

            // Total SSF Payable = Employee (11%) + Employer (20%)
            $totalSsfLiability = round($ssfEmployee + $ssfEmployer, 2);

            // Debits:
            $debitSalary = round($grossSalary, 2);
            $debitSsfEmployer = round($ssfEmployer, 2);

            // Credits:
            $creditTds = round($tdsTax, 2);
            $creditSsf = round($totalSsfLiability, 2);
            $creditNetPayable = round($netSalary, 2);

            // Balancing Check
            $totalDebit = round($debitSalary + $debitSsfEmployer, 2);
            $totalCredit = round($creditTds + $creditSsf + $creditNetPayable, 2);
            $diff = round($totalDebit - $totalCredit, 2);

            if (abs($diff) > 0.00 && abs($diff) <= 0.05) {
                $creditNetPayable = round($creditNetPayable + $diff, 2);
            }

            $voucherLines = [
                [
                    'account_id' => $accSalaryExp->id,
                    'account_number' => $accSalaryExp->account_number,
                    'description' => "Gross Staff Salaries & Allowances ({$run->run_number})",
                    'debit' => $debitSalary,
                    'credit' => 0.00,
                ],
                [
                    'account_id' => $accSsfExp->id,
                    'account_number' => $accSsfExp->account_number,
                    'description' => "Social Security Fund (SSF) Employer Contribution 20% ({$run->run_number})",
                    'debit' => $debitSsfEmployer,
                    'credit' => 0.00,
                ],
                [
                    'account_id' => $accTdsPayable->id,
                    'account_number' => $accTdsPayable->account_number,
                    'description' => "Staff TDS Withholding Tax Payable Sec 87 ({$run->run_number})",
                    'debit' => 0.00,
                    'credit' => $creditTds,
                ],
                [
                    'account_id' => $accSalaryPayable->id,
                    'account_number' => $accSalaryPayable->account_number,
                    'description' => "Staff Net Salaries & SSF Payable ({$run->run_number})",
                    'debit' => 0.00,
                    'credit' => round($creditSsf + $creditNetPayable, 2),
                ],
            ];

            $entry = $this->accountingService->postJournalEntry([
                'voucher_date' => $run->pay_date ? $run->pay_date->format('Y-m-d') : date('Y-m-d'),
                'entry_type' => 'manual',
                'reference_type' => 'hrm_payroll_run',
                'reference_id' => $run->id,
                'description' => "Payroll Accrual Voucher {$run->run_number} ({$run->name})",
                'notes' => "Automated Nepal statutory payroll accrual voucher for {$run->run_number} (FY {$run->fiscal_year}).",
                'currency' => 'NPR',
            ], $voucherLines, $user);

            $run->update([
                'status' => 'posted_to_accounting',
                'journal_entry_id' => $entry->id,
                'approved_by' => $user?->id,
                'approved_at' => now(),
            ]);

            return $entry;
        });
    }

    /**
     * Record actual net salary payment / bank disbursement to employees.
     *
     * Double Entry:
     *   Dr Staff Salaries & SSF Payable (2150 / 2740) - Net Salary Amount
     *   Cr Operating Bank Account (1120 / 2410) - Net Salary Amount
     */
    public function recordSalaryDisbursement(
        PayrollRun $run,
        BankAccount $bankAccount,
        ?User $user = null,
        ?string $reference = null
    ): JournalEntry {
        return DB::transaction(function () use ($run, $bankAccount, $user, $reference) {
            if ($run->status === 'paid' && $run->payout_journal_entry_id) {
                return JournalEntry::findOrFail($run->payout_journal_entry_id);
            }

            if ($run->status !== 'posted_to_accounting' && !$run->journal_entry_id) {
                $this->postPayrollToAccounting($run, $user);
            }

            $accSalaryPayable = Account::where('account_number', '2150')->first()
                ?: Account::where('account_number', '2740')->first()
                ?: Account::where('account_number', '2790')->firstOrFail();
            $accBank = $bankAccount->ledgerAccount ?: Account::where('account_number', '1120')->first()
                ?: Account::where('account_number', '2410')->firstOrFail();

            $payoutAmount = (float)$run->net_salary;
            $payDate = $run->pay_date ? $run->pay_date->format('Y-m-d') : date('Y-m-d');
            $ref = $reference ?: "SAL-PAY-{$run->run_number}";

            $voucherLines = [
                [
                    'account_id' => $accSalaryPayable->id,
                    'account_number' => $accSalaryPayable->account_number,
                    'description' => "Net Salary Disbursed to Staff ({$run->run_number})",
                    'debit' => $payoutAmount,
                    'credit' => 0.00,
                ],
                [
                    'account_id' => $accBank->id,
                    'account_number' => $accBank->account_number,
                    'description' => "Salary Payout via {$bankAccount->name} (Ref: {$ref})",
                    'debit' => 0.00,
                    'credit' => $payoutAmount,
                ],
            ];

            $entry = $this->accountingService->postJournalEntry([
                'voucher_date' => $payDate,
                'entry_type' => 'bank',
                'reference_type' => 'hrm_payroll_disbursement',
                'reference_id' => $run->id,
                'description' => "Salary Disbursement Voucher {$run->run_number} ({$bankAccount->name})",
                'notes' => "Net salary bank disbursement for payroll {$run->run_number} via {$bankAccount->bank_name}.",
                'currency' => 'NPR',
            ], $voucherLines, $user);

            // Record Bank Transaction
            BankTransaction::create([
                'bank_account_id' => $bankAccount->id,
                'transaction_date' => $payDate,
                'amount' => -$payoutAmount,
                'currency' => 'NPR',
                'description' => "Staff Salary Disbursement — {$run->name}",
                'external_reference' => $ref,
                'is_reconciled' => true,
                'reconciled_at' => now(),
                'journal_entry_id' => $entry->id,
                'match_type' => 'direct_voucher',
            ]);

            // Update bank account balance
            $bankAccount->decrement('current_balance', $payoutAmount);

            $run->update([
                'status' => 'paid',
                'payout_journal_entry_id' => $entry->id,
                'payout_bank_account_id' => $bankAccount->id,
                'payout_date' => $payDate,
                'payout_reference' => $ref,
            ]);

            return $entry;
        });
    }
}
