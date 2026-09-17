<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountingFiscalYear;
use App\Models\Accounting\AccountingInvoice;
use App\Models\Accounting\AccountingInvoiceItem;
use App\Models\Accounting\AccountingPeriod;
use App\Models\Accounting\BankAccount;
use App\Models\Accounting\BankTransaction;
use App\Models\Accounting\BikriKhataEntry;
use App\Models\Accounting\CodSettlement;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Accounting\KharidKhataEntry;
use App\Models\Accounting\TdsRecord;
use App\Models\Accounting\TdsRule;
use App\Models\Accounting\VatConfiguration;
use App\Models\Accounting\VatDeclaration;
use App\Models\Inventory\StockAdjustment;
use App\Models\OfflineSale;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use App\Services\Settings\SettingsService;
use App\Services\TaxCalculatorService;

class AccountingService
{
    public const NEPAL_VAT_RATE = 13.00;

    protected SettingsService $settingsService;

    public function __construct(?SettingsService $settingsService = null)
    {
        $this->settingsService = $settingsService ?? app(SettingsService::class);
    }

    /**
     * Seeds the standard Chart of Accounts compliant with Nepal Accounting Standards (NAS)
     * and Nepal Inland Revenue Department (IRD) requirements.
     */
    public function seedDefaultChartOfAccounts(): void
    {
        $this->seedNepalChartOfAccounts();

        // Initialize default Nepal Bank Accounts
        $bankNpr = Account::where('account_number', '1120')->first();
        if ($bankNpr) {
            BankAccount::firstOrCreate(
                ['ledger_account_id' => $bankNpr->id],
                [
                    'name' => 'Nabil Bank Operating Account NPR',
                    'bank_name' => 'Nabil Bank Ltd.',
                    'account_number' => '01201017500123',
                    'currency' => 'NPR',
                    'opening_balance' => 0.00,
                    'current_balance' => 0.00,
                ]
            );
        }

        $cashShowroom = Account::where('account_number', '1110')->first();
        if ($cashShowroom) {
            BankAccount::firstOrCreate(
                ['ledger_account_id' => $cashShowroom->id],
                [
                    'name' => 'Showroom Cash Drawer (NPR)',
                    'bank_name' => 'Cash Register Durbar Marg',
                    'currency' => 'NPR',
                    'opening_balance' => 0.00,
                    'current_balance' => 0.00,
                ]
            );
        }

        // Initialize default Periods for the current year
        $year = (int)date('Y');
        for ($m = 1; $m <= 12; $m++) {
            $monthStr = sprintf('%04d-%02d', $year, $m);
            $start = Carbon::create($year, $m, 1)->startOfMonth();
            $end = Carbon::create($year, $m, 1)->endOfMonth();

            AccountingPeriod::firstOrCreate(
                ['name' => $monthStr],
                [
                    'period_type' => 'month',
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                    'status' => 'open',
                ]
            );
        }

        // Full fiscal year period
        AccountingPeriod::firstOrCreate(
            ['name' => "FY-{$year}"],
            [
                'period_type' => 'year',
                'start_date' => "{$year}-01-01",
                'end_date' => "{$year}-12-31",
                'status' => 'open',
            ]
        );
    }

    /**
     * Seeds the standard Nepal Chart of Accounts (Nepal-first retail operating system)
     * adhering to Nepal IRD VAT and Income Tax compliance.
     */
    public function seedNepalChartOfAccounts(): void
    {
        $accounts = [
            // --- 1000-1999: ASSETS ---
            ['1110', 'Cash on Hand (POS Register / Showroom)', 'cash_bank', 'asset', 'debit', 0.00, 'Showroom cash drawer in Kathmandu'],
            ['1120', 'Operating Bank Account (Nabil / NIMB NPR)', 'cash_bank', 'asset', 'debit', 0.00, 'Primary commercial bank account'],
            ['1130', 'eSewa Merchant Clearing Account', 'receivables', 'asset', 'debit', 0.00, 'eSewa QR & manual customer transfer clearing'],
            ['1140', 'ConnectIPS / NCHL Clearing Account', 'receivables', 'asset', 'debit', 0.00, 'ConnectIPS gateway settlements in transit'],
            ['1150', 'COD Receivables — Courier Clearing (NCM / Pathao)', 'receivables', 'asset', 'debit', 0.00, 'Cash on delivery collected by courier partners'],
            ['1160', 'POS Card / Fonepay Clearing Account', 'receivables', 'asset', 'debit', 0.00, 'In-store digital swiper & Fonepay merchant clearing'],
            ['1210', 'Merchandise Inventory (Showroom & Warehouse)', 'inventory', 'asset', 'debit', 0.00, 'Retail stock at cost'],
            ['1310', 'Accounts Receivable (Customers / Corporate)', 'receivables', 'asset', 'debit', 0.00, 'Trade customer receivables'],

            // --- 2000-2999: LIABILITIES ---
            ['2110', 'Accounts Payable (Artisans & Sourcing Suppliers)', 'payables', 'liability', 'credit', 0.00, 'Supplier trade payables'],
            ['2120', 'VAT Payable (13% Nepal Output VAT)', 'vat_tax', 'liability', 'credit', 13.00, 'Nepal IRD Output VAT on sales'],
            ['2130', 'VAT Receivable / Input Tax Credit (13%)', 'vat_tax', 'asset', 'debit', 13.00, 'Nepal IRD Input VAT on purchases'],
            ['2140', 'TDS Withholding Tax Payable', 'vat_tax', 'liability', 'credit', 0.00, 'Withholding tax payable to Nepal IRD'],
            ['2150', 'Staff Salaries & SSF Payable', 'payables', 'liability', 'credit', 0.00, 'Accrued employee compensation'],

            // --- 3000-3999: EQUITY ---
            ['3110', 'Owner Capital', 'equity', 'equity', 'credit', 0.00, 'Contributed business capital'],
            ['3120', 'Retained Earnings', 'equity', 'equity', 'credit', 0.00, 'Accumulated earnings'],

            // --- 4000-4999: REVENUE ---
            ['4110', 'Retail Showroom / POS Sales Revenue', 'revenue', 'income', 'credit', 13.00, 'Physical showroom sales in Kathmandu'],
            ['4120', 'Online Storefront Sales Revenue (Laijau.com)', 'revenue', 'income', 'credit', 13.00, 'E-commerce website orders'],
            ['4130', 'Delivery & Courier Shipping Revenue', 'revenue', 'income', 'credit', 0.00, 'Customer shipping charges'],

            // --- 5000-5999: COGS ---
            ['5110', 'Cost of Goods Sold (Merchandise Sold)', 'cogs', 'expense', 'debit', 0.00, 'Direct landed cost of goods sold'],

            // --- 6000-6999: OPERATING EXPENSES ---
            ['6110', 'Showroom & Warehouse Rent', 'opex', 'expense', 'debit', 0.00, 'Facility lease expenses'],
            ['6120', 'Staff Salaries & Wages', 'personnel', 'expense', 'debit', 0.00, 'Employee payroll'],
            ['6130', 'Social Security Fund (SSF Employer Contribution)', 'personnel', 'expense', 'debit', 0.00, 'Employer SSF statutory contribution'],
            ['6140', 'Electricity & Water Utilities', 'opex', 'expense', 'debit', 0.00, 'NEA electricity and utility charges'],
            ['6150', 'Nationwide Courier & Delivery (Pathao / NCM)', 'opex', 'expense', 'debit', 0.00, 'Courier logistics fees'],
            ['6160', 'Packaging, Shopping Bags & Brand Boxes', 'opex', 'expense', 'debit', 13.00, 'Packaging and retail presentation'],
            ['6170', 'Digital Marketing & Social Media Ads', 'opex', 'expense', 'debit', 0.00, 'Promotional marketing'],
            ['6180', 'Software, Cloud Hosting & Telecom', 'opex', 'expense', 'debit', 13.00, 'IT, hosting and internet connectivity'],
            ['6190', 'Bank & Payment Gateway Processing Fees', 'financial', 'expense', 'debit', 0.00, 'eSewa, ConnectIPS and bank transaction fees'],
            ['6200', 'Showroom Repair, Cleaning & Maintenance', 'opex', 'expense', 'debit', 0.00, 'Store maintenance'],
            ['6210', 'Audit, Legal & Accounting Professional Fees', 'opex', 'expense', 'debit', 0.00, 'Nepal statutory compliance and audit fees'],
        ];

        foreach ($accounts as $acc) {
            Account::updateOrCreate(
                ['account_number' => $acc[0]],
                [
                    'name' => $acc[1],
                    'category' => $acc[2],
                    'account_type' => $acc[3],
                    'normal_balance' => $acc[4],
                    'default_vat_rate' => $acc[5],
                    'currency' => 'NPR',
                    'description' => $acc[6],
                    'is_active' => true,
                    'is_system' => true,
                ]
            );
        }
    }

    /**
     * Ensures that the default standard Nepal Chart of Accounts and Fiscal Years are seeded if missing.
     */
    public function ensureDefaultChartOfAccounts(): void
    {
        $this->seedNepalChartOfAccounts();
        $this->seedDefaultChartOfAccounts();
        $this->seedNepalFiscalYearsAndPeriods();
        $this->seedNepalTaxRulesAndVatConfigs();
    }

    /**
     * Seeds statutory Nepali Fiscal Years (Bikram Sambat cycle: Shrawan 1 to Ashadh 32)
     * and generates the 12 statutory Nepali monthly accounting periods.
     */
    public function seedNepalFiscalYearsAndPeriods(): void
    {
        $fiscalYears = [
            [
                'fiscal_year' => '2081/82',
                'start_date' => '2024-07-16',
                'end_date' => '2025-07-15',
                'is_current' => false,
                'status' => 'closed',
                'notes' => 'Audited Nepali Fiscal Year 2081/82',
            ],
            [
                'fiscal_year' => '2082/83',
                'start_date' => '2025-07-16',
                'end_date' => '2026-07-15',
                'is_current' => false,
                'status' => 'locked',
                'notes' => 'Prior Nepali Fiscal Year 2082/83 (Finance Act 2082)',
            ],
            [
                'fiscal_year' => '2083/84',
                'start_date' => '2026-07-16',
                'end_date' => '2027-07-15',
                'is_current' => true,
                'status' => 'open',
                'notes' => 'Active Nepali Fiscal Year 2083/84 (Finance Act 2083)',
            ],
        ];

        $nepaliMonthNames = [
            1 => ['Shrawan', 'श्रावण'],
            2 => ['Bhadra', 'भाद्र'],
            3 => ['Ashwin', 'आश्विन'],
            4 => ['Kartik', 'कार्तिक'],
            5 => ['Mangsir', 'मंसिर'],
            6 => ['Poush', 'पौष'],
            7 => ['Magh', 'माघ'],
            8 => ['Falgun', 'फाल्गुन'],
            9 => ['Chaitra', 'चैत्र'],
            10 => ['Baisakh', 'वैशाख'],
            11 => ['Jestha', 'ज्येष्ठ'],
            12 => ['Ashadh', 'आषाढ'],
        ];

        foreach ($fiscalYears as $fyData) {
            $fy = AccountingFiscalYear::updateOrCreate(
                ['fiscal_year' => $fyData['fiscal_year']],
                $fyData
            );

            // Generate 12 statutory Nepali monthly accounting periods (Shrawan to Ashadh)
            $start = Carbon::parse($fy->start_date);
            for ($m = 1; $m <= 12; $m++) {
                $monthStart = $start->copy()->addMonths($m - 1);
                $monthEnd = $m === 12 ? Carbon::parse($fy->end_date) : $start->copy()->addMonths($m)->subDay();

                $yearPart = explode('/', $fy->fiscal_year)[0];
                if ($m >= 10) {
                    $yearPart = (string)((int)$yearPart + 1);
                }
                $enName = $nepaliMonthNames[$m][0];
                $npName = $nepaliMonthNames[$m][1];
                $nepaliLabel = "{$yearPart}-{$enName} ({$npName})";
                $periodCode = "{$fy->fiscal_year}-M" . sprintf('%02d', $m);

                AccountingPeriod::firstOrCreate(
                    [
                        'fiscal_year' => $fy->fiscal_year,
                        'nepali_month' => $m,
                    ],
                    [
                        'name' => $periodCode,
                        'nepali_label' => $nepaliLabel,
                        'period_type' => 'month',
                        'start_date' => $monthStart->toDateString(),
                        'end_date' => $monthEnd->toDateString(),
                        'status' => $fy->status === 'closed' ? 'closed' : ($fy->status === 'locked' ? 'locked' : 'open'),
                    ]
                );
            }
        }
    }

