<?php

namespace App\Services\Logistics\Providers;

use App\Models\Order;
use App\Services\Logistics\Contracts\LogisticsProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NcmProvider implements LogisticsProviderInterface
{
    protected string $baseUrl;
    protected ?string $apiToken;
    protected string $fromBranch;

    public function __construct()
    {
        $config = config('services.ncm', []);
        $mode = $config['mode'] ?? 'sandbox';

        $this->baseUrl = $mode === 'production'
            ? rtrim($config['production_url'] ?? 'https://nepalcanmove.com', '/')
            : rtrim($config['sandbox_url'] ?? 'https://demo.nepalcanmove.com', '/');

        $this->apiToken = $config['api_token'] ?? null;
        $this->fromBranch = $config['from_branch'] ?? 'TINKUNE';
    }

    public function getProviderName(): string
    {
        return 'ncm';
    }

    public function getDisplayName(): string
    {
        return 'Nepal Can Move (NCM)';
    }

    public function createShipment(Order $order, array $options = []): array
    {
        if (empty($this->apiToken)) {
            Log::warning('NCM API token is missing in configuration.');
            return [
                'success' => false,
                'external_order_id' => null,
                'tracking_number' => null,
                'tracking_url' => null,
                'status' => null,
                'raw_response' => null,
                'error' => 'NCM API token is not configured.',
            ];
        }

        $endpoint = "{$this->baseUrl}/api/v1/order/create";

        $codCharge = 0;
        if (strtolower((string) $order->payment_method) === 'cod' && $order->payment_status !== Order::PAYMENT_STATUS_PAID) {
            $codCharge = (int) round((float) $order->total_amount);
        }

        $destinationBranch = $options['branch']
            ?? $this->resolveDestinationBranch($order->district, $order->shipping_city ?? $order->municipality);

        // Sanitize phone number (Nepal 10-digit mobile)
        $cleanPhone = preg_replace('/[^0-9]/', '', (string) $order->phone);
        if (strlen($cleanPhone) > 10) {
            $cleanPhone = substr($cleanPhone, -10);
        }

        $packageSummary = $order->items->map(function ($item) {
            return ($item->product_name ?? 'Item') . ' x' . $item->quantity;
        })->implode(', ');

        if (empty($packageSummary)) {
            $packageSummary = "Laijau Retail Package ({$order->order_number})";
        }

        $payload = [
            'name' => trim("{$order->first_name} {$order->last_name}"),
            'phone' => $cleanPhone,
            'phone2' => $order->alt_phone ? preg_replace('/[^0-9]/', '', (string) $order->alt_phone) : '',
            'cod_charge' => $codCharge,
            'address' => (string) ($order->full_address ?: $order->shipping_address ?: 'Kathmandu, Nepal'),
            'fbranch' => $options['fbranch'] ?? $this->fromBranch,
            'branch' => $destinationBranch,
            'package' => substr($packageSummary, 0, 150),
            'vref_id' => $order->order_number,
            'instruction' => (string) ($order->delivery_notes ?: 'Please call before delivery.'),
            'delivery_type' => $options['delivery_type'] ?? 'Door2Door',
            'weight' => $options['weight'] ?? 1.0,
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => "Token {$this->apiToken}",
                'Accept' => 'application/json',
            ])->timeout(15)->post($endpoint, $payload);

            $data = $response->json();

            if ($response->successful() && !empty($data['orderid'])) {
                $orderId = (string) $data['orderid'];
                return [
                    'success' => true,
                    'external_order_id' => $orderId,
                    'tracking_number' => $orderId,
                    'tracking_url' => $this->getTrackingUrl($orderId),
                    'status' => 'Created',
                    'raw_response' => $data,
                    'error' => null,
                ];
            }

            $errorMessage = $data['Message'] ?? $data['message'] ?? $data['detail'] ?? 'Failed to create NCM shipment';
            if (isset($data['errors'])) {
                $errorMessage .= ': ' . json_encode($data['errors']);
            }

            Log::error('NCM shipment creation failed', [
                'order_number' => $order->order_number,
                'status_code' => $response->status(),
                'response' => $data,
            ]);

            return [
                'success' => false,
                'external_order_id' => null,
                'tracking_number' => null,
                'tracking_url' => null,
                'status' => null,
                'raw_response' => $data,
                'error' => (string) $errorMessage,
            ];
        } catch (\Throwable $e) {
            Log::error('NCM shipment creation exception: ' . $e->getMessage(), [
                'order_number' => $order->order_number,
            ]);

            return [
                'success' => false,
                'external_order_id' => null,
                'tracking_number' => null,
                'tracking_url' => null,
                'status' => null,
                'raw_response' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function getShipmentStatus(string $externalOrderId): array
    {
        if (empty($this->apiToken)) {
            return [
                'success' => false,
                'status' => null,
                'comments' => null,
                'raw_response' => null,
                'error' => 'NCM API token not configured.',
            ];
        }

        $endpoint = "{$this->baseUrl}/api/v1/order/status";

        try {
            $response = Http::withHeaders([
                'Authorization' => "Token {$this->apiToken}",
                'Accept' => 'application/json',
            ])->timeout(10)->get($endpoint, ['id' => $externalOrderId]);

            $data = $response->json();

            if ($response->successful()) {
                $status = $data['status'] ?? null;
                $comment = $data['comments'] ?? $data['comment'] ?? null;

                return [
                    'success' => true,
                    'status' => $status,
                    'comments' => $comment,
                    'raw_response' => $data,
                    'error' => null,
                ];
            }

            return [
                'success' => false,
                'status' => null,
                'comments' => null,
                'raw_response' => $data,
                'error' => $data['Message'] ?? 'Failed to retrieve NCM status',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'status' => null,
                'comments' => null,
                'raw_response' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function addComment(string $externalOrderId, string $comment): array
    {
        if (empty($this->apiToken)) {
            return ['success' => false, 'raw_response' => null, 'error' => 'API token missing'];
        }

        $endpoint = "{$this->baseUrl}/api/v1/comment";

        try {
            $response = Http::withHeaders([
                'Authorization' => "Token {$this->apiToken}",
                'Accept' => 'application/json',
            ])->timeout(10)->post($endpoint, [
                'orderid' => (int) $externalOrderId,
                'comments' => $comment,
            ]);

            return [
                'success' => $response->successful(),
                'raw_response' => $response->json(),
                'error' => $response->successful() ? null : 'Failed to add comment',
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'raw_response' => null, 'error' => $e->getMessage()];
        }
    }

    public function parseWebhookPayload(array $payload, array $headers = []): array
    {
        $events = [];

        // 1. Handle test webhook
        if (!empty($payload['test']) || ($payload['order_id'] ?? null) === 'TEST-123456') {
            $events[] = [
                'external_order_id' => (string) ($payload['order_id'] ?? 'TEST-123456'),
                'event' => (string) ($payload['event'] ?? 'order.status.changed'),
                'status' => (string) ($payload['status'] ?? 'test'),
                'timestamp' => (string) ($payload['timestamp'] ?? now()->toIso8601String()),
                'is_test' => true,
                'idempotency_key' => 'ncm_test_' . md5(json_encode($payload)),
                'raw' => $payload,
            ];
            return $events;
        }

        // 2. Handle bulk webhook { "order_ids": [747, 748], "status": "Delivered", "event": "delivery_completed", "timestamp": "..." }
        if (!empty($payload['order_ids']) && is_array($payload['order_ids'])) {
            $commonEvent = $payload['event'] ?? null;
            $commonStatus = $payload['status'] ?? null;
            $timestamp = $payload['timestamp'] ?? now()->toIso8601String();

            foreach ($payload['order_ids'] as $orderId) {
                $idStr = (string) $orderId;
                $key = 'ncm_' . $idStr . '_' . ($commonEvent ?: $commonStatus) . '_' . substr($timestamp, 0, 19);
                $events[] = [
                    'external_order_id' => $idStr,
                    'event' => $commonEvent,
                    'status' => $commonStatus,
                    'timestamp' => $timestamp,
                    'is_test' => false,
                    'idempotency_key' => $key,
                    'raw' => $payload,
                ];
            }
            return $events;
        }

        // 3. Handle single webhook { "order_id": 747, "status": "Sent for Delivery", "event": "sent_for_delivery", "timestamp": "..." }
        if (!empty($payload['order_id'])) {
            $idStr = (string) $payload['order_id'];
            $event = $payload['event'] ?? null;
            $status = $payload['status'] ?? null;
            $timestamp = $payload['timestamp'] ?? now()->toIso8601String();
            $key = 'ncm_' . $idStr . '_' . ($event ?: $status) . '_' . substr($timestamp, 0, 19);

            $events[] = [
                'external_order_id' => $idStr,
                'event' => $event,
                'status' => $status,
                'timestamp' => $timestamp,
                'is_test' => false,
                'idempotency_key' => $key,
                'raw' => $payload,
            ];
            return $events;
        }

        return $events;
    }

    public function mapExternalStatusToLaijau(string $externalStatus, ?string $event = null): ?string
    {
        $normalizedStatus = strtolower(trim($externalStatus));
        $normalizedEvent = strtolower(trim((string) $event));

        // Delivery completed
        if (
            str_contains($normalizedEvent, 'delivery_completed') ||
            str_contains($normalizedStatus, 'delivered') ||
            str_contains($normalizedStatus, 'delivery complete')
        ) {
            return Order::STATUS_DELIVERED;
        }

        // Out for delivery / Sent for delivery / In transit
        if (
            str_contains($normalizedEvent, 'sent_for_delivery') ||
            str_contains($normalizedStatus, 'sent for delivery') ||
            str_contains($normalizedStatus, 'out for delivery') ||
            str_contains($normalizedEvent, 'order_dispatched') ||
            str_contains($normalizedStatus, 'dispatched') ||
            str_contains($normalizedStatus, 'in transit') ||
            str_contains($normalizedEvent, 'order_arrived') ||
            str_contains($normalizedStatus, 'arrived')
        ) {
            return Order::STATUS_IN_TRANSIT;
        }

        // Picked up
        if (
            str_contains($normalizedEvent, 'pickup_completed') ||
            str_contains($normalizedStatus, 'picked up') ||
            str_contains($normalizedStatus, 'pickup complete') ||
            str_contains($normalizedStatus, 'handed to courier')
        ) {
            return Order::STATUS_HANDED_TO_COURIER;
        }

        // Cancelled
        if (
            str_contains($normalizedEvent, 'cancelled') ||
            str_contains($normalizedStatus, 'cancelled')
        ) {
            return Order::STATUS_CANCELLED;
        }

        // Return
        if (
            str_contains($normalizedEvent, 'returned') ||
            str_contains($normalizedStatus, 'returned') ||
            str_contains($normalizedStatus, 'return')
        ) {
            return Order::STATUS_RETURNED;
        }

        // Failed delivery
        if (
            str_contains($normalizedEvent, 'failed') ||
            str_contains($normalizedStatus, 'undelivered') ||
            str_contains($normalizedStatus, 'failed delivery')
        ) {
            return Order::STATUS_FAILED_DELIVERY;
        }

        return null;
    }

    public function getTrackingUrl(string $externalOrderId): string
    {
        return "https://nepalcanmove.com/track?tracking_id=" . urlencode($externalOrderId);
    }

    protected function resolveDestinationBranch(?string $district, ?string $city): string
    {
        $target = strtoupper(trim((string) ($city ?: $district)));

        $branchMap = [
            'POKHARA' => 'POKHARA',
            'KASKI' => 'POKHARA',
            'BIRATNAGAR' => 'BIRATNAGAR',
            'MORANG' => 'BIRATNAGAR',
            'BUTWAL' => 'BUTWAL',
            'RUPANDEHI' => 'BUTWAL',
            'DHARAN' => 'DHARAN',
            'SUNSARI' => 'DHARAN',
            'NARAYANGADH' => 'NARAYANGADH',
            'CHITWAN' => 'NARAYANGADH',
            'BHARATPUR' => 'NARAYANGADH',
            'NEPALGUNJ' => 'NEPALGUNJ',
            'BANKE' => 'NEPALGUNJ',
            'HETAUDA' => 'HETAUDA',
            'MAKWANPUR' => 'HETAUDA',
            'DHANGADHI' => 'DHANGADHI',
            'KAILALI' => 'DHANGADHI',
            'BIRGUNJ' => 'BIRGUNJ',
            'PARSA' => 'BIRGUNJ',
            'DAMAK' => 'DAMAK',
            'JHAPA' => 'DAMAK',
            'BIRTAMOD' => 'BIRTAMOD',
            'BHAKTAPUR' => 'BHAKTAPUR',
            'LALITPUR' => 'LALITPUR',
            'KATHMANDU' => 'TINKUNE',
        ];

        foreach ($branchMap as $key => $branch) {
            if (str_contains($target, $key)) {
                return $branch;
            }
        }

        return 'TINKUNE';
    }
}
