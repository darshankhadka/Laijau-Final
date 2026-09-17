<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Order;
use App\Services\Inventory\InventoryService;
use App\Services\Operational\AuditLoggerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessOrderFulfillmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array<int, int>
     */
    public array $backoff = [5, 15, 60];

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 60;

    public function __construct(
        public int $orderId
    ) {}

    /**
     * Execute the job idempotently.
     */
    public function handle(InventoryService $inventoryService): void
    {
        DB::transaction(function () use ($inventoryService) {
            $order = Order::with('items')->where('id', $this->orderId)->lockForUpdate()->first();

            if (!$order) {
                Log::warning("[ProcessOrderFulfillmentJob] Order #{$this->orderId} not found. Skipping.");
                return;
            }

            // Check idempotency: if order already has stock movements recorded, skip
            $alreadyFulfilled = DB::table('inventory_stock_movements')
                ->where('reference_type', 'order')
                ->where('reference_id', $order->id)
                ->exists();

            if ($alreadyFulfilled) {
                Log::info("[ProcessOrderFulfillmentJob] Order #{$order->order_number} already fulfilled. Skipping.");
                return;
            }

            $inventoryService->fulfillOrderStock($order);

            Log::info("[ProcessOrderFulfillmentJob] Successfully fulfilled stock for Order #{$order->order_number}");
        });
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $logger = app(AuditLoggerService::class);
        $logger->securityViolation(
            'Queue_job_failure',
            "ProcessOrderFulfillmentJob failed for order ID {$this->orderId}: {$exception->getMessage()}",
            [
                'job' => self::class,
                'order_id' => $this->orderId,
                'exception_class' => get_class($exception),
            ]
        );
    }
}
