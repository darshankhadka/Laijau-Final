<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GdprController extends Controller
{
    /**
     * Anonymize the currently authenticated user's data.
     */
    public function erasure(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        // Revoke all tokens
        $user->tokens()->delete();

        // Anonymize user details
        $user->update([
            'name' => 'Anonymized User',
            'email' => 'anonymized_' . Str::uuid() . '@deleted.local',
            'google_id' => null,
            'avatar' => null,
            'password' => null,
        ]);

        // Anonymize personal info from orders but retain financial totals for tax compliance
        $user->orders()->update([
            'first_name' => 'Anonymized',
            'last_name' => 'User',
            'email' => 'anonymized@deleted.local',
            'phone' => null,
            'shipping_address' => 'Anonymized Address',
            // VAT and totals are kept intact
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User data has been fully anonymized per GDPR requirements.'
        ]);
    }
}
