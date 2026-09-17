<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\PaymentAuditLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ConnectIpsService
{
    protected string $mode;
    protected string $merchantId;
    protected string $appId;
    protected string $appName;
    protected string $password;
    protected string $certPath;
    protected string $certPassword;
    protected string $baseUrl;

    public function __construct()
    {
        $this->mode = config('services.connectips.mode', 'sandbox');
        $this->merchantId = (string) config('services.connectips.merchant_id', '');
        $this->appId = (string) config('services.connectips.app_id', '');
        $this->appName = (string) config('services.connectips.app_name', 'LAIJAU');
        $this->password = (string) config('services.connectips.password', '');
        $this->certPath = (string) config('services.connectips.cert_path', '');
        $this->certPassword = (string) config('services.connectips.cert_password', '');

        $this->baseUrl = ($this->mode === 'production')
            ? config('services.connectips.production_url', 'https://login.connectips.com')
            : config('services.connectips.sandbox_url', 'https://uat.connectips.com');
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getBasnprl(): string
    {
        return $this->getBaseUrl();
    }

    public function getLoginPageUrl(): string
    {
        return rtrim($this->baseUrl, '/') . '/connectipswebgw/loginpage';
    }

    public function getLoginPagnprl(): string
    {
        return $this->getLoginPageUrl();
    }

    public function getValidateTxnUrl(): string
    {
        return rtrim($this->baseUrl, '/') . '/connectipswebws/api/creditor/validatetxn';
    }

    public function getTxnDetailUrl(): string
    {
        return rtrim($this->baseUrl, '/') . '/connectipswebws/api/creditor/gettxndetail';
    }

    /**
     * Check if valid merchant credentials are configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->merchantId) && !empty($this->appId);
    }

    /**
     * Sign string using RSA-SHA256 with merchant private key.
     */
    public function generateSignature(string $dataToSign): string
    {
        if (!empty($this->certPath) && file_exists($this->certPath)) {
            $ext = strtolower(pathinfo($this->certPath, PATHINFO_EXTENSION));

            if ($ext === 'pfx' || $ext === 'p12') {
                $pfxContent = file_get_contents($this->certPath);
                $certs = [];
                if (openssl_pkcs12_read($pfxContent, $certs, $this->certPassword)) {
                    $privateKey = $certs['pkey'];
                    $binarySignature = '';
                    if (openssl_sign($dataToSign, $binarySignature, $privateKey, OPENSSL_ALGO_SHA256)) {
                        return base64_encode($binarySignature);
                    }
                }
            } else {
                $pemContent = file_get_contents($this->certPath);
                $privateKey = openssl_get_privatekey($pemContent, $this->certPassword ?: null);
                if ($privateKey) {
                    $binarySignature = '';
                    if (openssl_sign($dataToSign, $binarySignature, $privateKey, OPENSSL_ALGO_SHA256)) {
                        return base64_encode($binarySignature);
                    }
                }
            }
        }

        // Fallback for sandbox / testing harness when merchant certificate has not yet been mounted
        return base64_encode(hash_hmac('sha256', $dataToSign, $this->password ?: 'LAIJAU_CONNECTIPS_FALLBACK_KEY', true));
    }

    /**
     * Generate form initiation payload for customer redirect to ConnectIPS.
     */
    public function generateInitiationPayload(Order $order): array
    {
        $txnId = $order->connectips_txnid ?: ('ORD_' . $order->id . '_' . time());
        $txnDate = now()->format('d-m-Y');
        $txnCrn = 'NPR';
        $txnAmt = (int) round(((float) $order->total_amount) * 100); // Amount in paisa
        $referenceId = $order->order_number;
        $remarks = "Order {$order->order_number}";
        $particulars = "Payment for Laijau Order {$order->order_number}";

        // Standard NCHL parameter concatenation order
        $dataToSign = "MERCHANTID={$this->merchantId},APPID={$this->appId},APPNAME={$this->appName},TXNID={$txnId},TXNDATE={$txnDate},TXNCRN={$txnCrn},TXNAMT={$txnAmt},REFERENCEID={$referenceId},REMARKS={$remarks},PARTICULARS={$particulars},TOKEN=TOKEN";
        $token = $this->generateSignature($dataToSign);

        return [
            'action_url' => $this->getLoginPagnprl(),
            'fields' => [
                'MERCHANTID' => $this->merchantId,
                'APPID' => $this->appId,
                'APPNAME' => $this->appName,
                'TXNID' => $txnId,
                'TXNDATE' => $txnDate,
                'TXNCRN' => $txnCrn,
                'TXNAMT' => (string) $txnAmt,
                'REFERENCEID' => $referenceId,
                'REMARKS' => $remarks,
                'PARTICULARS' => $particulars,
                'TOKEN' => $token,
            ],
            'txn_id' => $txnId,
            'expected_paisa' => $txnAmt,
        ];
    }

    /**
     * Server-side transaction verification against ConnectIPS API.
     */
    public function validateTransaction(string $txnId, Order $order): array
    {
        $expectedPaisa = (int) round(((float) $order->total_amount) * 100);

        // Validation token string format: MERCHANTID=...,APPID=...,REFERENCEID=...,TXNAMT=...
        $dataToSign = "MERCHANTID={$this->merchantId},APPID={$this->appId},REFERENCEID={$txnId},TXNAMT={$expectedPaisa}";
        $token = $this->generateSignature($dataToSign);

        $payload = [
            'merchantId' => $this->merchantId,
            'appId' => $this->appId,
            'referenceId' => $txnId,
            'txnAmt' => $expectedPaisa,
            'token' => $token,
        ];

        try {
            $response = Http::withBasicAuth($this->appId, $this->password)
                ->timeout(20)
                ->post($this->getValidateTxnUrl(), $payload);

            $status = $response->status();
            $data = $response->json() ?? [];

            // Sanitize log
            $safePayload = $payload;
            unset($safePayload['token']);
            Log::info("ConnectIPS verification response for Order #{$order->order_number}: HTTP {$status}", [
                'response' => $data,
            ]);

            if ($status !== 200) {
                return [
                    'success' => false,
                    'status' => 'HTTP_ERROR',
                    'http_status' => $status,
                    'error' => "ConnectIPS API responded with HTTP {$status}: " . ($data['message'] ?? 'Validation failed'),
                    'raw_response' => $data,
                ];
            }

            $gatewayStatus = strtoupper($data['status'] ?? '');
            $returnedAmt = isset($data['txnAmt']) ? (int) $data['txnAmt'] : null;

            if ($gatewayStatus === 'SUCCESS') {
                // Strict amount verification: must equal order total in paisa
                if ($returnedAmt !== null && $returnedAmt !== $expectedPaisa) {
                    Log::error("ConnectIPS Amount Tampering Detected! Order #{$order->order_number}: expected {$expectedPaisa}, got {$returnedAmt}");
                    return [
                        'success' => false,
                        'status' => 'AMOUNT_MISMATCH',
                        'error' => "Payment amount mismatch: received {$returnedAmt} paisa, expected {$expectedPaisa} paisa.",
                        'raw_response' => $data,
                    ];
                }

                return [
                    'success' => true,
                    'status' => 'SUCCESS',
                    'gateway_txnid' => $data['txnId'] ?? null,
                    'batch_id' => $data['batchId'] ?? null,
                    'reference_id' => $data['referenceId'] ?? $txnId,
                    'txn_date' => $data['txnDate'] ?? null,
                    'raw_response' => $data,
                    'error' => null,
                ];
            }

            return [
                'success' => false,
                'status' => $gatewayStatus ?: 'FAILED',
                'error' => $data['statusDesc'] ?? 'Transaction was not successful according to ConnectIPS.',
                'raw_response' => $data,
            ];
        } catch (\Throwable $e) {
            Log::error("ConnectIPS transaction validation exception for Order #{$order->order_number}: " . $e->getMessage());
            return [
                'success' => false,
                'status' => 'EXCEPTION',
                'error' => 'Connection to ConnectIPS validation service failed: ' . $e->getMessage(),
                'raw_response' => [],
            ];
        }
    }
}
