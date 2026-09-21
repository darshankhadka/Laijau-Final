<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hardware\PosStation;
use App\Services\PrintAgent\PrintAgentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrintAgentController extends Controller
{
    protected PrintAgentService $printService;

    public function __construct(PrintAgentService $printService)
    {
        $this->printService = $printService;
    }

    /**
     * Authenticate incoming agent request using either station-specific hashed token or pre-shared key.
     */
    protected function authenticateAgent(Request $request): ?JsonResponse
    {
        $providedToken = $request->header('X-Print-Agent-Key')
            ?: $request->bearerToken()
            ?: $request->query('token');

        if (empty($providedToken)) {
            return response()->json([
                'error' => 'Unauthorized print agent. Valid X-Print-Agent-Key or Bearer token required.',
            ], 401);
        }

        // 1. Check dynamic station-specific tokens (SHA-256 hashed)
        $hashedToken = hash('sha256', (string) $providedToken);
        $station = PosStation::where('device_token_hash', $hashedToken)->first();

        // 2. Check legacy / bootstrap master token fallback
        if (!$station) {
            $configuredToken = (string) config('services.print_agent.token');
            if (empty($configuredToken)) {
                $configuredToken = 'laijau_showroom_counter_1_secure_key';
            }

            if (hash_equals($configuredToken, (string) $providedToken)) {
                $reqStationId = (string) ($request->input('station_id') ?: config('services.print_agent.default_station', 'showroom_counter_1'));
                $station = PosStation::where('code', $reqStationId)->first() ?? PosStation::where('is_active', true)->first();
            }
        }

        if (!$station) {
            return response()->json([
                'error' => 'Unauthorized print agent. Invalid authentication credentials.',
            ], 401);
        }

        if (!$station->is_active) {
            return response()->json([
                'error' => 'POS station is currently disabled.',
            ], 403);
        }

        // Validate station ownership if explicit station_id requested
        $requestedStationId = $request->input('station_id');
        if ($requestedStationId && $requestedStationId !== $station->code) {
            // If authenticated with station-specific token, reject cross-station access
            if ($station->device_token_hash === $hashedToken) {
                return response()->json([
                    'error' => "Unauthorized: Token belongs to station '{$station->code}', cannot access '{$requestedStationId}'.",
                ], 403);
            }
        }

        $request->attributes->set('authenticated_station', $station);
        return null;
    }

    /**
     * Helper to get authenticated station from request attributes.
     */
    protected function getStation(Request $request): PosStation
    {
        $station = $request->attributes->get('authenticated_station');
        if ($station instanceof PosStation) {
            return $station;
        }

        $stationCode = (string) ($request->input('station_id') ?: config('services.print_agent.default_station', 'showroom_counter_1'));
        return PosStation::where('code', $stationCode)->firstOrFail();
    }

    /**
     * POST /api/v1/print-agent/pair
     * Pair a new showroom PC agent using an admin-generated 10-minute pairing code.
     */
    public function pair(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pairing_code' => 'required|string|min:6|max:30',
            'hostname' => 'nullable|string|max:120',
            'os' => 'nullable|string|max:120',
            'agent_version' => 'nullable|string|max:50',
        ]);

        $result = $this->printService->pairStation(
            $validated['pairing_code'],
            $validated['hostname'] ?? 'Unknown Showroom Host',
            $validated['os'] ?? null,
            $validated['agent_version'] ?? '2.0.0'
        );

        if (!$result) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid or expired pairing code. Please generate a new pairing code from Admin Settings.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => "Print Agent successfully paired to station '{$result['station']->name}'.",
            'station_id' => $result['station']->code,
            'station_name' => $result['station']->name,
            'device_token' => $result['device_token'],
            'hardware_config' => $result['hardware_config'],
        ]);
    }

    /**
     * GET /api/v1/print-agent/config
     * Returns authoritative hardware configuration for local agent caching and offline resilience.
     */
    public function config(Request $request): JsonResponse
    {
        if ($authError = $this->authenticateAgent($request)) {
            return $authError;
        }

        $station = $this->getStation($request);
        $config = $this->printService->getStationHardwareConfig($station);

        return response()->json([
            'success' => true,
            'station_id' => $station->code,
            'hardware_config' => $config,
        ]);
    }

    /**
     * POST /api/v1/print-agent/heartbeat
     * Periodic agent telemetry heartbeat.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        if ($authError = $this->authenticateAgent($request)) {
            return $authError;
        }

        $station = $this->getStation($request);
        $this->printService->recordHeartbeat($station, $request->all());

        return response()->json([
            'status' => 'ok',
            'station_id' => $station->code,
            'server_time' => now()->toIso8601String(),
            'config_version' => $station->updated_at?->timestamp ?? time(),
        ]);
    }

    /**
     * GET /api/v1/print-agent/health
     */
    public function health(Request $request): JsonResponse
    {
        if ($authError = $this->authenticateAgent($request)) {
            return $authError;
        }

        $station = $this->getStation($request);
        $stats = $this->printService->getStationQueueStats($station->code);

        return response()->json([
            'status' => 'online',
            'station_id' => $station->code,
            'station_name' => $station->name,
            'server_time' => now()->toIso8601String(),
            'queue' => $stats,
        ]);
    }

    /**
     * GET /api/v1/print-agent/jobs/poll
     * Atomically claims the next queued job for the showroom station.
     */
    public function poll(Request $request): JsonResponse
    {
        if ($authError = $this->authenticateAgent($request)) {
            return $authError;
        }

        $station = $this->getStation($request);
        $job = $this->printService->pollNextJob($station->code);

        if (!$job) {
            return response()->json([
                'has_job' => false,
                'job' => null,
            ]);
        }

        $payload = $job->payload_data ?? [];
        $printerConfig = $payload['printer'] ?? ($job->printer?->toHardwareConfig() ?? $station->receiptPrinter?->toHardwareConfig());

        return response()->json([
            'has_job' => true,
            'job' => [
                'uuid' => $job->uuid,
                'job_type' => $job->job_type,
                'station_id' => $job->station_id,
                'idempotency_key' => $job->idempotency_key,
                'raw_escpos_base64' => $job->raw_escpos_base64,
                'payload_data' => $payload,
                'printer' => $printerConfig,
                'attempts' => $job->attempts,
                'created_at' => $job->created_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * POST /api/v1/print-agent/jobs/{uuid}/status
     * Reports completion or failure of a specific print job.
     */
    public function updateStatus(Request $request, string $uuid): JsonResponse
    {
        if ($authError = $this->authenticateAgent($request)) {
            return $authError;
        }

        $validated = $request->validate([
            'status' => 'required|in:printed,failed',
            'error' => 'nullable|string|max:1000',
        ]);

        $success = $validated['status'] === 'printed';
        $error = $validated['error'] ?? null;

        $updated = $this->printService->completeJob($uuid, $success, $error);

        if (!$updated) {
            return response()->json([
                'success' => false,
                'error' => 'Print job not found or invalid UUID.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'uuid' => $uuid,
            'status' => $validated['status'],
        ]);
    }

    /**
     * POST /api/v1/print-agent/test-job
     * Enqueues an on-demand hardware test receipt to verify printer connection.
     */
    public function testJob(Request $request): JsonResponse
    {
        if ($authError = $this->authenticateAgent($request)) {
            return $authError;
        }

        $station = $this->getStation($request);
        $job = $this->printService->createTestJob($station->code);

        return response()->json([
            'success' => true,
            'message' => 'Test print job enqueued successfully.',
            'job_uuid' => $job->uuid,
            'station_id' => $station->code,
        ]);
    }

    /**
     * POST /api/v1/print-agent/discovered-printers
     * Receives local network and USB printer discovery reports from the Showroom Print Agent.
     */
    public function reportDiscoveredPrinters(Request $request): JsonResponse
    {
        if ($authError = $this->authenticateAgent($request)) {
            return $authError;
        }

        $station = $this->getStation($request);
        $printers = $request->input('printers', []);

        $count = $this->printService->recordDiscoveredPrinters($station, (array) $printers);

        return response()->json([
            'success' => true,
            'message' => "Recorded {$count} discovered printer(s) on showroom network.",
            'station_id' => $station->code,
        ]);
    }
}

