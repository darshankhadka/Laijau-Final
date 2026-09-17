<?php

namespace App\Services\Logistics\Contracts;

use App\Models\Order;

interface LogisticsProviderInterface
{
    /**
     * Return provider identifier ('ncm', 'pathao', etc.)
     */
    public function getProviderName(): string;

    /**
     * Human readable name ('Nepal Can Move', 'Pathao Parcel', etc.)
     */
    public function getDisplayName(): string;

    /**
     * Create shipment with courier provider
     *
     * @param Order $order
     * @param array $options
     * @return array{success: bool, external_order_id: ?string, tracking_number: ?string, tracking_url: ?string, status: ?string, raw_response: ?array, error: ?string}
     */
    public function createShipment(Order $order, array $options = []): array;

    /**
     * Retrieve status from courier provider
     *
     * @param string $externalOrderId
     * @return array{success: bool, status: ?string, comments: ?string, raw_response: ?array, error: ?string}
     */
    public function getShipmentStatus(string $externalOrderId): array;

    /**
     * Add tracking/operational comment to courier shipment
     *
     * @param string $externalOrderId
     * @param string $comment
     * @return array{success: bool, raw_response: ?array, error: ?string}
     */
    public function addComment(string $externalOrderId, string $comment): array;

    /**
     * Parse incoming webhook payload into normalized event items
     *
     * @param array $payload
     * @param array $headers
     * @return array<int, array{external_order_id: string, event: ?string, status: ?string, timestamp: ?string, is_test: bool, idempotency_key: string, raw: array}>
     */
    public function parseWebhookPayload(array $payload, array $headers = []): array;

    /**
     * Map provider external status to LAIJAU internal Order::STATUS_* constant
     *
     * @param string $externalStatus
     * @param string|null $event
     * @return string|null
     */
    public function mapExternalStatusToLaijau(string $externalStatus, ?string $event = null): ?string;

    /**
     * Generate official customer-facing tracking URL
     *
     * @param string $externalOrderId
     * @return string
     */
    public function getTrackingUrl(string $externalOrderId): string;
}
