<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Order;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    /**
     * Handle before order updates.
     */
    public function updating(Order $order): void
    {
        if ($order->isDirty('status')) {
            $newStatus = $order->status;

            // When order is marked delivered
            if ($newStatus === Order::STATUS_DELIVERED) {
                // If Cash on Delivery, mark payment collected / paid
                if ($order->payment_method === 'cod' && $order->payment_status !== Order::PAYMENT_STATUS_PAID) {
                    $order->payment_status = Order::PAYMENT_STATUS_PAID;
                    $order->payment_verified_at = $order->payment_verified_at ?? now();
                }

                if (empty($order->actual_delivery_date)) {
                    $order->actual_delivery_date = now()->toDateString();
                }

                if (empty($order->delivered_at)) {
                    $order->delivered_at = now();
                }
            }

            // When order is cancelled
            if ($newStatus === Order::STATUS_CANCELLED) {
                if (empty($order->cancelled_at)) {
                    $order->cancelled_at = now();
                }
            }
        }
    }

    /**
     * Handle after order creation.
     */
    public function created(Order $order): void
    {
        if ($order->payment_status === Order::PAYMENT_STATUS_PAID || in_array($order->status, ['paid', Order::STATUS_PAYMENT_VERIFIED], true)) {
            try {
                $accountingService = app(\App\Services\Accounting\AccountingService::class);
                $accountingService->recordOrderSale($order);
            } catch (\Throwable $e) {
                // Accounting is non-blocking
            }
        }
    }

    /**
     * Handle after order updates.
     */
    public function updated(Order $order): void
    {
        if ($order->wasChanged('status')) {
            $newStatus = $order->status;

            // 1. Release active reservations on cancellation
            if ($newStatus === Order::STATUS_CANCELLED) {
                try {
                    $releasedCount = app(InventoryService::class)->cancelOrderReservations($order, 'cancelled');
                    if ($releasedCount > 0) {
                        Log::info("[OrderObserver] Released {$releasedCount} reserved stock items for cancelled Order #{$order->order_number}");
                    }
                } catch (\Throwable $e) {
                    Log::error("[OrderObserver] Failed to release reservations for cancelled Order #{$order->order_number}: " . $e->getMessage());
                }
            }

            // 2. Fulfill inventory stock on fulfillment progression states
            $fulfillmentStates = [
                Order::STATUS_PACKING,
                Order::STATUS_READY_FOR_DELIVERY,
                Order::STATUS_HANDED_TO_COURIER,
                Order::STATUS_IN_TRANSIT,
                Order::STATUS_DELIVERED,
            ];

            if (in_array($newStatus, $fulfillmentStates, true)) {
                try {
                    app(InventoryService::class)->fulfillOrderStock($order);
                } catch (\Throwable $e) {
                    Log::error("[OrderObserver] Error during fulfillOrderStock for Order #{$order->order_number}: " . $e->getMessage());
                }
            }

            // 3. Reverse accounting on cancellation or refund
            if (in_array($newStatus, [Order::STATUS_CANCELLED, 'refunded', 'returned'], true)) {
                try {
                    $accountingService = app(\App\Services\Accounting\AccountingService::class);
                    $voucher = \App\Models\Accounting\JournalEntry::where('reference_type', 'order')
                        ->where('reference_id', $order->id)
                        ->where('status', 'posted')
                        ->first();
                    if ($voucher) {
                        $accountingService->reverseJournalEntry($voucher, "Order #{$order->order_number} refunded/cancelled");
                    }
                } catch (\Throwable $e) {
                    // Accounting is non-blocking
                }
            }
        }

        // 4. Auto-post accounting journal entry when order payment is marked paid
        if ($order->wasChanged('payment_status') || $order->wasChanged('status')) {
            if ($order->payment_status === Order::PAYMENT_STATUS_PAID || in_array($order->status, ['paid', Order::STATUS_PAYMENT_VERIFIED], true)) {
                try {
                    $accountingService = app(\App\Services\Accounting\AccountingService::class);
                    $accountingService->recordOrderSale($order);
                } catch (\Throwable $e) {
                    // Accounting is non-blocking
                }
            }
        }
    }
}
