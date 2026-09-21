<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\Warehouse;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class PurgeUnverifiedProductsCommand extends Command
{
    protected $signature = 'laijau:purge-unverified-products
                            {--force : Force execution without interactive prompt}';

    protected $description = 'Purge unverified and falsely added POS memo products, retaining only legitimate catalog and factory purchase products.';

    public function handle(): int
    {
        $this->info('========================================================================');
        $this->info('  LAIJAU ERP — PURGE UNVERIFIED PRODUCTS & RESTORE CLEAN CATALOG');
        $this->info('========================================================================');

        if (!$this->option('force') && !$this->confirm('Are you sure you want to permanently purge all unverified/junk products?')) {
            $this->warn('Purge aborted by user.');
            return self::SUCCESS;
        }

        $centralWh = Warehouse::where('code', 'WH-KTM-MAIN')->first() ?? Warehouse::first();
        $showroomWh = Warehouse::where('code', 'STORE-KTM-01')->first()
            ?? Warehouse::where('type', 'showroom_pos')->first()
            ?? $centralWh;

        // 1. Ensure POS Anchor Product exists
        $posAnchor = Product::firstOrCreate(
            ['sku' => 'LJ-POS-ITEM'],
            [
                'name' => 'Laijau Showroom Retail Item',
                'slug' => 'laijau-showroom-retail-item',
                'type' => 'footwear',
                'price' => 2500.00,
                'cost_price' => 1500.00,
                'quantity' => 10000,
                'track_quantity' => false,
                'is_active' => true,
                'is_published' => false,
                'description' => 'System anchor product for authentic Showroom POS retail sales.',
            ]
        );

        $footwearCat = \App\Models\Category::where('slug', 'footwear')->first();
        if ($footwearCat) {
            $posAnchor->categories()->syncWithoutDetaching([$footwearCat->id]);
        }

        // 2. Gather all verified SKUs from authoritative sources
        $validSkus = DB::table('readyecommerce.products')->pluck('code')->filter()->map(fn($c) => strtoupper(trim((string)$c)))->toArray();

        $validPdfShoes = [];
        $shoePdf = base_path('private_docs/Real Laijau Data/Purchase from Jan/Purchase from 2026 jan.pdf');
        if (File::exists($shoePdf)) {
            $txt = shell_exec('pdftotext -layout ' . escapeshellarg($shoePdf) . ' -');
            if ($txt) {
                foreach (explode("\n", $txt) as $line) {
                    if (preg_match('~^\s*\d+/\d+/\d+\s+([A-Z\s]+?FACTORY|SK)\s+(\S+)\s+(.+?)\s+(\d+)\s+(\d+(?:\.\d+)?)\s+(\d+(?:\.\d+)?)\s*$~i', $line, $m)) {
                        $validPdfShoes[] = strtoupper(trim($m[2]));
                    }
                }
            }
        }

        $validPdfApparel = [];
        $clothPdf = base_path('private_docs/Real Laijau Data/Purchase from Jan/Clothes purchase.pdf');
        if (File::exists($clothPdf)) {
            $txt = shell_exec('pdftotext -layout ' . escapeshellarg($clothPdf) . ' -');
            if ($txt) {
                foreach (explode("\n", $txt) as $line) {
                    if (preg_match('~^\s*\d+/\d+/\d+\s+([A-Z\s]+?FACTORY|CLOTHES|LAIJAU|SUPPLIER)\s+(\S+)\s+(.+?)\s+(\d+)\s+(\d+(?:\.\d+)?)\s+(\d+(?:\.\d+)?)\s*$~i', $line, $m)) {
                        $validPdfApparel[] = strtoupper(trim($m[2]));
                    }
                }
            }
        }

        $allValidSkus = array_unique(array_merge(
            $validSkus,
            $validPdfShoes,
            $validPdfApparel,
            ['LJ-POS-ITEM', 'CW2288-111', 'DD1391-100']
        ));

        // 3. Identify all legitimate product IDs
        $legitIds = Product::where(function ($q) use ($allValidSkus) {
            $q->whereIn(DB::raw('UPPER(sku)'), $allValidSkus)
                ->orWhereNotNull('featured_image');
        })->where('name', 'NOT LIKE', '-%')
            ->where('name', 'NOT LIKE', '--%')
            ->where('sku', 'NOT LIKE', '-%')
            ->pluck('id')
            ->toArray();

        $legitIds = array_unique(array_merge($legitIds, [$posAnchor->id]));

        // 4. Identify all unverified junk product IDs
        $junkIds = Product::whereNotIn('id', $legitIds)->pluck('id')->toArray();
        $junkCount = count($junkIds);

        $this->info("Found {$junkCount} unverified/junk products to purge.");
        $this->info("Retaining " . count($legitIds) . " legitimate authoritative products.");

        if ($junkCount === 0) {
            $this->info("Catalog is already clean! No action needed.");
            return self::SUCCESS;
        }

        DB::beginTransaction();
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            // Re-link offline_sale_items: First link matching legitimate SKUs, then fallback everything else to posAnchor
            DB::affectingStatement("
                UPDATE offline_sale_items osi
                JOIN products p ON UPPER(p.sku) = UPPER(osi.sku)
                SET osi.product_id = p.id, osi.product_name = p.name
                WHERE p.id IN (" . implode(',', $legitIds) . ")
            ");

            DB::affectingStatement("
                UPDATE offline_sale_items
                SET product_id = {$posAnchor->id}, variant_id = NULL
                WHERE product_id NOT IN (" . implode(',', $legitIds) . ") OR product_id IS NULL
            ");

            DB::affectingStatement("
                UPDATE accounting_invoice_items
                SET product_id = {$posAnchor->id}
                WHERE product_id NOT IN (" . implode(',', $legitIds) . ") OR product_id IS NULL
            ");

            // Historical order items with no matched product are left as product_id = NULL
            // (their original SKU, name, price, and quantity are preserved on the row).
            DB::affectingStatement("
                UPDATE order_items
                SET product_id = NULL, variant_id = NULL
                WHERE product_id IS NOT NULL
                  AND product_id NOT IN (" . implode(',', $legitIds) . ")
            ");

            // Delete dependent records for junk products
            $junkVariantIds = DB::table('product_variants')->whereIn('product_id', $junkIds)->pluck('id')->toArray();
            if (!empty($junkVariantIds)) {
                DB::table('offline_sale_items')->whereIn('variant_id', $junkVariantIds)->update(['variant_id' => null]);
            }

            DB::table('category_product')->whereIn('product_id', $junkIds)->delete();
            DB::table('collection_product')->whereIn('product_id', $junkIds)->delete();
            DB::table('price_histories')->whereIn('product_id', $junkIds)->delete();
            DB::table('inventory_stock_levels')->whereIn('product_id', $junkIds)->delete();
            DB::table('inventory_stock_movements')->whereIn('product_id', $junkIds)->delete();
            DB::table('inventory_stock_count_items')->whereIn('product_id', $junkIds)->delete();
            DB::table('inventory_adjustments')->whereIn('product_id', $junkIds)->delete();
            DB::table('inventory_purchase_order_items')->whereIn('product_id', $junkIds)->delete();
            DB::table('inventory_reservations')->whereIn('product_id', $junkIds)->delete();
            DB::table('inventory_transfer_items')->whereIn('product_id', $junkIds)->delete();
            DB::table('product_variants')->whereIn('product_id', $junkIds)->delete();
            DB::table('restock_requests')->whereIn('product_id', $junkIds)->delete();
            DB::table('crm_leads')->whereIn('product_id', $junkIds)->delete();
            DB::table('contact_messages')->whereIn('product_id', $junkIds)->delete();

            // Purge junk products
            $deleted = DB::table('products')->whereIn('id', $junkIds)->delete();

            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            DB::commit();

            $this->info("✓ Successfully purged {$deleted} unverified products!");
            $this->info("✓ Total clean products remaining: " . Product::count());

            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            $this->error("Purge failed: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}
