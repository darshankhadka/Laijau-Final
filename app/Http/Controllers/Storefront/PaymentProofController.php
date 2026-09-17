<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PaymentProofController extends Controller
{
    /**
     * Securely serve payment proof image only to authorized users (admin, customer owner, or guest with token).
     */
    public function show(Request $request, Order $order)
    {
        $isAdmin = auth('admin')->check();
        $authUser = auth('web')->user() ?? $request->user('sanctum');
        $token = $request->query('token');

        $isOwner = $authUser && $order->user_id && (int)$authUser->id === (int)$order->user_id;
        $isTokenMatch = !empty($token) && hash_equals((string)$order->guest_access_token, (string)$token);

        if (!$isAdmin && !$isOwner && !$isTokenMatch) {
            abort(403, 'Unauthorized access to payment proof.');
        }

        $receiptPath = $order->payment_receipt_image;
        if (empty($receiptPath)) {
            abort(404, 'No payment proof uploaded for this order.');
        }

        // Check private disk first, then local, then public fallback
        $disk = null;
        if (Storage::disk('local')->exists($receiptPath)) {
            $disk = 'local';
        } elseif (Storage::disk('public')->exists($receiptPath)) {
            $disk = 'public';
        }

        if (!$disk) {
            abort(404, 'Payment proof file not found.');
        }

        $fullPath = Storage::disk($disk)->path($receiptPath);
        $mime = Storage::disk($disk)->mimeType($receiptPath) ?: 'image/jpeg';

        return response()->file($fullPath, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . basename($receiptPath) . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
        ]);
    }
}
