<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Attendance\AttendanceDevice;
use App\Models\Attendance\AttendanceEvent;
use App\Models\Attendance\AttendanceLocation;
use App\Models\Attendance\AttendanceSetting;
use App\Services\Attendance\AttendanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
        $employee = $request->attributes->get('attendance_employee');
        $device = $request->attributes->get('attendance_device');

        $status = $this->attendanceService->getEmployeeAttendanceState($employee);
        $recentHistory = $this->attendanceService->getEmployeeRecentHistory($employee, 7);

        $defaultLocation = AttendanceLocation::where('is_active', true)->where('is_default', true)->first()
            ?? AttendanceLocation::where('is_active', true)->first();

        $settings = [
            'gps_required' => AttendanceSetting::get('gps_required', true),
            'photo_required' => AttendanceSetting::get('photo_required', true),
            'geofencing_enabled' => AttendanceSetting::get('geofencing_enabled', true),
            'max_gps_accuracy_meters' => AttendanceSetting::get('max_gps_accuracy_meters', 100),
            'heartbeat_interval_seconds' => AttendanceSetting::get('heartbeat_interval_seconds', 180),
        ];

        return view('attendance.dashboard', compact('employee', 'device', 'status', 'recentHistory', 'defaultLocation', 'settings'));
    }

    /**
     * First-time Employee Setup & Device Registration Screen.
     */
    public function setup(Request $request)
    {
        // If employee is already authenticated, redirect straight to dashboard
        if (session('attendance_employee_id') && session('attendance_device_id')) {
            return redirect()->route('attendance.dashboard');
        }

        return view('attendance.setup');
    }

    /**
     * Subsequent Login / Unlock Screen for Registered Devices.
     */
    public function login(Request $request)
    {
        if (session('attendance_employee_id') && session('attendance_device_id')) {
            return redirect()->route('attendance.dashboard');
        }

        $deviceToken = $request->cookie('laijau_attendance_device')
            ?? $request->header('X-Device-Token');

        $device = null;
        if ($deviceToken) {
            $device = AttendanceDevice::with('employee')
                ->where('device_token_hash', hash('sha256', $deviceToken))
                ->where('is_active', true)
                ->first();
        }

        return view('attendance.login', compact('device'));
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
        $sw = file_get_contents(public_path('attendance-assets/sw.js'));

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

    /**
     * Secure Photo Viewer: only authorized admins or the owning employee may access.
     */
    public function viewPhoto(Request $request, AttendanceEvent $event)
    {
        $isAdmin = auth('admin')->check() || auth('web')->check();
        $isOwner = session('attendance_employee_id') == $event->employee_id;

        if (!$isAdmin && !$isOwner) {
            abort(403, 'Unauthorized access to employee attendance photo.');
        }

        if (empty($event->photo_path) || !Storage::disk('local')->exists($event->photo_path)) {
            abort(404, 'Attendance photo file not found.');
        }

        $fileContent = Storage::disk('local')->get($event->photo_path);
        $mime = 'image/jpeg';

        return response($fileContent, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="attendance_' . $event->id . '.jpg"',
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
        ]);
    }
}