    /**
     * Seeds statutory data-driven TDS Rules (Income Tax Act 2058) and versioned VAT Configurations.
     */
    public function seedNepalTaxRulesAndVatConfigs(): void
    {
        $fiscalYears = ['2081/82', '2082/83', '2083/84'];

        // 1. VAT Configurations
        foreach ($fiscalYears as $fy) {
            $fyModel = AccountingFiscalYear::where('fiscal_year', $fy)->first();
            $startDate = $fyModel?->start_date ? Carbon::parse($fyModel->start_date)->format('Y-m-d') : '2024-07-16';
            $endDate = $fyModel?->end_date ? Carbon::parse($fyModel->end_date)->format('Y-m-d') : null;

            VatConfiguration::firstOrCreate(
                ['fiscal_year' => $fy],
                [
                    'standard_vat_rate' => 13.00,
                    'taxable_eligible' => true,
                    'zero_rated_export_eligible' => true,
                    'exempt_schedule_eligible' => true,
                    'input_vat_restricted_categories' => ['passenger_vehicles', 'entertainment', 'personal_consumption'],
                    'filing_frequency' => 'monthly',
                    'effective_from' => $startDate,
                    'effective_until' => $endDate,
                    'notes' => "Statutory VAT configuration for Nepali FY {$fy} under Nepal VAT Act 2052 & Finance Acts",
                ]
            );
        }

        // 2. TDS Rules (Income Tax Act 2058 Sections 87, 88, 88Ka, 89)
        $statutoryTdsTemplates = [
            [
                'payment_type' => 'contract_goods',
                'section' => '89',
                'rate' => 1.50,
                'rate_without_pan' => 3.00,
                'threshold' => 50000.00,
                'requires_pan' => true,
                'exemptions' => 'Payment for procurement of goods under contract exceeding Rs. 50,000 (Section 89)',
            ],
            [
                'payment_type' => 'rent',
                'section' => '88',
                'rate' => 10.00,
                'rate_without_pan' => 10.00,
                'threshold' => 0.00,
                'requires_pan' => true,
                'exemptions' => 'House / store showroom rent withholding tax (Section 88)',
            ],
            [
                'payment_type' => 'consultancy',
                'section' => '88',
                'rate' => 15.00,
                'rate_without_pan' => 15.00,
                'threshold' => 0.00,
                'requires_pan' => true,
                'exemptions' => 'Professional & technical consultancy service fees (Section 88; 1.5% if supplied with VAT tax invoice)',
            ],
            [
                'payment_type' => 'transport_freight',
                'section' => '88',
                'rate' => 2.50,
                'rate_without_pan' => 5.00,
                'threshold' => 0.00,
                'requires_pan' => true,
                'exemptions' => 'Vehicle rental and cargo freight services (Section 88)',
            ],
            [
                'payment_type' => 'salary',
                'section' => '87',
                'rate' => 1.00,
                'rate_without_pan' => 1.00,
                'threshold' => 0.00,
                'requires_pan' => true,
                'exemptions' => 'Social security tax / employment withholding (Section 87)',
            ],
            [
                'payment_type' => 'interest',
                'section' => '88',
                'rate' => 5.00,
                'rate_without_pan' => 15.00,
                'threshold' => 0.00,
                'requires_pan' => true,
                'exemptions' => 'Interest payment withholding (Section 88)',
            ],
            [
                'payment_type' => 'commission',
                'section' => '88',
                'rate' => 15.00,
                'rate_without_pan' => 15.00,
                'threshold' => 0.00,
                'requires_pan' => true,
                'exemptions' => 'Brokerage and sales agency commission (Section 88)',
            ],
            [
                'payment_type' => 'audit_fee',
                'section' => '88',
                'rate' => 15.00,
                'rate_without_pan' => 15.00,
                'threshold' => 0.00,
                'requires_pan' => true,
                'exemptions' => 'Statutory audit and accounting certification fees (Section 88)',
            ],
            [
                'payment_type' => 'royalty',
                'section' => '88',
                'rate' => 15.00,
                'rate_without_pan' => 15.00,
                'threshold' => 0.00,
                'requires_pan' => true,
                'exemptions' => 'Royalty and intellectual property licensing fees (Section 88)',
            ],
        ];

        foreach ($fiscalYears as $fy) {
            $fyModel = AccountingFiscalYear::where('fiscal_year', $fy)->first();
            $startDate = $fyModel?->start_date ? Carbon::parse($fyModel->start_date)->format('Y-m-d') : '2024-07-16';
            $endDate = $fyModel?->end_date ? Carbon::parse($fyModel->end_date)->format('Y-m-d') : null;

            foreach ($statutoryTdsTemplates as $tmpl) {
                TdsRule::firstOrCreate(
                    [
                        'fiscal_year' => $fy,
                        'payment_type' => $tmpl['payment_type'],
                    ],
                    array_merge($tmpl, [
                        'fiscal_year' => $fy,
                        'is_active' => true,
                        'effective_from' => $startDate,
                        'effective_until' => $endDate,
                    ])
                );
            }
        }
    }

    /**
     * Resolves the applicable TDS rule for a given fiscal year, payment type, and transaction date.
     */
    public function resolveApplicableTdsRule(
        string $fiscalYear,
        string $paymentType,
        ?string $payeePan = null,
        ?string $date = null
    ): ?TdsRule {
        $this->seedNepalTaxRulesAndVatConfigs();
        return TdsRule::resolveRule($fiscalYear, $paymentType, $date);
    }

    /**
     * Dynamically calculates TDS according to the statutory rule for the fiscal year.
     */
    public function calculateTds(
        string $fiscalYear,
        string $paymentType,
        float $grossAmount,
        ?string $payeePan = null,
        ?string $date = null
    ): array {
        $rule = $this->resolveApplicableTdsRule($fiscalYear, $paymentType, $payeePan, $date);

        if (!$rule || !$rule->is_active) {
            return [
                'applicable' => false,
                'rule' => null,
                'rate' => 0.00,
                'amount' => 0.00,
                'section' => '88',
                'pan_provided' => !empty($payeePan),
                'threshold_met' => true,
            ];
        }

        $panProvided = !empty($payeePan);
        $thresholdMet = $grossAmount >= (float)$rule->threshold;

        if (!$thresholdMet) {
            return [
                'applicable' => false,
                'rule' => $rule,
                'rate' => 0.00,
                'amount' => 0.00,
                'section' => $rule->section,
                'pan_provided' => $panProvided,
                'threshold_met' => false,
            ];
        }

        // Check if penalty rate applies when payee lacks PAN
        $rate = (!$panProvided && $rule->rate_without_pan !== null)
            ? (float)$rule->rate_without_pan
            : (float)$rule->rate;

        $amount = round($grossAmount * ($rate / 100), 2);

        return [
            'applicable' => $amount > 0,
            'rule' => $rule,
            'rate' => $rate,
            'amount' => $amount,
            'section' => $rule->section,
            'pan_provided' => $panProvided,
            'threshold_met' => true,
        ];
    }

    /**
     * Resolves the versioned VAT configuration for a given fiscal year.
     */
    public function resolveVatConfiguration(string $fiscalYear, ?string $date = null): VatConfiguration
    {
        $this->seedNepalTaxRulesAndVatConfigs();
        return VatConfiguration::resolveForFiscalYear($fiscalYear, $date);
    }

    /**
     * Dynamically calculates VAT based on the fiscal year configuration.
     */
    public function calculateVat(
        string $fiscalYear,
        float $amount,
        string $taxType = 'taxable',
        ?string $date = null
    ): array {
        $vatConfig = $this->resolveVatConfiguration($fiscalYear, $date);

        if ($taxType === 'exempt') {
            return [
                'rate' => 0.00,
                'taxable' => 0.00,
                'vat' => 0.00,
                'exempt' => $amount,
                'export' => 0.00,
            ];
        }

        if ($taxType === 'export') {
            return [
                'rate' => 0.00,
                'taxable' => 0.00,
                'vat' => 0.00,
                'exempt' => 0.00,
                'export' => $amount,
            ];
        }

        $rate = (float)$vatConfig->standard_vat_rate;
        $vatAmount = round($amount * ($rate / 100), 2);

        return [
            'rate' => $rate,
            'taxable' => $amount,
            'vat' => $vatAmount,
            'exempt' => 0.00,
            'export' => 0.00,
        ];
    }

    /**
     * Resolves the statutory Nepali fiscal year model for a given date.
     */
    public function resolveFiscalYearForDate(\DateTimeInterface|string $date): ?AccountingFiscalYear
    {
        return AccountingFiscalYear::resolveForDate($date);
    }

    /**
     * Resolves or creates an open statutory Nepali accounting period for a given date.
     */
    public function resolvePeriodForDate(string $date): AccountingPeriod
    {
        $d = Carbon::parse($date)->toDateString();

        // 1. Try to find a matching statutory Nepali accounting period by date bounds
        $period = AccountingPeriod::where('start_date', '<=', $d)
            ->where('end_date', '>=', $d)
            ->first();

        if ($period) {
            return $period;
        }

        // 2. Resolve fiscal year
        $fy = AccountingFiscalYear::resolveForDate($date);
        $fiscalYearStr = $fy?->fiscal_year ?? '2083/84';

        // 3. Fallback: Monthly period
        $carbonDate = Carbon::parse($date);
        $monthName = $carbonDate->format('Y-m');

        return AccountingPeriod::firstOrCreate(
            ['name' => $monthName],
            [
                'fiscal_year' => $fiscalYearStr,
                'period_type' => 'month',
                'start_date' => $carbonDate->copy()->startOfMonth()->toDateString(),
                'end_date' => $carbonDate->copy()->endOfMonth()->toDateString(),
                'status' => 'open',
            ]
        );
    }

    /**
     * Posts an immutable double-entry journal voucher
     * enforcing sum(debit) == sum(credit) under Nepal Accounting Standards (NAS).
     * Automatically assigns sequential entry_number and locks entries into periods.
     */
    public function postJournalEntry(array $entryData, array $lines, ?User $user = null): JournalEntry
    {
        $entryType = $entryData['entry_type'] ?? 'manual';

        // Check permission if manual voucher
        if ($entryType === 'manual') {
            $allowedRolesRaw = $this->settingsService->getString('accounting', 'manual_journal_roles', 'admin,accountant');
            $allowedRoles = array_map('trim', explode(',', strtolower($allowedRolesRaw)));
            $actingUser = $user ?? auth()->user();
            if ($actingUser) {
                $userRole = strtolower($actingUser->role ?? 'user');
                if (!in_array($userRole, $allowedRoles, true)) {
                    throw new RuntimeException("User lacks authorization to create manual journal entries. Requires one of the following roles: {$allowedRolesRaw}.");
                }
            }
        }

        $voucherDate = $entryData['voucher_date'] ?? date('Y-m-d');
        $period = isset($entryData['accounting_period_id'])
            ? AccountingPeriod::find($entryData['accounting_period_id'])
            : $this->resolvePeriodForDate($voucherDate);

        if (!$period || !$period->isPostingAllowed()) {
            throw new RuntimeException("Accounting period {$period?->name} is locked or closed. Cannot post vouchers to a locked period.");
        }

        $allowBackdated = $this->settingsService->getBoolean('accounting', 'allow_backdated_postings', true);
        $maxBackdatedDays = $this->settingsService->getInteger('accounting', 'max_backdated_days', 30);
        $d = Carbon::parse($voucherDate);
        $daysInPast = $d->copy()->startOfDay()->diffInDays(now()->startOfDay(), false);
        if ($daysInPast > 0 && !$allowBackdated && $daysInPast > $maxBackdatedDays) {
            throw new RuntimeException("Backdating rejected: Voucher date {$voucherDate} is {$daysInPast} days in the past, exceeding the allowed limit of {$maxBackdatedDays} days under accounting policy.");
        }

        if (empty($lines)) {
            throw new InvalidArgumentException("A journal voucher must contain at least two posting lines (debit and credit).");
        }

        $currency = $entryData['currency'] ?? 'NPR';
        $exchangeRate = 1.000000;

        // Calculate and validate debit/credit balance
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $processedLines = [];

        foreach ($lines as $idx => $line) {
            $accountId = $line['account_id'] ?? null;
            $account = null;

            if (isset($line['account_number'])) {
                $account = Account::where('account_number', $line['account_number'])->first();
                if ($account) {
                    $accountId = $account->id;
                }
            } elseif ($accountId) {
                $account = Account::find($accountId);
            }

            if (!$account) {
                throw new InvalidArgumentException("Invalid ledger account specified on line " . ($idx + 1));
            }

            $lineDebit = round((float)($line['debit'] ?? 0), 4);
            $lineCredit = round((float)($line['credit'] ?? 0), 4);

            $totalDebit += $lineDebit;
            $totalCredit += $lineCredit;

            $processedLines[] = [
                'account_id' => $account->id,
                'account_number' => $account->account_number,
                'line_number' => $idx + 1,
                'description' => $line['description'] ?? ($entryData['description'] ?? null),
                'debit' => $lineDebit,
                'credit' => $lineCredit,
                'currency' => $currency,
                'amount_currency' => $lineDebit > 0 ? $lineDebit : $lineCredit,
                'vat_code' => $line['vat_code'] ?? null,
                'vat_rate' => (float)($line['vat_rate'] ?? 0),
                'vat_amount' => (float)($line['vat_amount'] ?? 0),
            ];
        }

        // Validate double entry equality
        $enforceBalanced = $this->settingsService->getBoolean('accounting', 'enforce_balanced_journals', true);
        $tolerance = $this->settingsService->getDecimal('accounting', 'max_journal_line_difference_tolerance', 0.01);
        if ($enforceBalanced && abs($totalDebit - $totalCredit) > $tolerance) {
            throw new RuntimeException("Journal voucher is not balanced! Total debit (" . number_format($totalDebit, 2) . " NPR) does not match total credit (" . number_format($totalCredit, 2) . " NPR). Difference: " . number_format(abs($totalDebit - $totalCredit), 2));
        }

        return DB::transaction(function () use ($entryData, $voucherDate, $period, $currency, $exchangeRate, $totalDebit, $totalCredit, $processedLines, $user) {
            $entryNumber = $entryData['entry_number'] ?? JournalEntry::generateNextEntryNumber($voucherDate);

            $journalEntry = JournalEntry::create([
                'entry_number' => $entryNumber,
                'voucher_date' => $voucherDate,
                'accounting_period_id' => $period->id,
                'entry_type' => $entryData['entry_type'] ?? 'manual',
                'reference_type' => $entryData['reference_type'] ?? null,
                'reference_id' => $entryData['reference_id'] ?? null,
                'description' => $entryData['description'] ?? 'Voucher ' . $entryNumber,
                'currency' => $currency,
                'exchange_rate_to_npr' => $exchangeRate,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'is_balanced' => true,
                'status' => 'posted',
                'created_by' => $user?->id ?? auth()->id(),
                'posted_at' => now(),
                'notes' => $entryData['notes'] ?? null,
            ]);

            $affectedAccountIds = [];

            foreach ($processedLines as $pLine) {
                $pLine['journal_entry_id'] = $journalEntry->id;
                JournalEntryLine::create($pLine);
                $affectedAccountIds[$pLine['account_id']] = true;
            }

            // Atomically recalculate live balance for affected accounts
            foreach (array_keys($affectedAccountIds) as $accId) {
                $acc = Account::find($accId);
                $acc?->recalculateBalance();
            }

            return $journalEntry;
        });
    }

