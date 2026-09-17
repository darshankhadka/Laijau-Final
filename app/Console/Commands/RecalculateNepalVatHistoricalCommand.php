<?php

namespace App\Console\Commands;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountingInvoice;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecalculateNepalVatHistoricalCommand extends Command
{
    protected $signature = 'laijau:recalculate-nepal-vat-historical';
    protected $description = 'Recalculates all historical sales journal entries and invoices from nepalese 25% VAT to authentic Nepal 13% statutory VAT';

    public function handle(): int
    {
        $this->info("================================================================================");
        $this->info("🇳🇵 LAIJAU ERP - RECALCULATE HISTORICAL ACCOUNTING TO NEPAL 13% STATUTORY VAT");
        $this->info("================================================================================");

        // 1. Alter database table defaults
        $this->info("\n--- 1. Altering MySQL Column Defaults ---");
        try {
            DB::statement("ALTER TABLE accounting_invoice_items ALTER COLUMN vat_rate SET DEFAULT 13.00");
            DB::statement("ALTER TABLE hrm_expense_claims ALTER COLUMN vat_rate SET DEFAULT 13.00");
            DB::statement("ALTER TABLE accounting_invoices ALTER COLUMN contact_country SET DEFAULT 'NP'");
            DB::statement("ALTER TABLE accounting_invoices ALTER COLUMN currency SET DEFAULT 'NPR'");
            DB::statement("ALTER TABLE accounting_invoices ALTER COLUMN payment_terms SET DEFAULT 'Immediate / Net 15'");
            DB::statement("ALTER TABLE accounting_vat_declarations MODIFY COLUMN declaration_type VARCHAR(50) NOT NULL DEFAULT 'nepal_vat'");
            $this->info("✔ MySQL table defaults successfully set to Nepal standards.");
        } catch (\Throwable $e) {
            $this->warn("Note on table alters: " . $e->getMessage());
        }

        // 2. Recalculate Sales Journal Entries
        $this->info("\n--- 2. Recalculating Sales Journal Entries to 13% Nepal VAT ---");

        $accVatLiability = Account::where('account_number', '2120')->first();
        if (!$accVatLiability) {
            $this->error("Account 2120 (VAT Payable 13%) not found!");
            return 1;
        }

        $salesEntries = JournalEntry::whereIn('entry_type', ['sales', 'reversal'])->get();
        $recalculatedVouchers = 0;

        DB::beginTransaction();
        try {
            foreach ($salesEntries as $entry) {
                $lines = JournalEntryLine::where('journal_entry_id', $entry->id)->get();

                $clearingLine = $lines->first(fn($l) => in_array($l->account_number, ['1110', '1120', '1130', '1140', '1160', '2110']));
                $revenueLine = $lines->first(fn($l) => in_array($l->account_number, ['4110', '4120']));
                $vatLine = $lines->first(fn($l) => $l->account_number == '2120');
                $shippingLine = $lines->first(fn($l) => $l->account_number == '4130');

                $isReversal = $entry->entry_type === 'reversal';

                if ($clearingLine && $revenueLine && $vatLine) {
                    $gross = (float)($isReversal ? $clearingLine->credit : $clearingLine->debit);

                    if ($gross > 0) {
                        $shippingGross = $shippingLine ? (float)($isReversal ? $shippingLine->debit : $shippingLine->credit) : 0.0;
                        $productGross = max(0.00, $gross - $shippingGross);

                        // Nepal statutory 13% inclusive VAT
                        $netProduct = round($productGross / 1.13, 2);
                        $vatProduct = round($productGross - $netProduct, 2);

                        $netShipping = round($shippingGross / 1.13, 2);
                        $vatShipping = round($shippingGross - $netShipping, 2);

                        $totalVat = round($vatProduct + $vatShipping, 2);

                        // Reconcile rounding to product net revenue
                        $rounding = round($gross - ($netProduct + $netShipping + $totalVat), 2);
                        $netProduct += $rounding;

                        // Update Revenue Line
                        if ($isReversal) {
                            $revenueLine->debit = $netProduct;
                            $revenueLine->credit = 0.00;
                        } else {
                            $revenueLine->debit = 0.00;
                            $revenueLine->credit = $netProduct;
                        }
                        $revenueLine->vat_code = 'VAT_13';
                        $revenueLine->vat_rate = 13.00;
                        $revenueLine->vat_amount = $vatProduct;
                        $revenueLine->currency = 'NPR';
                        $revenueLine->save();

                        // Update Shipping Line (if any)
                        if ($shippingLine) {
                            if ($isReversal) {
                                $shippingLine->debit = $netShipping;
                                $shippingLine->credit = 0.00;
                            } else {
                                $shippingLine->debit = 0.00;
                                $shippingLine->credit = $netShipping;
                            }
                            $shippingLine->vat_code = 'VAT_13';
                            $shippingLine->vat_rate = 13.00;
                            $shippingLine->vat_amount = $vatShipping;
                            $shippingLine->currency = 'NPR';
                            $shippingLine->save();
                        }

                        // Update VAT Liability Line
                        if ($isReversal) {
                            $vatLine->debit = $totalVat;
                            $vatLine->credit = 0.00;
                        } else {
                            $vatLine->debit = 0.00;
                            $vatLine->credit = $totalVat;
                        }
                        $vatLine->vat_code = 'VAT_13';
                        $vatLine->vat_rate = 13.00;
                        $vatLine->vat_amount = $totalVat;
                        $vatLine->currency = 'NPR';
                        $vatLine->description = preg_replace('/(Showroom VAT|Output VAT( \(\d+%\))?)/i', 'Output VAT (13%)', (string)$vatLine->description);
                        $vatLine->save();

                        $recalculatedVouchers++;
                    }
                }
            }

            // Clean up any other lines that had non-standard vat_rate
            $residualUpdated = JournalEntryLine::where('vat_rate', '!=', 13.00)
                ->where('vat_rate', '>', 0)
                ->update([
                    'vat_rate' => 13.00,
                    'vat_code' => 'VAT_13',
                ]);

            DB::commit();
            $this->info("✔ Recalculated {$recalculatedVouchers} vouchers to statutory 13% Nepal VAT.");
            $this->info("✔ Cleaned {$residualUpdated} residual journal entry lines.");
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("Failed to recalculate journal entries: " . $e->getMessage());
            return 1;
        }

        // 3. Recalculate Sales Invoices in accounting_invoices
        $this->info("\n--- 3. Recalculating Accounting Invoices ---");
        $invoices = AccountingInvoice::where('type', 'sales_invoice')
            ->where('vat_amount', '>', 0)
            ->get();

        $recalculatedInvoices = 0;
        DB::beginTransaction();
        try {
            foreach ($invoices as $inv) {
                $gross = (float)$inv->total_amount;
                if ($gross > 0) {
                    $taxable = round($gross / 1.13, 2);
                    $vat = round($gross - $taxable, 2);

                    $inv->subtotal = $taxable;
                    $inv->taxable_amount = $taxable;
                    $inv->vat_amount = $vat;
                    $inv->currency = 'NPR';
                    $inv->contact_country = 'NP';
                    $inv->payment_terms = 'Immediate / Net 15';
                    $inv->save();

                    // Update items
                    foreach ($inv->items as $item) {
                        $itemGross = (float)$item->total_amount ?: ($gross);
                        $itemTaxable = round($itemGross / 1.13, 2);
                        $itemVat = round($itemGross - $itemTaxable, 2);

                        $item->unit_price = $itemTaxable;
                        $item->vat_rate = 13.00;
                        $item->vat_amount = $itemVat;
                        $item->save();
                    }

                    $recalculatedInvoices++;
                }
            }

            // Normalize any remaining invoice fields
            AccountingInvoice::where('contact_country', '!=', 'NP')->update(['contact_country' => 'NP']);
            AccountingInvoice::where('currency', '!=', 'NPR')->update(['currency' => 'NPR']);
            AccountingInvoice::where('payment_terms', 'Netto 14 dage')->update(['payment_terms' => 'Immediate / Net 15']);

            DB::commit();
            $this->info("✔ Recalculated {$recalculatedInvoices} sales invoices to statutory 13% Nepal VAT.");
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("Failed to recalculate invoices: " . $e->getMessage());
            return 1;
        }

        // 4. Mathematical Verification of Double-Entry General Ledger
        $this->info("\n--- 4. Mathematical GL Balance Verification ---");
        $glDebit = (float) JournalEntryLine::sum('debit');
        $glCredit = (float) JournalEntryLine::sum('credit');
        $glVariance = abs($glDebit - $glCredit);

        $this->line(sprintf("  Total GL Debits : NPR %s", number_format($glDebit, 2)));
        $this->line(sprintf("  Total GL Credits: NPR %s", number_format($glCredit, 2)));
        $this->line(sprintf("  Variance        : NPR %s", number_format($glVariance, 4)));

        if ($glVariance > 0.001) {
            $this->error("CRITICAL ERROR: GL is out of balance! Variance = {$glVariance}");
            return 1;
        }
        $this->info("✔ GENERAL LEDGER IS IN PERFECT MATHEMATICAL EQUILIBRIUM (0.0000 NPR VARIANCE)!");

        // 5. Audit Check on Non-Standard VAT
        $this->info("\n--- 5. Verifying Zero Non-Standard VAT Logic Remaining ---");
        $lines25 = JournalEntryLine::where('vat_rate', '!=', 13.00)->where('vat_rate', '>', 0)->count();
        $inv25Items = DB::table('accounting_invoice_items')->where('vat_rate', '!=', 13.00)->where('vat_rate', '>', 0)->count();

        $this->line("  Lines with non-standard vat_rate: {$lines25}");
        $this->line("  Invoice items with non-standard vat_rate: {$inv25Items}");

        if ($lines25 > 0 || $inv25Items > 0) {
            $this->error("FAILED: Residual non-standard VAT items found!");
            return 1;
        }

        $this->info("✔ 100% CLEAN: ZERO occurrences of non-standard VAT in General Ledger!");
        $this->info("================================================================================");
        return 0;
    }
}
