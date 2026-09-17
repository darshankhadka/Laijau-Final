<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class HealthPingCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'erp:health-ping';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Record a periodic scheduler heartbeat in the cache store for health monitoring';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $timestamp = now()->toIso8601String();
        Cache::put('system:scheduler:last_heartbeat', [
            'timestamp' => $timestamp,
            'epoch' => now()->timestamp,
            'pid' => getmypid(),
        ], now()->addHours(2));

        $this->info("Scheduler heartbeat recorded at {$timestamp}.");

        return self::SUCCESS;
    }
}