    /**
     * Reverses a posted journal voucher under Nepal Accounting Standards (NAS) immutability rules.
     * Posts a linked reversing voucher and updates balances.
     */
    public function reverseJournalEntry(JournalEntry $originalEntry, string $reason, ?User $user = null, ?string $customDate = null): JournalEntry
    {
        if ($originalEntry->status === 'reversed') {
            throw new RuntimeException("Voucher {$originalEntry->entry_number} is already reversed.");
        }

        if ($this->settingsService->getBoolean('accounting', 'require_reversal_reason', true)) {
            if (empty(trim($reason))) {
                throw new InvalidArgumentException("A valid reason must be provided to reverse a posted journal voucher.");
            }
        }

        $reversalDate = $customDate ?: date('Y-m-d');
        $period = $this->resolvePeriodForDate($reversalDate);

        if (!$period || !$period->isPostingAllowed()) {
            throw new RuntimeException("Accounting period {$period?->name} is locked or closed. Reversals cannot be posted to a closed period.");
        }

        $allowReversalsInClosed = $this->settingsService->getBoolean('accounting', 'allow_reversals_in_closed_periods', false);
        $origPeriod = $originalEntry->period;
        if (!$allowReversalsInClosed && $origPeriod && !$origPeriod->isPostingAllowed() && $reversalDate === $originalEntry->voucher_date) {
            throw new RuntimeException("Reversals in a closed accounting period ({$origPeriod->name}) are not allowed per accounting policy.");
        }

        return DB::transaction(function () use ($originalEntry, $reason, $user, $reversalDate, $period) {
            $reversalNumber = JournalEntry::generateNextEntryNumber('reversal', $reversalDate);

            // Invert debit and credit on all lines
            $reversalLines = [];
            foreach ($originalEntry->lines as $origLine) {
                $reversalLines[] = [
                    'account_id' => $origLine->account_id,
                    'account_number' => $origLine->account_number,
                    'line_number' => $origLine->line_number,
                    'description' => "Reversal: " . ($origLine->description ?: $originalEntry->description),
                    'debit' => $origLine->credit, // swapped
                    'credit' => $origLine->debit, // swapped
                    'currency' => $origLine->currency,
                    'amount_currency' => $origLine->amount_currency,
                    'vat_code' => $origLine->vat_code,
                    'vat_rate' => $origLine->vat_rate,
                    'vat_amount' => $origLine->vat_amount,
                ];
            }

            $reversalEntry = JournalEntry::create([
                'entry_number' => $reversalNumber,
                'voucher_date' => $reversalDate,
                'accounting_period_id' => $period->id,
                'entry_type' => 'reversal',
                'reference_type' => 'reversal',
                'reference_id' => $originalEntry->id,
                'description' => "Reversal of {$originalEntry->entry_number}: {$reason}",
                'currency' => $originalEntry->currency,
                'exchange_rate_to_npr' => $originalEntry->exchange_rate_to_npr,
                'total_debit' => $originalEntry->total_credit,
                'total_credit' => $originalEntry->total_debit,
                'is_balanced' => true,
                'status' => 'posted',
                'reversal_of_entry_id' => $originalEntry->id,
                'reversal_reason' => $reason,
                'created_by' => $user?->id ?? auth()->id(),
                'posted_at' => now(),
            ]);

            foreach ($reversalLines as $rLine) {
                $rLine['journal_entry_id'] = $reversalEntry->id;
                JournalEntryLine::create($rLine);
            }

            // Mark original voucher as reversed
            $originalEntry->update([
                'status' => 'reversed',
                'reversed_by_entry_id' => $reversalEntry->id,
                'reversal_reason' => $reason,
            ]);

            // Recalculate account balances
            foreach ($originalEntry->lines->pluck('account_id')->unique() as $accId) {
                $acc = Account::find($accId);
                $acc?->recalculateBalance();
            }

            return $reversalEntry;
        });
    }

    /**
     * Automatically records a double-entry sales voucher for an e-commerce Order.
     * Integrates: Payment Gateway Clearing, Net Revenue, Nepal IRD 13% VAT, Shipping Fee, and COGS/Inventory.
     */
    public function recordOrderSale(Order $order): ?JournalEntry
    {
        $this->ensureDefaultChartOfAccounts();

        // Prevent duplicate posting
        $existing = JournalEntry::where('reference_type', 'order')
            ->where('reference_id', $order->id)
            ->where('status', '!=', 'reversed')
            ->first();

        if ($existing) {
            return $existing;
        }

        $currency = 'NPR';
        $orderTotal = (float)$order->total_amount;
        $grossNpr = round($orderTotal, 2);
        $shippingNpr = round((float)($order->shipping_cost ?? 0.0), 2);

        // Calculate Nepal 13% VAT and Net Revenue
        $vatRate = (float) $this->settingsService->getDecimal('accounting', 'standard_vat_rate', 13.0);

        $grossWithoutShipping = max(0.00, $grossNpr - $shippingNpr);
        $netProductRevenue = round($grossWithoutShipping / (1 + ($vatRate / 100)), 2);
        $netShippingRevenue = round($shippingNpr / (1 + ($vatRate / 100)), 2);
        $vatProduct = round($grossWithoutShipping - $netProductRevenue, 2);
        $vatShipping = round($shippingNpr - $netShippingRevenue, 2);
        $totalVat = round($vatProduct + $vatShipping, 2);

        // Reconcile rounding difference to ensure sum(credit) == grossNpr
        $roundingDiff = round($grossNpr - ($netProductRevenue + $netShippingRevenue + $totalVat), 2);
        $netProductRevenue += $roundingDiff;

        // Calculate landed COGS from order items
        $totalCogsNpr = 0.0;
        foreach ($order->items as $item) {
            $product = $item->product;
            if ($product) {
                $qty = (int)$item->quantity;
                $costNpr = (float)($product->cost_price ?: ($product->cost_price_npr ?: 0));
                $totalCogsNpr += round($costNpr * $qty, 2);
            }
        }

        // Accounts
        $salesVatAccCode = $this->settingsService->getString('accounting', 'gl_sales_vat_account', '2120');
        $cogsAccCode = $this->settingsService->getString('accounting', 'gl_cogs_account', '5110');
        $inventoryAccCode = $this->settingsService->getString('accounting', 'gl_inventory_asset_account', '1210');

        $accClearing = Account::where('account_number', '1130')->first()
            ?: Account::where('account_number', '1140')->firstOrFail();

        $accSales = Account::where('account_number', '4120')->firstOrFail();

        $accShippingRevenue = Account::where('account_number', '4130')->first();

        $accVatLiability = Account::where('account_number', $salesVatAccCode)->first()
            ?: Account::where('account_number', '2120')->firstOrFail();

        $accCogs = Account::where('account_number', $cogsAccCode)->first()
            ?: Account::where('account_number', '5110')->first();

        $accInventory = Account::where('account_number', $inventoryAccCode)->first()
            ?: Account::where('account_number', '1210')->first();

        $lines = [];

        // 1. Debit Clearing (total amount customer paid)
        $lines[] = [
            'account_id' => $accClearing->id,
            'account_number' => $accClearing->account_number,
            'description' => "Order payment #{$order->order_number}",
            'debit' => $grossNpr,
            'credit' => 0.00,
        ];

        // 2. Credit Product Net Revenue
        $lines[] = [
            'account_id' => $accSales->id,
            'account_number' => $accSales->account_number,
            'description' => "Product sales order #{$order->order_number}",
            'debit' => 0.00,
            'credit' => $netProductRevenue,
            'vat_code' => 'VAT_13',
            'vat_rate' => $vatRate,
            'vat_amount' => $vatProduct,
        ];

        // 3. Credit Shipping Revenue (if any)
        if ($netShippingRevenue > 0) {
            $lines[] = [
                'account_id' => $accShippingRevenue->id,
                'account_number' => $accShippingRevenue->account_number,
                'description' => "Shipping fee order #{$order->order_number}",
                'debit' => 0.00,
                'credit' => $netShippingRevenue,
                'vat_code' => 'VAT_13',
                'vat_rate' => $vatRate,
                'vat_amount' => $vatShipping,
            ];
        }

        // 4. Credit VAT liability
        if ($totalVat > 0) {
            $lines[] = [
                'account_id' => $accVatLiability->id,
                'account_number' => $accVatLiability->account_number,
                'description' => "Output VAT (13%) order #{$order->order_number}",
                'debit' => 0.00,
                'credit' => $totalVat,
                'vat_rate' => $vatRate,
                'vat_amount' => $totalVat,
            ];
        }

        $syncGlInventory = $this->settingsService->getBoolean('accounting', 'enable_inventory_gl_sync', true);
        // 5. COGS & Inventory adjustment
        if ($syncGlInventory && $totalCogsNpr > 0 && $accCogs && $accInventory) {
            $lines[] = [
                'account_id' => $accCogs->id,
                'account_number' => $accCogs->account_number,
                'description' => "Cost of Goods Sold order #{$order->order_number}",
                'debit' => $totalCogsNpr,
                'credit' => 0.00,
            ];
            $lines[] = [
                'account_id' => $accInventory->id,
                'account_number' => $accInventory->account_number,
                'description' => "Inventory stock deduction order #{$order->order_number}",
                'debit' => 0.00,
                'credit' => $totalCogsNpr,
            ];
        }

        $voucherDate = $order->created_at ? $order->created_at->toDateString() : date('Y-m-d');

        $journalEntry = $this->postJournalEntry([
            'voucher_date' => $voucherDate,
            'entry_type' => 'sales',
            'reference_type' => 'order',
            'reference_id' => $order->id,
            'description' => "Online store order #{$order->order_number} ({$order->customer_name})",
            'currency' => 'NPR',
            'exchange_rate_to_npr' => 1.000000,
        ], $lines);

        $period = $this->resolvePeriodForDate($voucherDate);
        $fiscalYear = $period->fiscal_year ?: (AccountingFiscalYear::resolveForDate($voucherDate)?->fiscal_year ?? '2083/84');

        $buyerPan = $order->customer?->pan_number ?? $order->buyer_pan ?? null;
        $customerType = !empty($buyerPan) ? 'b2b_corporate' : 'b2c_retail';
        $salesChannel = match (strtolower((string)$order->channel)) {
            'pos' => 'pos_showroom',
            'manual', 'whatsapp', 'nw' => 'concierge_whatsapp',
            default => strtolower((string)($order->source ?? '')) === 'nw' ? 'concierge_whatsapp' : 'web_storefront',
        };

        // Create or update statutory Bikri Khata Sales Invoice entry
        $invoice = AccountingInvoice::updateOrCreate(
            ['reference_order_id' => $order->id],
            [
                'invoice_number' => AccountingInvoice::generateNextInvoiceNumber('sales_invoice', $voucherDate),
                'type' => 'sales_invoice',
                'fiscal_year' => $fiscalYear,
                'tax_period_id' => $period->id,
                'contact_name' => $order->customer_name ?: 'Webshop Customer',
                'contact_email' => $order->customer_email,
                'contact_phone' => $order->customer_phone,
                'contact_address' => $order->full_address ?? $order->shipping_address,
                'contact_country' => 'NP',
                'buyer_pan' => $buyerPan,
                'customer_type' => $customerType,
                'sales_channel' => $salesChannel,
                'issue_date' => $voucherDate,
                'due_date' => $voucherDate,
                'currency' => 'NPR',
                'exchange_rate_to_npr' => 1.000000,
                'subtotal' => $netProductRevenue,
                'taxable_amount' => $netProductRevenue,
                'vat_amount' => $totalVat,
                'exempt_amount' => 0.00,
                'export_amount' => 0.00,
                'discount_amount' => (float)($order->discount_amount ?? 0),
                'total_amount' => $grossNpr,
                'paid_amount' => $grossNpr,
                'payment_status' => 'paid',
                'is_credit' => false,
                'branch' => 'Durbar Marg, Kathmandu',
                'posted_to_gl' => true,
                'journal_entry_id' => $journalEntry->id,
            ]
        );

        if ($invoice->items()->count() === 0 && $order->items->isNotEmpty()) {
            foreach ($order->items as $item) {
                $lineGross = (float)($item->total_price ?? ($item->price * $item->quantity));
                $vatData = TaxCalculatorService::calcInclusive($lineGross);
                $lineTaxable = $vatData['net_amount'];
                $lineVat = $vatData['vat_amount'];

                $invoice->items()->create([
                    'description' => ($item->product_name ?? 'Product') . ($item->variant_title ? " - {$item->variant_title}" : ''),
                    'quantity' => $item->quantity ?? 1,
                    'unit_price' => $item->price ?? $lineTaxable,
                    'vat_rate' => 13.00,
                    'vat_amount' => $lineVat,
                    'total_amount' => $lineGross,
                ]);
            }
        }

        return $journalEntry;
    }

    /**
     * Automatically records a double-entry sales voucher for a physical Showroom / Offline Sale.
     * Integrates: Cash/Digital Clearing, Net Showroom Revenue, Nepal 13% VAT, COGS/Inventory,
     * and statutory Bikri Khata (Sales Book — IRD Annex 7).
     */
    public function recordOfflineSale(OfflineSale $sale): ?JournalEntry
    {
        $this->ensureDefaultChartOfAccounts();

        // Prevent duplicate posting
        $existing = JournalEntry::where('reference_type', 'offline_sale')
            ->where('reference_id', $sale->id)
            ->where('status', '!=', 'reversed')
            ->first();

        if ($existing) {
            return $existing;
        }

        $currency = 'NPR';
        $saleTotal = (float)$sale->total_amount;
        $grossNpr = round($saleTotal, 2);

        // Nepal 13% statutory VAT via centralized engine
        $vatData = TaxCalculatorService::calcInclusive($grossNpr);
        $netRevenue = $vatData['net_amount'];
        $vatAmount = $vatData['vat_amount'];

        // COGS
        $totalCost = (float)($sale->total_cost ?: ($sale->total_cost_npr ?: 0));
        $totalCogsNpr = round($totalCost, 2);

        // Determine payment debit account
        $paymentMethod = strtolower($sale->payment_method ?: 'cash');
        $debitAccountNumber = match ($paymentMethod) {
            'cash' => '1110', // Showroom Cash Register
            'card', 'fonepay' => '1160', // POS Card / Fonepay Clearing
            'esewa' => '1130', // eSewa Merchant Clearing Account
            'connectips' => '1140', // ConnectIPS Clearing Account
            default => '1110',
        };

        $accDebit = Account::where('account_number', $debitAccountNumber)->first()
            ?: Account::where('account_number', '1110')->firstOrFail();

        $accSales = Account::where('account_number', '4110')->firstOrFail();
        $accVat = Account::where('account_number', '2120')->firstOrFail();
        $accCogs = Account::where('account_number', '5110')->first();
        $accInventory = Account::where('account_number', '1210')->first();

        $lines = [];

        // 1. Debit Payment Asset
        $lines[] = [
            'account_id' => $accDebit->id,
            'account_number' => $accDebit->account_number,
            'description' => "Showroom payment ({$paymentMethod}) sale #{$sale->sale_number}",
            'debit' => $grossNpr,
            'credit' => 0.00,
        ];

        // 2. Credit Showroom Net Revenue
        $lines[] = [
            'account_id' => $accSales->id,
            'account_number' => $accSales->account_number,
            'description' => "Showroom sales #{$sale->sale_number}",
            'debit' => 0.00,
            'credit' => $netRevenue,
            'vat_code' => 'VAT_13',
            'vat_rate' => 13.0,
            'vat_amount' => $vatAmount,
        ];

        // 3. Credit Output VAT
        $lines[] = [
            'account_id' => $accVat->id,
            'account_number' => $accVat->account_number,
            'description' => "Showroom Output VAT #{$sale->sale_number}",
            'debit' => 0.00,
            'credit' => $vatAmount,
            'vat_rate' => 13.0,
            'vat_amount' => $vatAmount,
        ];

        // 4. Debit COGS & Credit Inventory
        if ($totalCogsNpr > 0 && $accCogs && $accInventory) {
            $lines[] = [
                'account_id' => $accCogs->id,
                'account_number' => $accCogs->account_number,
                'description' => "Cost of Goods Sold (COGS) #{$sale->sale_number}",
                'debit' => $totalCogsNpr,
                'credit' => 0.00,
            ];
            $lines[] = [
                'account_id' => $accInventory->id,
                'account_number' => $accInventory->account_number,
                'description' => "Inventory stock deduction #{$sale->sale_number}",
                'debit' => 0.00,
                'credit' => $totalCogsNpr,
            ];
        }

        $voucherDate = $sale->sold_at ? Carbon::parse($sale->sold_at)->toDateString() : date('Y-m-d');

        $journalEntry = $this->postJournalEntry([
            'voucher_date' => $voucherDate,
            'entry_type' => 'sales',
            'reference_type' => 'offline_sale',
            'reference_id' => $sale->id,
            'description' => "Kathmandu Showroom POS sale #{$sale->sale_number} ({$sale->customer_name})",
            'currency' => 'NPR',
            'exchange_rate_to_npr' => 1.000000,
        ], $lines);

        $period = $this->resolvePeriodForDate($voucherDate);
        $fiscalYear = $period->fiscal_year ?: (AccountingFiscalYear::resolveForDate($voucherDate)?->fiscal_year ?? '2083/84');

        // Create or update statutory Bikri Khata entry for offline showroom sale
        $invoice = AccountingInvoice::updateOrCreate(
            ['reference_offline_sale_id' => $sale->id],
            [
                'invoice_number' => $sale->sale_number,
                'type' => 'sales_invoice',
                'fiscal_year' => $fiscalYear,
                'tax_period_id' => $period->id,
                'contact_name' => $sale->customer_name ?: 'Walk-in Showroom Customer',
                'contact_email' => $sale->customer_email,
                'contact_phone' => $sale->customer_phone,
                'contact_country' => 'NP',
                'customer_type' => 'walk_in_pos',
                'sales_channel' => 'pos_showroom',
                'issue_date' => $voucherDate,
                'due_date' => $voucherDate,
                'currency' => 'NPR',
                'exchange_rate_to_npr' => 1.000000,
                'subtotal' => $netRevenue,
                'taxable_amount' => $netRevenue,
                'vat_amount' => $vatAmount,
                'exempt_amount' => 0.00,
                'export_amount' => 0.00,
                'discount_amount' => (float)($sale->discount_amount ?? 0),
                'total_amount' => $grossNpr,
                'paid_amount' => $grossNpr,
                'payment_status' => 'paid',
                'is_credit' => false,
                'branch' => 'Showroom — Durbar Marg, Kathmandu',
                'salesperson_id' => $sale->created_by,
                'posted_to_gl' => true,
                'journal_entry_id' => $journalEntry->id,
            ]
        );

        if ($invoice->items()->count() === 0 && $sale->items->isNotEmpty()) {
            foreach ($sale->items as $item) {
                $lineGross = (float)($item->total_price ?? ($item->unit_price * $item->quantity));
                $vatData = TaxCalculatorService::calcInclusive($lineGross);
                $lineTaxable = $vatData['net_amount'];
                $lineVat = $vatData['vat_amount'];

                $invoice->items()->create([
                    'description' => ($item->product_name ?? 'Showroom Item') . ($item->variant_title ? " - {$item->variant_title}" : ''),
                    'quantity' => $item->quantity ?? 1,
                    'unit_price' => $item->unit_price ?? $lineTaxable,
                    'vat_rate' => 13.00,
                    'vat_amount' => $lineVat,
                    'total_amount' => $lineGross,
                ]);
            }
        }

        return $journalEntry;
    }

