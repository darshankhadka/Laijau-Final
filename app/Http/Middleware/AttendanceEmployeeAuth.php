<?php

namespace App\Http\Middleware;

use App\Models\Attendance\AttendanceAuthToken;
use App\Models\Attendance\AttendanceDevice;
use App\Models\Hrm\Employee;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AttendanceEmployeeAuth
{
    /**
     * Handle an incoming request for Attendance PWA.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $employee = null;
        $employeeId = session('attendance_employee_id');

        if ($employeeId) {
            $employee = Employee::find($employeeId);
        }

        // Check persistent auth token from cookie or header if session not set
        if (!$employee) {
            $token = $request->cookie('laijau_attendance_session')
                ?? $request->header('X-Attendance-Token')
                ?? $request->input('attendance_token');

            if ($token) {
                $employee = AttendanceAuthToken::validateToken($token);
            }
        }

        // Backward compatibility fallback: check legacy device token if present
        if (!$employee) {
            $deviceToken = $request->cookie('laijau_attendance_device')
                ?? $request->header('X-Device-Token')
                ?? $request->input('device_token');

            if ($deviceToken) {
                $hash = hash('sha256', $deviceToken);
                $device = AttendanceDevice::where('device_token_hash', $hash)->where('is_active', true)->first();
                if ($device && $device->employee) {
                    $employee = $device->employee;
                }
            }
        }

        // Validate active attendance access
        if (!$employee || !$employee->attendance_access_enabled || $employee->status !== 'active') {
            session()->forget(['attendance_employee_id', 'attendance_auth_time', 'attendance_device_id']);

            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Unauthenticated attendance session. Please enter your PIN.',
                    'requires_auth' => true,
                ], 401);
            }

            return redirect()->route('attendance.login');
        }

        // Re-hydrate session if resolved from persistent token
        if (!session('attendance_employee_id')) {
            session([
                'attendance_employee_id' => $employee->id,
                'attendance_auth_time' => now()->timestamp,
            ]);
        }

        // Store resolved employee on request attributes for controllers
        $request->attributes->set('attendance_employee', $employee);

        return $next($request);
    }
}
