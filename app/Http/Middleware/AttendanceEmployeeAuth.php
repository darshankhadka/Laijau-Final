<?php

namespace App\Http\Middleware;

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
        $employeeId = session('attendance_employee_id');
        $deviceId = session('attendance_device_id');

        $device = null;
        $employee = null;

        if ($employeeId && $deviceId) {
            $device = AttendanceDevice::find($deviceId);
            $employee = Employee::find($employeeId);
        }

        // Fallback: check device token from cookie or authorization header
        if (!$device || !$employee) {
            $deviceToken = $request->cookie('laijau_attendance_device')
                ?? $request->header('X-Device-Token')
                ?? $request->input('device_token');

            if ($deviceToken) {
                $hash = hash('sha256', $deviceToken);
                $device = AttendanceDevice::where('device_token_hash', $hash)->where('is_active', true)->first();
                if ($device && $device->isUsable()) {
                    $employee = $device->employee;
                }
            }
        }

        // Validate active status
        if (!$device || !$employee || !$device->isUsable() || !$employee->attendance_access_enabled || $employee->status !== 'active') {
            session()->forget(['attendance_employee_id', 'attendance_device_id', 'attendance_auth_time']);

            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Unauthenticated attendance session. Please sign in with your PIN or register your device.',
                    'requires_auth' => true,
                ], 401);
            }

            // Check if device is recognized to direct to PIN login vs fresh setup
            $hasDevice = (bool) ($request->cookie('laijau_attendance_device') || $request->header('X-Device-Token'));
            return redirect()->route($hasDevice ? 'attendance.login' : 'attendance.setup');
        }

        // Store resolved employee and device on request attributes for controllers
        $request->attributes->set('attendance_employee', $employee);
        $request->attributes->set('attendance_device', $device);

        return $next($request);
    }
}