    /**
     * Reconciles a ConnectIPS / Card clearing payout into Bank Account (NPR).
     * Debits Operating Bank (1120/2410), Debits Processing Fees (1310/6120), Credits Clearing (1140/1160).
     */
    public function reconcileConnectIpsPayout(float $netPayout, float $fee, string $payoutId, ?string $date = null): JournalEntry
    {
        $grossCleared = round($netPayout + $fee, 2);
        $voucherDate = $date ?: date('Y-m-d');

        $accBank = Account::where('account_number', '1120')->first() ?: Account::where('account_number', '2410')->firstOrFail();
        $accFee = Account::where('account_number', '6120')->first() ?: Account::where('account_number', '6110')->firstOrFail();
        $accClearing = Account::where('account_number', '1140')->first() ?: Account::where('account_number', '1160')->first() ?: Account::where('account_number', '2320')->firstOrFail();

        $lines = [
            [
                'account_id' => $accBank->id,
                'account_number' => $accBank->account_number,
                'description' => "ConnectIPS/Card payout deposited to bank account ({$payoutId})",
                'debit' => $netPayout,
                'credit' => 0.00,
            ],
            [
                'account_id' => $accFee->id,
                'account_number' => $accFee->account_number,
                'description' => "Payment processing transaction fee ({$payoutId})",
                'debit' => $fee,
                'credit' => 0.00,
            ],
            [
                'account_id' => $accClearing->id,
                'account_number' => $accClearing->account_number,
                'description' => "Clear gateway balance in transit ({$payoutId})",
                'debit' => 0.00,
                'credit' => $grossCleared,
            ],
        ];

        $entry = $this->postJournalEntry([
            'voucher_date' => $voucherDate,
            'entry_type' => 'settlement',
            'reference_type' => 'connectips_payout',
            'description' => "ConnectIPS/Card Payout Settlement: {$payoutId}",
            'currency' => 'NPR',
        ], $lines);

        // Record bank transaction
        $bankAccount = BankAccount::where('ledger_account_id', $accBank->id)->first();
        if ($bankAccount) {
            BankTransaction::create([
                'bank_account_id' => $bankAccount->id,
                'transaction_date' => $voucherDate,
                'amount' => $netPayout,
                'currency' => 'NPR',
                'description' => "ConnectIPS/Card payout {$payoutId}",
                'external_reference' => $payoutId,
                'is_reconciled' => true,
                'reconciled_at' => now(),
                'journal_entry_id' => $entry->id,
                'match_type' => 'connectips_payout',
            ]);
            $bankAccount->recalculateBalance();
        }

        return $entry;
    }



    /**
     * Reconciles an eSewa / digital wallet payout into Bank Account (NPR).
     */
    public function reconcileEsewaPayout(float $netPayout, float $fee, string $batchRef, ?string $date = null): JournalEntry
    {
        $grossCleared = round($netPayout + $fee, 2);
        $voucherDate = $date ?: date('Y-m-d');

        $accBank = Account::where('account_number', '1120')->first() ?: Account::where('account_number', '2410')->firstOrFail();
        $accFee = Account::where('account_number', '6120')->first() ?: Account::where('account_number', '6110')->firstOrFail();
        $accClearing = Account::where('account_number', '1130')->first() ?: Account::where('account_number', '2330')->firstOrFail();

        $lines = [
            [
                'account_id' => $accBank->id,
                'account_number' => $accBank->account_number,
                'description' => "eSewa digital wallet payout received in bank ({$batchRef})",
                'debit' => $netPayout,
                'credit' => 0.00,
            ],
            [
                'account_id' => $accFee->id,
                'account_number' => $accFee->account_number,
                'description' => "eSewa gateway transaction fee ({$batchRef})",
                'debit' => $fee,
                'credit' => 0.00,
            ],
            [
                'account_id' => $accClearing->id,
                'account_number' => $accClearing->account_number,
                'description' => "Clear eSewa digital wallet balance ({$batchRef})",
                'debit' => 0.00,
                'credit' => $grossCleared,
            ],
        ];

        $entry = $this->postJournalEntry([
            'voucher_date' => $voucherDate,
            'entry_type' => 'settlement',
            'reference_type' => 'esewa_settlement',
            'description' => "eSewa Wallet Settlement: {$batchRef}",
            'currency' => 'NPR',
        ], $lines);

        // Record bank transaction
        $bankAccount = BankAccount::where('ledger_account_id', $accBank->id)->first();
        if ($bankAccount) {
            BankTransaction::create([
                'bank_account_id' => $bankAccount->id,
                'transaction_date' => $voucherDate,
                'amount' => $netPayout,
                'currency' => 'NPR',
                'description' => "eSewa wallet settlement {$batchRef}",
                'external_reference' => $batchRef,
                'is_reconciled' => true,
                'reconciled_at' => now(),
                'journal_entry_id' => $entry->id,
                'match_type' => 'esewa_settlement',
            ]);
            $bankAccount->recalculateBalance();
        }

        return $entry;
    }



    /**
     * Reconciles a Courier COD remittance payout into Operating Bank Account (NPR).
     * Clears COD Receivables (1150) into Operating Bank (1120) less courier fees (6120).
     */
    public function reconcileCourierCodSettlement(float $netPayout, float $fee, string $batchRef, ?string $date = null, string $courier = 'NCM'): JournalEntry
    {
        $grossCleared = round($netPayout + $fee, 2);
        $voucherDate = $date ?: date('Y-m-d');

        $accBank = Account::where('account_number', '1120')->firstOrFail();
        $accFee = Account::where('account_number', '6120')->first() ?: Account::where('account_number', '6110')->firstOrFail();
        $accCodClearing = Account::where('account_number', '1150')->firstOrFail();

        $lines = [
            [
                'account_id' => $accBank->id,
                'account_number' => $accBank->account_number,
                'description' => "{$courier} COD remittance deposited to bank ({$batchRef})",
                'debit' => $netPayout,
                'credit' => 0.00,
            ],
            [
                'account_id' => $accFee->id,
                'account_number' => $accFee->account_number,
                'description' => "{$courier} courier shipping / COD collection fee ({$batchRef})",
                'debit' => $fee,
                'credit' => 0.00,
            ],
            [
                'account_id' => $accCodClearing->id,
                'account_number' => $accCodClearing->account_number,
                'description' => "Clear COD Receivables ({$courier} batch {$batchRef})",
                'debit' => 0.00,
                'credit' => $grossCleared,
            ],
        ];

        $entry = $this->postJournalEntry([
            'voucher_date' => $voucherDate,
            'entry_type' => 'settlement',
            'reference_type' => 'cod_settlement',
            'description' => "{$courier} COD Remittance Settlement: {$batchRef}",
            'currency' => 'NPR',
        ], $lines);

        $bankAccount = BankAccount::where('ledger_account_id', $accBank->id)->first();
        if ($bankAccount) {
            BankTransaction::create([
                'bank_account_id' => $bankAccount->id,
                'transaction_date' => $voucherDate,
                'amount' => $netPayout,
                'currency' => 'NPR',
                'description' => "{$courier} COD remittance {$batchRef}",
                'external_reference' => $batchRef,
                'is_reconciled' => true,
                'reconciled_at' => now(),
                'journal_entry_id' => $entry->id,
                'match_type' => 'cod_settlement',
            ]);
            $bankAccount->recalculateBalance();
        }

        return $entry;
    }

    /**
     * Reconciles an eSewa merchant wallet settlement credited to Bank.
     */
    public function reconcileEsewaSettlement(float $netAmount, float $commissionFee, string $reference, ?string $date = null): JournalEntry
    {
        $this->ensureDefaultChartOfAccounts();
        $voucherDate = $date ?: date('Y-m-d');
        $gross = round($netAmount + $commissionFee, 2);

        $accBank = Account::where('account_number', '1120')->first() ?: Account::where('account_number', '2410')->firstOrFail();
        $accClearing = Account::where('account_number', '1130')->first() ?: Account::where('account_number', '2320')->firstOrFail();
        $accFee = Account::where('account_number', '6190')->first() ?: Account::where('account_number', '1310')->firstOrFail();

        $lines = [
            [
                'account_id' => $accBank->id,
                'account_number' => $accBank->account_number,
                'description' => "eSewa remittance deposited to Bank ({$reference})",
                'debit' => $netAmount,
                'credit' => 0.00,
            ],
            [
                'account_id' => $accFee->id,
                'account_number' => $accFee->account_number,
                'description' => "eSewa merchant service commission ({$reference})",
                'debit' => $commissionFee,
                'credit' => 0.00,
            ],
            [
                'account_id' => $accClearing->id,
                'account_number' => $accClearing->account_number,
                'description' => "Clear eSewa digital wallet balance ({$reference})",
                'debit' => 0.00,
                'credit' => $gross,
            ],
        ];

        $entry = $this->postJournalEntry([
            'voucher_date' => $voucherDate,
            'entry_type' => 'settlement',
            'reference_type' => 'esewa_settlement',
            'description' => "eSewa Settlement: {$reference}",
            'currency' => 'NPR',
        ], $lines);

        $bankAccount = BankAccount::where('ledger_account_id', $accBank->id)->first();
        if ($bankAccount) {
            BankTransaction::create([
                'bank_account_id' => $bankAccount->id,
                'transaction_date' => $voucherDate,
                'amount' => $netAmount,
                'currency' => 'NPR',
                'description' => "eSewa Settlement {$reference}",
                'external_reference' => $reference,
                'is_reconciled' => true,
                'reconciled_at' => now(),
                'journal_entry_id' => $entry->id,
                'match_type' => 'direct_voucher',
            ]);
            $bankAccount->recalculateBalance();
        }

        return $entry;
    }

    /**
     * Reconciles a ConnectIPS / NCHL gateway settlement credited to Bank.
     */
    public function reconcileConnectIpsSettlement(float $netAmount, float $fee, string $reference, ?string $date = null): JournalEntry
    {
        $this->ensureDefaultChartOfAccounts();
        $voucherDate = $date ?: date('Y-m-d');
        $gross = round($netAmount + $fee, 2);

        $accBank = Account::where('account_number', '1120')->first() ?: Account::where('account_number', '2410')->firstOrFail();
        $accClearing = Account::where('account_number', '1140')->first() ?: Account::where('account_number', '2330')->firstOrFail();
        $accFee = Account::where('account_number', '6190')->first() ?: Account::where('account_number', '1320')->firstOrFail();

        $lines = [
            [
                'account_id' => $accBank->id,
                'account_number' => $accBank->account_number,
                'description' => "ConnectIPS NCHL settlement to Bank ({$reference})",
                'debit' => $netAmount,
                'credit' => 0.00,
            ],
            [
                'account_id' => $accFee->id,
                'account_number' => $accFee->account_number,
                'description' => "ConnectIPS transaction gateway charge ({$reference})",
                'debit' => $fee,
                'credit' => 0.00,
            ],
            [
                'account_id' => $accClearing->id,
                'account_number' => $accClearing->account_number,
                'description' => "Clear ConnectIPS clearing account ({$reference})",
                'debit' => 0.00,
                'credit' => $gross,
            ],
        ];

        return $this->postJournalEntry([
            'voucher_date' => $voucherDate,
            'entry_type' => 'settlement',
            'reference_type' => 'connectips_settlement',
            'description' => "ConnectIPS Settlement: {$reference}",
            'currency' => 'NPR',
        ], $lines);
    }

