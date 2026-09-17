<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Operational\BackupService;
use App\Services\Operational\RealDataMigrationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DataImportCommand extends Command
{
    protected $signature = 'laijau:data-import {--force : Bypass confirmation prompt}';
    protected $description = 'Execute real Laijau business data migration with backup and transactional rollback';

    public function handle(RealDataMigrationService $migrationService, BackupService $backupService): int
    {
        $this->info('================================================================');
        $this->info('  LAIJAU ERP — REAL BUSINESS DATA PRODUCTION IMPORT');
        $this->info('================================================================');

        if (!$this->option('force') && !$this->confirm('Are you ready to import real business data into the database?', true)) {
            $this->warn('Migration cancelled by user.');
            return 0;
        }

        // 1. Mandatory Pre-Migration Backup
        $this->info('[1/6] Taking automatic safety backup of database before migration...');
        $backupFile = storage_path('app/backups/pre_real_data_migration_' . now()->format('Ymd_His') . '.sql');
        $cmd = sprintf(
            'mysqldump -h %s -P %s -u %s -p%s %s > %s 2>&1',
            escapeshellarg(config('database.connections.mysql.host', '127.0.0.1')),
            escapeshellarg((string)config('database.connections.mysql.port', '3306')),
            escapeshellarg(config('database.connections.mysql.username', 'darshan')),
            escapeshellarg(config('database.connections.mysql.password', 'Dars@@9861')),
            escapeshellarg(config('database.connections.mysql.database', 'LAIJAU')),
            escapeshellarg($backupFile)
        );
        exec($cmd, $output, $returnVar);

        if ($returnVar === 0 && file_exists($backupFile) && filesize($backupFile) > 0) {
            $this->info("Safety backup created successfully: {$backupFile} (" . round(filesize($backupFile) / 1048576, 2) . ' MB)');
        } else {
            $this->warn('Mysqldump notice. Capturing internal JSON snapshot via BackupService...');
            $backupService->createBackup('database', false, 'Pre Real Data Migration Automated Snapshot');
        }

        // 2. Execute Transactional Migration
        $this->info('[2/6] Executing transactional real business data import...');
        $startTime = microtime(true);

        try {
            $metrics = $migrationService->import();
        } catch (\Throwable $e) {
            $this->error('FATAL ERROR DURING MIGRATION: ' . $e->getMessage());
            $this->error('Stack Trace: ' . $e->getTraceAsString());
            $this->warn('Database transaction automatically rolled back. No corrupt data saved.');
            return 1;
        }

        $elapsed = round(microtime(true) - $startTime, 2);

        $this->info("[3/6] Migration completed successfully in {$elapsed} seconds!");
        $this->newLine();
        $this->line("Mode:                           <comment>{$metrics['mode']}</comment>");
        $this->line("Suppliers Processed:            <info>{$metrics['suppliers_processed']}</info> (New: {$metrics['suppliers_created']}, Linked: {$metrics['suppliers_updated']})");
        $this->line("Catalog Products Matched:       <info>{$metrics['products_matched']}</info>");
        $this->line("New Products Created:           <info>{$metrics['products_created']}</info>");
        $this->line("Product Variants Built:         <info>{$metrics['variants_created']}</info>");
        $this->line("Warehouse Stock Levels:         <info>{$metrics['stock_levels_recorded']}</info>");
        $this->line("Purchase Orders Formed:         <info>{$metrics['purchase_orders_created']}</info> (Items: {$metrics['purchase_order_items_created']})");
        $this->line("Showroom POS Sales Posted:      <info>{$metrics['sales_orders_created']}</info> (Items: {$metrics['sales_items_created']})");
        $this->line("Showroom Revenue Inserted:      <info>NPR " . number_format($metrics['sales_revenue_npr'], 2) . "</info>");
        $this->line("Operational Expenses Booked:    <info>{$metrics['expenses_recorded']}</info> (NPR " . number_format($metrics['expense_total_npr'], 2) . ")");
        $this->line("Online Dispatched Orders Added: <info>{$metrics['online_orders_created']}</info>");
        $this->line("Registered Users Inserted:      <info>{$metrics['users_created']}</info>");
        $this->line("Double-Entry Vouchers Created:  <info>{$metrics['journal_entries_created']}</info>");

        // 3. Trigger Automatic Reconciliation
        $this->newLine();
        $this->info('[4/6] Running statutory financial and stock reconciliation...');
        $this->call('laijau:data-reconcile');

        return 0;
    }
}
