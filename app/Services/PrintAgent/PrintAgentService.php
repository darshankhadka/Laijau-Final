<?php

namespace App\Services\PrintAgent;

use App\Models\Hardware\PosDiscoveredPrinter;
use App\Models\Hardware\PosHardwareAuditLog;
use App\Models\Hardware\PosPrinter;
use App\Models\Hardware\PosStation;
use App\Models\OfflineSale;
use App\Models\Order;
use App\Models\PosPrintJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PrintAgentService
{
    /**
     * Resolve the active showroom station (one central showroom station).
     */
    public function getShowroomStation(?string $stationCode = null): ?PosStation
    {
        if ($stationCode) {
            $station = PosStation::where('code', $stationCode)->first();
            if ($station) {
                return $station;
            }
        }

        return PosStation::where('is_active', true)->first()
            ?? PosStation::where('code', config('services.print_agent.default_station', 'showroom_counter_1'))->first()
            ?? PosStation::first();
    }

    /**
     * Enqueue an internal 80mm thermal receipt print job for a completed POS OfflineSale.
     * Works seamlessly regardless of which device (desktop, tablet, mobile) recorded the sale.
     * Guaranteed idempotent: calling multiple times for the same sale returns the existing job.
     */
    public function queueOfflineSaleReceipt(OfflineSale $sale, ?string $stationId = null): ?PosPrintJob
    {
        $idempotencyKey = 'pos_sale_' . $sale->id . '_receipt';

        // Check if job already exists (strict idempotency)
        $existing = PosPrintJob::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing;
        }

        $station = $this->getShowroomStation($stationId);
        $stationCode = $station ? $station->code : 'showroom_counter_1';

        // Check if POS sale auto-printing is enabled
        if ($station && !$station->auto_print_pos_sale && !$station->auto_print_receipt) {
            return null;
        }

        $printer = $station?->receiptPrinter;
        $paperCols = ($printer && $printer->paper_width_mm === 58) ? 32 : 48;
        $escposBuilder = EscPosReceiptBuilder::buildFromOfflineSale($sale);

        $payloadData = [
            'sale_id' => $sale->id,
            'sale_number' => $sale->sale_number,
            'customer_name' => $sale->customer_name,
            'total_amount' => (float)$sale->total_amount,
            'payment_method' => (string)$sale->payment_method,
            'items_count' => $sale->items->count(),
            'station_code' => $stationCode,
            'printer' => $printer ? $printer->toHardwareConfig() : null,
            'created_at' => now()->toIso8601String(),
        ];

        return PosPrintJob::create([
            'uuid' => (string) Str::uuid(),
            'station_id' => $stationCode,
            'printer_id' => $printer?->id,
            'source_type' => 'offline_sale',
            'source_id' => $sale->id,
            'idempotency_key' => $idempotencyKey,
            'job_type' => 'pos_receipt',
            'status' => 'queued',
            'payload_data' => $payloadData,
            'raw_escpos_base64' => $escposBuilder->getBase64(),
            'attempts' => 0,
            'max_attempts' => 3,
        ]);
    }

    /**
     * Enqueue an internal 80mm fulfillment receipt print job for an Online Order.
     * Guaranteed idempotent: calling multiple times for the same order returns the existing job.
     */
    public function queueOnlineOrderReceipt(Order $order, ?string $stationId = null): ?PosPrintJob
    {
        $idempotencyKey = 'online_order_' . $order->id . '_receipt';

        // Strict idempotency
        $existing = PosPrintJob::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing;
        }

        $station = $this->getShowroomStation($stationId);
        $stationCode = $station ? $station->code : 'showroom_counter_1';

        // Check if online order auto-printing is enabled
        if ($station && !$station->auto_print_online_order) {
            return null;
        }

        $printer = $station?->receiptPrinter;
        $paperCols = ($printer && $printer->paper_width_mm === 58) ? 32 : 48;
        $escposBuilder = EscPosReceiptBuilder::buildFromOnlineOrder($order, $paperCols);

        $payloadData = [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'customer_name' => trim($order->first_name . ' ' . $order->last_name),
            'customer_phone' => (string) $order->phone,
            'total_amount' => (float)$order->total_amount,
            'payment_method' => (string)$order->payment_method,
            'shipping_city' => (string)($order->shipping_city ?: 'Kathmandu'),
            'station_code' => $stationCode,
            'printer' => $printer ? $printer->toHardwareConfig() : null,
            'created_at' => now()->toIso8601String(),
        ];

        return PosPrintJob::create([
            'uuid' => (string) Str::uuid(),
            'station_id' => $stationCode,
            'printer_id' => $printer?->id,
            'source_type' => 'online_order',
            'source_id' => $order->id,
            'idempotency_key' => $idempotencyKey,
            'job_type' => 'online_order',
            'status' => 'queued',
            'payload_data' => $payloadData,
            'raw_escpos_base64' => $escposBuilder->getBase64(),
            'attempts' => 0,
            'max_attempts' => 3,
        ]);
    }

    /**
     * Enqueue a warehouse packing slip print job for an Online Order.
     */
    public function queueOnlineOrderPackingSlip(Order $order, ?string $stationId = null): ?PosPrintJob
    {
        $idempotencyKey = 'online_order_' . $order->id . '_packing_slip';

        $existing = PosPrintJob::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing;
        }

        $station = $this->getShowroomStation($stationId);
        $stationCode = $station ? $station->code : 'showroom_counter_1';

        if ($station && !$station->auto_print_packing_slip) {
            return null;
        }

        $printer = $station?->labelPrinter ?? $station?->receiptPrinter;
        $paperCols = ($printer && $printer->paper_width_mm === 58) ? 32 : 48;
        $escposBuilder = EscPosReceiptBuilder::buildPackingSlipFromOnlineOrder($order, $paperCols);

        $payloadData = [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'type' => 'packing_slip',
            'station_code' => $stationCode,
            'printer' => $printer ? $printer->toHardwareConfig() : null,
            'created_at' => now()->toIso8601String(),
        ];

        return PosPrintJob::create([
            'uuid' => (string) Str::uuid(),
            'station_id' => $stationCode,
            'printer_id' => $printer?->id,
            'source_type' => 'online_order',
            'source_id' => $order->id,
            'idempotency_key' => $idempotencyKey,
            'job_type' => 'packing_slip',
            'status' => 'queued',
            'payload_data' => $payloadData,
            'raw_escpos_base64' => $escposBuilder->getBase64(),
            'attempts' => 0,
            'max_attempts' => 3,
        ]);
    }

    /**
     * Enqueue an on-demand reprint job for an OfflineSale (explicit cashier action).
     */
    public function reprintOfflineSaleReceipt(OfflineSale $sale, ?string $stationId = null): PosPrintJob
    {
        $station = $this->getShowroomStation($stationId);
        $stationCode = $station ? $station->code : 'showroom_counter_1';
        $printer = $station?->receiptPrinter;

        $idempotencyKey = 'pos_sale_' . $sale->id . '_reprint_' . time() . '_' . Str::random(4);
        $escposBuilder = EscPosReceiptBuilder::buildFromOfflineSale($sale);

        $payloadData = [
            'sale_id' => $sale->id,
            'sale_number' => $sale->sale_number,
            'reprint' => true,
            'total_amount' => (float)$sale->total_amount,
            'station_code' => $stationCode,
            'printer' => $printer ? $printer->toHardwareConfig() : null,
            'requested_at' => now()->toIso8601String(),
        ];

        return PosPrintJob::create([
            'uuid' => (string) Str::uuid(),
            'station_id' => $stationCode,
            'printer_id' => $printer?->id,
            'source_type' => 'offline_sale',
            'source_id' => $sale->id,
            'idempotency_key' => $idempotencyKey,
            'job_type' => 'reprint',
            'status' => 'queued',
            'payload_data' => $payloadData,
            'raw_escpos_base64' => $escposBuilder->getBase64(),
            'attempts' => 0,
            'max_attempts' => 3,
        ]);
    }

    /**
     * Atomically claim the next queued print job for a station.
     */
    public function pollNextJob(string $stationId): ?PosPrintJob
    {
        return DB::transaction(function () use ($stationId) {
            $job = PosPrintJob::where('station_id', $stationId)
                ->where('status', 'queued')
                ->where('attempts', '<', 3)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->first();

            if (!$job) {
                return null;
            }

            // Ensure printer hardware parameters are attached to payload
            if (!$job->printer_id) {
                $station = PosStation::where('code', $stationId)->first();
                if ($station && $station->receiptPrinter) {
                    $job->printer_id = $station->receiptPrinter->id;
                    $payload = $job->payload_data ?? [];
                    if (empty($payload['printer'])) {
                        $payload['printer'] = $station->receiptPrinter->toHardwareConfig();
                        $job->payload_data = $payload;
                    }
                }
            } elseif ($job->printer_id) {
                $printer = PosPrinter::find($job->printer_id);
                if ($printer) {
                    $payload = $job->payload_data ?? [];
                    $payload['printer'] = $printer->toHardwareConfig();
                    $job->payload_data = $payload;
                }
            }

            $job->claimed_at = now();
            $job->markAsPrinting();
            return $job;
        });
    }

    /**
     * Mark a print job as finished (printed or failed) and update station/printer status.
     */
    public function completeJob(string $uuid, bool $success, ?string $error = null): bool
    {
        $job = PosPrintJob::where('uuid', $uuid)->first();
        if (!$job) {
            return false;
        }

        $now = now();
        $station = PosStation::where('code', $job->station_id)->first();
        $printer = $job->printer_id ? PosPrinter::find($job->printer_id) : null;

        if ($success) {
            $updated = $job->markAsPrinted();

            if ($station) {
                $station->update([
                    'last_successful_print_at' => $now,
                    'last_error_message' => null,
                ]);
            }

            if ($printer) {
                $printer->update([
                    'last_test_printed_at' => $now,
                    'last_test_status' => 'success',
                    'last_test_error' => null,
                ]);
            }

            return $updated;
        }

        $updated = $job->markAsFailed($error);

        if ($station) {
            $station->update([
                'last_failed_print_at' => $now,
                'last_error_message' => $error,
            ]);
        }

        if ($printer) {
            $printer->update([
                'last_test_status' => 'failed',
                'last_test_error' => $error,
            ]);
        }

        return $updated;
    }

    /**
     * Retry a failed job.
     */
    public function retryJob(PosPrintJob $job): bool
    {
        return $job->update([
            'status' => 'queued',
            'attempts' => 0,
            'last_error' => null,
            'claimed_at' => null,
            'printed_at' => null,
        ]);
    }

    /**
     * Cancel a queued or printing job.
     */
    public function cancelJob(PosPrintJob $job): bool
    {
        return $job->update([
            'status' => 'cancelled',
            'last_error' => 'Cancelled by administrator.',
        ]);
    }

    /**
     * Enqueue a connection/hardware test job for a station.
     */
    public function createTestJob(string $stationId = 'showroom_counter_1'): PosPrintJob
    {
        $idempotencyKey = 'test_job_' . $stationId . '_' . microtime(true);
        $station = $this->getShowroomStation($stationId);
        $printer = $station?->receiptPrinter;

        $builder = EscPosReceiptBuilder::buildTestReceipt($station ? $station->name : $stationId);

        return PosPrintJob::create([
            'uuid' => (string) Str::uuid(),
            'station_id' => $station ? $station->code : $stationId,
            'printer_id' => $printer?->id,
            'source_type' => 'test',
            'source_id' => null,
            'idempotency_key' => $idempotencyKey,
            'job_type' => 'test_receipt',
            'status' => 'queued',
            'payload_data' => [
                'type' => 'hardware_test',
                'station_id' => $stationId,
                'printer' => $printer ? $printer->toHardwareConfig() : null,
                'timestamp' => now()->toIso8601String(),
            ],
            'raw_escpos_base64' => $builder->getBase64(),
            'attempts' => 0,
            'max_attempts' => 2,
        ]);
    }

    /**
     * Enqueue a test print job specifically for a chosen PosPrinter.
     */
    public function createPrinterTestJob(PosPrinter $printer): PosPrintJob
    {
        $station = $printer->station ?? $this->getShowroomStation();
        $stationCode = $station ? $station->code : 'showroom_counter_1';
        $stationName = $station ? $station->name : 'Showroom Counter 1';

        $connDetails = $printer->connection_type === 'network'
            ? "LAN TCP: {$printer->network_ip}:{$printer->network_port}"
            : "USB Device: {$printer->usb_device_path}";

        $paperCols = $printer->paper_width_mm === 58 ? 32 : 48;
        $builder = EscPosReceiptBuilder::buildPrinterTestReceipt($stationName, $printer->name, $connDetails, $paperCols);

        $idempotencyKey = 'printer_test_' . $printer->id . '_' . microtime(true);

        $job = PosPrintJob::create([
            'uuid' => (string) Str::uuid(),
            'station_id' => $stationCode,
            'printer_id' => $printer->id,
            'source_type' => 'printer_test',
            'source_id' => $printer->id,
            'idempotency_key' => $idempotencyKey,
            'job_type' => 'printer_test',
            'status' => 'queued',
            'payload_data' => [
                'type' => 'printer_test',
                'printer_id' => $printer->id,
                'printer' => $printer->toHardwareConfig(),
                'timestamp' => now()->toIso8601String(),
            ],
            'raw_escpos_base64' => $builder->getBase64(),
            'attempts' => 0,
            'max_attempts' => 2,
        ]);

        PosHardwareAuditLog::record(
            'pos_printer',
            $printer->id,
            $printer->name,
            'test_print_requested',
            null,
            null,
            null,
            ['job_uuid' => $job->uuid, 'connection' => $connDetails]
        );

        return $job;
    }

    /**
     * Pair a station with a local print agent using the 10-minute pairing code.
     */
    public function pairStation(string $pairingCode, string $hostname, ?string $os = null, ?string $version = null): ?array
    {
        $station = PosStation::where('pairing_code', trim($pairingCode))
            ->where('pairing_expires_at', '>', now())
            ->first();

        if (!$station) {
            return null;
        }

        $rawToken = $station->pairWithAgent($hostname, $os, $version);

        PosHardwareAuditLog::record(
            'pos_station',
            $station->id,
            $station->name,
            'agent_paired',
            null,
            null,
            null,
            [
                'hostname' => $hostname,
                'os' => $os,
                'agent_version' => $version,
            ]
        );

        return [
            'station' => $station,
            'device_token' => $rawToken,
            'hardware_config' => $this->getStationHardwareConfig($station),
        ];
    }

    /**
     * Record heartbeat telemetry from local print agent.
     */
    public function recordHeartbeat(PosStation $station, array $telemetry = []): void
    {
        $update = [
            'last_heartbeat_at' => now(),
        ];

        if (!empty($telemetry['hostname'])) {
            $update['last_seen_hostname'] = (string) $telemetry['hostname'];
        }
        if (!empty($telemetry['os'])) {
            $update['last_seen_os'] = (string) $telemetry['os'];
        }
        if (!empty($telemetry['agent_version'])) {
            $update['last_seen_agent_version'] = (string) $telemetry['agent_version'];
        }

        $station->update($update);
    }

    /**
     * Record printers discovered by the local agent on the showroom LAN.
     */
    public function recordDiscoveredPrinters(PosStation $station, array $printersList): int
    {
        $count = 0;
        foreach ($printersList as $p) {
            if (empty($p['network_ip']) && empty($p['usb_device_path'])) {
                continue;
            }

            PosDiscoveredPrinter::updateOrCreate(
                [
                    'station_id' => $station->id,
                    'network_ip' => $p['network_ip'] ?? null,
                    'network_port' => $p['network_port'] ?? 9100,
                    'usb_device_path' => $p['usb_device_path'] ?? null,
                ],
                [
                    'name' => $p['name'] ?? 'Discovered ESC/POS Thermal',
                    'connection_type' => $p['connection_type'] ?? 'network',
                    'status' => $p['status'] ?? 'available',
                    'last_seen_at' => now(),
                ]
            );
            $count++;
        }

        return $count;
    }

    /**
     * Get authoritative station hardware configuration for local agent caching.
     */
    public function getStationHardwareConfig(PosStation $station): array
    {
        return [
            'station_id' => $station->code,
            'station_name' => $station->name,
            'location' => $station->location,
            'is_active' => (bool) $station->is_active,
            'auto_print_receipt' => (bool) ($station->auto_print_pos_sale ?? $station->auto_print_receipt),
            'print_rules' => [
                'pos_sale' => (bool) ($station->auto_print_pos_sale ?? $station->auto_print_receipt),
                'online_order' => (bool) $station->auto_print_online_order,
                'packing_slip' => (bool) $station->auto_print_packing_slip,
                'returns' => (bool) $station->auto_print_returns,
            ],
            'barcode_scanner' => [
                'enabled' => (bool) $station->barcode_scanner_enabled,
                'burst_threshold_ms' => (int) $station->barcode_scan_burst_threshold_ms,
                'min_length' => (int) $station->barcode_min_length,
                'max_length' => (int) $station->barcode_max_length,
                'ignore_keyboard_typing' => (bool) $station->barcode_ignore_keyboard_typing,
                'global_listener' => (bool) $station->barcode_global_listener,
            ],
            'printers' => $station->printers()->where('is_active', true)->get()->map(fn($p) => $p->toHardwareConfig())->values()->all(),
            'default_receipt_printer' => $station->receiptPrinter?->toHardwareConfig(),
            'default_label_printer' => $station->labelPrinter?->toHardwareConfig(),
            'config_version' => $station->updated_at?->timestamp ?? time(),
        ];
    }

    /**
     * Get count of pending and printing jobs for a station.
     */
    public function getStationQueueStats(string $stationId): array
    {
        return [
            'queued' => PosPrintJob::where('station_id', $stationId)->where('status', 'queued')->count(),
            'printing' => PosPrintJob::where('station_id', $stationId)->where('status', 'printing')->count(),
            'printed_today' => PosPrintJob::where('station_id', $stationId)->where('status', 'printed')->whereDate('printed_at', today())->count(),
            'failed_today' => PosPrintJob::where('station_id', $stationId)->where('status', 'failed')->whereDate('updated_at', today())->count(),
        ];
    }

    /**
     * System-wide hardware overview metrics for the Admin Dashboard.
     */
    public function getSystemHardwareSummary(): array
    {
        $stations = PosStation::with(['printers', 'receiptPrinter'])->get();
        $printers = PosPrinter::all();

        $onlineAgents = $stations->filter(fn($s) => $s->isOnline())->count();
        $offlineAgents = $stations->count() - $onlineAgents;

        $lastJob = PosPrintJob::where('status', 'printed')->latest('printed_at')->first();

        return [
            'total_stations' => $stations->count(),
            'online_agents' => $onlineAgents,
            'offline_agents' => $offlineAgents,
            'total_printers' => $printers->count(),
            'active_printers' => $printers->where('is_active', true)->count(),
            'receipt_printers' => $printers->where('role', 'receipt')->count(),
            'label_printers' => $printers->where('role', 'label')->count(),
            'scanners_configured' => $stations->where('barcode_scanner_enabled', true)->count(),
            'queued_jobs' => PosPrintJob::where('status', 'queued')->count(),
            'printing_jobs' => PosPrintJob::where('status', 'printing')->count(),
            'failed_jobs' => PosPrintJob::where('status', 'failed')->count(),
            'printed_today' => PosPrintJob::where('status', 'printed')->whereDate('printed_at', today())->count(),
            'last_print' => $lastJob ? [
                'time' => $lastJob->printed_at?->diffForHumans() ?? 'Recently',
                'station' => $lastJob->station_id,
                'job_type' => $lastJob->job_type,
            ] : null,
        ];
    }
}
