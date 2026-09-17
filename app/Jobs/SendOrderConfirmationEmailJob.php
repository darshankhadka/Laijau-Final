<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\OrderConfirmationMail;
use App\Models\Order;
use App\Services\MailSettingsService;
use App\Services\Operational\AuditLoggerService;
use App\Services\Settings\SettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendOrderConfirmationEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [5, 15, 60];
    public int $timeout = 30;

    public function __construct(
        public int $orderId
    ) {}

    /**
     * Execute the job idempotently.
     */
    public function handle(MailSettingsService $mailService, SettingsService $settingsService): void
    {
        $order = Order::find($this->orderId);
        if (!$order) {
            Log::warning("[SendOrderConfirmationEmailJob] Order #{$this->orderId} not found. Skipping.");
            return;
        }

        // Idempotency: Skip if already sent
        if (!is_null($order->confirmation_email_sent_at)) {
            Log::info("[SendOrderConfirmationEmailJob] Confirmation email already sent for Order #{$order->order_number}. Skipping.");
            return;
        }

        $autoSend = $settingsService->getBoolean('commerce', 'auto_send_order_confirmation', true);
        if (!$autoSend || empty($order->email) || !$mailService->isEventEnabled('order_confirmation')) {
            Log::info("[SendOrderConfirmationEmailJob] Email dispatch disabled or recipient missing for Order #{$order->order_number}.");
            return;
        }

        $mailService->configureRuntimeTransport();
        Mail::to($order->email)->send(new OrderConfirmationMail($order));

        $order->updateQuietly([
            'confirmation_email_sent_at' => now(),
        ]);

        Log::info("[SendOrderConfirmationEmailJob] Successfully sent confirmation email for Order #{$order->order_number}");
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $logger = app(AuditLoggerService::class);
        $logger->securityViolation(
            'Queue_email_job_failure',
            "SendOrderConfirmationEmailJob failed for order ID {$this->orderId}: {$exception->getMessage()}",
            [
                'job' => self::class,
                'order_id' => $this->orderId,
                'exception_class' => get_class($exception),
            ]
        );
    }
}