    /**
     * Reconciles a Courier Cash-on-Delivery (COD) remittance into Bank.
     */
    public function reconcileCodSettlement(float $remittedAmount, float $courierFee, string $courierName, string $batchRef, ?string $date = null, ?int $orderId = null): JournalEntry
    {
        $this->ensureDefaultChartOfAccounts();
        $voucherDate = $date ?: date('Y-m-d');
        $gross = round($remittedAmount + $courierFee, 2);

        $accBank = Account::where('account_number', '1120')->first() ?: Account::where('account_number', '2410')->firstOrFail();
        $accClearing = Account::where('account_number', '1150')->first() ?: Account::where('account_number', '2340')->firstOrFail();
        $accDeliveryExp = Account::where('account_number', '6150')->first() ?: Account::where('account_number', '1330')->firstOrFail();

        $lines = [
            [
                'account_id' => $accBank->id,
                'account_number' => $accBank->account_number,
                'description' => "{$courierName} COD remittance deposited to Bank ({$batchRef})",
                'debit' => $remittedAmount,
                'credit' => 0.00,
            ],
            [
                'account_id' => $accDeliveryExp->id,
                'account_number' => $accDeliveryExp->account_number,
                'description' => "{$courierName} delivery service fee ({$batchRef})",
                'debit' => $courierFee,
                'credit' => 0.00,
            ],
            [
                'account_id' => $accClearing->id,
                'account_number' => $accClearing->account_number,
                'description' => "Clear COD receivable from {$courierName} ({$batchRef})",
                'debit' => 0.00,
                'credit' => $gross,
            ],
        ];

        return $this->postJournalEntry([
            'voucher_date' => $voucherDate,
            'entry_type' => 'settlement',
            'reference_type' => 'cod_settlement',
            'reference_id' => $orderId,
            'description' => "{$courierName} COD Remittance Settlement: {$batchRef}",
            'currency' => 'NPR',
        ], $lines);
    }

    /**
     * Reconciles physical cash drawn from POS register and deposited into Bank.
     */
    public function reconcileCashDeposit(float $amount, string $depositSlipRef, ?string $date = null): JournalEntry
    {
        $this->ensureDefaultChartOfAccounts();
        $voucherDate = $date ?: date('Y-m-d');

        $accBank = Account::where('account_number', '1120')->first() ?: Account::where('account_number', '2410')->firstOrFail();
        $accCash = Account::where('account_number', '1110')->first() ?: Account::where('account_number', '2430')->firstOrFail();

        $lines = [
            [
                'account_id' => $accBank->id,
                'account_number' => $accBank->account_number,
                'description' => "Showroom cash deposit to Bank ({$depositSlipRef})",
                'debit' => $amount,
                'credit' => 0.00,
            ],
            [
                'account_id' => $accCash->id,
                'account_number' => $accCash->account_number,
                'description' => "Cash drawn from Showroom POS drawer ({$depositSlipRef})",
                'debit' => 0.00,
                'credit' => $amount,
            ],
        ];

        return $this->postJournalEntry([
            'voucher_date' => $voucherDate,
            'entry_type' => 'bank',
            'reference_type' => 'cash_deposit',
            'description' => "Showroom Cash Deposit: {$depositSlipRef}",
            'currency' => 'NPR',
        ], $lines);
    }

    /**
     * Records an internal bank transfer or cash deposit between bank accounts / registers.
     * Example: Showroom Cash Drawer (1110) deposit to Nabil Bank Operating Account (1120).
     */
    public function recordInternalTransfer(int $fromBankAccountId, int $toBankAccountId, float $amount, ?string $reference = null, ?string $date = null): JournalEntry
    {
        if ($fromBankAccountId === $toBankAccountId) {
            throw new \InvalidArgumentException("Source and destination accounts cannot be the same.");
        }
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Transfer amount must be greater than zero.");
        }

        $fromBank = BankAccount::with('ledgerAccount')->findOrFail($fromBankAccountId);
        $toBank = BankAccount::with('ledgerAccount')->findOrFail($toBankAccountId);

        $voucherDate = $date ?: date('Y-m-d');
        $refText = $reference ?: "Internal transfer: {$fromBank->name} -> {$toBank->name}";

        $lines = [
            [
                'account_id' => $toBank->ledger_account_id,
                'account_number' => $toBank->ledgerAccount->account_number,
                'description' => "Internal transfer received from {$fromBank->name} (" . ($reference ?: 'Deposit') . ")",
                'debit' => $amount,
                'credit' => 0.00,
            ],
            [
                'account_id' => $fromBank->ledger_account_id,
                'account_number' => $fromBank->ledgerAccount->account_number,
                'description' => "Internal transfer to {$toBank->name} (" . ($reference ?: 'Deposit') . ")",
                'debit' => 0.00,
                'credit' => $amount,
            ],
        ];

        $entry = $this->postJournalEntry([
            'voucher_date' => $voucherDate,
            'entry_type' => 'bank',
            'reference_type' => 'internal_transfer',
            'description' => $refText,
            'currency' => 'NPR',
        ], $lines);

        // Record BankTransaction on source (withdrawal / negative)
        BankTransaction::create([
            'bank_account_id' => $fromBank->id,
            'transaction_date' => $voucherDate,
            'amount' => -$amount,
            'currency' => $fromBank->currency,
            'description' => "Transfer to {$toBank->name}: " . ($reference ?: 'Internal transfer'),
            'external_reference' => $entry->entry_number,
            'is_reconciled' => true,
            'reconciled_at' => now(),
            'journal_entry_id' => $entry->id,
            'match_type' => 'direct_voucher',
        ]);
        $fromBank->recalculateBalance();

        // Record BankTransaction on destination (deposit / positive)
        BankTransaction::create([
            'bank_account_id' => $toBank->id,
            'transaction_date' => $voucherDate,
            'amount' => $amount,
            'currency' => $toBank->currency,
            'description' => "Modtaget fra {$fromBank->name}: " . ($reference ?: 'Intern flytning'),
            'external_reference' => $entry->entry_number,
            'is_reconciled' => true,
            'reconciled_at' => now(),
            'journal_entry_id' => $entry->id,
            'match_type' => 'direct_voucher',
        ]);
        $toBank->recalculateBalance();

