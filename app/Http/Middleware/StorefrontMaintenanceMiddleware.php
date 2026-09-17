<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class StorefrontMaintenanceMiddleware
{
    /**
     * Handle incoming storefront requests during storefront maintenance mode.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $maintenanceEnabled = (bool) Setting::get('maintenance_mode', false);

        if (!$maintenanceEnabled) {
            return $next($request);
        }

        // 1. Critical System & Health Routes Bypass
        if ($request->is('up') || $request->is('health')) {
            return $next($request);
        }

        // 2. Admin & Filament Panel Routes Bypass
        if ($request->is('intadmin*') || $request->is('admin*') || $request->is('filament*') || $request->is('livewire*') || $request->is('storage*')) {
            return $next($request);
        }

        // 3. Webhooks & Payment Return Callback Bypass
        if (
            $request->is('api/webhooks*') ||
            $request->is('webhooks*') ||
            $request->is('payment*') ||
            $request->is('webhook') ||
            $request->is('checkout/success*') ||
            $request->is('checkout/cancel*')
        ) {
            return $next($request);
        }

        // 4. Authenticated Admin / Staff Bypass
        $user = Auth::guard('web')->user();
        if ($user && (method_exists($user, 'canViewSettings') && $user->canViewSettings())) {
            return $next($request);
        }

        // 5. Whitelisted IP Address Bypass
        $allowedIpsRaw = (string) Setting::get('maintenance_allowed_ips', '');
        if (!empty($allowedIpsRaw)) {
            $allowedIps = array_filter(array_map('trim', explode(',', $allowedIpsRaw)));
            $clientIp = $request->ip();

            if (!empty($clientIp) && in_array($clientIp, $allowedIps, true)) {
                return $next($request);
            }
        }

        // If API or JSON request, return structured 503
        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'success' => false,
                'status' => 'maintenance',
                'message' => Setting::get('maintenance_message', 'Laijau is currently undergoing scheduled maintenance. We will be back online shortly.'),
                'expected_return' => Setting::get('maintenance_expected_return', 'Shortly'),
                'contact' => Setting::get('support_email', 'support@laijau.com'),
            ], 503)->header('Retry-After', '3600');
        }

        // Render 503 view
        $message = Setting::get('maintenance_message', 'Laijau is currently undergoing scheduled maintenance. We will be back online shortly.');
        $expectedReturn = Setting::get('maintenance_expected_return', 'Shortly');
        $contactEmail = Setting::get('support_email', 'support@laijau.com');

        return response()->view('errors.503', compact('message', 'expectedReturn', 'contactEmail'), 503)
            ->header('Retry-After', '3600');
    }
}
