<?php

declare(strict_types=1);

namespace App\Services\Logistics;

use App\Models\LogisticsEvent;
use App\Models\Order;
use App\Models\Shipment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class NcmReconciliationService
{
    public const PROVIDER = 'ncm';

    /**
     * Clean and strip HTML placeholders (like <br>, <br/>, &nbsp;).
     */
    public function cleanHtmlPlaceholder(?string $val): ?string
    {
        if ($val === null) {
            return null;
        }

        $cleaned = trim($val);
        $cleaned = preg_replace('/<br\s*\/?>/i', '', $cleaned);
        $cleaned = str_ireplace('&nbsp;', '', $cleaned);
        $cleaned = trim($cleaned);

        return $cleaned === '' ? null : $cleaned;
    }

    /**
     * Normalize Nepal phone numbers (extract 10-digit mobile).
     */
    public function normalizePhone(?string $phone): string
    {
        if (!$phone) {
            return '';
        }

        $digits = preg_replace('/\D/', '', $phone);

        if (str_starts_with($digits, '977') && strlen($digits) === 13) {
            $digits = substr($digits, 3);
        } elseif (str_starts_with($digits, '0') && strlen($digits) === 11) {
            $digits = substr($digits, 1);
        }

        return $digits;
    }

    /**
     * Normalize customer name for comparison.
     */
    public function normalizeName(?string $name): string
    {
        if (!$name) {
            return '';
        }

        return preg_replace('/\s+/', ' ', strtolower(trim($name)));
    }

    /**
     * Normalize NCM raw status string to canonical internal status.
     */
    public function normalizeStatus(string $rawStatus, bool $isVendorReturn = false): string
    {
        if ($isVendorReturn) {
            return Shipment::STATUS_RETURNED_TO_VENDOR;
        }

        $s = strtolower(trim($rawStatus));

        return match ($s) {
            'delivered' => Shipment::STATUS_DELIVERED,
            'sent for delivery' => Shipment::STATUS_OUT_FOR_DELIVERY,
            'dispatched' => Shipment::STATUS_DISPATCHED,
            'arrived' => Shipment::STATUS_ARRIVED,
            'sent for pickup' => Shipment::STATUS_PICKUP_PENDING,
            default => Shipment::STATUS_OTHER,
        };
    }

    /**
     * Safely parse Y-m-d date into datetime string.
     */
    public function parseDate(?string $dateStr): ?string
    {
        $clean = $this->cleanHtmlPlaceholder($dateStr);
        if (!$clean) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $clean)->startOfDay()->toDateTimeString();
        } catch (\Throwable $e) {
            try {
                return Carbon::parse($clean)->toDateTimeString();
            } catch (\Throwable $e2) {
                return null;
            }
        }
    }

    /**
     * Load, audit, match, and optionally sync NCM CSV records.
     *
     * @param string $csvPath Full path to the NCM CSV file.
     * @param bool $apply If false, runs in dry-run mode (0 mutations).
     * @param string|null $batchId Unique batch identifier.
     * @return array Summary metrics and audit results.
     */
    public function sync(string $csvPath, bool $apply = false, ?string $batchId = null): array
    {
        if (!file_exists($csvPath) || !is_readable($csvPath)) {
            throw new InvalidArgumentException("NCM CSV file not found or unreadable at: {$csvPath}");
        }

        $batchId = $batchId ?: 'ncm_batch_' . date('Ymd_His');
        $fileName = basename($csvPath);

        // 1. Read and parse CSV rows
        $rows = [];
        $fh = fopen($csvPath, 'r');
        $header = fgetcsv($fh);
        if (!$header) {
            fclose($fh);
            throw new InvalidArgumentException("NCM CSV header is empty or invalid.");
        }

        // Clean UTF-8 BOM from headers
        $header[0] = preg_replace('/^[\xEF\xBB\xBF]/', '', $header[0]);
        $header = array_map('trim', $header);

        $lineNum = 2;
        while (($line = fgetcsv($fh)) !== false) {
            if (count($line) === count($header)) {
                $rows[] = [
                    'source_row' => $lineNum,
                    'data' => array_combine($header, $line),
                ];
            }
            $lineNum++;
        }
        fclose($fh);

        // 2. Preload Orders into Memory Lookup Maps
        $orders = Order::select([
            'id',
            'order_number',
            'first_name',
            'last_name',
            'phone',
            'alt_phone',
            'province',
            'district',
            'municipality',
            'total_amount',
            'status',
            'payment_method',
            'payment_status',
            'tracking_number',
            'courier_order_id',
            'carrier',
            'courier_name',
            'courier_status',
            'delivered_at',
            'actual_delivery_date',
            'created_at'
        ])->get();

        $ordersByTracking = [];
        $ordersByPhone = [];

        foreach ($orders as $ord) {
            $t = trim((string)$ord->tracking_number);
            if ($t !== '') {
                $ordersByTracking[$t] = $ord;
            }
            $c = trim((string)$ord->courier_order_id);
            if ($c !== '') {
                $ordersByTracking[$c] = $ord;
            }

            $p1 = $this->normalizePhone($ord->phone);
            if (strlen($p1) >= 10) {
                $ordersByPhone[$p1][] = $ord;
            }
            $p2 = $this->normalizePhone($ord->alt_phone);
            if (strlen($p2) >= 10 && $p2 !== $p1) {
                $ordersByPhone[$p2][] = $ord;
            }
        }

        // Preload Existing Shipments for Idempotency
        $existingShipments = Shipment::where('provider', self::PROVIDER)
            ->get()
            ->keyBy('external_tracking_number');

        // 3. Precompute Multi-Pass Evidence-Based Matching to Enforce Strict 1-to-1 Order Tracking Invariant
        $rowsByPhone = [];
        foreach ($rows as $item) {
            $data = $item['data'];
            $rawOid = trim((string)($data['Order ID'] ?? ''));
            if ($rawOid === '') continue;
            $rPhone = $this->cleanHtmlPlaceholder($data['Receiver Phone'] ?? null);
            $nPhone = $this->normalizePhone($rPhone);
            $rowsByPhone[$nPhone][] = $item;
        }

        $decisions = [];
        $claimedOrders = [];

        // PASS 1: Level 1 Exact Tracking Number or Courier Order ID Match
        foreach ($rows as $item) {
            $rawOid = trim((string)($item['data']['Order ID'] ?? ''));
            if ($rawOid === '') continue;

            if (isset($ordersByTracking[$rawOid])) {
                $ord = $ordersByTracking[$rawOid];
                if (!isset($claimedOrders[$ord->id])) {
                    $claimedOrders[$ord->id] = $rawOid;
                    $decisions[$rawOid] = [
                        'matched_order' => $ord,
                        'match_status' => Shipment::MATCH_STATUS_MATCHED,
                        'match_method' => 'exact_tracking',
                        'match_confidence' => 'exact',
                        'match_reason' => "Exact NCM tracking number match with Order #{$ord->order_number}",
                        'candidate_order_ids' => [$ord->id],
                    ];
                }
            }
        }

        // PASS 2: Phone groups where exactly 1 NCM shipment exists
        foreach ($rowsByPhone as $phone => $items) {
            if (count($items) === 1) {
                $item = $items[0];
                $rawOid = trim((string)($item['data']['Order ID'] ?? ''));
                if (isset($decisions[$rawOid])) {
                    continue; // already exact matched
                }

                if ($phone === '' || !isset($ordersByPhone[$phone])) {
                    $decisions[$rawOid] = [
                        'matched_order' => null,
                        'match_status' => Shipment::MATCH_STATUS_UNMATCHED,
                        'match_method' => null,
                        'match_confidence' => 'unmatched',
                        'match_reason' => "Receiver phone {$phone} not found in any existing Laijau orders",
                        'candidate_order_ids' => [],
                    ];
                    continue;
                }

                // Available unclaimed orders for this phone
                $candidates = array_values(array_filter($ordersByPhone[$phone], fn($o) => !isset($claimedOrders[$o->id])));

                if (count($candidates) === 1) {
                    $ord = $candidates[0];
                    $claimedOrders[$ord->id] = $rawOid;
                    $decisions[$rawOid] = [
                        'matched_order' => $ord,
                        'match_status' => Shipment::MATCH_STATUS_MATCHED,
                        'match_method' => 'phone_single',
                        'match_confidence' => 'high',
                        'match_reason' => "Unique customer phone match with Order #{$ord->order_number}",
                        'candidate_order_ids' => [$ord->id],
                    ];
                } elseif (count($candidates) === 0) {
                    $allCandidateIds = array_map(fn($o) => $o->id, $ordersByPhone[$phone]);
                    $decisions[$rawOid] = [
                        'matched_order' => null,
                        'match_status' => Shipment::MATCH_STATUS_AMBIGUOUS,
                        'match_method' => null,
                        'match_confidence' => 'ambiguous',
                        'match_reason' => "All candidate orders for phone {$phone} were claimed by prior shipments (Orders: " . implode(', ', $allCandidateIds) . ")",
                        'candidate_order_ids' => $allCandidateIds,
                    ];
                } else {
                    // 1 shipment, multiple candidate orders
                    $codAmount = (float)($item['data']['COD Charge'] ?? 0);
                    $codMatches = array_values(array_filter($candidates, fn($c) => abs((float)$c->total_amount - $codAmount) < 1.0));
                    if (count($codMatches) === 1) {
                        $ord = $codMatches[0];
                        $claimedOrders[$ord->id] = $rawOid;
                        $decisions[$rawOid] = [
                            'matched_order' => $ord,
                            'match_status' => Shipment::MATCH_STATUS_MATCHED,
                            'match_method' => 'phone_cod',
                            'match_confidence' => 'high',
                            'match_reason' => "Multi-order phone match disambiguated by exact COD amount (Rs. {$codAmount}) with Order #{$ord->order_number}",
                            'candidate_order_ids' => [$ord->id],
                        ];
                    } else {
                        $ncmCreated = $this->parseDate($item['data']['Created Date'] ?? null);
                        $ncmTimestamp = $ncmCreated ? strtotime($ncmCreated) : null;
                        $dateMatches = $ncmTimestamp ? array_values(array_filter($candidates, function ($c) use ($ncmTimestamp) {
                            $ordTimestamp = strtotime($c->created_at->format('Y-m-d'));
                            return abs($ordTimestamp - $ncmTimestamp) <= 3 * 86400;
                        })) : [];

                        if (count($dateMatches) === 1) {
                            $ord = $dateMatches[0];
                            $claimedOrders[$ord->id] = $rawOid;
                            $decisions[$rawOid] = [
                                'matched_order' => $ord,
                                'match_status' => Shipment::MATCH_STATUS_MATCHED,
                                'match_method' => 'phone_date',
                                'match_confidence' => 'medium',
                                'match_reason' => "Multi-order phone match disambiguated by order creation date proximity (within 3 days) with Order #{$ord->order_number}",
                                'candidate_order_ids' => [$ord->id],
                            ];
                        } else {
                            $candidateIds = array_map(fn($c) => $c->id, $candidates);
                            $decisions[$rawOid] = [
                                'matched_order' => null,
                                'match_status' => Shipment::MATCH_STATUS_AMBIGUOUS,
                                'match_method' => null,
                                'match_confidence' => 'ambiguous',
                                'match_reason' => "Phone number {$phone} matches multiple candidate orders (" . implode(', ', $candidateIds) . ") with no conclusive COD/date tiebreaker",
                                'candidate_order_ids' => $candidateIds,
                            ];
                        }
                    }
                }
            }
        }

        // PASS 3: Phone groups where multiple NCM shipments exist
        foreach ($rowsByPhone as $phone => $items) {
            if (count($items) > 1) {
                if ($phone === '' || !isset($ordersByPhone[$phone])) {
                    foreach ($items as $item) {
                        $rawOid = trim((string)($item['data']['Order ID'] ?? ''));
                        $decisions[$rawOid] = [
                            'matched_order' => null,
                            'match_status' => Shipment::MATCH_STATUS_UNMATCHED,
                            'match_method' => null,
                            'match_confidence' => 'unmatched',
                            'match_reason' => "Receiver phone {$phone} not found in any existing Laijau orders",
                            'candidate_order_ids' => [],
                        ];
                    }
                    continue;
                }

                $availableOrders = array_values(array_filter($ordersByPhone[$phone], fn($o) => !isset($claimedOrders[$o->id])));
                $unresolvedItems = [];

                foreach ($items as $item) {
                    $rawOid = trim((string)($item['data']['Order ID'] ?? ''));
                    if (!isset($decisions[$rawOid])) {
                        $unresolvedItems[] = $item;
                    }
                }

                // Iteratively match unique non-zero COD amounts
                $matchedThisRound = true;
                while ($matchedThisRound && count($unresolvedItems) > 0 && count($availableOrders) > 0) {
                    $matchedThisRound = false;
                    foreach ($unresolvedItems as $idx => $item) {
                        $cod = (float)($item['data']['COD Charge'] ?? 0);
                        if ($cod <= 0) continue;

                        $matchingOrders = array_values(array_filter($availableOrders, fn($o) => abs((float)$o->total_amount - $cod) < 1.0));
                        $matchingShipments = array_values(array_filter($unresolvedItems, fn($other) => abs((float)($other['data']['COD Charge'] ?? 0) - $cod) < 1.0));

                        if (count($matchingOrders) === 1 && count($matchingShipments) === 1) {
                            $ord = $matchingOrders[0];
                            $rawOid = trim((string)($item['data']['Order ID'] ?? ''));
                            $claimedOrders[$ord->id] = $rawOid;
                            $decisions[$rawOid] = [
                                'matched_order' => $ord,
                                'match_status' => Shipment::MATCH_STATUS_MATCHED,
                                'match_method' => 'phone_cod',
                                'match_confidence' => 'high',
                                'match_reason' => "Multi-shipment phone group disambiguated by unique COD amount (Rs. {$cod}) with Order #{$ord->order_number}",
                                'candidate_order_ids' => [$ord->id],
                            ];
                            $availableOrders = array_values(array_filter($availableOrders, fn($o) => $o->id !== $ord->id));
                            unset($unresolvedItems[$idx]);
                            $unresolvedItems = array_values($unresolvedItems);
                            $matchedThisRound = true;
                            break;
                        }
                    }
                }

                // If exactly 1 unresolved shipment and 1 available order remain:
                if (count($unresolvedItems) === 1 && count($availableOrders) === 1) {
                    $item = $unresolvedItems[0];
                    $ord = $availableOrders[0];
                    $rawOid = trim((string)($item['data']['Order ID'] ?? ''));
                    $ncmDate = $this->parseDate($item['data']['Created Date'] ?? null);
                    $diffDays = $ncmDate ? abs(strtotime($ord->created_at->format('Y-m-d')) - strtotime($ncmDate)) / 86400 : 999;
                    if ($diffDays <= 7) {
                        $claimedOrders[$ord->id] = $rawOid;
                        $decisions[$rawOid] = [
                            'matched_order' => $ord,
                            'match_status' => Shipment::MATCH_STATUS_MATCHED,
                            'match_method' => 'phone_date',
                            'match_confidence' => 'medium',
                            'match_reason' => "Remaining phone pair disambiguated by order creation date proximity (within {$diffDays} days) with Order #{$ord->order_number}",
                            'candidate_order_ids' => [$ord->id],
                        ];
                        $unresolvedItems = [];
                        $availableOrders = [];
                    }
                }

                // All remaining shipments in this phone group are ambiguous
                $allCandidateIds = array_map(fn($o) => $o->id, $ordersByPhone[$phone]);
                foreach ($unresolvedItems as $item) {
                    $rawOid = trim((string)($item['data']['Order ID'] ?? ''));
                    $decisions[$rawOid] = [
                        'matched_order' => null,
                        'match_status' => Shipment::MATCH_STATUS_AMBIGUOUS,
                        'match_method' => null,
                        'match_confidence' => 'ambiguous',
                        'match_reason' => "Phone number {$phone} has multiple competing shipments/orders with no unique COD/date tiebreaker (Candidates: " . implode(', ', $allCandidateIds) . ")",
                        'candidate_order_ids' => $allCandidateIds,
                    ];
                }
            }
        }

        // 4. Process each NCM Row using Precomputed Decisions
        $metrics = [
            'batch_id' => $batchId,
            'source_file' => $fileName,
            'source_rows' => count($rows),
            'unique_ncm_shipments' => count($rows),
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'matched_total' => 0,
            'matched_exact' => 0,
            'matched_high' => 0,
            'matched_medium' => 0,
            'ambiguous' => 0,
            'unmatched' => 0,
            'status_breakdown' => [
                'delivered' => 0,
                'out_for_delivery' => 0,
                'dispatched' => 0,
                'arrived' => 0,
                'pickup_pending' => 0,
                'returned_to_vendor' => 0,
                'other' => 0,
            ],
            'vendor_returns_count' => 0,
            'cod_total' => 0.0,
            'delivery_charge_total' => 0.0,
            'weight_total' => 0.0,
            'orders_synchronized' => 0,
        ];

        $reconciliationMatrix = [];
        $manualReviewRows = [];
        $matchedOrdersTracker = [];

        foreach ($rows as $item) {
            $rowNum = $item['source_row'];
            $data = $item['data'];

            $rawOrderId = trim((string)($data['Order ID'] ?? ''));
            if ($rawOrderId === '') {
                continue;
            }

            $ncmCreated = $this->parseDate($data['Created Date'] ?? null);
            $ncmDelivered = $this->parseDate($data['Delivered Date'] ?? null);
            $rawStatus = trim((string)($data['Status'] ?? ''));
            $isVendorReturn = filter_var($data['Vendor Return'] ?? false, FILTER_VALIDATE_BOOLEAN);

            $sourceBranch = $this->cleanHtmlPlaceholder($data['Source Branch'] ?? null);
            $destBranch = $this->cleanHtmlPlaceholder($data['Destination Branch'] ?? null);
            $receiverName = $this->cleanHtmlPlaceholder($data['Receiver'] ?? null);
            $receiverPhone = $this->cleanHtmlPlaceholder($data['Receiver Phone'] ?? null);
            $normPhone = $this->normalizePhone($receiverPhone);

            $codAmount = (float)($data['COD Charge'] ?? 0);
            $deliveryCharge = (float)($data['Delivery Charge'] ?? 0);
            $weight = (float)($data['Weight'] ?? 1.0);

            $pkgDesc = $this->cleanHtmlPlaceholder($data['Package Description'] ?? null);
            $remarks = $this->cleanHtmlPlaceholder($data['Remarks'] ?? null);
            $refId = $this->cleanHtmlPlaceholder($data['Reference ID'] ?? null);
            $createdBy = $this->cleanHtmlPlaceholder($data['Created By'] ?? null);

            $normalizedStatus = $this->normalizeStatus($rawStatus, $isVendorReturn);

            // Accumulate financial & physical totals
            $metrics['cod_total'] += $codAmount;
            $metrics['delivery_charge_total'] += $deliveryCharge;
            $metrics['weight_total'] += $weight;
            if ($isVendorReturn) {
                $metrics['vendor_returns_count']++;
            }
            if (isset($metrics['status_breakdown'][$normalizedStatus])) {
                $metrics['status_breakdown'][$normalizedStatus]++;
            } else {
                $metrics['status_breakdown']['other']++;
            }

            // Retrieve Precomputed 1-to-1 Decision
            $decision = $decisions[$rawOrderId] ?? [
                'matched_order' => null,
                'match_status' => Shipment::MATCH_STATUS_UNMATCHED,
                'match_method' => null,
                'match_confidence' => 'unmatched',
                'match_reason' => "No decision precomputed",
                'candidate_order_ids' => [],
            ];

            $matchedOrder = $decision['matched_order'];
            $matchStatus = $decision['match_status'];
            $matchMethod = $decision['match_method'];
            $matchConfidence = $decision['match_confidence'];
            $matchReason = $decision['match_reason'];
            $candidateOrderIds = $decision['candidate_order_ids'];

            // Tally metrics
            if ($matchStatus === Shipment::MATCH_STATUS_MATCHED) {
                $metrics['matched_total']++;
                if ($matchMethod === 'exact_tracking') {
                    $metrics['matched_exact']++;
                } elseif ($matchConfidence === 'high') {
                    $metrics['matched_high']++;
                } else {
                    $metrics['matched_medium']++;
                }
                $matchedOrdersTracker[$matchedOrder->id] = true;
            } elseif ($matchStatus === Shipment::MATCH_STATUS_AMBIGUOUS) {
                $metrics['ambiguous']++;
            } else {
                $metrics['unmatched']++;
            }

            // Determine if shipment already exists in DB
            $existing = $existingShipments[$rawOrderId] ?? null;
            $shipmentAttributes = [
                'order_id' => $matchedOrder?->id,
                'provider' => self::PROVIDER,
                'external_tracking_number' => $rawOrderId,
                'external_reference' => $refId,
                'source' => 'ncm_csv',
                'source_file' => $fileName,
                'source_created_at' => $ncmCreated,
                'status' => $rawStatus,
                'normalized_status' => $normalizedStatus,
                'source_branch' => $sourceBranch,
                'destination_branch' => $destBranch,
                'receiver_name' => $receiverName,
                'receiver_phone' => $receiverPhone,
                'normalized_receiver_phone' => $normPhone,
                'cod_amount' => $codAmount,
                'delivery_charge' => $deliveryCharge,
                'package_description' => $pkgDesc,
                'remarks' => $remarks,
                'weight' => $weight,
                'delivered_at' => $ncmDelivered,
                'vendor_return' => $isVendorReturn,
                'created_by_source' => $createdBy,
                'match_status' => $matchStatus,
                'match_method' => $matchMethod,
                'match_confidence' => $matchConfidence,
                'match_reason' => $matchReason,
                'raw_metadata' => $data,
            ];

            if ($existing) {
                // Check if any attributes changed
                $changed = false;
                if (
                    $existing->status !== $rawStatus ||
                    $existing->normalized_status !== $normalizedStatus ||
                    $existing->delivered_at?->toDateTimeString() !== $ncmDelivered ||
                    $existing->order_id !== $matchedOrder?->id
                ) {
                    $changed = true;
                }

                if ($changed) {
                    $metrics['updated']++;
                } else {
                    $metrics['unchanged']++;
                }
            } else {
                $metrics['created']++;
            }

            // Apply Mutations Transactionally if in apply mode
            if ($apply) {
                $shipment = Shipment::updateOrCreate(
                    [
                        'provider' => self::PROVIDER,
                        'external_tracking_number' => $rawOrderId,
                    ],
                    $shipmentAttributes
                );

                // Synchronize matched Laijau Order non-destructively
                if ($matchedOrder) {
                    $orderUpdates = [];

                    if (empty($matchedOrder->carrier) || $matchedOrder->carrier !== 'Nepal Can Move (NCM)') {
                        $orderUpdates['carrier'] = 'Nepal Can Move (NCM)';
                        $orderUpdates['courier_name'] = 'Nepal Can Move (NCM)';
                    }
                    if (empty($matchedOrder->courier_order_id) || $matchedOrder->courier_order_id !== $rawOrderId) {
                        $orderUpdates['courier_order_id'] = $rawOrderId;
                    }
                    if ($matchedOrder->status !== 'cancelled' && (empty($matchedOrder->tracking_number) || $matchedOrder->tracking_number !== $rawOrderId)) {
                        $orderUpdates['tracking_number'] = $rawOrderId;
                    }
                    $trackingUrl = "https://nepalcanmove.com/track?tracking_id=" . urlencode($rawOrderId);
                    if (empty($matchedOrder->tracking_url) || $matchedOrder->tracking_url !== $trackingUrl) {
                        $orderUpdates['tracking_url'] = $trackingUrl;
                    }
                    if ($matchedOrder->courier_status !== $rawStatus) {
                        $orderUpdates['courier_status'] = $rawStatus;
                    }

                    // Delivery Date Synchronization
                    if ($normalizedStatus === Shipment::STATUS_DELIVERED && $ncmDelivered) {
                        $ncmDateString = Carbon::parse($ncmDelivered)->toDateString();
                        $currentDelDate = $matchedOrder->actual_delivery_date ? Carbon::parse($matchedOrder->actual_delivery_date)->toDateString() : null;
                        if ($currentDelDate !== $ncmDateString) {
                            $orderUpdates['actual_delivery_date'] = $ncmDateString;
                        }
                        $currentDelAt = $matchedOrder->delivered_at ? Carbon::parse($matchedOrder->delivered_at)->toDateTimeString() : null;
                        if ($currentDelAt !== $ncmDelivered) {
                            $orderUpdates['delivered_at'] = $ncmDelivered;
                        }
                        // Advance order status to delivered if currently pending / in_transit / processing
                        if (in_array($matchedOrder->status, ['processing', 'ready_for_dispatch', 'in_transit', 'shipped', 'handed_to_courier'], true)) {
                            $orderUpdates['status'] = Order::STATUS_DELIVERED;
                        }
                    }

                    if (!empty($orderUpdates)) {
                        $matchedOrder->update($orderUpdates);
                        $metrics['orders_synchronized']++;
                    }
                }

                // Log a LogisticsEvent for provenance if not already recorded
                $idempotencyKey = "ncm_sync_{$rawOrderId}_" . md5($rawStatus . ($ncmDelivered ?? ''));
                $eventExists = LogisticsEvent::where('idempotency_key', $idempotencyKey)->exists();
                if (!$eventExists) {
                    LogisticsEvent::create([
                        'order_id' => $matchedOrder?->id,
                        'provider' => self::PROVIDER,
                        'external_order_id' => $rawOrderId,
                        'event' => 'ncm.status_reconciled',
                        'status' => $rawStatus,
                        'payload' => $data,
                        'processing_status' => 'processed',
                        'idempotency_key' => $idempotencyKey,
                        'received_at' => $ncmCreated ?: now(),
                        'processed_at' => now(),
                    ]);
                }
            }

            // Build Matrix Row (28 Operational Columns)
            $matrixRow = [
                'ncm_order_id' => $rawOrderId,
                'source_row' => $rowNum,
                'ncm_created_date' => $data['Created Date'] ?? '',
                'receiver' => $receiverName ?? '',
                'receiver_phone' => $receiverPhone ?? '',
                'normalized_phone' => $normPhone,
                'cod_amount' => number_format($codAmount, 2, '.', ''),
                'delivery_charge' => number_format($deliveryCharge, 2, '.', ''),
                'raw_status' => $rawStatus,
                'normalized_status' => $normalizedStatus,
                'vendor_return' => $isVendorReturn ? 'TRUE' : 'FALSE',
                'source_branch' => $sourceBranch ?? '',
                'destination_branch' => $destBranch ?? '',
                'weight' => number_format($weight, 2, '.', ''),
                'delivered_date' => $data['Delivered Date'] ?? '',
                'laijau_order_id' => $matchedOrder?->id ?? '',
                'laijau_order_number' => $matchedOrder?->order_number ?? '',
                'laijau_order_total' => $matchedOrder ? number_format((float)$matchedOrder->total_amount, 2, '.', '') : '',
                'laijau_order_status' => $matchedOrder?->status ?? '',
                'match_status' => $matchStatus,
                'match_method' => $matchMethod ?? '',
                'match_confidence' => $matchConfidence ?? '',
                'match_reason' => $matchReason ?? '',
                'candidate_orders' => implode(';', $candidateOrderIds),
                'package_description' => $pkgDesc ?? '',
                'remarks' => $remarks ?? '',
                'source_file' => $fileName,
                'import_batch_id' => $batchId,
            ];
            $reconciliationMatrix[] = $matrixRow;

            // Manual Review Queue
            if ($matchStatus === Shipment::MATCH_STATUS_AMBIGUOUS || $matchStatus === Shipment::MATCH_STATUS_UNMATCHED) {
                $manualReviewRows[] = $matrixRow;
            }
        }

        // Clean up orphan NCM carrier fields on orders that do not have a confirmed 1-to-1 match
        if ($apply && !empty($claimedOrders)) {
            Order::where('carrier', 'Nepal Can Move (NCM)')
                ->whereNotIn('id', array_keys($claimedOrders))
                ->update([
                    'carrier' => null,
                    'courier_name' => null,
                    'courier_order_id' => null,
                    'tracking_number' => null,
                    'tracking_url' => null,
                    'courier_status' => null,
                ]);
        }

        // 4. Export Artifacts
        $this->exportArtifacts($metrics, $reconciliationMatrix, $manualReviewRows);

        return $metrics;
    }

    /**
     * Export the 3 required reconciliation artifacts.
     */
    protected function exportArtifacts(array $metrics, array $matrix, array $manualReview): void
    {
        $dir = storage_path('app/migration');
        if (!File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        // 1. JSON Report
        File::put(
            "{$dir}/ncm_reconciliation_report.json",
            json_encode($metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        // 2. CSV Matrix (28 Columns)
        if (!empty($matrix)) {
            $csvFh = fopen("{$dir}/ncm_reconciliation_matrix.csv", 'w');
            fputcsv($csvFh, array_keys($matrix[0]));
            foreach ($matrix as $row) {
                fputcsv($csvFh, $row);
            }
            fclose($csvFh);
        }

        // 3. Manual Review CSV
        if (!empty($manualReview)) {
            $revFh = fopen("{$dir}/ncm_manual_review_required.csv", 'w');
            fputcsv($revFh, array_keys($manualReview[0]));
            foreach ($manualReview as $row) {
                fputcsv($revFh, $row);
            }
            fclose($revFh);
        }
    }

    /**
     * Manually link an ambiguous or unmatched NCM shipment to a Laijau order.
     */
    public function manuallyLinkShipment(Shipment $shipment, Order $order, int $userId, ?string $reason = null): void
    {
        DB::transaction(function () use ($shipment, $order, $userId, $reason) {
            $shipment->update([
                'order_id' => $order->id,
                'match_status' => Shipment::MATCH_STATUS_MANUAL,
                'match_method' => 'manual',
                'match_confidence' => 'manual',
                'match_reason' => $reason ?: "Manually linked by staff User #{$userId}",
                'matched_by' => $userId,
                'matched_at' => now(),
            ]);

            $orderUpdates = [
                'carrier' => 'Nepal Can Move (NCM)',
                'courier_name' => 'Nepal Can Move (NCM)',
                'courier_order_id' => $shipment->external_tracking_number,
                'tracking_number' => $shipment->external_tracking_number,
                'tracking_url' => $shipment->tracking_url,
                'courier_status' => $shipment->status,
            ];

            if ($shipment->isDelivered() && $shipment->delivered_at) {
                if (empty($order->actual_delivery_date)) {
                    $orderUpdates['actual_delivery_date'] = $shipment->delivered_at->toDateString();
                }
                if (empty($order->delivered_at)) {
                    $orderUpdates['delivered_at'] = $shipment->delivered_at;
                }
                if (in_array($order->status, ['processing', 'ready_for_dispatch', 'in_transit', 'shipped', 'handed_to_courier'], true)) {
                    $orderUpdates['status'] = Order::STATUS_DELIVERED;
                }
            }

            $order->update($orderUpdates);
        });
    }
}