        return $entry;
    }

    /**
     * Generates standard Nepal Accounting Standards (NAS) Statement of Profit or Loss.
     * Structure:
     *   Revenue (Sales)
     *   - Cost of Goods Sold (COGS)
     *   = Gross Profit
     *   - Payment & Gateway Fees
     *   - Operating Expenses (OPEX)
     *   - Personnel Expenses
     *   = EBITDA
     *   - Depreciation
     *   = EBIT (Operating Profit)
     *   +/- Financial Items
     *   = Net Profit Before Tax (EBT)
     */
    public function generateProfitAndLoss(?string $startDate = null, ?string $endDate = null): array
    {
        $query = DB::table('accounting_journal_entry_lines as l')
            ->join('accounting_journal_entries as e', 'l.journal_entry_id', '=', 'e.id')
            ->join('accounting_accounts as a', 'l.account_id', '=', 'a.id')
            ->whereIn('e.status', ['posted', 'reversed']);

        if ($startDate) {
            $query->where('e.voucher_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('e.voucher_date', '<=', $endDate);
        }

        $rows = $query->groupBy('l.account_id', 'a.account_number', 'a.name', 'a.category', 'a.account_type', 'a.normal_balance')
            ->select(
                'l.account_id',
                'a.account_number',
                'a.name',
                'a.category',
                'a.account_type',
                'a.normal_balance',
                DB::raw('SUM(l.debit) as total_debit'),
                DB::raw('SUM(l.credit) as total_credit')
            )
            ->get();

        // Account aggregations
        $sumByCategory = [];
        $accountDetails = [];

        foreach ($rows as $row) {
            $cat = $row->category;
            $num = $row->account_number;

            // In P&L, income normal balance is credit (credit - debit)
            // expense normal balance is debit (debit - credit)
            $net = $row->normal_balance === 'credit'
                ? ((float)$row->total_credit - (float)$row->total_debit)
                : ((float)$row->total_debit - (float)$row->total_credit);

            $sumByCategory[$cat] = ($sumByCategory[$cat] ?? 0.0) + $net;

            $accountDetails[$num] = [
                'account_number' => $num,
                'name' => $row->name,
                'category' => $cat,
                'amount' => round($net, 2),
            ];
        }

        $revenue = round($sumByCategory['revenue'] ?? 0.0, 2);
        $cogs = round($sumByCategory['cogs'] ?? 0.0, 2);
        $grossProfit = round($revenue - $cogs, 2);
        $grossMargin = $revenue > 0 ? round(($grossProfit / $revenue) * 100, 2) : 0.0;

        $paymentFees = round($sumByCategory['payment_fees'] ?? 0.0, 2);
        $opex = round($sumByCategory['opex'] ?? 0.0, 2);
        $personnel = round($sumByCategory['personnel'] ?? 0.0, 2);

        $ebitda = round($grossProfit - $paymentFees - $opex - $personnel, 2);

        // Depreciation (account 1950)
        $depreciation = round($accountDetails['1950']['amount'] ?? 0.0, 2);
        $ebit = round($ebitda - $depreciation, 2);

        // Net financials (financial expenses vs income)
        $financials = round($sumByCategory['financial'] ?? 0.0, 2);
        $ebt = round($ebit - $financials, 2);
        $netResult = $ebt;

        return [
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'revenue' => $revenue,
            'cogs' => $cogs,
            'gross_profit' => $grossProfit,
            'gross_margin_percent' => $grossMargin,
            'payment_fees' => $paymentFees,
            'opex' => $opex,
            'personnel' => $personnel,
            'ebitda' => $ebitda,
            'depreciation' => $depreciation,
            'ebit' => $ebit,
            'financials' => $financials,
            'ebt' => $ebt,
            'net_result' => $netResult,
            'accounts_breakdown' => array_values($accountDetails),
        ];
    }

    /**
     * Generates standard Nepal Accounting Standards (NAS) Statement of Financial Position (Balance Sheet).
     * Structure:
     *   ASSETS:
     *     - Non-Current Assets (Furniture, Showroom Fixtures)
     *     - Current Assets (Inventory, Receivables, Cash & Bank)
     *   EQUITY & LIABILITIES:
     *     - Equity (Capital & Retained Earnings)
     *     - Current Liabilities (VAT Payable, Payables, TDS)
     */
    public function generateBalanceSheet(?string $asOfDate = null): array
    {
        $asOf = $asOfDate ?: date('Y-m-d');

        // Fetch all balance sheet accounts
        $accounts = Account::whereIn('account_type', ['asset', 'liability', 'equity'])->get();

        $aggregated = DB::table('accounting_journal_entry_lines as l')
            ->join('accounting_journal_entries as e', 'l.journal_entry_id', '=', 'e.id')
            ->whereIn('e.status', ['posted', 'reversed'])
            ->where('e.voucher_date', '<=', $asOf)
            ->groupBy('l.account_id')
            ->select('l.account_id', DB::raw('SUM(l.debit) as total_debit'), DB::raw('SUM(l.credit) as total_credit'))
            ->get()
            ->keyBy('account_id');

        $fixedAssets = 0.0;
        $inventory = 0.0;
        $receivables = 0.0;
        $cashBank = 0.0;

        $equity = 0.0;
        $vatTax = 0.0;
        $payables = 0.0;

        $accountList = [];

        foreach ($accounts as $acc) {
            $row = $aggregated->get($acc->id);
            $debit = (float)($row?->total_debit ?? 0.0);
            $credit = (float)($row?->total_credit ?? 0.0);

            $bal = $acc->normal_balance === 'credit'
                ? round($credit - $debit, 2)
                : round($debit - $credit, 2);

            $accountList[$acc->account_number] = [
                'number' => $acc->account_number,
                'name' => $acc->name,
                'category' => $acc->category,
                'balance' => $bal,
            ];

            switch ($acc->category) {
                case 'fixed_assets':
                    $fixedAssets += $bal;
                    break;
                case 'inventory':
                    $inventory += $bal;
                    break;
                case 'receivables':
                    $receivables += $bal;
                    break;
                case 'cash_bank':
                    $cashBank += $bal;
                    break;
                case 'equity':
                    $equity += $bal;
                    break;
                case 'vat_tax':
                    $vatTax += ($acc->normal_balance === 'credit' ? $bal : -$bal);
                    break;
                case 'payables':
                    $payables += $bal;
                    break;
            }
        }

        // Current period net result rolls into equity
        $pnl = $this->generateProfitAndLoss(null, $asOf);
        $periodNetResult = $pnl['net_result'];
        $totalEquity = round($equity + $periodNetResult, 2);

        $totalAssets = round($fixedAssets + $inventory + $receivables + $cashBank, 2);
        $totalLiabilitiesAndEquity = round($totalEquity + $vatTax + $payables, 2);
        $difference = round($totalAssets - $totalLiabilitiesAndEquity, 2);

        return [
            'as_of_date' => $asOf,
            'assets' => [
                'fixed_assets' => round($fixedAssets, 2),
                'inventory' => round($inventory, 2),
                'receivables' => round($receivables, 2),
                'cash_bank' => round($cashBank, 2),
                'total_assets' => $totalAssets,
            ],
            'liabilities_and_equity' => [
                'equity' => $totalEquity,
                'equity_base' => round($equity, 2),
                'period_net_result' => $periodNetResult,
                'vat_tax' => round($vatTax, 2),
                'payables' => round($payables, 2),
                'total_liabilities_and_equity' => $totalLiabilitiesAndEquity,
            ],
            'is_balanced' => abs($difference) < 0.05,
            'difference' => $difference,
            'accounts' => $accountList,
        ];
    }

    /**
     * Generates Nepal Accounting Standards (NAS) Trial Balance (सन्तुलन परीक्षण) where Total Debit == Total Credit.
     */
    public function generateTrialBalance(?string $startDate = null, ?string $endDate = null): array
    {
        $accounts = Account::orderBy('account_number')->get();

        $query = DB::table('accounting_journal_entry_lines as l')
            ->join('accounting_journal_entries as e', 'l.journal_entry_id', '=', 'e.id')
            ->whereIn('e.status', ['posted', 'reversed']);

        if ($startDate) {
            $query->where('e.voucher_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('e.voucher_date', '<=', $endDate);
        }

        $aggregated = $query->groupBy('l.account_id')
            ->select('l.account_id', DB::raw('SUM(l.debit) as total_debit'), DB::raw('SUM(l.credit) as total_credit'))
            ->get()
            ->keyBy('account_id');

        $items = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($accounts as $acc) {
            $row = $aggregated->get($acc->id);
            $sumDebit = round((float)($row?->total_debit ?? 0.0), 2);
            $sumCredit = round((float)($row?->total_credit ?? 0.0), 2);

            if ($sumDebit > 0 || $sumCredit > 0) {
                $items[] = [
                    'account_number' => $acc->account_number,
                    'name' => $acc->name,
                    'category' => $acc->category,
                    'account_type' => $acc->account_type,
                    'debit' => $sumDebit,
                    'credit' => $sumCredit,
                    'net_balance' => $acc->normal_balance === 'credit'
                        ? round($sumCredit - $sumDebit, 2)
                        : round($sumDebit - $sumCredit, 2),
                ];

                $totalDebit += $sumDebit;
                $totalCredit += $sumCredit;
            }
        }

        return [
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'accounts' => $items,
            'total_debit' => round($totalDebit, 2),
            'total_credit' => round($totalCredit, 2),
            'is_balanced' => abs(round($totalDebit - $totalCredit, 2)) < 0.05,
        ];
    }

    /**
     * Generates Hovedbog (General Ledger) drill-down for a specific account.
     */
    public function generateGeneralLedger(int $accountId, ?string $startDate = null, ?string $endDate = null): array
    {
        $account = Account::findOrFail($accountId);

        $query = JournalEntryLine::where('account_id', $account->id)
            ->whereHas('journalEntry', function ($q) use ($startDate, $endDate) {
                $q->whereIn('status', ['posted', 'reversed']);
                if ($startDate) $q->where('voucher_date', '>=', $startDate);
                if ($endDate) $q->where('voucher_date', '<=', $endDate);
            })
            ->with('journalEntry')
            ->join('accounting_journal_entries', 'accounting_journal_entry_lines.journal_entry_id', '=', 'accounting_journal_entries.id')
            ->orderBy('accounting_journal_entries.voucher_date')
            ->orderBy('accounting_journal_entries.id')
            ->select('accounting_journal_entry_lines.*');

        $lines = $query->get();

        $runningBalance = 0.0;
        $ledgerEntries = [];

        foreach ($lines as $line) {
            $debit = (float)$line->debit;
            $credit = (float)$line->credit;

            if ($account->normal_balance === 'credit') {
                $runningBalance += ($credit - $debit);
            } else {
                $runningBalance += ($debit - $credit);
            }

            $ledgerEntries[] = [
                'id' => $line->id,
                'voucher_date' => $line->journalEntry?->voucher_date?->toDateString(),
                'entry_number' => $line->journalEntry?->entry_number,
                'description' => $line->description ?: $line->journalEntry?->description,
                'debit' => $debit,
                'credit' => $credit,
                'running_balance' => round($runningBalance, 2),
            ];
        }

        return [
            'account' => [
                'id' => $account->id,
                'account_number' => $account->account_number,
                'name' => $account->name,
                'category' => $account->category,
                'normal_balance' => $account->normal_balance,
            ],
            'entries' => $ledgerEntries,
            'closing_balance' => round($runningBalance, 2),
        ];
    }

    /**
     * Generates statutory Nepal Inland Revenue Department (IRD) 13% VAT Return.
     */
    public function generateVatReturn(string $startDate, string $endDate): array
    {
        // 2120: Output VAT 13% (मूल्य अभिवृद्धि कर दायित्व)
        $accOutputVat = Account::where('account_number', '2120')->first();
        $outputVat = $accOutputVat ? (float)JournalEntryLine::where('account_id', $accOutputVat->id)
            ->whereHas('journalEntry', fn($q) => $q->whereIn('status', ['posted', 'reversed'])->whereBetween('voucher_date', [$startDate, $endDate]))
            ->sum('credit') : 0.0;

        // 2130: Input VAT 13% (मूल्य अभिवृद्धि कर कट्टी दाबी)
        $accInputVat = Account::where('account_number', '2130')->first();
        $inputVat = $accInputVat ? (float)JournalEntryLine::where('account_id', $accInputVat->id)
            ->whereHas('journalEntry', fn($q) => $q->whereIn('status', ['posted', 'reversed'])->whereBetween('voucher_date', [$startDate, $endDate]))
            ->sum('debit') : 0.0;

        // Zero-rated / Export sales
        $exportSales = 0.0;

        // Exempt sales
        $exemptSales = 0.0;

        // Net VAT payable to IRD (+) or refund due (-)
        $netVat = round($outputVat - $inputVat, 2);

        $taxableSales = round($outputVat / 0.13, 2);
        $taxablePurchases = round($inputVat / 0.13, 2);

        return [
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'taxable_sales_13' => $taxableSales,
            'output_vat_13' => round($outputVat, 2),
            'tax_exempt_sales' => round($exemptSales, 2),
            'export_sales' => round($exportSales, 2),
            'taxable_purchases' => $taxablePurchases,
            'taxable_purchases_13' => $taxablePurchases,
            'input_vat_13' => round($inputVat, 2),
            'net_vat_payable' => $netVat,
            'net_vat_position' => $netVat,
        ];
    }

    /**
     * Performs Year-End Closing under Nepal Accounting Standards (NAS).
     * Zeroes P&L nominal accounts (Revenue 4000-4999 & Expenses 5000-6999)
     * and transfers net profit/loss to Retained Earnings (3200 / 3120).
     * Locks the fiscal year and its periods.
     */
    public function performYearEndClosing(string|int $fiscalYear, ?User $user = null): JournalEntry
    {
        $this->ensureDefaultChartOfAccounts();

        $fyString = is_numeric($fiscalYear) ? "{$fiscalYear}" : (string)$fiscalYear;
        $fy = AccountingFiscalYear::where('fiscal_year', $fyString)->first()
            ?: (str_contains($fyString, '/') ? null : AccountingFiscalYear::where('fiscal_year', 'like', "{$fyString}%")->first());

        if ($fy && in_array($fy->status, ['locked', 'closed'], true)) {
            $existing = JournalEntry::where('entry_type', 'closing')
                ->where('description', 'like', "%{$fy->fiscal_year}%")
                ->where('status', 'posted')
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        if ($fy) {
            $startDate = Carbon::parse($fy->start_date)->format('Y-m-d');
            $endDate = Carbon::parse($fy->end_date)->format('Y-m-d');
            $fyName = $fy->fiscal_year;
        } else {
            $startDate = "{$fyString}-01-01";
            $endDate = "{$fyString}-12-31";
            $fyName = $fyString;
        }

        $existingClosing = JournalEntry::where('entry_type', 'closing')
            ->where(function ($q) use ($fyName, $endDate) {
                $q->where('description', 'like', "%{$fyName}%")
                    ->orWhere('voucher_date', $endDate);
            })
            ->where('status', 'posted')
            ->first();

        if ($existingClosing) {
            return $existingClosing;
        }

        $pnl = $this->generateProfitAndLoss($startDate, $endDate);
        $netResult = $pnl['net_result'];

        $accRetained = Account::where('account_number', '3200')->first()
            ?: (Account::where('account_number', '3120')->first() ?: Account::where('account_number', '2520')->firstOrFail());

        // Zero nominal accounts (Revenue & Expenses)
        $nominalAccounts = Account::whereIn('account_type', ['income', 'expense'])->get();
        $lines = [];

        foreach ($nominalAccounts as $acc) {
            $debitSum = (float)JournalEntryLine::where('account_id', $acc->id)
                ->whereHas('journalEntry', fn($q) => $q->where('status', 'posted')->whereBetween('voucher_date', [$startDate, $endDate]))
                ->sum('debit');
            $creditSum = (float)JournalEntryLine::where('account_id', $acc->id)
                ->whereHas('journalEntry', fn($q) => $q->where('status', 'posted')->whereBetween('voucher_date', [$startDate, $endDate]))
                ->sum('credit');

            $netBalance = $acc->normal_balance === 'credit'
                ? round($creditSum - $debitSum, 2)
                : round($debitSum - $creditSum, 2);

            if (abs($netBalance) > 0.005) {
                if ($acc->normal_balance === 'credit') {
                    $lines[] = [
                        'account_id' => $acc->id,
                        'account_number' => $acc->account_number,
                        'description' => "Year-end zeroing of {$acc->name} for FY {$fyName}",
                        'debit' => $netBalance > 0 ? $netBalance : 0.00,
                        'credit' => $netBalance < 0 ? abs($netBalance) : 0.00,
                    ];
                } else {
                    $lines[] = [
                        'account_id' => $acc->id,
                        'account_number' => $acc->account_number,
                        'description' => "Year-end zeroing of {$acc->name} for FY {$fyName}",
                        'debit' => $netBalance < 0 ? abs($netBalance) : 0.00,
                        'credit' => $netBalance > 0 ? $netBalance : 0.00,
                    ];
                }
            }
        }

        // Net profit -> Credit Retained Earnings; Net loss -> Debit Retained Earnings
        if ($netResult > 0) {
            $lines[] = [
                'account_id' => $accRetained->id,
                'account_number' => $accRetained->account_number,
                'description' => "Net profit for FY {$fyName} transferred to Retained Earnings",
                'debit' => 0.00,
                'credit' => $netResult,
            ];
        } elseif ($netResult < 0) {
            $lines[] = [
                'account_id' => $accRetained->id,
                'account_number' => $accRetained->account_number,
                'description' => "Net loss for FY {$fyName} transferred from Retained Earnings",
                'debit' => abs($netResult),
                'credit' => 0.00,
            ];
        }

        if (empty($lines)) {
            $lines[] = [
                'account_id' => $accRetained->id,
                'account_number' => $accRetained->account_number,
                'description' => "FY {$fyName} zero-activity closing to Retained Earnings",
                'debit' => 0.00,
                'credit' => 0.00,
            ];
        }

        $closingEntry = $this->postJournalEntry([
            'voucher_date' => $endDate,
            'entry_type' => 'closing',
            'description' => "Year-End Closing for Fiscal Year {$fyName} — transferred to Retained Earnings",
            'currency' => 'NPR',
        ], $lines, $user);

        if ($fy) {
            $fy->update(['status' => 'closed']);
            AccountingPeriod::where('fiscal_year', $fy->fiscal_year)->update([
                'status' => 'closed',
                'closed_at' => now(),
            ]);
        }

        return $closingEntry;
    }

    /**
     * Rolls forward the balance sheet ending balances from the previous fiscal year
     * into a balanced Day 1 Opening Balance journal entry in the new fiscal year.
     */
    public function generateOpeningBalances(string $newFiscalYear, string $previousFiscalYear, ?User $user = null): JournalEntry
    {
        $this->ensureDefaultChartOfAccounts();

        $prevFy = AccountingFiscalYear::where('fiscal_year', $previousFiscalYear)->firstOrFail();
        $newFy = AccountingFiscalYear::where('fiscal_year', $newFiscalYear)->firstOrFail();

        $openingDate = Carbon::parse($newFy->start_date)->format('Y-m-d');
        $priorCutoff = Carbon::parse($prevFy->end_date)->format('Y-m-d');

        $existingOpening = JournalEntry::where(function ($q) use ($newFiscalYear, $openingDate) {
            $q->where('description', 'like', "%Opening Balances for FY {$newFiscalYear}%")
                ->orWhere(function ($sub) use ($openingDate) {
                    $sub->where('voucher_date', $openingDate)->where('entry_type', 'closing');
                });
        })->where('status', 'posted')->first();

        if ($existingOpening) {
            return $existingOpening;
        }

        // Balance sheet accounts (Asset, Liability, Equity)
        $realAccounts = Account::whereIn('account_type', ['asset', 'liability', 'equity'])->get();
        $lines = [];
        $totalDebit = 0.00;
        $totalCredit = 0.00;

        foreach ($realAccounts as $acc) {
            $debitSum = (float)JournalEntryLine::where('account_id', $acc->id)
                ->whereHas('journalEntry', fn($q) => $q->where('status', 'posted')->where('voucher_date', '<=', $priorCutoff))
                ->sum('debit');
            $creditSum = (float)JournalEntryLine::where('account_id', $acc->id)
                ->whereHas('journalEntry', fn($q) => $q->where('status', 'posted')->where('voucher_date', '<=', $priorCutoff))
                ->sum('credit');

            $netBalance = $acc->normal_balance === 'credit'
                ? round($creditSum - $debitSum, 2)
                : round($debitSum - $creditSum, 2);

            if (abs($netBalance) > 0.005) {
                if ($acc->normal_balance === 'credit') {
                    $cr = $netBalance > 0 ? $netBalance : 0.00;
                    $dr = $netBalance < 0 ? abs($netBalance) : 0.00;
                } else {
                    $dr = $netBalance > 0 ? $netBalance : 0.00;
                    $cr = $netBalance < 0 ? abs($netBalance) : 0.00;
                }

                $lines[] = [
                    'account_id' => $acc->id,
                    'account_number' => $acc->account_number,
                    'description' => "Opening balance roll-forward: {$acc->name}",
                    'debit' => $dr,
                    'credit' => $cr,
                ];

                $totalDebit += $dr;
                $totalCredit += $cr;
            }
        }

        $delta = round($totalDebit - $totalCredit, 2);
        if (abs($delta) > 0.005) {
            $accRetained = Account::where('account_number', '3200')->first()
                ?: (Account::where('account_number', '3120')->first() ?: Account::where('account_number', '2520')->firstOrFail());

            if ($delta > 0) {
                $lines[] = [
                    'account_id' => $accRetained->id,
                    'account_number' => $accRetained->account_number,
                    'description' => "Opening balance retained earnings balancing",
                    'debit' => 0.00,
                    'credit' => $delta,
                ];
            } else {
                $lines[] = [
                    'account_id' => $accRetained->id,
                    'account_number' => $accRetained->account_number,
                    'description' => "Opening balance retained earnings balancing",
                    'debit' => abs($delta),
                    'credit' => 0.00,
                ];
            }
        }

        if (empty($lines)) {
            $accCash = Account::where('account_number', '1110')->firstOrFail();
            $lines[] = [
                'account_id' => $accCash->id,
                'account_number' => $accCash->account_number,
                'description' => "Opening balance base",
                'debit' => 0.00,
                'credit' => 0.00,
            ];
        }

        return $this->postJournalEntry([
            'voucher_date' => $openingDate,
            'entry_type' => 'closing',
            'description' => "Opening Balances for FY {$newFiscalYear} rolled forward from FY {$previousFiscalYear}",
            'currency' => 'NPR',
        ], $lines, $user);
    }

    /**
     * Records an inventory stock adjustment / count variance in the General Ledger.
     * Positive delta: Dr Inventory (1210), Cr Inventory Adjustments (5200)
     * Negative delta: Dr Inventory Adjustments (5200), Cr Inventory (1210).
     */
    public function recordStockAdjustmentAccounting(StockAdjustment $adjustment, ?User $user = null): ?JournalEntry
    {
        $this->ensureDefaultChartOfAccounts();

        if ($adjustment->journal_entry_id && ($existing = JournalEntry::find($adjustment->journal_entry_id))) {
            return $existing;
        }

        $totalVal = abs((float)($adjustment->total_value_npr ?? 0));
        if ($totalVal <= 0) {
            $unitCost = (float)($adjustment->unit_cost_npr ?? 0);
            $totalVal = abs($unitCost * (int)$adjustment->quantity);
        }

        if ($totalVal <= 0) {
            return null;
        }

        $accInventory = Account::where('account_number', '1210')->first()
            ?: Account::where('account_number', '2210')->first();
        $accAdjustment = Account::where('account_number', '5200')->first()
            ?: (Account::where('account_number', '1230')->first() ?: Account::where('category', 'cogs')->first());

        if (!$accInventory || !$accAdjustment) {
            return null;
        }

        $isGain = (int)$adjustment->quantity > 0;
        $debitAcc = $isGain ? $accInventory : $accAdjustment;
        $creditAcc = $isGain ? $accAdjustment : $accInventory;

        $entry = $this->postJournalEntry([
            'voucher_date' => now()->toDateString(),
            'entry_type' => 'cogs',
            'reference_type' => 'stock_adjustment',
            'reference_id' => $adjustment->id,
            'reference_number' => $adjustment->adjustment_number,
            'description' => "Stock adjustment #{$adjustment->adjustment_number} ({$adjustment->reason})",
            'currency' => 'NPR',
            'exchange_rate_to_npr' => 1.000000,
        ], [
            [
                'account_id' => $debitAcc->id,
                'account_number' => $debitAcc->account_number,
                'description' => "Stock adjustment: " . ($isGain ? 'Inventory Gain' : 'Inventory Loss / Shrinkage'),
                'debit' => $totalVal,
                'credit' => 0.00,
            ],
            [
                'account_id' => $creditAcc->id,
                'account_number' => $creditAcc->account_number,
                'description' => "Stock adjustment offset: " . ($isGain ? 'Inventory Gain' : 'Inventory Loss / Shrinkage'),
                'debit' => 0.00,
                'credit' => $totalVal,
            ],
        ], $user);

        $adjustment->journal_entry_id = $entry->id;
        $adjustment->saveQuietly();

        return $entry;
    }

    /**
     * Exports Trial Balance to CSV for auditor and internal accounting.
     */
    public function exportTrialBalanceCsv(?string $startDate = null, ?string $endDate = null): string
    {
        $tb = $this->generateTrialBalance($startDate, $endDate);

        $out = fopen('php://memory', 'r+');
        fputcsv($out, ['Account Number', 'Account Name', 'Category', 'Account Type', 'Total Debit (NPR)', 'Total Credit (NPR)', 'Net Balance (NPR)'], ',');

        foreach ($tb['accounts'] as $acc) {
            fputcsv($out, [
                $acc['account_number'],
                $acc['name'],
                $acc['category'],
                $acc['account_type'],
                number_format($acc['debit'], 2, '.', ''),
                number_format($acc['credit'], 2, '.', ''),
                number_format($acc['net_balance'], 2, '.', ''),
            ], ',');
        }

        // Summary row
        fputcsv($out, [
            'TOTAL',
            'Total',
            '',
            '',
            number_format($tb['total_debit'], 2, '.', ''),
            number_format($tb['total_credit'], 2, '.', ''),
            number_format(round($tb['total_debit'] - $tb['total_credit'], 2), 2, '.', ''),
        ], ',');

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    /**
     * Records a supplier purchase bill in Kharid Khata (Purchase Book — IRD Annex 8)
     * and automatically posts balanced double-entry vouchers under Nepal Accounting Standards (NAS).
     * Dr Inventory (1210), Dr Input VAT (2130), Cr Accounts Payable (2110), Cr TDS (2140).
     */
    public function recordPurchaseBillKharidKhata(array $billData, array $items = []): KharidKhataEntry
    {
        $this->ensureDefaultChartOfAccounts();

        $billDate = $billData['issue_date'] ?? date('Y-m-d');
        $period = $this->resolvePeriodForDate($billDate);
        $fiscalYear = $period->fiscal_year ?: (AccountingFiscalYear::resolveForDate($billDate)?->fiscal_year ?? '2083/84');

        $taxableAmount = round((float)($billData['taxable_amount'] ?? 0), 2);
        $exemptAmount = round((float)($billData['exempt_amount'] ?? 0), 2);

        // Versioned VAT rate calculation
        $vatConfig = $this->resolveVatConfiguration($fiscalYear, $billDate);
        $defaultVatRate = (float)$vatConfig->standard_vat_rate;
        $vatAmount = round((float)($billData['vat_amount'] ?? ($taxableAmount * ($defaultVatRate / 100))), 2);
        $discountAmount = round((float)($billData['discount_amount'] ?? 0), 2);
        $totalAmount = round((float)($billData['total_amount'] ?? ($taxableAmount + $exemptAmount + $vatAmount - $discountAmount)), 2);

        // Data-driven TDS calculation based on Fiscal Year and Payment Type
        $paymentType = ($billData['purchase_type'] ?? '') === 'administrative_service' ? 'consultancy' : 'contract_goods';
        $tdsCalc = $this->calculateTds($fiscalYear, $paymentType, $taxableAmount, $billData['seller_pan'] ?? null, $billDate);

        $tdsApplicable = isset($billData['tds_applicable']) ? (bool)$billData['tds_applicable'] : $tdsCalc['applicable'];
        $tdsRate = isset($billData['tds_rate']) ? (float)$billData['tds_rate'] : $tdsCalc['rate'];
        $tdsAmount = isset($billData['tds_amount']) ? round((float)$billData['tds_amount'], 2) : ($tdsApplicable ? round($taxableAmount * ($tdsRate / 100), 2) : 0.00);

        $netPayableToSupplier = round($totalAmount - $tdsAmount, 2);

        // Resolve Accounts
        $accInventory = Account::where('account_number', '1210')->firstOrFail();
        $accInputVat = Account::where('account_number', '2130')->firstOrFail();
        $accPayable = Account::where('account_number', '2110')->firstOrFail();
        $accTds = Account::where('account_number', '2140')->first();

        $lines = [];

        // 1. Dr Inventory Asset
        $lines[] = [
            'account_id' => $accInventory->id,
            'account_number' => $accInventory->account_number,
            'description' => "Inventory purchase: " . ($billData['contact_name'] ?? 'Supplier') . " Bill #" . ($billData['invoice_number'] ?? ''),
            'debit' => round($taxableAmount + $exemptAmount, 2),
            'credit' => 0.00,
        ];

        // 2. Dr Input VAT (13% deductible statutory credit)
        if ($vatAmount > 0) {
            $lines[] = [
                'account_id' => $accInputVat->id,
                'account_number' => $accInputVat->account_number,
                'description' => "Input VAT Credit (13%) Bill #" . ($billData['invoice_number'] ?? ''),
                'debit' => $vatAmount,
                'credit' => 0.00,
                'vat_rate' => 13.0,
                'vat_amount' => $vatAmount,
            ];
        }

        // 3. Cr Trade Accounts Payable (Net supplier obligation)
        $lines[] = [
            'account_id' => $accPayable->id,
            'account_number' => $accPayable->account_number,
            'description' => "Trade Payable: " . ($billData['contact_name'] ?? 'Supplier'),
            'debit' => 0.00,
            'credit' => $netPayableToSupplier,
        ];

        // 4. Cr TDS Payable (Withholding tax to IRD)
        if ($tdsAmount > 0 && $accTds) {
            $lines[] = [
                'account_id' => $accTds->id,
                'account_number' => $accTds->account_number,
                'description' => "TDS Withholding ({$tdsRate}%): " . ($billData['contact_name'] ?? 'Payee'),
                'debit' => 0.00,
                'credit' => $tdsAmount,
            ];
        }

        $journalEntry = $this->postJournalEntry([
            'voucher_date' => $billDate,
            'entry_type' => 'purchase',
            'reference_type' => 'supplier_bill',
            'reference_id' => $billData['reference_purchase_order_id'] ?? null,
            'description' => "Kharid Khata Bill #{$billData['invoice_number']} from " . ($billData['contact_name'] ?? 'Supplier'),
            'currency' => 'NPR',
            'exchange_rate_to_npr' => 1.000000,
        ], $lines);

        // Create KharidKhataEntry
        /** @var KharidKhataEntry $kharidEntry */
        $kharidEntry = KharidKhataEntry::create([
            'invoice_number' => $billData['invoice_number'],
            'type' => 'supplier_bill',
            'fiscal_year' => $fiscalYear,
            'tax_period_id' => $period->id,
            'contact_name' => $billData['contact_name'],
            'contact_email' => $billData['contact_email'] ?? null,
            'contact_phone' => $billData['contact_phone'] ?? null,
            'seller_pan' => $billData['seller_pan'] ?? null,
            'buyer_pan' => $billData['buyer_pan'] ?? $this->settingsService->get('business_registration_number', '604335148'), // Laijau / Delta Nine business group PAN
            'purchase_type' => $billData['purchase_type'] ?? 'local_taxable_13',
            'issue_date' => $billDate,
            'due_date' => $billData['due_date'] ?? $billDate,
            'currency' => 'NPR',
            'subtotal' => $taxableAmount + $exemptAmount,
            'taxable_amount' => $taxableAmount,
            'vat_amount' => $vatAmount,
            'exempt_amount' => $exemptAmount,
            'discount_amount' => $discountAmount,
            'total_amount' => $totalAmount,
            'paid_amount' => !empty($billData['is_paid']) ? $totalAmount : 0.00,
            'payment_status' => !empty($billData['is_paid']) ? 'paid' : 'unpaid',
            'is_credit' => !empty($billData['is_credit']),
            'tds_applicable' => $tdsApplicable,
            'tds_rate' => $tdsRate,
            'tds_amount' => $tdsAmount,
            'supporting_document_path' => $billData['supporting_document_path'] ?? null,
            'reference_purchase_order_id' => $billData['reference_purchase_order_id'] ?? null,
            'journal_entry_id' => $journalEntry->id,
            'posted_to_gl' => true,
            'branch' => $billData['branch'] ?? 'Durbar Marg, Kathmandu',
            'notes' => $billData['notes'] ?? null,
        ]);

        // Create line items
        if (!empty($items)) {
            foreach ($items as $item) {
                $kharidEntry->items()->create([
                    'description' => $item['description'] ?? 'Procured Stock Item',
                    'quantity' => (float)($item['quantity'] ?? 1),
                    'unit_price' => (float)($item['unit_price'] ?? 0),
                    'vat_rate' => (float)($item['vat_rate'] ?? 13.00),
                    'vat_amount' => (float)($item['vat_amount'] ?? 0),
                    'total_amount' => (float)($item['total_amount'] ?? 0),
                ]);
            }
        }

        // Register TDS Record if applicable
        if ($tdsApplicable && $tdsAmount > 0) {
            TdsRecord::create([
                'tds_number' => TdsRecord::generateNextTdsNumber($fiscalYear),
                'fiscal_year' => $fiscalYear,
                'payee_name' => $billData['contact_name'],
                'payee_pan' => $billData['seller_pan'] ?? null,
                'payment_type' => $paymentType,
                'gross_amount' => $taxableAmount,
                'tds_rate' => $tdsRate,
                'tds_amount' => $tdsAmount,
                'transaction_date' => $billDate,
                'deposit_status' => 'pending',
                'journal_entry_id' => $journalEntry->id,
                'reference_type' => KharidKhataEntry::class,
                'reference_id' => $kharidEntry->id,
                'notes' => "Withheld under Section " . ($tdsCalc['section'] ?? '88/89') . " from Kharid Khata Bill #{$billData['invoice_number']}",
            ]);
        }

        return $kharidEntry;
    }

    /**
     * Records a Sales Return / Credit Note in Bikri Khata and adjusts General Ledger.
     */
    public function recordSalesReturn(AccountingInvoice $original, float $returnAmount, string $reason, ?User $user = null): AccountingInvoice
    {
        $this->ensureDefaultChartOfAccounts();

        $returnAmount = round($returnAmount, 2);
        $returnDate = date('Y-m-d');
        $period = $this->resolvePeriodForDate($returnDate);
        $fiscalYear = $period->fiscal_year ?: '2083/84';

        // 13% statutory Nepal VAT breakdown via centralized engine
        $vatData = TaxCalculatorService::calcInclusive($returnAmount);
        $netReturn = $vatData['net_amount'];
        $vatReturn = $vatData['vat_amount'];

        $accSalesReturn = Account::where('account_number', '4500')->first()
            ?: Account::where('account_number', '4120')->firstOrFail();

        $accVatLiability = Account::where('account_number', '2120')->firstOrFail();

        $accReceivable = Account::where('account_number', '1310')->first()
            ?: Account::where('account_number', '1110')->firstOrFail();

        $lines = [
            [
                'account_id' => $accSalesReturn->id,
                'account_number' => $accSalesReturn->account_number,
                'description' => "Sales Return: Credit Note for #{$original->invoice_number} ({$reason})",
                'debit' => $netReturn,
                'credit' => 0.00,
            ],
            [
                'account_id' => $accVatLiability->id,
                'account_number' => $accVatLiability->account_number,
                'description' => "Output VAT Reversal (13%) for #{$original->invoice_number}",
                'debit' => $vatReturn,
                'credit' => 0.00,
                'vat_rate' => 13.0,
                'vat_amount' => $vatReturn,
            ],
            [
                'account_id' => $accReceivable->id,
                'account_number' => $accReceivable->account_number,
                'description' => "Customer Refund/Credit for #{$original->invoice_number}",
                'debit' => 0.00,
                'credit' => $returnAmount,
            ],
        ];

        $journalEntry = $this->postJournalEntry([
            'voucher_date' => $returnDate,
            'entry_type' => 'sales',
            'reference_type' => 'credit_note',
            'reference_id' => $original->id,
            'description' => "Bikri Khata Credit Note for #{$original->invoice_number}: {$reason}",
            'currency' => 'NPR',
            'exchange_rate_to_npr' => 1.000000,
        ], $lines, $user);

        return AccountingInvoice::create([
            'invoice_number' => AccountingInvoice::generateNextInvoiceNumber('credit_note', $returnDate),
            'type' => 'credit_note',
            'original_invoice_id' => $original->id,
            'fiscal_year' => $fiscalYear,
            'tax_period_id' => $period->id,
            'contact_name' => $original->contact_name,
            'contact_email' => $original->contact_email,
            'contact_phone' => $original->contact_phone,
            'buyer_pan' => $original->buyer_pan,
            'customer_type' => $original->customer_type,
            'sales_channel' => $original->sales_channel,
            'issue_date' => $returnDate,
            'due_date' => $returnDate,
            'currency' => 'NPR',
            'subtotal' => -$netReturn,
            'taxable_amount' => -$netReturn,
            'vat_amount' => -$vatReturn,
            'total_amount' => -$returnAmount,
            'paid_amount' => -$returnAmount,
            'payment_status' => 'paid',
            'posted_to_gl' => true,
            'journal_entry_id' => $journalEntry->id,
            'notes' => "Reason: {$reason}",
        ]);
    }

    /**
     * Records a Purchase Return / Debit Note in Kharid Khata.
     */
    public function recordDebitNote(AccountingInvoice $originalBill, float $returnAmount, string $reason, ?User $user = null): AccountingInvoice
    {
        $this->ensureDefaultChartOfAccounts();

        $returnAmount = round($returnAmount, 2);
        $returnDate = date('Y-m-d');
        $period = $this->resolvePeriodForDate($returnDate);
        $fiscalYear = $period->fiscal_year ?: '2083/84';

        // 13% statutory Nepal VAT breakdown via centralized engine
        $vatData = TaxCalculatorService::calcInclusive($returnAmount);
        $netReturn = $vatData['net_amount'];
        $vatReturn = $vatData['vat_amount'];

        $accPayable = Account::where('account_number', '2110')->firstOrFail();
        $accInputVat = Account::where('account_number', '2130')->firstOrFail();
        $accInventory = Account::where('account_number', '1210')->firstOrFail();

        $lines = [
            [
                'account_id' => $accPayable->id,
                'account_number' => $accPayable->account_number,
                'description' => "Debit Note: Trade Payable reduction for #{$originalBill->invoice_number} ({$reason})",
                'debit' => $returnAmount,
                'credit' => 0.00,
            ],
            [
                'account_id' => $accInputVat->id,
                'account_number' => $accInputVat->account_number,
                'description' => "Input VAT Credit Reversal (13%) for #{$originalBill->invoice_number}",
                'debit' => 0.00,
                'credit' => $vatReturn,
                'vat_rate' => 13.0,
                'vat_amount' => $vatReturn,
            ],
            [
                'account_id' => $accInventory->id,
                'account_number' => $accInventory->account_number,
                'description' => "Inventory reduction from purchase return #{$originalBill->invoice_number}",
                'debit' => 0.00,
                'credit' => $netReturn,
            ],
        ];

        $journalEntry = $this->postJournalEntry([
            'voucher_date' => $returnDate,
            'entry_type' => 'purchase',
            'reference_type' => 'debit_note',
            'reference_id' => $originalBill->id,
            'description' => "Kharid Khata Debit Note for #{$originalBill->invoice_number}: {$reason}",
            'currency' => 'NPR',
            'exchange_rate_to_npr' => 1.000000,
        ], $lines, $user);

        return AccountingInvoice::create([
            'invoice_number' => AccountingInvoice::generateNextInvoiceNumber('debit_note', $returnDate),
            'type' => 'debit_note',
            'original_invoice_id' => $originalBill->id,
            'fiscal_year' => $fiscalYear,
            'tax_period_id' => $period->id,
            'contact_name' => $originalBill->contact_name,
            'contact_email' => $originalBill->contact_email,
            'contact_phone' => $originalBill->contact_phone,
            'seller_pan' => $originalBill->seller_pan,
            'buyer_pan' => $originalBill->buyer_pan,
            'purchase_type' => $originalBill->purchase_type,
            'issue_date' => $returnDate,
            'due_date' => $returnDate,
            'currency' => 'NPR',
            'subtotal' => -$netReturn,
            'taxable_amount' => -$netReturn,
            'vat_amount' => -$vatReturn,
            'total_amount' => -$returnAmount,
            'paid_amount' => -$returnAmount,
            'payment_status' => 'paid',
            'posted_to_gl' => true,
            'journal_entry_id' => $journalEntry->id,
            'notes' => "Reason: {$reason}",
        ]);
    }

    /**
     * Comprehensive COD Courier Remittance Settlement (Pathao / Nepal Can Move / Aramex)
     * Records double-entry GL voucher and persists to the statutory CodSettlement register.
     */
    public function reconcileCodCourierSettlement(
        float $remittedAmount,
        float $courierFee,
        string $courierName,
        string $batchRef,
        ?string $date = null,
        array $orderIds = []
    ): CodSettlement {
        $entry = $this->reconcileCodSettlement($remittedAmount, $courierFee, $courierName, $batchRef, $date);

        $bankAccount = BankAccount::where('bank_name', 'like', '%Nabil%')->first() ?: BankAccount::first();

        return CodSettlement::create([
            'settlement_number' => CodSettlement::generateNextSettlementNumber(),
            'courier_name' => $courierName,
            'settlement_date' => $date ?: date('Y-m-d'),
            'settlement_reference' => $batchRef,
            'total_order_amount' => round($remittedAmount + $courierFee, 2),
            'courier_fee' => round($courierFee, 2),
            'net_bank_deposited' => round($remittedAmount, 2),
            'bank_account_id' => $bankAccount?->id,
            'journal_entry_id' => $entry->id,
            'status' => 'posted',
            'reconciled_order_ids' => $orderIds,
            'notes' => "Remittance verified and reconciled against bank deposit statement.",
        ]);
    }

    /**
     * Generates official Nepal Inland Revenue Department (IRD) Anusuchi 10 VAT Return
     * with complete line-by-line invoice drill-down audit trail.
     */
    public function generateStatutoryNepalVatReturn(string $fiscalYear, ?string $periodName = null): array
    {
        $salesQuery = BikriKhataEntry::where('fiscal_year', $fiscalYear);
        $purchQuery = KharidKhataEntry::where('fiscal_year', $fiscalYear);

        if ($periodName) {
            $period = AccountingPeriod::where('name', $periodName)
                ->orWhere('nepali_label', 'like', "%{$periodName}%")
                ->first();
            if ($period) {
                $salesQuery->where('tax_period_id', $period->id);
                $purchQuery->where('tax_period_id', $period->id);
            }
        }

        $salesInvoices = $salesQuery->orderBy('issue_date')->get();
        $purchaseBills = $purchQuery->orderBy('issue_date')->get();

        $taxableSales = (float)$salesInvoices->where('type', 'sales_invoice')->sum('taxable_amount');
        $exemptSales = (float)$salesInvoices->where('type', 'sales_invoice')->sum('exempt_amount');
        $exportSales = (float)$salesInvoices->where('type', 'sales_invoice')->sum('export_amount');
        $salesReturnsTaxable = (float)$salesInvoices->where('type', 'credit_note')->sum('taxable_amount');
        $salesReturnsVat = (float)$salesInvoices->where('type', 'credit_note')->sum('vat_amount');

        $netTaxableSales = $taxableSales + $salesReturnsTaxable;
        $outputVat = (float)$salesInvoices->where('type', 'sales_invoice')->sum('vat_amount') + $salesReturnsVat;
        $totalSales = (float)$salesInvoices->sum('total_amount');

        $taxablePurchases = (float)$purchaseBills->where('purchase_type', 'local_taxable_13')->where('type', 'supplier_bill')->sum('taxable_amount');
        $importPurchases = (float)$purchaseBills->where('purchase_type', 'import_customs')->sum('taxable_amount');
        $exemptPurchases = (float)$purchaseBills->where('purchase_type', 'local_exempt')->sum('exempt_amount');
        $inputVat = (float)$purchaseBills->sum('vat_amount');
        $totalPurchases = (float)$purchaseBills->sum('total_amount');

        $netVatPosition = round($outputVat - $inputVat, 2);

        return [
            'fiscal_year' => $fiscalYear,
            'period_name' => $periodName ?: 'Full Fiscal Year',
            'company_name' => $this->settingsService->get('legal_entity_name', 'Delta Nine business group'),
            'company_pan' => $this->settingsService->get('business_registration_number', '604335148'),
            'ird_office' => 'Large Taxpayer Office (LTO) / IRO Lazimpat, Kathmandu',
            'sales' => [
                'taxable_sales_13' => $taxableSales,
                'export_sales_0' => $exportSales,
                'exempt_sales' => $exemptSales,
                'credit_notes_taxable' => abs($salesReturnsTaxable),
                'net_taxable_sales' => $netTaxableSales,
                'total_sales' => $totalSales,
                'output_vat' => $outputVat,
            ],
            'purchases' => [
                'taxable_purchases_13' => $taxablePurchases,
                'taxable_imports' => $importPurchases,
                'exempt_purchases' => $exemptPurchases,
                'total_purchases' => $totalPurchases,
                'input_vat' => $inputVat,
            ],
            'reconciliation' => [
                'output_vat' => $outputVat,
                'input_vat' => $inputVat,
                'net_vat_position' => $netVatPosition,
                'status' => $netVatPosition >= 0 ? 'vat_payable' : 'vat_credit_carry_forward',
                'payable_to_ird' => max(0.00, $netVatPosition),
                'vat_credit_carried_forward' => abs(min(0.00, $netVatPosition)),
            ],
            'drill_down' => [
                'sales_count' => $salesInvoices->count(),
                'sales_invoices' => $salesInvoices,
                'purchase_count' => $purchaseBills->count(),
                'purchase_bills' => $purchaseBills,
            ],
        ];
    }

    /**
     * Cross-reconciliation audit checking Commerce Orders & POS sales vs General Ledger.
     */
    public function reconcileCommerceVsAccounting(): array
    {
        $activeOrdersCount = Order::whereNotIn('status', ['cancelled', 'failed_delivery'])->count();
        $cancelledOrdersCount = Order::whereIn('status', ['cancelled', 'failed_delivery'])->count();
        $invoicedOrdersCount = AccountingInvoice::whereNotNull('reference_order_id')->count();
        $unpostedOrdersCount = max(0, $activeOrdersCount - $invoicedOrdersCount);

        $totalPosSalesCount = OfflineSale::where('status', '!=', 'voided')->count();
        $invoicedPosCount = AccountingInvoice::whereNotNull('reference_offline_sale_id')->count();
        $unpostedPosCount = max(0, $totalPosSalesCount - $invoicedPosCount);

        $totalCreditNotesCount = AccountingInvoice::where('type', 'credit_note')->count();
        $totalSupplierBillsCount = KharidKhataEntry::where('type', 'supplier_bill')->count();
        $totalDebitNotesCount = KharidKhataEntry::where('type', 'debit_note')->count();

        // 3. Inventory Stock Valuation vs GL Inventory Account (1210)
        $accInventory = Account::where('account_number', '1210')->first() ?: Account::where('account_number', '2210')->first();
        $inventoryGlBalance = $accInventory ? $accInventory->recalculateBalance() : 0.00;
        $physicalStockValuation = (float)\App\Models\Inventory\StockLevel::join('product_variants', 'inventory_stock_levels.variant_id', '=', 'product_variants.id')
            ->selectRaw('SUM(inventory_stock_levels.quantity_on_hand * COALESCE(product_variants.cost_price, 0)) as val')
            ->value('val') ?? 0.00;

        // 4. COD Courier Clearing (1150) vs Delivered COD Orders
        $accCod = Account::where('account_number', '1150')->first() ?: Account::where('account_number', '1130')->first();
        $codGlBalance = $accCod ? $accCod->recalculateBalance() : 0.00;
        $unsettledCodOrdersCount = Order::where('payment_method', 'cod')->where('status', Order::STATUS_DELIVERED)->where('payment_status', '!=', Order::PAYMENT_STATUS_PAID)->count();
        $unsettledCodAmount = (float)Order::where('payment_method', 'cod')->where('status', Order::STATUS_DELIVERED)->where('payment_status', '!=', Order::PAYMENT_STATUS_PAID)->sum('total_amount');

        // 5. Bank Accounts
        $bankAccounts = BankAccount::with('ledgerAccount')->get();
        $bankSummary = [];
        foreach ($bankAccounts as $ba) {
            $bankSummary[] = [
                'name' => $ba->name,
                'account_number' => $ba->account_number,
                'current_balance' => (float)$ba->current_balance,
                'gl_account' => $ba->ledgerAccount?->account_number,
            ];
        }

        return [
            'orders' => [
                'total' => $activeOrdersCount,
                'posted_invoices' => $invoicedOrdersCount,
                'unposted' => $unpostedOrdersCount,
                'cancelled' => $cancelledOrdersCount,
            ],
            'pos' => [
                'total' => $totalPosSalesCount,
                'posted_invoices' => $invoicedPosCount,
                'unposted' => $unpostedPosCount,
            ],
            'credit_notes' => $totalCreditNotesCount,
            'purchases' => [
                'supplier_bills' => $totalSupplierBillsCount,
                'debit_notes' => $totalDebitNotesCount,
            ],
            'inventory' => [
                'physical_valuation' => round($physicalStockValuation, 2),
                'gl_balance' => round($inventoryGlBalance, 2),
                'variance' => round($physicalStockValuation - $inventoryGlBalance, 2),
            ],
            'cod' => [
                'unsettled_orders_count' => $unsettledCodOrdersCount,
                'unsettled_amount' => round($unsettledCodAmount, 2),
                'gl_clearing_balance' => round($codGlBalance, 2),
            ],
            'bank' => $bankSummary,
        ];
    }

    /**
     * Calculates Accounts Receivable (AR) & Accounts Payable (AP) Aging Brackets.
     */
    public function generateReceivablesPayablesAging(): array
    {
        $now = Carbon::now();

        // 1. Receivables: Unpaid Sales Invoices
        $receivables = AccountingInvoice::whereIn('type', ['sales_invoice'])
            ->where('payment_status', '!=', 'paid')
            ->orderBy('due_date')
            ->get();

        $arCurrent = 0.0;
        $ar30 = 0.0;
        $ar60 = 0.0;
        $ar90Plus = 0.0;

        foreach ($receivables as $inv) {
            $due = $inv->due_date ? Carbon::parse($inv->due_date) : Carbon::parse($inv->issue_date);
            $daysPast = $due->diffInDays($now, false);
            $unpaid = (float)($inv->total_amount - $inv->paid_amount);

            if ($daysPast <= 0) {
                $arCurrent += $unpaid;
            } elseif ($daysPast <= 30) {
                $ar30 += $unpaid;
            } elseif ($daysPast <= 60) {
                $ar60 += $unpaid;
            } else {
                $ar90Plus += $unpaid;
            }
        }

        // 2. Payables: Unpaid Supplier Bills
        $payables = KharidKhataEntry::whereIn('type', ['supplier_bill'])
            ->where('payment_status', '!=', 'paid')
            ->orderBy('due_date')
            ->get();

        $apCurrent = 0.0;
        $ap30 = 0.0;
        $ap60 = 0.0;
        $ap90Plus = 0.0;

        foreach ($payables as $bill) {
            $due = $bill->due_date ? Carbon::parse($bill->due_date) : Carbon::parse($bill->issue_date);
            $daysPast = $due->diffInDays($now, false);
            $unpaid = (float)($bill->total_amount - $bill->paid_amount);

            if ($daysPast <= 0) {
                $apCurrent += $unpaid;
            } elseif ($daysPast <= 30) {
                $ap30 += $unpaid;
            } elseif ($daysPast <= 60) {
                $ap60 += $unpaid;
            } else {
                $ap90Plus += $unpaid;
            }
        }

        return [
            'receivables' => [
                'total' => round($arCurrent + $ar30 + $ar60 + $ar90Plus, 2),
                'current' => round($arCurrent, 2),
                'days_30' => round($ar30, 2),
                'days_60' => round($ar60, 2),
                'days_90_plus' => round($ar90Plus, 2),
                'count' => $receivables->count(),
                'records' => $receivables,
            ],
            'payables' => [
                'total' => round($apCurrent + $ap30 + $ap60 + $ap90Plus, 2),
                'current' => round($apCurrent, 2),
                'days_30' => round($ap30, 2),
                'days_60' => round($ap60, 2),
                'days_90_plus' => round($ap90Plus, 2),
                'count' => $payables->count(),
                'records' => $payables,
            ],
        ];
    }
}
