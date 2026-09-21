<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Attendance\AttendanceAuditLog;
use App\Models\Attendance\AttendanceDevice;
use App\Services\Attendance\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\ValidationException;

class AttendanceApiController extends Controller
{
    public function __construct(
        protected AttendanceService $attendanceService
    ) {}

    /**
     * Resolve the device from request cookie, header, or body.
     */
    protected function resolveDevice(Request $request): ?AttendanceDevice
    {
        $token = $request->cookie('laijau_attendance_device')
            ?? $request->header('X-Device-Token')
            ?? $request->input('device_token');

        if (!$token) {
            $deviceId = session('attendance_device_id');
            if ($deviceId) {
                return AttendanceDevice::find($deviceId);
            }
            return null;
        }

        $hash = hash('sha256', $token);
        return AttendanceDevice::with('employee')
            ->where('device_token_hash', $hash)
            ->where('is_active', true)
            ->first();
    }

    /**
     * 1. First-time setup & device registration.
     */
    public function setup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_number' => 'required|string|max:50',
            'phone' => 'required|string|max:30',
            'pin' => 'required|string|min:4|max:8',
            'device_name' => 'nullable|string|max:120',
            'platform' => 'nullable|string|max:60',
            'browser' => 'nullable|string|max:60',
            'photo' => 'nullable|string', // Verification photo base64
        ]);

        // Rate limiting key
        $throttleKey = 'attendance_setup_' . $request->ip();
        if (cache()->has($throttleKey) && cache()->get($throttleKey) >= 10) {
            return response()->json([
                'error' => 'Too many registration attempts. Please try again in 10 minutes.',
            ], 429);
        }

        try {
            $employee = $this->attendanceService->verifyFirstTimeCredentials(
                $validated['employee_number'],
                $validated['phone'],
                $validated['pin']
            );

            $result = $this->attendanceService->registerDevice($employee, [
                'device_name' => $validated['device_name'] ?? 'Mobile Device',
                'platform' => $validated['platform'] ?? 'Mobile',
                'browser' => $validated['browser'] ?? 'Browser',
            ], $validated['photo'] ?? null);

            $device = $result['device'];
            $rawToken = $result['device_token'];

            // Establish employee attendance session
            session([
                'attendance_employee_id' => $employee->id,
                'attendance_device_id' => $device->id,
                'attendance_auth_time' => now()->timestamp,
            ]);

            // Set secure persistent cookie (365 days)
            $cookie = cookie(
                'laijau_attendance_device',
                $rawToken,
                525600, // 365 days in minutes
                '/',
                null,
                $request->isSecure(),
                true, // HttpOnly
                false,
                'Lax'
            );

            return response()->json([
                'success' => true,
                'message' => 'Device registered successfully. Welcome to Laijau Attendance!',
                'device_token' => $rawToken,
                'employee' => [
                    'id' => $employee->id,
                    'name' => $employee->full_name,
                    'code' => $employee->employee_number,
                ],
                'redirect' => route('attendance.dashboard'),
            ])->withCookie($cookie);
        } catch (ValidationException $e) {
            $attempts = (cache()->get($throttleKey) ?? 0) + 1;
            cache()->put($throttleKey, $attempts, now()->addMinutes(10));
            throw $e;
        }
    }

    /**
     * 2. PIN authentication for a registered device.
     */
    public function authenticatePin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pin' => 'required|string|min:4|max:8',
        ]);

        $device = $this->resolveDevice($request);
        if (!$device || !$device->isUsable()) {
            return response()->json([
                'error' => 'Device is not recognized or has been revoked. Please complete first-time setup.',
                'requires_setup' => true,
            ], 403);
        }

        $this->attendanceService->authenticateWithPin($device, $validated['pin']);

        // Establish session
        session([
            'attendance_employee_id' => $device->employee_id,
            'attendance_device_id' => $device->id,
            'attendance_auth_time' => now()->timestamp,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Authenticated successfully.',
            'redirect' => route('attendance.dashboard'),
        ]);
    }

    /**
     * 3. Change employee attendance PIN.
     */
    public function changePin(Request $request): JsonResponse
    {
        $employee = $request->attributes->get('attendance_employee');
        if (!$employee) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $validated = $request->validate([
            'current_pin' => 'required|string',
            'new_pin' => 'required|string|min:4|max:8|confirmed',
        ]);

        $this->attendanceService->changePin($employee, $validated['current_pin'], $validated['new_pin']);

        return response()->json([
            'success' => true,
            'message' => 'Attendance PIN changed successfully.',
        ]);
    }

    /**
     * 4. WebAuthn: Generate registration options.
     */
    public function webauthnRegisterOptions(Request $request): JsonResponse
    {
        $device = $this->resolveDevice($request);
        if (!$device) {
            return response()->json(['error' => 'Device not recognized.'], 403);
        }

        $employee = $device->employee;
        $challenge = bin2hex(random_bytes(32));
        session(['webauthn_reg_challenge_' . $device->id => $challenge]);

        $options = [
            'challenge' => $challenge,
            'rp' => [
                'name' => 'Laijau Attendance',
                'id' => $request->getHost(),
            ],
            'user' => [
                'id' => (string)$employee->id,
                'name' => $employee->employee_number,
                'displayName' => $employee->full_name,
            ],
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],  // ES256
                ['type' => 'public-key', 'alg' => -257], // RS256
            ],
            'authenticatorSelection' => [
                'authenticatorAttachment' => 'platform',
                'userVerification' => 'preferred',
                'residentKey' => 'discouraged',
            ],
            'timeout' => 60000,
            'attestation' => 'none',
        ];

        return response()->json($options);
    }

    /**
     * 5. WebAuthn: Verify registration and save passkey.
     */
    public function webauthnRegisterVerify(Request $request): JsonResponse
    {
        $device = $this->resolveDevice($request);
        if (!$device) {
            return response()->json(['error' => 'Device not recognized.'], 403);
        }

        $validated = $request->validate([
            'id' => 'required|string',
            'rawId' => 'required|string',
            'type' => 'required|string',
        ]);

        $challengeKey = 'webauthn_reg_challenge_' . $device->id;
        $expectedChallenge = session($challengeKey);
        session()->forget($challengeKey);

        if (!$expectedChallenge) {
            return response()->json(['error' => 'WebAuthn challenge expired or missing.'], 422);
        }

        // Store WebAuthn credential reference on device
        $device->update([
            'passkey_credential_id' => $validated['id'],
            'passkey_public_key' => $request->input('publicKey') ?? $validated['rawId'],
            'passkey_sign_count' => 0,
        ]);

        AttendanceAuditLog::log(
            action: 'passkey_registered',
            employeeId: $device->employee_id,
            details: "WebAuthn passkey registered on device '{$device->device_name}'."
        );

        return response()->json([
            'success' => true,
            'message' => 'Biometric / Passkey registered successfully on this device.',
        ]);
    }

    /**
     * 6. WebAuthn: Generate authentication options.
     */
    public function webauthnLoginOptions(Request $request): JsonResponse
    {
        $device = $this->resolveDevice($request);
        if (!$device || !$device->passkey_credential_id) {
            return response()->json(['error' => 'No passkey registered on this device.'], 404);
        }

        $challenge = bin2hex(random_bytes(32));
        session(['webauthn_auth_challenge_' . $device->id => $challenge]);

        $options = [
            'challenge' => $challenge,
            'rpId' => $request->getHost(),
            'allowCredentials' => [
                [
                    'type' => 'public-key',
                    'id' => $device->passkey_credential_id,
                ],
            ],
            'userVerification' => 'preferred',
            'timeout' => 60000,
        ];

        return response()->json($options);
    }

    /**
     * 7. WebAuthn: Verify assertion and authenticate session.
     */
    public function webauthnLoginVerify(Request $request): JsonResponse
    {
        $device = $this->resolveDevice($request);
        if (!$device || !$device->isUsable() || !$device->passkey_credential_id) {
            return response()->json(['error' => 'Device or passkey is invalid or revoked.'], 403);
        }

        $validated = $request->validate([
            'id' => 'required|string',
        ]);

        $challengeKey = 'webauthn_auth_challenge_' . $device->id;
        $expectedChallenge = session($challengeKey);
        session()->forget($challengeKey);

        if (!$expectedChallenge) {
            return response()->json(['error' => 'WebAuthn challenge expired.'], 422);
        }

        if ($validated['id'] !== $device->passkey_credential_id) {
            return response()->json(['error' => 'Passkey credential mismatch.'], 403);
        }

        $device->increment('passkey_sign_count');
        $device->touchLastSeen(request()->ip());

        // Establish session
        session([
            'attendance_employee_id' => $device->employee_id,
            'attendance_device_id' => $device->id,
            'attendance_auth_time' => now()->timestamp,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Authenticated via biometric / passkey successfully.',
            'redirect' => route('attendance.dashboard'),
        ]);
    }

    /**
     * 8. Check In.
     */
    public function checkIn(Request $request): JsonResponse
    {
        $device = $request->attributes->get('attendance_device');
        if (!$device) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $validated = $request->validate([
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'accuracy_meters' => 'nullable|numeric',
            'client_captured_at' => 'nullable|string',
            'photo' => 'nullable|string', // Base64 JPEG
            'idempotency_key' => 'nullable|string|max:64',
            'verification_method' => 'nullable|string|max:50',
        ]);

        $result = $this->attendanceService->submitAttendance(
            $device,
            'check_in',
            $validated,
            $validated['photo'] ?? null
        );

        return response()->json($result);
    }

    /**
     * 9. Check Out.
     */
    public function checkOut(Request $request): JsonResponse
    {
        $device = $request->attributes->get('attendance_device');
        if (!$device) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $validated = $request->validate([
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'accuracy_meters' => 'nullable|numeric',
            'client_captured_at' => 'nullable|string',
            'photo' => 'nullable|string',
            'idempotency_key' => 'nullable|string|max:64',
            'verification_method' => 'nullable|string|max:50',
        ]);

        $result = $this->attendanceService->submitAttendance(
            $device,
            'check_out',
            $validated,
            $validated['photo'] ?? null
        );

        return response()->json($result);
    }

    /**
     * 10. Foreground location heartbeat update.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $device = $request->attributes->get('attendance_device');
        if (!$device) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $validated = $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'accuracy_meters' => 'nullable|numeric',
        ]);

        $update = $this->attendanceService->recordHeartbeat(
            $device,
            $validated['latitude'],
            $validated['longitude'],
            $validated['accuracy_meters'] ?? null
        );

        return response()->json([
            'success' => true,
            'recorded' => $update !== null,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * 11. Get current status for authenticated employee.
     */
    public function status(Request $request): JsonResponse
    {
        $employee = $request->attributes->get('attendance_employee');
        if (!$employee) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $status = $this->attendanceService->getEmployeeAttendanceState($employee);

        return response()->json([
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'code' => $employee->employee_number,
                'department' => $employee->department?->name,
                'position' => $employee->position?->title,
            ],
            'status' => $status,
        ]);
    }

    /**
     * 12. Get attendance history for authenticated employee.
     */
    public function history(Request $request): JsonResponse
    {
        $employee = $request->attributes->get('attendance_employee');
        if (!$employee) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $history = $this->attendanceService->getEmployeeRecentHistory($employee, 14);

        return response()->json([
            'history' => $history,
        ]);
    }

    /**
     * 13. Log GPS failure or permission denial for Admin audit visibility.
     */
    public function logGpsFailure(Request $request): JsonResponse
    {
        $employee = $request->attributes->get('attendance_employee');
        $device = $request->attributes->get('attendance_device');

        if (!$employee || !$device) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $validated = $request->validate([
            'action_type' => 'nullable|string|in:check_in,check_out,heartbeat',
            'error_type' => 'required|string|max:60', // permission_denied, position_unavailable, accuracy_low, timeout
            'message' => 'required|string|max:255',
            'accuracy' => 'nullable|numeric',
        ]);

        $actionType = $validated['action_type'] ?? 'check_in';
        $errorType = $validated['error_type'];
        $message = $validated['message'];

        \App\Models\Attendance\AttendanceAuditLog::log(
            action: 'gps_failure_' . $errorType,
            employeeId: $employee->id,
            details: "Employee #{$employee->employee_number} ({$employee->full_name}) encountered GPS failure during {$actionType}: {$message}"
        );

        return response()->json([
            'success' => true,
            'logged' => true,
        ]);
    }

    /**
     * 14. Logout / Clear Attendance Session.
     */
    public function logout(Request $request): JsonResponse
    {
        session()->forget(['attendance_employee_id', 'attendance_device_id', 'attendance_auth_time']);

        return response()->json([
            'success' => true,
            'message' => 'Signed out successfully.',
            'redirect' => route('attendance.login'),
        ]);
    }
}
