<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Order;
use App\Mail\PasswordResetMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    /**
     * Customer registration.
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:50',
            'gdpr_consent' => 'nullable|boolean',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'role' => 'customer',
            'gdpr_consent' => (bool)($validated['gdpr_consent'] ?? true),
            'gdpr_consented_at' => ($validated['gdpr_consent'] ?? true) ? now() : null,
        ]);

        $customerRole = \Spatie\Permission\Models\Role::where('name', 'Customer')->where('guard_name', 'web')->first();
        if ($customerRole) {
            $user->assignRole($customerRole);
        }

        // Safely link any past guest orders matching this verified email
        Order::where('email', $user->email)
            ->whereNull('user_id')
            ->update(['user_id' => $user->id]);

        auth('web')->login($user, true);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        $token = $user->createToken('customer_token')->plainTextToken;

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => true,
                'message' => 'Account registered successfully.',
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'avatar' => $user->avatar,
                    'saved_measurements' => $user->saved_measurements,
                ],
            ], 201);
        }

        return redirect()->route('storefront.account');
    }

    /**
     * Customer email/password login.
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid email address or password.',
                ], 401);
            }
            return back()->withErrors(['email' => 'Invalid email address or password.'])->withInput();
        }

        // Safely link any past guest orders matching this verified email
        Order::where('email', $user->email)
            ->whereNull('user_id')
            ->update(['user_id' => $user->id]);

        auth('web')->login($user, true);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        $token = $user->createToken('customer_token')->plainTextToken;

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => true,
                'message' => 'Welcome back to Laijau.',
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'avatar' => $user->avatar,
                    'saved_measurements' => $user->saved_measurements,
                ],
            ]);
        }

        return redirect()->route('storefront.account');
    }

    /**
     * Customer logout.
     */
    public function logout(Request $request)
    {
        if ($request->user()) {
            $request->user()->currentAccessToken()?->delete();
        }

        auth('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully.',
            ]);
        }

        return redirect()->route('storefront.account');
    }

    /**
     * Google OAuth Redirect URL generator.
     */
    public function googleRedirect(Request $request)
    {
        try {
            $clientId = config('services.google.client_id');
            if (empty($clientId) || str_contains($clientId, 'placeholder')) {
                if ($request->expectsJson() || $request->is('api/*')) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Google Sign-In is currently in configuration. Please use email sign in.',
                        'error_code' => 'GOOGLE_OAUTH_NOT_CONFIGURED',
                    ], 503);
                }
                return redirect()->route('storefront.account')->with('error', 'Google Sign-In is currently in configuration.');
            }

            $target = Socialite::driver('google')->stateless()->redirect();
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['success' => true, 'url' => $target->getTargetUrl()]);
            }
            return $target;
        } catch (\Throwable $e) {
            Log::error('Google redirect URL generation failed: ' . $e->getMessage());
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to generate Google authorization URL.',
                ], 500);
            }
            return redirect()->route('storefront.account')->with('error', 'google_auth_failed');
        }
    }

    /**
     * Google OAuth Callback.
     */
    public function googleCallback(Request $request)
    {
        // Check for Google error response (e.g. user cancelled)
        if ($request->has('error')) {
            $err = $request->query('error');
            $code = $err === 'access_denied' ? 'google_auth_cancelled' : 'google_auth_failed';
            return redirect()->route('storefront.account', ['error' => $code]);
        }

        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (\Throwable $e) {
            Log::warning('Google OAuth callback failed: ' . $e->getMessage());
            return redirect()->route('storefront.account', ['error' => 'google_auth_failed']);
        }

        $googleId = (string)$googleUser->getId();
        $googleEmail = (string)$googleUser->getEmail();
        $googleName = $googleUser->getName() ?: 'Valued Customer';
        $googleAvatar = $googleUser->getAvatar();

        if (empty($googleEmail) || empty($googleId)) {
            return redirect()->route('storefront.account', ['error' => 'google_email_missing']);
        }

        // Account Linking: Check by Google ID and Email
        $userWithGoogleId = User::where('google_id', $googleId)->first();
        $userWithEmail = User::where('email', $googleEmail)->first();

        // Case C: Conflict detection (Google ID belongs to another customer with different email)
        if ($userWithGoogleId && $userWithEmail && $userWithGoogleId->id !== $userWithEmail->id) {
            Log::warning("Google OAuth collision: Google ID {$googleId} belongs to user {$userWithGoogleId->id} but email matches user {$userWithEmail->id}");
            return redirect()->route('storefront.account', ['error' => 'account_conflict']);
        }

        if ($userWithGoogleId) {
            // Existing customer recognized by Google ID
            $user = $userWithGoogleId;
            if ($googleAvatar && empty($user->avatar)) {
                $user->avatar = $googleAvatar;
                $user->save();
            }
        } elseif ($userWithEmail) {
            // Cases A & D: Link Google ID to existing customer account
            $user = $userWithEmail;
            $user->google_id = $googleId;
            if ($googleAvatar && empty($user->avatar)) {
                $user->avatar = $googleAvatar;
            }
            if (empty($user->email_verified_at)) {
                $user->email_verified_at = now();
            }
            $user->save();
        } else {
            // Case B: Create new customer safely
            $user = User::create([
                'name' => $googleName,
                'email' => $googleEmail,
                'google_id' => $googleId,
                'avatar' => $googleAvatar,
                'password' => Hash::make(Str::random(32)),
                'email_verified_at' => now(),
                'role' => 'customer',
                'gdpr_consent' => true,
                'gdpr_consented_at' => now(),
            ]);

            $customerRole = \Spatie\Permission\Models\Role::where('name', 'Customer')->where('guard_name', 'web')->first();
            if ($customerRole) {
                $user->assignRole($customerRole);
            }
        }

        // Safely link any past guest orders matching this verified email
        Order::where('email', $user->email)
            ->whereNull('user_id')
            ->update(['user_id' => $user->id]);

        auth('web')->login($user, true);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        $token = $user->createToken('customer_token')->plainTextToken;

        return redirect()->route('storefront.account', ['token' => $token]);
    }

    /**
     * Handle direct POST with Google Token from Google Sign-In SDK.
     */
    public function googleLogin(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        try {
            $googleUser = Socialite::driver('google')->stateless()->userFromToken($request->token);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired Google authorization token.',
            ], 401);
        }

        $googleId = (string)$googleUser->getId();
        $googleEmail = (string)$googleUser->getEmail();
        $googleName = $googleUser->getName() ?: 'Valued Customer';
        $googleAvatar = $googleUser->getAvatar();

        if (empty($googleEmail) || empty($googleId)) {
            return response()->json([
                'success' => false,
                'message' => 'Google account must have an associated email address.',
            ], 422);
        }

        $userWithGoogleId = User::where('google_id', $googleId)->first();
        $userWithEmail = User::where('email', $googleEmail)->first();

        // Case C: Conflict detection
        if ($userWithGoogleId && $userWithEmail && $userWithGoogleId->id !== $userWithEmail->id) {
            return response()->json([
                'success' => false,
                'message' => 'This Google account is linked to another profile.',
                'error_code' => 'ACCOUNT_CONFLICT',
            ], 409);
        }

        if ($userWithGoogleId) {
            $user = $userWithGoogleId;
            if ($googleAvatar && empty($user->avatar)) {
                $user->avatar = $googleAvatar;
                $user->save();
            }
        } elseif ($userWithEmail) {
            $user = $userWithEmail;
            $user->google_id = $googleId;
            if ($googleAvatar && empty($user->avatar)) {
                $user->avatar = $googleAvatar;
            }
            if (empty($user->email_verified_at)) {
                $user->email_verified_at = now();
            }
            $user->save();
        } else {
            $user = User::create([
                'name' => $googleName,
                'email' => $googleEmail,
                'google_id' => $googleId,
                'avatar' => $googleAvatar,
                'password' => Hash::make(Str::random(32)),
                'email_verified_at' => now(),
                'gdpr_consent' => true,
                'gdpr_consented_at' => now(),
            ]);

            if (\Spatie\Permission\Models\Role::where('name', 'Customer')->exists()) {
                $user->assignRole('Customer');
            }
        }

        Order::where('email', $user->email)
            ->whereNull('user_id')
            ->update(['user_id' => $user->id]);

        auth('web')->login($user, true);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        $token = $user->createToken('customer_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Authenticated with Google successfully.',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar' => $user->avatar,
                'saved_measurements' => $user->saved_measurements,
            ],
        ]);
    }

    /**
     * Request a password reset link.
     */
    public function forgotPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $validated['email'])->first();

        // Always return success to prevent email enumeration
        if (!$user) {
            return response()->json([
                'success' => true,
                'message' => 'If an account exists with that email, a password reset link has been dispatched.',
            ]);
        }

        // Generate cryptographically secure reset token
        $rawToken = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => Hash::make($rawToken),
                'created_at' => now(),
            ]
        );

        try {
            Mail::to($user->email)->send(new PasswordResetMail($user, $rawToken));
        } catch (\Throwable $e) {
            Log::error("Failed to send password reset email to {$user->email}: " . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'If an account exists with that email, a password reset link has been dispatched.',
        ]);
    }

    /**
     * Reset account password using token.
     */
    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $validated['email'])
            ->first();

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired password reset token.',
            ], 422);
        }

        // Verify token expiry from system settings (default: 60 minutes)
        $timeoutMinutes = app(\App\Services\Settings\SettingsService::class)->getInteger('system', 'password_reset_timeout_minutes', 60);
        if (now()->diffInMinutes($record->created_at) > $timeoutMinutes) {
            DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();
            return response()->json([
                'success' => false,
                'message' => 'Password reset token has expired. Please request a new one.',
            ], 422);
        }

        // Verify token signature
        if (!Hash::check($validated['token'], $record->token)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid password reset token.',
            ], 422);
        }

        $user = User::where('email', $validated['email'])->first();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Customer account not found.',
            ], 404);
        }

        // Update password and invalidate single-use token
        $user->password = Hash::make($validated['password']);
        $user->save();

        DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();

        auth('web')->login($user, true);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        // Issue fresh Sanctum token
        $token = $user->createToken('customer_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Your password has been successfully reset.',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar' => $user->avatar,
                'saved_measurements' => $user->saved_measurements,
            ],
        ]);
    }
}
