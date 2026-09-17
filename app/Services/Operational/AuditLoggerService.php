<?php

declare(strict_types=1);

namespace App\Services\Operational;

use Illuminate\Support\Facades\Log;

class AuditLoggerService
{
    /**
     * Log a security or authorization violation.
     */
    public function securityViolation(string $action, string $reason, array $context = []): void
    {
        $payload = array_merge([
            'category' => 'security_violation',
            'action' => $action,
            'reason' => $reason,
            'user_id' => auth()->id(),
            'user_email' => auth()->user()?->email,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toIso8601String(),
        ], $context);

        Log::channel('single')->warning("[SECURITY_AUDIT] {$action}: {$reason}", $payload);
    }

    /**
     * Log a payment lifecycle event (intent, confirmation, refund, failure).
     */
    public function paymentEvent(string $event, string $orderNumber, float $amount, string $currency, array $context = []): void
    {
        $payload = array_merge([
            'category' => 'payment_lifecycle',
            'event' => $event,
            'order_number' => $orderNumber,
            'amount' => $amount,
            'currency' => $currency,
            'timestamp' => now()->toIso8601String(),
        ], $context);

        Log::channel('single')->info("[PAYMENT_AUDIT] Order #{$orderNumber} {$event} ({$amount} {$currency})", $payload);
    }

    /**
     * Log inventory events (movements, reorders, stock adjustments, negative warnings).
     */
    public function inventoryEvent(string $type, int $productId, ?int $variantId, int $quantity, array $context = []): void
    {
        $payload = array_merge([
            'category' => 'inventory_audit',
            'type' => $type,
            'product_id' => $productId,
            'variant_id' => $variantId,
            'quantity' => $quantity,
            'user_id' => auth()->id(),
            'timestamp' => now()->toIso8601String(),
        ], $context);

        Log::channel('single')->info("[INVENTORY_AUDIT] {$type} for Product #{$productId}: Qty {$quantity}", $payload);
    }

    /**
     * Log fiscal and accounting events (posting, period locking, reversals).
     */
    public function fiscalEvent(string $eventType, string $identifier, array $context = []): void
    {
        $payload = array_merge([
            'category' => 'fiscal_governance',
            'event_type' => $eventType,
            'identifier' => $identifier,
            'user_id' => auth()->id(),
            'user_email' => auth()->user()?->email,
            'timestamp' => now()->toIso8601String(),
        ], $context);

        Log::channel('single')->info("[FISCAL_AUDIT] {$eventType} - {$identifier}", $payload);
    }

    /**
     * Log backup and disaster recovery events.
     */
    public function backupEvent(string $type, string $archiveName, int $fileSizeBytes, array $context = []): void
    {
        $payload = array_merge([
            'category' => 'disaster_recovery',
            'type' => $type,
            'archive_name' => $archiveName,
            'file_size_bytes' => $fileSizeBytes,
            'timestamp' => now()->toIso8601String(),
        ], $context);

        Log::channel('single')->info("[BACKUP_AUDIT] {$type}: {$archiveName} ({$fileSizeBytes} bytes)", $payload);
    }

    /**
     * Log queue worker job failures and dead-letter events.
     */
    public function queueAlert(string $jobName, string $exceptionMessage, array $context = []): void
    {
        $payload = array_merge([
            'category' => 'queue_dead_letter',
            'job_name' => $jobName,
            'exception' => $exceptionMessage,
            'timestamp' => now()->toIso8601String(),
        ], $context);

        Log::channel('single')->critical("[QUEUE_DEAD_LETTER] Job {$jobName} failed permanently: {$exceptionMessage}", $payload);
    }

}
