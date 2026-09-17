<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\RestockRequest;
use Illuminate\Http\Request;

class RestockRequestController extends Controller
{
    /**
     * Submit a restock notification request for a piece.
     */
    public function store(Request $request, $productId)
    {
        $request->validate([
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'quantity' => 'nullable|integer|min:1|max:100',
            'size' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:50',
            'variant_id' => 'nullable|exists:product_variants,id',
        ]);

        $product = Product::findOrFail($productId);

        if ($product->isPermanentlyUnavailable()) {
            return response()->json([
                'success' => false,
                'message' => 'This piece is permanently retired and will not be restocked.',
            ], 422);
        }

        $email = trim(strtolower($request->input('email')));
        $user = $request->user('sanctum');

        // Idempotent find or create pending request
        $restockRequest = RestockRequest::firstOrCreate(
            [
                'product_id' => $product->id,
                'email' => $email,
                'size' => $request->input('size'),
                'color' => $request->input('color'),
                'status' => 'pending',
            ],
            [
                'product_variant_id' => $request->input('variant_id'),
                'user_id' => $user?->id,
                'phone' => $request->input('phone'),
                'quantity' => (int) ($request->input('quantity') ?: 1),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => "You're on the list. We'll email you when this piece is available again.",
            'data' => [
                'id' => $restockRequest->id,
                'product_id' => $product->id,
                'email' => $email,
                'status' => 'pending',
            ],
        ]);
    }
}
