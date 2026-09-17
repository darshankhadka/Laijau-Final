<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAuditLog extends Model
{
    protected $table = 'payment_audit_logs';

    protected $fillable = [
        'order_id',
        'payment_method',
        'previous_payment_status',
        'new_payment_status',
        'transaction_reference',
        'amount',
        'actor_type',
        'actor_id',
        'provider_response',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'provider_response' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Record a payment audit event safely without leaking secrets.
     */
    public static function record(
        Order $order,
        string $newPaymentStatus,
        ?string $previousPaymentStatus = null,
        ?string $transactionReference = null,
        string $actorType = 'system',
        ?string $actorId = null,
        ?array $providerResponse = null,
        ?string $notes = null
    ): self {
        // Sanitize sensitive provider data if any
        if ($providerResponse) {
            unset($providerResponse['password'], $providerResponse['token'], $providerResponse['secret']);
        }

        return self::create([
            'order_id' => $order->id,
            'payment_method' => $order->payment_method ?? 'unknown',
            'previous_payment_status' => $previousPaymentStatus ?? $order->getOriginal('payment_status') ?? $order->payment_status,
            'new_payment_status' => $newPaymentStatus,
            'transaction_reference' => $transactionReference ?? $order->payment_reference ?? $order->connectips_txnid,
            'amount' => $order->total_amount ?? 0.00,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'provider_response' => $providerResponse,
            'notes' => $notes,
        ]);
    }
}
