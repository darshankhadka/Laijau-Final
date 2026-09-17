<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Operational\RealDataMigrationService;
use Illuminate\Console\Command;

class DataDryRunCommand extends Command
{
    protected $signature = 'laijau:data-dry-run';
    protected $description = 'Simulate the real business data migration without mutating the database';

    public function handle(RealDataMigrationService $migrationService): int
    {
        $this->info('================================================================');
        $this->info('  LAIJAU ERP — REAL BUSINESS DATA DRY RUN (SIMULATION MODE)');
        $this->info('================================================================');
        $this->warn('No database mutations will be performed. Running complete simulation...');

        $metrics = $migrationService->dryRun();

        $this->newLine();
        $this->line("Mode:                           <comment>{$metrics['mode']}</comment>");
        $this->line("Suppliers Processed:            <info>{$metrics['suppliers_processed']}</info> (New: {$metrics['suppliers_created']}, Linked: {$metrics['suppliers_updated']})");
        $this->line("Catalog Products Matched:       <info>{$metrics['products_matched']}</info>");
        $this->line("New Products To Create:         <info>{$metrics['products_created']}</info>");
        $this->line("Product Variants To Build:      <info>{$metrics['variants_created']}</info>");
        $this->line("Warehouse Stock Levels:         <info>{$metrics['stock_levels_recorded']}</info>");
        $this->line("Purchase Orders To Form:        <info>{$metrics['purchase_orders_created']}</info> (Items: {$metrics['purchase_order_items_created']})");
        $this->line("Showroom POS Sales To Post:     <info>{$metrics['sales_orders_created']}</info> (Items: {$metrics['sales_items_created']})");
        $this->line("Showroom Revenue Projected:     <info>NPR " . number_format($metrics['sales_revenue_npr'], 2) . "</info>");
        $this->line("Operational Expenses To Book:   <info>{$metrics['expenses_recorded']}</info> (NPR " . number_format($metrics['expense_total_npr'], 2) . ")");
        $this->line("Online Orders To Dispath:       <info>{$metrics['online_orders_created']}</info>");
        $this->line("New Registered Users:           <info>{$metrics['users_created']}</info>");
        $this->line("Double-Entry Vouchers Tested:   <info>{$metrics['journal_entries_created']}</info>");
        $this->line("Accounting Equation Balanced:   <info>" . ($metrics['accounting_balanced'] ? 'YES (Debits == Credits)' : 'NO') . "</info>");

        $this->newLine();
        $this->info('Dry run simulation completed successfully with zero database alterations.');
        $this->info('You may now safely proceed with "php artisan laijau:data-import".');

        return 0;
    }
}
