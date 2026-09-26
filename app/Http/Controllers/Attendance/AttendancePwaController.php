<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Attendance\AttendanceAuthToken;
use App\Models\Attendance\AttendanceLocation;
use App\Models\Attendance\AttendanceSetting;
use App\Models\Hrm\Employee;
use App\Services\Attendance\AttendanceService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AttendancePwaController extends Controller
{
    public function __construct(
        protected AttendanceService $attendanceService
    ) {}

    /**
     * Employee Attendance PWA Main Dashboard.
     */
    public function dashboard(Request $request)
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('attendance_employee');

        $status = $this->attendanceService->getEmployeeAttendanceState($employee);
        $recentHistory = $this->attendanceService->getEmployeeRecentHistory($employee, 7);

        $defaultLocation = AttendanceLocation::where('is_active', true)->where('is_default', true)->first()
            ?? AttendanceLocation::where('is_active', true)->first();

        $settings = [
            'gps_required' => AttendanceSetting::get('gps_required', true),
            'geofencing_enabled' => AttendanceSetting::get('geofencing_enabled', true),
            'max_gps_accuracy_meters' => (int) AttendanceSetting::get('max_gps_accuracy_meters', 100),
            'heartbeat_interval_seconds' => (int) AttendanceSetting::get('heartbeat_interval_seconds', 180),
        ];

        $canPunchFromAnywhere = $employee->canPunchFromAnywhere();

        return view('attendance.dashboard', compact('employee', 'status', 'recentHistory', 'defaultLocation', 'settings', 'canPunchFromAnywhere'));
    }

    /**
     * Standalone Fullscreen PIN Entry Screen.
     */
    public function login(Request $request)
    {
        // If already authenticated via session, redirect directly to dashboard
        if (session('attendance_employee_id')) {
            $employee = Employee::find(session('attendance_employee_id'));
            if ($employee && $employee->attendance_access_enabled && $employee->status === 'active') {
                return redirect()->route('attendance.dashboard');
            }
        }

        // If authenticated via persistent token cookie, redirect to dashboard
        $token = $request->cookie('laijau_attendance_session');
        if ($token) {
            $employee = AttendanceAuthToken::validateToken($token);
            if ($employee) {
                session([
                    'attendance_employee_id' => $employee->id,
                    'attendance_auth_time' => now()->timestamp,
                ]);
                return redirect()->route('attendance.dashboard');
            }
        }

        return view('attendance.login');
    }

    /**
     * Dynamic Web App Manifest.
     */
    public function manifest()
    {
        $manifest = [
            'name' => 'Laijau Employee Attendance',
            'short_name' => 'Attendance',
            'description' => 'Laijau Showroom Employee Attendance Verification PWA',
            'start_url' => '/attendance',
            'scope' => '/attendance',
            'display' => 'standalone',
            'background_color' => '#ffffff',
            'theme_color' => '#001b48',
            'orientation' => 'portrait-primary',
            'icons' => [
                [
                    'src' => '/attendance-assets/icons/icon-192.png',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any maskable',
                ],
                [
                    'src' => '/attendance-assets/icons/icon-512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any maskable',
                ],
            ],
        ];

        return response()->json($manifest, 200, [
            'Content-Type' => 'application/manifest+json; charset=utf-8',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Service Worker script.
     */
    public function serviceWorker()
    {
        $swPath = public_path('attendance-assets/sw.js');
        $sw = file_exists($swPath) ? file_get_contents($swPath) : '// Service worker';

        return response($sw, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Service-Worker-Allowed' => '/attendance',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * Offline notification page.
     */
    public function offline()
    {
        return view('attendance.offline');
    }
}
