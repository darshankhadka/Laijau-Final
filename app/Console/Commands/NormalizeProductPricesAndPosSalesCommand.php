<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Accounting\AccountingInvoice;
use App\Models\OfflineSale;
use App\Models\OfflineSaleItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizeProductPricesAndPosSalesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'laijau:normalize-prices-and-pos
                            {--dry-run : Simulate without writing changes to the database}
                            {--force : Bypass confirmation prompt}';

    /**
     * The console command description.
     */
    protected $description = 'Audit and normalize product selling prices (multipliers of 100/500), sanitize POS customer names, and sync POS sales';

    public function handle(): int
    {
        $this->info('========================================================================');
        $this->info('  LAIJAU ERP — SELLING PRICE AUDIT & POS SALES SYNCHRONIZATION');
        $this->info('========================================================================');

        $isDryRun = (bool)$this->option('dry-run');

        if (!$isDryRun && !$this->option('force')) {
            if (!$this->confirm('Proceed with live normalization of prices, customer names, and POS sales?')) {
                $this->warn('Operation cancelled by operator.');
                return self::SUCCESS;
            }
        }

        if ($isDryRun) {
            $this->warn('[DRY RUN] Simulating normalization changes...');
        } else {
            DB::beginTransaction();
        }

        try {
            // STEP 1: Selling Price Normalization
            $this->info("\n[Step 1] Auditing & Normalizing Product Selling Prices...");
            $priceChanges = $this->normalizeProductPrices($isDryRun);
            $this->info("  ✓ Normalized {$priceChanges} product selling prices to standard multiples of 100 / 500.");

            // STEP 2: Product Variants Synchronization
            $this->info("\n[Step 2] Synchronizing Product Variant Prices...");
            $variantChanges = $this->normalizeVariantPrices($isDryRun);
            $this->info("  ✓ Synchronized {$variantChanges} product variants with parent product prices.");

            // STEP 3: Customer Name Sanitization
            $this->info("\n[Step 3] Sanitizing Showroom POS & Bikri Khata Customer Names...");
            $customerChanges = $this->sanitizeCustomerNames($isDryRun);
            $this->info("  ✓ Sanitized {$customerChanges['sales']} POS sales and {$customerChanges['invoices']} Bikri Khata invoices.");

            // STEP 4: POS Sales & Line Items Synchronization
            $this->info("\n[Step 4] Synchronizing POS Sales Line Items & Costs/Margins...");
            $syncedItems = $this->syncPosSalesItems($isDryRun);
            $this->info("  ✓ Synchronized {$syncedItems} POS line items and parent sales summaries.");

            // STEP 5: General Ledger Equilibrium Verification
            $this->info("\n[Step 5] Verifying General Ledger Equilibrium...");
            $glEquilibrium = $this->verifyGlEquilibrium();
            $this->info("  ✓ General Ledger strictly verified: Debits = Credits = " . number_format($glEquilibrium['debit'], 4) . " NPR (Variance: " . number_format($glEquilibrium['variance'], 4) . " NPR).");

            if (!$isDryRun) {
                DB::commit();
                $this->info("\n========================================================================");
                $this->info("  ALL CHANGES SUCCESSFULLY COMMITTED TO DATABASE.");
                $this->info("========================================================================");
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            if (!$isDryRun) {
                DB::rollBack();
            }
            $this->error("Normalization failed: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return self::FAILURE;
        }
    }

    /**
     * Normalize selling prices across products.
     */
    protected function normalizeProductPrices(bool $isDryRun): int
    {
        // Compute sales modes (most frequent sales unit price) for products with sales
        $salesModes = DB::table('offline_sale_items')
            ->select('product_id', 'unit_price', DB::raw('count(*) as count'))
            ->where('unit_price', '>', 0)
            ->groupBy('product_id', 'unit_price')
            ->orderBy('product_id')
            ->orderBy('count', 'desc')
            ->get()
            ->groupBy('product_id')
            ->map(fn($group) => (float)$group->first()->unit_price);

        $changedCount = 0;
        $products = Product::all();

        foreach ($products as $product) {
            $oldPrice = (float)$product->price;
            $newPrice = $oldPrice;

            $isFractional = round($oldPrice, 0) != $oldPrice;
            $isNot100 = fmod(round($oldPrice), 100) != 0;

            if ($isFractional || $isNot100 || $oldPrice <= 0) {
                // Check if product has a clear sales mode that is a round multiple of 100
                if (isset($salesModes[$product->id]) && $salesModes[$product->id] >= 100 && fmod($salesModes[$product->id], 100) == 0) {
                    $newPrice = $salesModes[$product->id];
                } else {
                    // Smart retail rounding
                    if ($oldPrice < 500) {
                        // Multiples of 50 or 100 for small accessories/socks
                        $newPrice = round($oldPrice / 50) * 50;
                        if ($newPrice < 100) {
                            $newPrice = 100;
                        }
                    } elseif ($oldPrice < 1500) {
                        // Multiples of 100
                        $newPrice = round($oldPrice / 100) * 100;
                    } else {
                        // Multiples of 500 if within 120 NPR of a 500 increment, else 100
                        $rem500 = fmod($oldPrice, 500);
                        if ($rem500 <= 120 || $rem500 >= 380) {
                            $newPrice = round($oldPrice / 500) * 500;
                        } else {
                            $newPrice = round($oldPrice / 100) * 100;
                        }
                    }
                }
            }

            // Also normalize compare_at_price if present
            $oldCompare = $product->compare_at_price !== null ? (float)$product->compare_at_price : null;
            $newCompare = $oldCompare;
            if ($newCompare !== null) {
                $newCompare = round($newCompare / 100) * 100;
                if ($newCompare <= $newPrice) {
                    $newCompare = null;
                }
            }

            // Maintain realistic cost price (40% gross margin rule)
            $oldCost = (float)$product->cost_price;
            $newCost = $oldCost;
            if ($newPrice > 0 && ($newCost <= 0 || $newCost >= $newPrice || (($newPrice - $newCost) / $newPrice) > 0.60)) {
                $newCost = round($newPrice * 0.60, 2);
            }

            // Check if prices JSON column needs update
            $newPricesJson = $product->prices;
            if (is_array($newPricesJson) && isset($newPricesJson['NPR'])) {
                $newPricesJson['NPR'] = (int)$newPrice;
            }

            if ($newPrice != $oldPrice || $newCompare !== $oldCompare || $newCost != $oldCost) {
                $changedCount++;
                if (!$isDryRun) {
                    $updateData = [
                        'price' => $newPrice,
                        'compare_at_price' => $newCompare,
                        'cost_price' => $newCost,
                    ];
                    if (is_array($newPricesJson)) {
                        $updateData['prices'] = json_encode($newPricesJson);
                    }
                    DB::table('products')->where('id', $product->id)->update($updateData);
                }
            }
        }

        return $changedCount;
    }

    /**
     * Synchronize product variants with parent product prices.
     */
    protected function normalizeVariantPrices(bool $isDryRun): int
    {
        $variants = ProductVariant::join('products', 'products.id', '=', 'product_variants.product_id')
            ->select(
                'product_variants.id',
                'product_variants.product_id',
                'product_variants.price as var_price',
                'product_variants.cost_price as var_cost',
                'products.price as prod_price',
                'products.cost_price as prod_cost'
            )
            ->get();

        $updatedCount = 0;

        foreach ($variants as $v) {
            $varPrice = (float)$v->var_price;
            $prodPrice = (float)$v->prod_price;
            $varCost = (float)$v->var_cost;
            $prodCost = (float)$v->prod_cost;

            // If variant price is test (<= 10) or doesn't match parent, sync to parent
            if ($varPrice <= 10 || $varPrice != $prodPrice || round($varPrice, 0) != $varPrice || fmod(round($varPrice), 50) != 0) {
                $updatedCount++;
                if (!$isDryRun) {
                    DB::table('product_variants')->where('id', $v->id)->update([
                        'price' => $prodPrice,
                        'cost_price' => ($varCost <= 0 || $varCost >= $prodPrice) ? $prodCost : $varCost,
                    ]);
                }
            }
        }

        return $updatedCount;
    }

    /**
     * Sanitize customer names across offline_sales and accounting_invoices.
     */
    protected function sanitizeCustomerNames(bool $isDryRun): array
    {
        $allSales = DB::table('offline_sales')->select('id', 'customer_name')->get();

        $monthWords = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec', 'january', 'february', 'march', 'april', 'june', 'july', 'august', 'september', 'october', 'november', 'december'];
        $garmentWords = ['shoes', 'shoe', 'shirt', 't-shirt', 'tshirt', 'boot', 'boots', 'trouser', 'trousers', 'pant', 'pants', 'jacket', 'jackets', 'windcheater', 'boxer', 'socks', 'sock', 'hoodie', 'track', 'joggers', 'tanktop', 'half pant'];

        $updatedSales = 0;
        $salesToUpdate = []; // id => new_name

        foreach ($allSales as $sale) {
            $orig = trim((string)$sale->customer_name);
            $lower = strtolower($orig);

            $newName = null;

            // Rule 1: 'Th', '7 Th', 'Th '
            if (in_array($lower, ['th', '7 th', 'th ', '7th'], true)) {
                $newName = 'Walk-in Customer';
            }
            // Rule 2: Pure month names / date abbreviations
            elseif (in_array($lower, $monthWords, true) || preg_match('/^(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)\s+\d+$/i', $orig)) {
                $newName = 'Walk-in Customer';
            }
            // Rule 3: Shorthand payment terms / generic
            elseif (in_array($lower, ['cash', 'online', 'uncle', 'cash ', 'online '], true)) {
                $newName = 'Walk-in Customer';
            }
            // Rule 4: Starts with ### followed by a human name (e.g. '### Subash')
            elseif (preg_match('/^###\s+([A-Za-z\s]+)$/', $orig, $m) && !in_array(strtolower(trim($m[1])), $garmentWords, true)) {
                $newName = trim($m[1]);
            }
            // Rule 5: Month prefix before a human name (e.g. 'July Dhan Bahadur Tamang', 'Apr 9 Anil Tamang')
            elseif (preg_match('/^(July|June|Apr|May|Aug|Sep|Jan|Feb|Mar)\s+(?:\d+\s+)?([A-Z][a-z]+(?:\s+[A-Z][a-z]+)+)$/', $orig, $m)) {
                $newName = trim($m[2]);
            }
            // Rule 6: Starting with '-' or '#' (product notes, color codes, e.g. '-Black', '-Br', '# Shoes x2', '#-Windcheater', '### -')
            elseif (preg_match('/^[-#]/', $orig)) {
                $newName = 'Walk-in Customer';
            }
            // Rule 7: Product codes / quantities (e.g. 'X2', 'X3', 'X4', 'BC-535-Black', '2242-Br', '2255-Br', 'PTM-Black', 'TBL-Grey')
            elseif (preg_match('/^x\d+$/i', $lower) || (preg_match('/^[A-Z0-9]{2,4}$/', $orig) && !in_array($lower, ['ram', 'dev', 'raj'], true))) {
                $newName = 'Walk-in Customer';
            }
            // Rule 8: Product model code with hyphen and color
            elseif (preg_match('/^[A-Z0-9]+[-_]/i', $orig) && !preg_match('/\s[A-Z][a-z]+/', $orig)) {
                $newName = 'Walk-in Customer';
            }
            // Rule 9: Pure garment name or memo
            elseif (in_array($lower, $garmentWords, true) || preg_match('/^(sale\s+|puma\s+|nike\s+|cat\s+|dior\s+|tbl\s+)?(shoes?|boots?|t-?shirts?|jackets?|trousers?|pants?|socks?|boxer)$/i', $lower)) {
                $newName = 'Walk-in Customer';
            }

            if ($newName !== null && $newName !== $orig) {
                $salesToUpdate[$sale->id] = $newName;
                $updatedSales++;
            }
        }

        if (!$isDryRun && !empty($salesToUpdate)) {
            foreach (array_chunk($salesToUpdate, 500, true) as $chunk) {
                foreach ($chunk as $saleId => $cleanName) {
                    DB::table('offline_sales')->where('id', $saleId)->update(['customer_name' => $cleanName]);
                }
            }
        }

        // Update corresponding accounting_invoices (Bikri Khata)
        $updatedInvoices = 0;
        if (!$isDryRun && !empty($salesToUpdate)) {
            $updatedInvoices = DB::affectingStatement("
                UPDATE accounting_invoices ai
                JOIN offline_sales os ON os.id = ai.reference_offline_sale_id
                SET ai.contact_name = os.customer_name
                WHERE ai.type = 'sales_invoice' AND ai.contact_name != os.customer_name
            ");
        } else {
            $updatedInvoices = count($salesToUpdate);
        }

        return [
            'sales' => $updatedSales,
            'invoices' => $updatedInvoices,
        ];
    }

    /**
     * Synchronize POS sales line items with products and audit margins.
     */
    protected function syncPosSalesItems(bool $isDryRun): int
    {
        if ($isDryRun) {
            return DB::table('offline_sale_items')->count();
        }

        // 1. Relink named SKUs to matching products
        DB::affectingStatement("
            UPDATE offline_sale_items osi
            JOIN products p ON p.sku = osi.sku
            SET osi.product_id = p.id, osi.product_name = p.name
            WHERE osi.sku != 'SHOWROOM-ITEM' AND osi.sku != ''
        ");

        // 2. Synchronize costs and profits based on audited product cost_price
        DB::affectingStatement("
            UPDATE offline_sale_items osi
            JOIN products p ON p.id = osi.product_id
            SET 
                osi.unit_cost_npr = CASE 
                    WHEN p.cost_price > 0 AND p.cost_price < osi.unit_price AND ((osi.unit_price - p.cost_price) / osi.unit_price) <= 0.60 THEN p.cost_price
                    ELSE ROUND(osi.unit_price * 0.60, 4)
                END,
                osi.total_cost_npr = (
                    CASE 
                        WHEN p.cost_price > 0 AND p.cost_price < osi.unit_price AND ((osi.unit_price - p.cost_price) / osi.unit_price) <= 0.60 THEN p.cost_price
                        ELSE ROUND(osi.unit_price * 0.60, 4)
                    END * osi.quantity
                ),
                osi.unit_profit_npr = osi.unit_price - (
                    CASE 
                        WHEN p.cost_price > 0 AND p.cost_price < osi.unit_price AND ((osi.unit_price - p.cost_price) / osi.unit_price) <= 0.60 THEN p.cost_price
                        ELSE ROUND(osi.unit_price * 0.60, 4)
                    END
                ),
                osi.total_profit_npr = osi.total_price - (
                    CASE 
                        WHEN p.cost_price > 0 AND p.cost_price < osi.unit_price AND ((osi.unit_price - p.cost_price) / osi.unit_price) <= 0.60 THEN p.cost_price
                        ELSE ROUND(osi.unit_price * 0.60, 4)
                    END * osi.quantity
                ),
                osi.margin_percentage = CASE 
                    WHEN osi.total_price > 0 THEN ROUND(((osi.total_price - (
                        CASE 
                            WHEN p.cost_price > 0 AND p.cost_price < osi.unit_price AND ((osi.unit_price - p.cost_price) / osi.unit_price) <= 0.60 THEN p.cost_price
                            ELSE ROUND(osi.unit_price * 0.60, 4)
                        END * osi.quantity
                    )) / osi.total_price) * 100, 2)
                    ELSE 0.00
                END,
                osi.cost_type = 'realized'
        ");

        // 3. Synchronize parent offline_sales table totals
        DB::affectingStatement("
            UPDATE offline_sales os
            JOIN (
                SELECT 
                    offline_sale_id,
                    SUM(total_cost_npr) as agg_cost
                FROM offline_sale_items
                GROUP BY offline_sale_id
            ) agg ON agg.offline_sale_id = os.id
            SET 
                os.total_cost_npr = agg.agg_cost,
                os.total_profit_npr = (os.total_amount - agg.agg_cost),
                os.margin_percentage = CASE 
                    WHEN os.total_amount > 0 THEN ROUND(((os.total_amount - agg.agg_cost) / os.total_amount) * 100, 2)
                    ELSE 0.00
                END,
                os.profit_status = 'realized'
        ");

        return DB::table('offline_sale_items')->count();
    }

    /**
     * Verify General Ledger equilibrium.
     */
    protected function verifyGlEquilibrium(): array
    {
        $totals = DB::table('accounting_journal_entry_lines')
            ->selectRaw('COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit')
            ->first();

        $debit = round((float)($totals->total_debit ?? 0), 4);
        $credit = round((float)($totals->total_credit ?? 0), 4);
        $variance = abs($debit - $credit);

        if ($variance > 0.0001) {
            throw new \RuntimeException("CRITICAL GL MISMATCH: Debits ({$debit}) != Credits ({$credit}), Variance: {$variance}");
        }

        return [
            'debit' => $debit,
            'credit' => $credit,
            'variance' => $variance,
        ];
    }
}
