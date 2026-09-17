<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Operational\HealthCheckService;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __construct(
        protected HealthCheckService $healthService
    ) {}

    /**
     * Return comprehensive system health status.
     */
    public function status(): JsonResponse
    {
        $report = $this->healthService->check();
        $statusCode = ($report['status'] === 'healthy') ? 200 : 503;

        return response()->json($report, $statusCode);
    }
}
