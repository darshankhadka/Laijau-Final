<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ==============================================================================
// Laijau ERP — Production Task Scheduler (Shared Hosting & Enterprise)
// ==============================================================================

// 1. Operational Heartbeat (monitored by HealthCheckService)
Schedule::command('erp:health-ping')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->name('scheduler-heartbeat-ping');

// 2. Automated Enterprise Nightly Backup (Database & Media)
Schedule::command('erp:backup --type=all')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->name('nightly-enterprise-backup');

// 3. Queue Maintenance: Prune failed jobs older than 7 days (168 hours)
Schedule::command('queue:prune-failed --hours=168')
    ->weekly()
    ->name('prune-stale-failed-jobs');

// 4. Security Maintenance: Prune expired password reset tokens
Schedule::command('auth:clear-resets')
    ->everyFifteenMinutes()
    ->name('clear-expired-password-resets');

// 5. Eloquent Model Pruning (Soft-deleted records with Prunable trait)
Schedule::command('model:prune')
    ->daily()
    ->name('prune-expired-models');

// 6. Shared-Hosting Database Queue Worker (Runs via cPanel Cron without Supervisor)
if (config('queue.default') === 'database') {
    Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
        ->everyMinute()
        ->withoutOverlapping()
        ->name('cpanel-database-queue-worker');
}
