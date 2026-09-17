<?php

namespace App\Services\Logistics\Providers;

use App\Models\Order;
use App\Services\Logistics\Contracts\LogisticsProviderInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PathaoProvider implements LogisticsProviderInterface
{
    protected string $baseUrl;
    protected ?string $clientId;
    protected ?string $clientSecret;
    protected ?string $username;
    protected ?string $password;
    protected int $storeId;

    public function __construct()
    {
        $config = config('services.pathao', []);
        $mode = $config['mode'] ?? 'sandbox';

        $this->baseUrl = $mode === 'production'
            ? rtrim($config['production_url'] ?? 'https://courier-api.pathao.com', '/')
            : rtrim($config['sandbox_url'] ?? 'https://courier-api-sandbox.pathao.com', '/');

        $this->clientId = $config['client_id'] ?? null;
        $this->clientSecret = $config['client_secret'] ?? null;
        $this->username = $config['username'] ?? null;
        $this->password = $config['password'] ?? null;
        $this->storeId = (int) ($config['store_id'] ?? 1);
    }

    public function getProviderName(): string
    {
        return 'pathao';
    }

    public function getDisplayName(): string
    {
        return 'Pathao Parcel';
    }

    /**
     * Get OAuth token, caching until expiry
     */
    public function getAccessToken(): ?string
    {
        if (empty($this->clientId) || empty($this->clientSecret) || empty($this->username) || empty($this->password)) {
            Log::warning('Pathao credentials are not fully configured.');
            return null;
        }

        $cacheKey = 'pathao_oauth_access_token_' . md5($this->clientId . $this->username);

        return Cache::remember($cacheKey, 400000, function () {
            try {
                $response = Http::asJson()->timeout(15)->post("{$this->baseUrl}/aladdin/api/v1/issue-token", [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'username' => $this->username,
                    'password' => $this->password,
                    'grant_type' => 'password',
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    return $data['access_token'] ?? null;
                }

                Log::error('Failed to obtain Pathao OAuth token: ' . $response->body());
                return null;
            } catch (\Throwable $e) {
                Log::error('Pathao token issue exception: ' . $e->getMessage());
                return null;
            }
        });
    }

    public function createShipment(Order $order, array $options = []): array
    {
        $token = $this->getAccessToken();
        if (empty($token)) {
            return [
                'success' => false,
                'external_order_id' => null,
                'tracking_number' => null,
                'tracking_url' => null,
                'status' => null,
                'raw_response' => null,
                'error' => 'Unable to authenticate with Pathao Parcel API.',
            ];
        }

        $endpoint = "{$this->baseUrl}/aladdin/api/v1/orders";

        $codAmount = 0;
        if (strtolower((string) $order->payment_method) === 'cod' && $order->payment_status !== Order::PAYMENT_STATUS_PAID) {
            $codAmount = (int) round((float) $order->total_amount);
        }

        $cleanPhone = preg_replace('/[^0-9]/', '', (string) $order->phone);
        if (strlen($cleanPhone) > 10) {
            $cleanPhone = substr($cleanPhone, -10);
        }

        $itemsSummary = $order->items->map(function ($item) {
            return ($item->product_name ?? 'Product') . ' x' . $item->quantity;
        })->implode(', ');

        $payload = [
            'store_id' => $options['store_id'] ?? $this->storeId,
            'merchant_order_id' => $order->order_number,
            'recipient_name' => trim("{$order->first_name} {$order->last_name}"),
            'recipient_phone' => $cleanPhone,
            'recipient_address' => (string) ($order->full_address ?: $order->shipping_address ?: 'Kathmandu, Nepal'),
            'recipient_city' => $options['recipient_city'] ?? $this->resolveRecipientCity($order->district, $order->shipping_city ?? $order->municipality),
            'recipient_zone' => $options['recipient_zone'] ?? $this->resolveRecipientZone((int) ($options['recipient_city'] ?? $this->resolveRecipientCity($order->district, $order->shipping_city ?? $order->municipality))),
            'delivery_type' => $options['delivery_type'] ?? 48,
            'item_type' => $options['item_type'] ?? 2,
            'special_instruction' => (string) ($order->delivery_notes ?: 'Please call customer before delivery'),
            'item_quantity' => max(1, $order->items->sum('quantity')),
            'item_weight' => $options['weight'] ?? 1.0,
            'amount_to_collect' => $codAmount,
            'item_description' => substr($itemsSummary ?: 'Retail Apparel', 0, 200),
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
                'Accept' => 'application/json',
            ])->timeout(15)->post($endpoint, $payload);

            $data = $response->json();

            if ($response->successful() && !empty($data['data']['consignment_id'])) {
                $consignmentId = (string) $data['data']['consignment_id'];
                return [
                    'success' => true,
                    'external_order_id' => $consignmentId,
                    'tracking_number' => $consignmentId,
                    'tracking_url' => $this->getTrackingUrl($consignmentId),
                    'status' => $data['data']['order_status'] ?? 'Pending',
                    'raw_response' => $data,
                    'error' => null,
                ];
            }

            $errorMessage = $data['message'] ?? 'Failed to create Pathao shipment';
            if (!empty($data['errors'])) {
                $errorMessage .= ': ' . json_encode($data['errors']);
            }

            Log::error('Pathao shipment creation error: ' . $errorMessage, [
                'order_number' => $order->order_number,
                'response' => $data,
            ]);

            return [
                'success' => false,
                'external_order_id' => null,
                'tracking_number' => null,
                'tracking_url' => null,
                'status' => null,
                'raw_response' => $data,
                'error' => $errorMessage,
            ];
        } catch (\Throwable $e) {
            Log::error('Pathao shipment creation exception: ' . $e->getMessage());
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
        $token = $this->getAccessToken();
        if (empty($token)) {
            return [
                'success' => false,
                'status' => null,
                'comments' => null,
                'raw_response' => null,
                'error' => 'Pathao credentials not configured.',
            ];
        }

        $endpoint = "{$this->baseUrl}/aladdin/api/v1/orders/{$externalOrderId}/info";

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
                'Accept' => 'application/json',
            ])->timeout(10)->get($endpoint);

            $data = $response->json();

            if ($response->successful()) {
                $orderData = $data['data'] ?? [];
                return [
                    'success' => true,
                    'status' => $orderData['order_status'] ?? null,
                    'comments' => $orderData['order_description'] ?? null,
                    'raw_response' => $data,
                    'error' => null,
                ];
            }

            return [
                'success' => false,
                'status' => null,
                'comments' => null,
                'raw_response' => $data,
                'error' => $data['message'] ?? 'Unable to retrieve Pathao order info',
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
        // Pathao API stores notes on shipment creation. Operational comments can be logged internally.
        return [
            'success' => true,
            'raw_response' => ['note' => 'Operational comment noted internally for Pathao parcel: ' . $comment],
            'error' => null,
        ];
    }

    public function parseWebhookPayload(array $payload, array $headers = []): array
    {
        $events = [];

        $consignmentId = $payload['consignment_id'] ?? $payload['data']['consignment_id'] ?? null;
        if (empty($consignmentId)) {
            return $events;
        }

        $status = $payload['order_status'] ?? $payload['data']['order_status'] ?? ($payload['status'] ?? null);
        $event = $payload['event'] ?? $status;
        $timestamp = $payload['updated_at'] ?? now()->toIso8601String();
        $isTest = !empty($payload['test']);

        $key = 'pathao_' . $consignmentId . '_' . ($event ?: $status) . '_' . substr($timestamp, 0, 19);

        $events[] = [
            'external_order_id' => (string) $consignmentId,
            'event' => $event,
            'status' => $status,
            'timestamp' => $timestamp,
            'is_test' => $isTest,
            'idempotency_key' => $key,
            'raw' => $payload,
        ];

        return $events;
    }

    public function mapExternalStatusToLaijau(string $externalStatus, ?string $event = null): ?string
    {
        $status = strtolower(str_replace([' ', '-'], '_', trim($externalStatus)));

        if (in_array($status, ['delivered', 'delivery_completed', 'paid', 'payment_collected'])) {
            return Order::STATUS_DELIVERED;
        }

        if (in_array($status, ['in_transit', 'dispatch', 'hub_received', 'out_for_delivery', 'sent_for_delivery'])) {
            return Order::STATUS_IN_TRANSIT;
        }

        if (in_array($status, ['picked', 'picked_up', 'pickup_completed'])) {
            return Order::STATUS_HANDED_TO_COURIER;
        }

        if (in_array($status, ['pickup_requested', 'assigned_for_pickup', 'assigned'])) {
            return Order::STATUS_READY_FOR_DELIVERY;
        }

        if (in_array($status, ['cancelled', 'cancel'])) {
            return Order::STATUS_CANCELLED;
        }

        if (in_array($status, ['return', 'returned', 'returned_to_merchant'])) {
            return Order::STATUS_RETURNED;
        }

        if (in_array($status, ['failed_delivery', 'undelivered', 'attempted'])) {
            return Order::STATUS_FAILED_DELIVERY;
        }

        return null;
    }

    public function getTrackingUrl(string $externalOrderId): string
    {
        return "https://pathao.com/np/parcel-tracking/?consignment_id=" . urlencode($externalOrderId);
    }

    protected function resolveRecipientCity(?string $district, ?string $city): int
    {
        $target = strtoupper(trim((string) ($city ?: $district)));

        $cityMap = [
            'POKHARA' => 79,
            'KASKI' => 79,
            'CHITWAN' => 82,
            'BHARATPUR' => 82,
            'NARAYANGADH' => 82,
            'BIRATNAGAR' => 83,
            'MORANG' => 83,
            'ITAHARI' => 84,
            'SUNSARI' => 84,
            'BIRTAMOD' => 85,
            'JHAPA' => 85,
            'HETAUDA' => 86,
            'MAKWANPUR' => 86,
            'NEPALGUNJ' => 87,
            'BANKE' => 87,
            'DAMAK' => 88,
            'SURKHET' => 89,
            'DAMAULI' => 90,
            'BARDIBAS' => 91,
            'JANAKPUR' => 92,
            'DHANGADHI' => 93,
            'DHARAN' => 94,
            'DHADING' => 114,
            'BIRGUNJ' => 111,
            'PARSA' => 111,
            'BUTWAL' => 149,
            'RUPANDEHI' => 149,
            'BHAIRAHAWA' => 80,
            'BAGLUNG' => 110,
        ];

        foreach ($cityMap as $key => $cityId) {
            if (str_contains($target, $key)) {
                return $cityId;
            }
        }

        // Default to Kathmandu Valley (city_id 65)
        return 65;
    }

    protected function resolveRecipientZone(int $cityId): int
    {
        // Zone map for Nepal cities in Pathao
        $zoneMap = [
            65  => 1389, // Kathmandu Valley (Airport Area / Central)
            79  => 1326, // Pokhara
            80  => 1318, // Bhairahawa
            82  => 1319, // Chitwan
            83  => 1320, // Biratnagar
            84  => 1321, // Itahari
            85  => 1322, // Birtamode
            86  => 1323, // Hetauda
            87  => 1324, // Nepalgunj
            111 => 1325, // Birgunj
            149 => 1327, // Butwal
        ];

        return $zoneMap[$cityId] ?? 1389;
    }
}
