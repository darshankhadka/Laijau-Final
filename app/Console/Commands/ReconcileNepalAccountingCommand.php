<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountingInvoice;
use App\Models\Accounting\BankAccount;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Settings\ModuleSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileNepalAccountingCommand extends Command
{
    protected $signature = 'laijau:reconcile-nepal-accounting {--dry-run : Simulate without committing mutations}';

    protected $description = 'Enforce 100% authentic Nepal NAS/IRD accounting standards and purge legacy accounts';

    public function handle(): int
    {
        $this->info('===============================================================');
        $this->info('LAIJAU — ENFORCE NEPAL NAS/IRD ACCOUNTING STANDARDS');
        $this->info('===============================================================');

        $isDryRun = (bool)$this->option('dry-run');
        if ($isDryRun) {
            $this->warn('DRY RUN MODE ENABLED — No changes will be committed to database.');
        }

        DB::beginTransaction();

        try {
            // STEP 1: Ensure authentic Nepal accounts exist
            $this->info('Step 1: Establishing authoritative Nepal Chart of Accounts...');
            $this->setupNepalChartOfAccounts();

            // STEP 2: Remap GL lines to Nepal accounts
            $this->info('Step 2: Remapping Journal Entry Lines to Nepal accounts...');
            $this->remapJournalEntryLines();

            // STEP 3: Normalize descriptions, prefixes, and currencies
            $this->info('Step 3: Cleansing descriptions, prefixes, and currencies...');
            $this->normalizeVouchersAndInvoices();

            // STEP 4: Remove placeholder bank accounts and delete legacy accounts
            $this->info('Step 4: Purging placeholder bank accounts and legacy accounts...');
            $this->purgeLegacyAccounts();

            // STEP 5: Update module settings
            $this->info('Step 5: Synchronizing module settings with Nepal NAS/IRD configuration...');
            $this->synchronizeModuleSettings();

            // STEP 6: Balance Verification
            $this->info('Step 6: Verifying General Ledger equilibrium...');
            $totalDebit = (float)JournalEntryLine::sum('debit');
            $totalCredit = (float)JournalEntryLine::sum('credit');
            $variance = abs($totalDebit - $totalCredit);

            $this->table(
                ['Metric', 'Amount (NPR)'],
                [
                    ['Total General Ledger Debits', number_format($totalDebit, 4)],
                    ['Total General Ledger Credits', number_format($totalCredit, 4)],
                    ['Variance', number_format($variance, 4)],
                ]
            );

            if ($variance > 0.0001) {
                throw new \RuntimeException("General Ledger out of balance! Variance: {$variance} NPR");
            }

            $this->info('✓ General Ledger is in perfect equilibrium (0.0000 NPR variance).');

            $nonNprEntries = JournalEntry::where('currency', '!=', 'NPR')->count();
            if ($nonNprEntries > 0) {
                throw new \RuntimeException("Found {$nonNprEntries} journal entries with non-NPR currency!");
            }

            $bilEntries = JournalEntry::where('entry_number', 'like', 'BIL-%')->count();
            if ($bilEntries > 0) {
                throw new \RuntimeException("Found {$bilEntries} journal entries with BIL- prefix!");
            }

            $fakInvoices = AccountingInvoice::where('invoice_number', 'like', 'FAK-%')->count();
            if ($fakInvoices > 0) {
                throw new \RuntimeException("Found {$fakInvoices} invoices with FAK- prefix!");
            }

            if ($isDryRun) {
                DB::rollBack();
                $this->warn('DRY RUN COMPLETE — All checks passed successfully. No data committed.');
                return self::SUCCESS;
            }

            DB::commit();
            $this->info('✓ SUCCESS: Nepal NAS/IRD accounting system 100% active and verified.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('ERROR during accounting reconciliation: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return self::FAILURE;
        }
    }

    protected function setupNepalChartOfAccounts(): void
    {
        $acc2110 = Account::find(29);
        if ($acc2110) {
            $acc2110->update([
                'name' => 'Trade Accounts Payable (Artisans & Sourcing Suppliers)',
                'category' => 'payables',
                'account_type' => 'liability',
                'normal_balance' => 'credit',
                'default_vat_rate' => 0.00,
                'currency' => 'NPR',
                'is_active' => true,
                'is_system' => true,
                'description' => 'Supplier and artisan trade payables per Nepal Accounting Standards (NAS)',
            ]);
            $this->line("  - Converted Account 29 to Nepal '2110 Trade Accounts Payable'.");
        }

        $acc7 = Account::find(7);
        if ($acc7 && $acc7->account_number === '1210') {
            $acc7->update(['account_number' => 'OLD_1210']);
            $this->line("  - Renamed Account 7 from 1210 to OLD_1210.");
        }

        $acc31 = Account::find(31);
        if ($acc31) {
            $acc31->update([
                'account_number' => '1210',
                'name' => 'Merchandise Inventory Asset (Showroom & Warehouse)',
                'category' => 'inventory',
                'account_type' => 'asset',
                'normal_balance' => 'debit',
                'default_vat_rate' => 0.00,
                'currency' => 'NPR',
                'is_active' => true,
                'is_system' => true,
                'description' => 'Retail stock and merchandise at landed cost under Nepal Accounting Standards (NAS)',
            ]);
            $this->line("  - Converted Account 31 to Nepal '1210 Merchandise Inventory Asset'.");
        }

        $acc11 = Account::find(11);
        if ($acc11 && $acc11->account_number === '1310') {
            $acc11->update([
                'name' => 'Trade Accounts Receivable (Corporate & Wholesale)',
                'category' => 'receivables',
                'account_type' => 'asset',
                'normal_balance' => 'debit',
                'default_vat_rate' => 0.00,
                'currency' => 'NPR',
                'is_active' => true,
                'is_system' => true,
                'description' => 'Trade customer and wholesale receivables',
            ]);
            $this->line("  - Converted Account 11 to Nepal '1310 Trade Accounts Receivable'.");
        }

        $nepalAccounts = [
            ['1110', 'Cash on Hand (POS Register / Showroom)', 'cash_bank', 'asset', 'debit', 0.00, 'Showroom cash drawer in Kathmandu'],
            ['1120', 'Operating Bank Account (Nabil / NIMB NPR)', 'cash_bank', 'asset', 'debit', 0.00, 'Primary commercial bank account'],
            ['1130', 'eSewa Merchant Clearing Account', 'receivables', 'asset', 'debit', 0.00, 'eSewa QR & customer digital clearing'],
            ['1140', 'ConnectIPS / NCHL Clearing Account', 'receivables', 'asset', 'debit', 0.00, 'ConnectIPS gateway settlements in transit'],
            ['1150', 'COD Receivables — Courier Clearing (NCM / Pathao)', 'receivables', 'asset', 'debit', 0.00, 'Cash on delivery collected by courier partners'],
            ['1160', 'POS Card / Fonepay Clearing Account', 'receivables', 'asset', 'debit', 0.00, 'Showroom card swiper & Fonepay merchant clearing'],
            ['1210', 'Merchandise Inventory Asset (Showroom & Warehouse)', 'inventory', 'asset', 'debit', 0.00, 'Retail stock at landed cost'],
            ['1310', 'Trade Accounts Receivable (Corporate & Wholesale)', 'receivables', 'asset', 'debit', 0.00, 'Trade customer receivables'],
            ['2110', 'Trade Accounts Payable (Artisans & Sourcing Suppliers)', 'payables', 'liability', 'credit', 0.00, 'Supplier trade payables'],
            ['2120', 'VAT Payable (13% Nepal Output VAT)', 'vat_tax', 'liability', 'credit', 13.00, 'Nepal IRD Output VAT collected on sales'],
            ['2130', 'VAT Receivable / Input Tax Credit (13%)', 'vat_tax', 'asset', 'debit', 13.00, 'Nepal IRD Input VAT credit claimed on procurement'],
            ['2140', 'TDS Withholding Tax Payable', 'vat_tax', 'liability', 'credit', 0.00, 'Withholding tax payable to Nepal IRD'],
            ['2150', 'Staff Salaries & SSF Payable', 'payables', 'liability', 'credit', 0.00, 'Accrued employee compensation and SSF liability'],
            ['3110', 'Owner Capital', 'equity', 'equity', 'credit', 0.00, 'Contributed business equity'],
            ['3120', 'Retained Earnings', 'equity', 'equity', 'credit', 0.00, 'Accumulated earnings retained in business'],
            ['4110', 'Retail Showroom / POS Sales Revenue', 'revenue', 'income', 'credit', 13.00, 'Kathmandu showroom walk-in sales revenue'],
            ['4120', 'Online Storefront Sales Revenue (Laijau.com)', 'revenue', 'income', 'credit', 13.00, 'E-commerce storefront sales revenue'],
            ['4130', 'Delivery & Courier Shipping Revenue', 'revenue', 'income', 'credit', 0.00, 'Customer shipping charges'],
            ['5110', 'Cost of Goods Sold (Merchandise Sold)', 'cogs', 'expense', 'debit', 0.00, 'Direct landed cost of merchandise sold'],
            ['6110', 'Showroom & Warehouse Rent', 'opex', 'expense', 'debit', 0.00, 'Showroom and warehouse lease payments'],
            ['6120', 'Staff Salaries & Wages', 'personnel', 'expense', 'debit', 0.00, 'Employee monthly payroll'],
            ['6130', 'Social Security Fund (SSF Employer Contribution)', 'personnel', 'expense', 'debit', 0.00, 'Employer statutory SSF contributions'],
            ['6140', 'Electricity & Water Utilities', 'opex', 'expense', 'debit', 0.00, 'Showroom electricity (NEA) and utilities'],
            ['6150', 'Nationwide Courier & Delivery (Pathao / NCM)', 'opex', 'expense', 'debit', 0.00, 'Courier logistics fees'],
            ['6160', 'Packaging, Shopping Bags & Brand Boxes', 'opex', 'expense', 'debit', 13.00, 'Branded packaging and presentation'],
            ['6170', 'Digital Marketing & Social Media Ads', 'opex', 'expense', 'debit', 0.00, 'Social media campaigns and ads'],
            ['6180', 'Software, Cloud Hosting & Telecom', 'opex', 'expense', 'debit', 13.00, 'Server hosting, SaaS and broadband'],
            ['6190', 'Bank & Payment Gateway Processing Fees', 'financial', 'expense', 'debit', 0.00, 'Payment processor and bank charges'],
            ['6200', 'Showroom Repair, Cleaning & Maintenance', 'opex', 'expense', 'debit', 0.00, 'Showroom cleaning and maintenance'],
            ['6210', 'Audit, Legal & Accounting Professional Fees', 'opex', 'expense', 'debit', 0.00, 'Statutory compliance and audit fees'],
        ];

        foreach ($nepalAccounts as $spec) {
            Account::updateOrCreate(
                ['account_number' => $spec[0]],
                [
                    'name' => $spec[1],
                    'category' => $spec[2],
                    'account_type' => $spec[3],
                    'normal_balance' => $spec[4],
                    'default_vat_rate' => $spec[5],
                    'description' => $spec[6],
                    'currency' => 'NPR',
                    'is_active' => true,
                    'is_system' => true,
                ]
            );
        }
    }

    protected function remapJournalEntryLines(): void
    {
        $acc2120 = Account::where('account_number', '2120')->firstOrFail();
        $acc4110 = Account::where('account_number', '4110')->firstOrFail();
        $acc5110 = Account::where('account_number', '5110')->firstOrFail();
        $acc1130 = Account::where('account_number', '1130')->firstOrFail();
        $acc1160 = Account::where('account_number', '1160')->firstOrFail();

        // 1. Remap Account 44 -> Account 2120 (Nepal Output VAT 13%)
        $vatUpdated = JournalEntryLine::where('account_id', 44)->update([
            'account_id' => $acc2120->id,
            'account_number' => '2120',
            'vat_rate' => 13.00,
            'currency' => 'NPR',
        ]);
        $this->line("  - Remapped {$vatUpdated} lines from Account 44 to Account {$acc2120->id} (2120).");

        // 2. Remap Account 4 -> Account 4110 (Nepal Retail Showroom Revenue)
        $posSalesUpdated = JournalEntryLine::where('account_id', 4)->update([
            'account_id' => $acc4110->id,
            'account_number' => '4110',
            'currency' => 'NPR',
        ]);
        $this->line("  - Remapped {$posSalesUpdated} lines from Account 4 to Account {$acc4110->id} (4110).");

        // 3. Remap Account 7 -> Account 5110 (Nepal COGS)
        $cogsUpdated = JournalEntryLine::where('account_id', 7)->update([
            'account_id' => $acc5110->id,
            'account_number' => '5110',
            'currency' => 'NPR',
        ]);
        $this->line("  - Remapped {$cogsUpdated} lines from Account 7 to Account {$acc5110->id} (5110).");

        // 4. Update lines on Account 31 (now 1210 Inventory Asset)
        $invUpdated = JournalEntryLine::where('account_id', 31)->update([
            'account_number' => '1210',
            'currency' => 'NPR',
        ]);
        $this->line("  - Updated {$invUpdated} lines on Account 31 to account_number 1210.");

        // 5. Update lines on Account 29 (now 2110 Trade Accounts Payable)
        $apUpdated = JournalEntryLine::where('account_id', 29)->update([
            'account_number' => '2110',
            'currency' => 'NPR',
        ]);
        $this->line("  - Updated {$apUpdated} lines on Account 29 to account_number 2110.");

        // 6. Remap Account 34 -> Account 1130 (eSewa Merchant Clearing)
        $esewaUpdated = JournalEntryLine::where('account_id', 34)->update([
            'account_id' => $acc1130->id,
            'account_number' => '1130',
            'currency' => 'NPR',
        ]);
        $this->line("  - Remapped {$esewaUpdated} lines from Account 34 to Account {$acc1130->id} (1130 eSewa).");

        // 7. Remap Account 35 -> Account 1160 (Fonepay / Card Clearing)
        $cardUpdated = JournalEntryLine::where('account_id', 35)->update([
            'account_id' => $acc1160->id,
            'account_number' => '1160',
            'currency' => 'NPR',
        ]);
        $this->line("  - Remapped {$cardUpdated} lines from Account 35 to Account {$acc1160->id} (1160 Fonepay).");

        // Set currency to NPR across all remaining journal lines
        JournalEntryLine::where('currency', '!=', 'NPR')->update(['currency' => 'NPR']);
    }

    protected function normalizeVouchersAndInvoices(): void
    {
        // Replace BIL- with JV- in descriptions
        DB::table('accounting_journal_entries')
            ->where('description', 'like', '%BIL-%')
            ->update([
                'description' => DB::raw("REPLACE(description, 'BIL-', 'JV-')"),
            ]);

        // Replace entry_number prefix BIL- with JV-
        $bilEntries = DB::table('accounting_journal_entries')
            ->where('entry_number', 'like', 'BIL-%')
            ->count();

        if ($bilEntries > 0) {
            DB::statement("UPDATE accounting_journal_entries SET entry_number = CONCAT('JV-', SUBSTRING(entry_number, 5)) WHERE entry_number LIKE 'BIL-%'");
            $this->line("  - Converted {$bilEntries} journal entry numbers from 'BIL-' to 'JV-'.");
        }

        // Set currency NPR across all journal entries
        $entriesNpr = DB::table('accounting_journal_entries')
            ->where('currency', '!=', 'NPR')
            ->update([
                'currency' => 'NPR',
                'exchange_rate_to_npr' => 1.000000,
            ]);
        if ($entriesNpr > 0) {
            $this->line("  - Updated currency to NPR on {$entriesNpr} journal entries.");
        }

        // Standardize invoices: Replace FAK- with INV-
        $fakCount = DB::table('accounting_invoices')
            ->where('invoice_number', 'like', 'FAK-%')
            ->count();

        if ($fakCount > 0) {
            DB::statement("UPDATE accounting_invoices SET invoice_number = CONCAT('INV-', SUBSTRING(invoice_number, 5)) WHERE invoice_number LIKE 'FAK-%'");
            $this->line("  - Converted {$fakCount} sales invoice numbers from 'FAK-' to 'INV-'.");
        }

        // Update contact_country -> NP
        DB::table('accounting_invoices')
            ->where('contact_country', '!=', 'NP')
            ->update(['contact_country' => 'NP']);

        // Update payment terms
        DB::table('accounting_invoices')
            ->where('payment_terms', 'like', '%Netto 14%')
            ->update(['payment_terms' => 'Immediate / Net 15']);

        // Set currency NPR on all invoices
        DB::table('accounting_invoices')
            ->where('currency', '!=', 'NPR')
            ->update([
                'currency' => 'NPR',
                'exchange_rate_to_npr' => 1.000000,
            ]);

        // Standardize invoice items vat_rate to 13.00 if set to 25.00
        DB::table('accounting_invoice_items')
            ->where('vat_rate', 25.00)
            ->update(['vat_rate' => 13.00]);
    }

    protected function purgeLegacyAccounts(): void
    {
        $deletedBanks = BankAccount::whereIn('id', [1, 2, 3])->delete();
        $this->line("  - Deleted {$deletedBanks} unused placeholder bank accounts.");

        $legacyAccountIds = Account::whereBetween('id', [1, 52])
            ->whereNotIn('id', [29, 31])
            ->pluck('id');

        $deletedAccounts = Account::whereIn('id', $legacyAccountIds)->delete();
        $this->line("  - Purged {$deletedAccounts} legacy chart of accounts records.");
    }

    protected function synchronizeModuleSettings(): void
    {
        $settings = [
            'standard_vat_rate' => '13.00',
            'gl_sales_vat_account' => '2120',
            'gl_purchase_vat_account' => '2130',
            'gl_accounts_payable_account' => '2110',
            'gl_accounts_receivable_account' => '1150',
            'gl_cogs_account' => '5110',
            'gl_inventory_asset_account' => '1210',
            'gl_esewa_clearing_account' => '1130',
            'gl_connectips_clearing_account' => '1140',
            'gl_card_clearing_account' => '1160',
            'journal_voucher_prefix' => 'JV-',
            'sales_invoice_prefix' => 'INV-',
            'supplier_bill_prefix' => 'KH-',
            'credit_note_prefix' => 'CN-',
            'vat_reporting_frequency' => 'monthly',
            'vat_calculation_mode' => 'nepal_single_rate_13',
        ];

        foreach ($settings as $key => $value) {
            ModuleSetting::where('module', 'accounting')
                ->where('key', $key)
                ->update(['value' => $value]);
        }

        ModuleSetting::where('module', 'accounting')
            ->where('key', 'gl_sales_vat_account')
            ->update(['description' => 'General Ledger account for Nepal IRD Output VAT (2120 - मूल्य अभिवृद्धि कर दायित्व).']);

        ModuleSetting::where('module', 'accounting')
            ->where('key', 'gl_purchase_vat_account')
            ->update(['description' => 'General Ledger account for Nepal IRD Input VAT Credit (2130 - मूल्य अभिवृद्धि कर कट्टी दाबी).']);

        ModuleSetting::where('module', 'accounting')
            ->where('key', 'standard_vat_rate')
            ->update(['description' => 'Standard statutory Nepal VAT rate under Value Added Tax Act, 2052 (13.00%).']);

        $this->line("  - Synchronized module_settings to 13.00% VAT and Nepal accounts/prefixes.");
    }
}
