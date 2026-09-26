<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Attendance\AttendanceAuditLog;
use App\Models\Attendance\AttendanceAuthToken;
use App\Models\Hrm\Employee;
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
     * 1. Authenticate employee using their unique attendance PIN.
     * Establishes both a PHP session and a persistent secure device cookie.
     */
    public function authenticatePin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pin' => 'required|string|min:4|max:8',
            'employee_number' => 'nullable|string|max:50',
            'device_name' => 'nullable|string|max:120',
        ]);

        $throttleKey = 'attendance_pin_' . $request->ip();
        if (cache()->has($throttleKey) && cache()->get($throttleKey) >= 15) {
            return response()->json([
                'error' => 'Too many failed login attempts. Please wait 10 minutes and try again.',
            ], 429);
        }

        try {
            $employee = $this->attendanceService->authenticateByPin(
                $validated['pin'],
                $validated['employee_number'] ?? null
            );

            // Establish employee session
            session([
                'attendance_employee_id' => $employee->id,
                'attendance_auth_time' => now()->timestamp,
            ]);

            // Create persistent 180-day device token so the employee doesn't re-enter PIN daily
            $deviceName = $validated['device_name'] ?? 'PWA Device';
            $rawToken = AttendanceAuthToken::createToken($employee, $deviceName);

            // Set secure persistent cookie
            $cookie = cookie(
                'laijau_attendance_session',
                $rawToken,
                259200, // 180 days in minutes
                '/',
                null,
                $request->isSecure(),
                true, // HttpOnly
                false,
                'Lax'
            );

            $state = $this->attendanceService->getEmployeeAttendanceState($employee);

            return response()->json([
                'success' => true,
                'message' => 'Authenticated successfully. Welcome, ' . $employee->first_name . '!',
                'token' => $rawToken,
                'employee' => [
                    'id' => $employee->id,
                    'name' => $employee->full_name,
                    'first_name' => $employee->first_name,
                    'code' => $employee->employee_number,
                    'department' => $employee->department?->name ?? 'Showroom',
                    'position' => $employee->position?->title ?? 'Staff',
                    'can_punch_from_anywhere' => $employee->canPunchFromAnywhere(),
                ],
                'state' => $state,
                'redirect' => route('attendance.dashboard'),
            ])->withCookie($cookie);
        } catch (ValidationException $e) {
            $attempts = (cache()->get($throttleKey) ?? 0) + 1;
            cache()->put($throttleKey, $attempts, now()->addMinutes(10));
            throw $e;
        }
    }

    /**
     * 2. Clock In with GPS location verification.
     */
    public function checkIn(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('attendance_employee');
        if (!$employee) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $validated = $request->validate([
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'accuracy_meters' => 'nullable|numeric',
            'client_captured_at' => 'nullable|string',
            'idempotency_key' => 'nullable|string|max:64',
            'verification_method' => 'nullable|string|max:50',
        ]);

        $result = $this->attendanceService->submitAttendance(
            $employee,
            'check_in',
            $validated
        );

        return response()->json($result);
    }

    /**
     * 3. Clock Out with GPS location verification.
     */
    public function checkOut(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('attendance_employee');
        if (!$employee) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $validated = $request->validate([
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'accuracy_meters' => 'nullable|numeric',
            'client_captured_at' => 'nullable|string',
            'idempotency_key' => 'nullable|string|max:64',
            'verification_method' => 'nullable|string|max:50',
        ]);

        $result = $this->attendanceService->submitAttendance(
            $employee,
            'check_out',
            $validated
        );

        return response()->json($result);
    }

    /**
     * 4. Authoritative attendance status check for today.
     */
    public function status(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('attendance_employee');
        if (!$employee) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $status = $this->attendanceService->getEmployeeAttendanceState($employee);

        return response()->json([
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'first_name' => $employee->first_name,
                'code' => $employee->employee_number,
                'department' => $employee->department?->name,
                'position' => $employee->position?->title,
                'can_punch_from_anywhere' => $employee->canPunchFromAnywhere(),
            ],
            'status' => $status,
            'state' => $status,
        ]);
    }

    /**
     * 5. Recent attendance history for authenticated employee.
     */
    public function history(Request $request): JsonResponse
    {
        /** @var Employee $employee */
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
     * Employee Timesheet Summary for Daily, Weekly, Monthly, or Custom periods.
     */
    public function summary(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('attendance_employee');
        if (!$employee) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $period = $request->query('period', 'monthly');
        $start = $request->query('start_date') ? \Carbon\Carbon::parse($request->query('start_date')) : null;
        $end = $request->query('end_date') ? \Carbon\Carbon::parse($request->query('end_date')) : null;

        $summary = $this->attendanceService->getEmployeeTimesheetSummary($employee, $period, $start, $end);

        return response()->json($summary);
    }

    /**
     * 6. Foreground location heartbeat while clocked in.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('attendance_employee');
        if (!$employee) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $validated = $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'accuracy_meters' => 'nullable|numeric',
        ]);

        $update = $this->attendanceService->recordHeartbeat(
            $employee,
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
     * 7. Change employee attendance PIN.
     */
    public function changePin(Request $request): JsonResponse
    {
        /** @var Employee $employee */
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
     * 8. Log GPS failure or permission denial for Admin audit visibility.
     */
    public function logGpsFailure(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('attendance_employee');
        if (!$employee) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $validated = $request->validate([
            'action_type' => 'nullable|string|in:check_in,check_out,heartbeat',
            'error_type' => 'required|string|max:60',
            'message' => 'required|string|max:255',
            'accuracy' => 'nullable|numeric',
        ]);

        $actionType = $validated['action_type'] ?? 'check_in';
        $errorType = $validated['error_type'];
        $message = $validated['message'];

        AttendanceAuditLog::log(
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
     * 9. Logout / Clear Attendance Session and persistent tokens.
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->cookie('laijau_attendance_session')
            ?? $request->header('X-Attendance-Token');

        if ($token) {
            AttendanceAuthToken::revokeToken($token);
        }

        session()->forget(['attendance_employee_id', 'attendance_auth_time', 'attendance_device_id']);

        $forgetCookie = Cookie::forget('laijau_attendance_session');
        $forgetLegacy = Cookie::forget('laijau_attendance_device');

        return response()->json([
            'success' => true,
            'message' => 'Signed out successfully.',
            'redirect' => route('attendance.login'),
        ])->withCookie($forgetCookie)->withCookie($forgetLegacy);
    }
}
