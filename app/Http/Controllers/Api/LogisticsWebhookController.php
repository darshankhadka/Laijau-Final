<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Logistics\LogisticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogisticsWebhookController extends Controller
{
    /**
     * Handle incoming NCM (Nepal Can Move) Webhook
     */
    public function handleNcm(Request $request, LogisticsService $logisticsService): JsonResponse
    {
        $payload = $request->all();

        // Secret verification if configured
        $configuredSecret = config('services.ncm.webhook_secret');
        if (!empty($configuredSecret)) {
            $incomingSecret = $request->header('X-NCM-Webhook-Secret') ?? $request->header('X-Webhook-Secret') ?? $request->query('secret');
            if (empty($incomingSecret) || !hash_equals((string)$configuredSecret, (string)$incomingSecret)) {
                Log::warning('Unauthorized NCM webhook attempt: Invalid secret token.');
                return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
            }
        }

        if (empty($payload)) {
            return response()->json(['status' => 'error', 'message' => 'Empty payload'], 400);
        }

        $result = $logisticsService->processWebhook('ncm', $payload, $request->headers->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Webhook received and processed successfully',
            'data' => $result,
        ], 200);
    }

    /**
     * Handle incoming Pathao Parcel Webhook
     */
    public function handlePathao(Request $request, LogisticsService $logisticsService): JsonResponse
    {
        $payload = $request->all();

        // Secret verification if configured
        $configuredSecret = config('services.pathao.webhook_secret');
        if (!empty($configuredSecret)) {
            $incomingSecret = $request->header('X-Pathao-Signature') ?? $request->header('X-Webhook-Secret') ?? $request->query('secret');
            if (empty($incomingSecret) || !hash_equals((string)$configuredSecret, (string)$incomingSecret)) {
                Log::warning('Unauthorized Pathao webhook attempt: Invalid secret token.');
                return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
            }
        }

        if (empty($payload)) {
            return response()->json(['status' => 'error', 'message' => 'Empty payload'], 400);
        }

        $result = $logisticsService->processWebhook('pathao', $payload, $request->headers->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Pathao webhook processed',
            'data' => $result,
        ], 200);
    }
}
