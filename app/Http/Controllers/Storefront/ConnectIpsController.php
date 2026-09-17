<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentAuditLog;
use App\Models\Inventory\StockReservation;
use App\Services\Inventory\InventoryService;
use App\Services\Payment\ConnectIpsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ConnectIpsController extends Controller
{
    protected ConnectIpsService $connectIpsService;
    protected InventoryService $inventoryService;

    public function __construct(ConnectIpsService $connectIpsService, InventoryService $inventoryService)
    {
        $this->connectIpsService = $connectIpsService;
        $this->inventoryService = $inventoryService;
    }

    /**
     * Display auto-submitting gateway redirect form to ConnectIPS.
     */
    public function initiate(Request $request, Order $order)
    {
        $token = $request->query('token');
        $placedOrders = (array) ($request->hasSession() ? $request->session()->get('placed_orders', []) : []);
        $isSessionPlaced = in_array($order->order_number, $placedOrders) || in_array((string)$order->id, $placedOrders);
        $isTokenMatch = !empty($token) && hash_equals((string)$order->guest_access_token, (string)$token);
        $isUserMatch = auth('web')->check() && (int)auth('web')->id() === (int)$order->user_id;

        if (!$isTokenMatch && !$isSessionPlaced && !$isUserMatch && !auth('admin')->check()) {
            abort(403, 'Unauthorized access to order.');
        }

        if ($order->payment_status === Order::PAYMENT_STATUS_PAID) {
            return redirect()->route('storefront.checkout.success', [
                'order_number' => $order->order_number,
                'token' => $order->guest_access_token,
            ]);
        }

        $payload = $this->connectIpsService->generateInitiationPayload($order);

        // Record initiation audit log
        PaymentAuditLog::record(
            $order,
            $order->payment_status,
            $order->payment_status,
            $payload['txn_id'],
            'customer',
            auth('web')->id() ? (string)auth('web')->id() : null,
            ['action_url' => $payload['action_url']],
            'Customer initiated ConnectIPS payment.'
        );

        return view('storefront.payments.connectips_redirect', [
            'order' => $order,
            'actionUrl' => $payload['action_url'],
            'fields' => $payload['fields'],
        ]);
    }

    /**
     * Handle customer return from ConnectIPS gateway.
     */
    public function returnUrl(Request $request)
    {
        $txnId = $request->query('TXNID') ?: $request->input('TXNID');

        Log::info("ConnectIPS Return received with TXNID: " . ($txnId ?: 'NONE'));

        if (empty($txnId)) {
            return redirect()->route('storefront.checkout')->with('error', 'ConnectIPS payment was cancelled or no transaction reference was returned.');
        }

        $order = Order::where('connectips_txnid', $txnId)->first();

        if (!$order) {
            // Check if TXNID contains order reference
            Log::warning("Order not found for ConnectIPS TXNID: {$txnId}");
            return redirect()->route('storefront.checkout')->with('error', "Unable to locate order for transaction reference: {$txnId}");
        }

        // Idempotency: If order is already paid, redirect straight to success
        if ($order->payment_status === Order::PAYMENT_STATUS_PAID) {
            return redirect()->route('storefront.checkout.success', [
                'order_number' => $order->order_number,
                'token' => $order->guest_access_token,
            ])->with('info', 'Payment has already been successfully verified.');
        }

        // Perform server-side validation against ConnectIPS API
        $validation = $this->connectIpsService->validateTransaction($txnId, $order);

        if ($validation['success']) {
            $prevStatus = $order->payment_status;

            $order->update([
                'payment_status' => Order::PAYMENT_STATUS_PAID,
                'payment_id' => $validation['gateway_txnid'] ?? $txnId,
                'payment_reference' => $validation['reference_id'] ?? $order->order_number,
                'payment_verified_at' => now(),
                'status' => in_array($order->status, [Order::STATUS_PENDING, Order::STATUS_PAYMENT_PENDING])
                    ? Order::STATUS_PAYMENT_VERIFIED
                    : $order->status,
                'payment_notes' => "ConnectIPS Verified: TXN {$validation['gateway_txnid']} | Batch {$validation['batch_id']}",
            ]);

            PaymentAuditLog::record(
                $order,
                Order::PAYMENT_STATUS_PAID,
                $prevStatus,
                $validation['gateway_txnid'] ?? $txnId,
                'gateway',
                'connectips',
                $validation['raw_response'],
                'ConnectIPS transaction verified successfully server-side.'
            );

            // Synchronize payment to Accounting & Bikri Khata
            try {
                app(\App\Services\Accounting\AccountingService::class)->recordOrderSale($order);
            } catch (\Throwable $e) {
                Log::warning("Accounting synchronization notice on ConnectIPS return: " . $e->getMessage());
            }

            // Ensure session preserves this placed order
            if ($request->hasSession()) {
                $placed = (array) $request->session()->get('placed_orders', []);
                $placed[] = $order->order_number;
                $request->session()->put('placed_orders', array_unique($placed));
            }

            return redirect()->route('storefront.checkout.success', [
                'order_number' => $order->order_number,
                'token' => $order->guest_access_token,
            ])->with('success', 'ConnectIPS payment verified successfully! Your order has been placed.');
        }

        // Payment Failed or Tampered
        $order->update([
            'payment_status' => 'failed',
            'payment_notes' => 'ConnectIPS Verification Failed: ' . ($validation['error'] ?? 'Unknown error'),
        ]);

        PaymentAuditLog::record(
            $order,
            'failed',
            $order->getOriginal('payment_status'),
            $txnId,
            'gateway',
            'connectips',
            $validation['raw_response'] ?? [],
            'ConnectIPS payment failed: ' . ($validation['error'] ?? 'Verification rejected')
        );

        // Release inventory reservation on payment failure
        $activeReservations = StockReservation::where('reference_type', 'online_order')
            ->where('reference_id', $order->id)
            ->where('status', 'active')
            ->get();

        foreach ($activeReservations as $res) {
            $this->inventoryService->releaseReservation($res, 'cancelled');
        }

        return redirect()->route('storefront.checkout')->with('error', 'ConnectIPS payment could not be verified: ' . ($validation['error'] ?? 'Transaction failed. Please try again.'));
    }

    /**
     * Server-to-server webhook / callback notification from ConnectIPS.
     */
    public function callback(Request $request)
    {
        $txnId = $request->input('TXNID') ?: $request->query('TXNID');

        Log::info("ConnectIPS Server-to-Server Callback received: " . ($txnId ?: 'EMPTY'));

        if (empty($txnId)) {
            return response()->json(['status' => 'FAILED', 'message' => 'Missing TXNID'], 400);
        }

        $order = Order::where('connectips_txnid', $txnId)->first();

        if (!$order) {
            return response()->json(['status' => 'NOT_FOUND', 'message' => 'Order not found'], 404);
        }

        if ($order->payment_status === Order::PAYMENT_STATUS_PAID) {
            return response()->json(['status' => 'SUCCESS', 'message' => 'Already paid'], 200);
        }

        $validation = $this->connectIpsService->validateTransaction($txnId, $order);

        if ($validation['success']) {
            $order->update([
                'payment_status' => Order::PAYMENT_STATUS_PAID,
                'payment_id' => $validation['gateway_txnid'] ?? $txnId,
                'payment_reference' => $validation['reference_id'] ?? $order->order_number,
                'payment_verified_at' => now(),
                'status' => in_array($order->status, [Order::STATUS_PENDING, Order::STATUS_PAYMENT_PENDING])
                    ? Order::STATUS_PAYMENT_VERIFIED
                    : $order->status,
                'payment_notes' => "ConnectIPS Webhook Verified: TXN {$validation['gateway_txnid']}",
            ]);

            PaymentAuditLog::record(
                $order,
                Order::PAYMENT_STATUS_PAID,
                'unpaid',
                $validation['gateway_txnid'] ?? $txnId,
                'gateway',
                'connectips_webhook',
                $validation['raw_response'],
                'ConnectIPS payment verified via asynchronous webhook.'
            );

            try {
                app(\App\Services\Accounting\AccountingService::class)->recordOrderSale($order);
            } catch (\Throwable $e) {
                Log::warning("Accounting synchronization notice on ConnectIPS webhook: " . $e->getMessage());
            }

            return response()->json(['status' => 'SUCCESS'], 200);
        }

        return response()->json(['status' => 'FAILED', 'message' => $validation['error']], 400);
    }
}
