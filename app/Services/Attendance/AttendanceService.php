<?php

namespace App\Services\Attendance;

use App\Models\Attendance\AttendanceAuditLog;
use App\Models\Attendance\AttendanceDevice;
use App\Models\Attendance\AttendanceEvent;
use App\Models\Attendance\AttendanceLocation;
use App\Models\Attendance\AttendanceLocationUpdate;
use App\Models\Attendance\AttendanceSetting;
use App\Models\Hrm\Employee;
use App\Models\Hrm\Timesheet;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    /**
     * Verify first-time employee credentials (Employee ID + Phone + Admin-Created PIN).
     *
     * @throws ValidationException
     */
    public function verifyFirstTimeCredentials(string $employeeNumber, string $phone, string $pin): Employee
    {
        $cleanNumber = strtoupper(trim($employeeNumber));
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);

        // Find employee by employee_number (case-insensitive)
        $employee = Employee::whereRaw('UPPER(employee_number) = ?', [$cleanNumber])
            ->orWhereRaw('UPPER(REPLACE(employee_number, "-", "")) = ?', [str_replace('-', '', $cleanNumber)])
            ->first();

        if (!$employee) {
            throw ValidationException::withMessages([
                'employee_number' => ['The specified Employee ID was not found in Laijau records.'],
            ]);
        }

        if (!$employee->attendance_access_enabled || $employee->status !== 'active') {
            throw ValidationException::withMessages([
                'employee_number' => ['Attendance access has been disabled for this employee. Please contact the showroom administrator.'],
            ]);
        }

        if ($employee->isAttendanceLocked()) {
            $minutes = $employee->attendance_locked_until ? ceil(now()->diffInSeconds($employee->attendance_locked_until) / 60) : 15;
            throw ValidationException::withMessages([
                'pin' => ["Account temporarily locked due to failed attempts. Please retry in {$minutes} minutes or ask Admin to reset your PIN."],
            ]);
        }

        // Validate phone number
        $dbPhone = preg_replace('/[^0-9]/', '', (string)$employee->phone);
        if (empty($dbPhone) || (substr($dbPhone, -8) !== substr($cleanPhone, -8) && $dbPhone !== $cleanPhone)) {
            $employee->recordFailedPinAttempt();
            throw ValidationException::withMessages([
                'phone' => ['Registered phone number does not match employee records.'],
            ]);
        }

        // Validate Admin-created PIN
        if (!$employee->hasAttendancePin()) {
            throw ValidationException::withMessages([
                'pin' => ['No attendance PIN has been configured for this employee yet. Please ask the showroom administrator to generate your PIN first.'],
            ]);
        }

        if (!$employee->verifyAttendancePin($pin)) {
            $locked = $employee->recordFailedPinAttempt();
            $msg = $locked
                ? 'Too many failed PIN attempts. Account temporarily locked.'
                : 'Invalid attendance PIN. Please check the credentials provided by the administrator.';

            throw ValidationException::withMessages([
                'pin' => [$msg],
            ]);
        }

        // Credentials fully verified! Clear any failure count
        $employee->clearFailedPinAttempts();

        return $employee;
    }

    /**
     * Register a new device for the employee during first-time setup.
     */
    public function registerDevice(Employee $employee, array $deviceMeta, ?string $photoDataUrl = null): array
    {
        // Enforce max active devices per employee
        $maxDevices = AttendanceSetting::get('max_allowed_devices', 2);
        $activeDevices = $employee->activeAttendanceDevices()->orderBy('last_seen_at', 'asc')->get();

        if ($activeDevices->count() >= $maxDevices) {
            // Revoke the oldest device to make room for the new one cleanly
            $oldest = $activeDevices->first();
            $oldest->revoke(null, 'Automatically revoked: new device registered exceeding max limit (' . $maxDevices . ')');

            AttendanceAuditLog::log(
                action: 'device_auto_revoked_limit',
                employeeId: $employee->id,
                details: "Device #{$oldest->id} ({$oldest->device_name}) auto-revoked due to max device limit of {$maxDevices}."
            );
        }

        // Generate high-entropy 64-char device token
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);

        // Save verification setup photo if provided
        $photoPath = null;
        if (!empty($photoDataUrl)) {
            $photoPath = $this->storeVerificationPhoto($photoDataUrl, $employee->id, 'setup');
        }

        $device = AttendanceDevice::create([
            'employee_id' => $employee->id,
            'device_token_hash' => $tokenHash,
            'device_name' => $deviceMeta['device_name'] ?? 'Mobile Device',
            'platform' => $deviceMeta['platform'] ?? 'Mobile',
            'browser' => $deviceMeta['browser'] ?? 'Browser',
            'user_agent' => request()->userAgent(),
            'ip_address' => request()->ip(),
            'is_active' => true,
            'registered_at' => now(),
            'last_seen_at' => now(),
        ]);

        AttendanceAuditLog::log(
            action: 'device_registered',
            employeeId: $employee->id,
            details: "Device '{$device->device_name}' ({$device->platform}) registered successfully.",
            newValues: ['device_id' => $device->id, 'device_name' => $device->device_name, 'platform' => $device->platform]
        );

        return [
            'device' => $device,
            'device_token' => $rawToken,
            'photo_path' => $photoPath,
        ];
    }

    /**
     * Authenticate an already-registered device using PIN.
     *
     * @throws ValidationException
     */
    public function authenticateWithPin(AttendanceDevice $device, string $pin): bool
    {
        $employee = $device->employee;

        if (!$device->isUsable()) {
            throw ValidationException::withMessages([
                'device' => ['This device has been revoked or attendance access is disabled. Please contact your administrator.'],
            ]);
        }

        if ($employee->isAttendanceLocked()) {
            $minutes = $employee->attendance_locked_until ? ceil(now()->diffInSeconds($employee->attendance_locked_until) / 60) : 15;
            throw ValidationException::withMessages([
                'pin' => ["Account locked due to consecutive failed attempts. Retry in {$minutes} minutes."],
            ]);
        }

        if (!$employee->verifyAttendancePin($pin)) {
            $locked = $employee->recordFailedPinAttempt();
            $msg = $locked
                ? 'Too many failed attempts. Device access temporarily locked.'
                : 'Invalid PIN. Please try again.';

            throw ValidationException::withMessages([
                'pin' => [$msg],
            ]);
        }

        $employee->clearFailedPinAttempts();
        $device->touchLastSeen(request()->ip());

        return true;
    }

    /**
     * Change employee attendance PIN.
     *
     * @throws ValidationException
     */
    public function changePin(Employee $employee, string $currentPin, string $newPin): void
    {
        if (!$employee->verifyAttendancePin($currentPin)) {
            throw ValidationException::withMessages([
                'current_pin' => ['The current PIN entered is incorrect.'],
            ]);
        }

        if (strlen($newPin) < 4 || strlen($newPin) > 8 || !ctype_digit($newPin)) {
            throw ValidationException::withMessages([
                'new_pin' => ['PIN must be between 4 and 8 numeric digits.'],
            ]);
        }

        $employee->setAttendancePin($newPin);

        AttendanceAuditLog::log(
            action: 'pin_changed_by_employee',
            employeeId: $employee->id,
            details: 'Employee changed their own attendance PIN from PWA.'
        );
    }

    /**
     * Submit an attendance event (Check In or Check Out).
     *
     * @throws ValidationException
     */
    public function submitAttendance(
        AttendanceDevice $device,
        string $type,
        array $data,
        ?string $photoDataUrl = null
    ): array {
        $employee = $device->employee;

        if (!$device->isUsable()) {
            throw ValidationException::withMessages([
                'device' => ['This device is revoked or attendance access is disabled.'],
            ]);
        }

        if (!AttendanceSetting::get('attendance_enabled', true)) {
            throw ValidationException::withMessages([
                'system' => ['The Laijau attendance system is currently offline for maintenance.'],
            ]);
        }

        // Authoritative server timestamp
        $serverTime = now();
        $today = $serverTime->toDateString();

        // 1. Idempotency Check (prevent double-taps on slow network)
        $idempotencyKey = $data['idempotency_key'] ?? null;
        if (!empty($idempotencyKey)) {
            $existing = AttendanceEvent::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return [
                    'success' => true,
                    'event' => $existing,
                    'message' => 'Attendance event already recorded (idempotent submission).',
                    'state' => $this->getEmployeeAttendanceState($employee, $serverTime),
                ];
            }
        }

        // 2. Validate State Progression
        $currentState = $this->getEmployeeAttendanceState($employee, $serverTime);

        if ($type === 'check_in') {
            if ($currentState['state'] === 'checked_in') {
                throw ValidationException::withMessages([
                    'type' => ['You are already checked in for today. Please check out first.'],
                ]);
            }
        } elseif ($type === 'check_out') {
            if ($currentState['state'] !== 'checked_in') {
                throw ValidationException::withMessages([
                    'type' => ['You must check in first before checking out.'],
                ]);
            }
        } else {
            throw ValidationException::withMessages([
                'type' => ['Invalid attendance event type.'],
            ]);
        }

        // 3. GPS Accuracy & Geofence Validation
        $latitude = isset($data['latitude']) ? (float)$data['latitude'] : null;
        $longitude = isset($data['longitude']) ? (float)$data['longitude'] : null;
        $accuracy = isset($data['accuracy_meters']) ? (float)$data['accuracy_meters'] : null;
        $clientCapturedAt = isset($data['client_captured_at']) ? Carbon::parse($data['client_captured_at']) : null;

        $maxAccuracy = AttendanceSetting::get('max_gps_accuracy_meters', 100);

        // GPS is strictly mandatory for Check In and Check Out (Zero fallback to IP or last known location)
        if ($latitude === null || $longitude === null || !is_numeric($data['latitude'] ?? null) || !is_numeric($data['longitude'] ?? null) || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            $this->recordRejectedEvent(
                $employee,
                $device,
                $type,
                $serverTime,
                null,
                null,
                null,
                null,
                'GPS coordinates missing or location permission denied.'
            );

            AttendanceAuditLog::log(
                action: 'attendance_gps_denied',
                employeeId: $employee->id,
                details: "Attendance {$type} attempt blocked: Location permission is required but was missing or denied."
            );

            throw ValidationException::withMessages([
                'gps' => ['Location permission is required to record attendance. Please enable Location access and try again.'],
            ]);
        }

        if ($accuracy === null || $accuracy > $maxAccuracy) {
            $measuredText = $accuracy !== null ? "±{$accuracy}m" : 'unknown';
            $this->recordRejectedEvent(
                $employee,
                $device,
                $type,
                $serverTime,
                $latitude,
                $longitude,
                $accuracy,
                null,
                "Location accuracy ({$measuredText}) exceeded maximum acceptable threshold ({$maxAccuracy}m)."
            );

            AttendanceAuditLog::log(
                action: 'attendance_gps_accuracy_rejected',
                employeeId: $employee->id,
                details: "Attendance {$type} attempt rejected: Measured accuracy {$measuredText} exceeded maximum allowed {$maxAccuracy}m."
            );

            throw ValidationException::withMessages([
                'gps' => ["Location accuracy is too low (measured: {$measuredText}, required: within {$maxAccuracy}m). Please move to an open area and try again."],
            ]);
        }

        // Geofencing Check
        $geofencingEnabled = AttendanceSetting::get('geofencing_enabled', true);
        $location = AttendanceLocation::where('is_active', true)->where('is_default', true)->first()
            ?? AttendanceLocation::where('is_active', true)->first();

        $distance = null;
        $geofencePassed = true;

        if ($location && $latitude !== null && $longitude !== null) {
            $distance = $location->calculateDistanceTo($latitude, $longitude);

            if ($geofencingEnabled && !$location->isWithinGeofence($latitude, $longitude)) {
                $geofencePassed = false;
                $radius = $location->radius_meters;

                $this->recordRejectedEvent(
                    $employee,
                    $device,
                    $type,
                    $serverTime,
                    $latitude,
                    $longitude,
                    $accuracy,
                    $location->id,
                    "Outside permitted attendance location. Distance: {$distance}m, Permitted radius: {$radius}m."
                );

                throw ValidationException::withMessages([
                    'geofence' => ["You are outside the permitted attendance location ({$location->name}). Current distance: {$distance}m (permitted: {$radius}m)."],
                ]);
            }
        }

        // 4. Photo Verification
        $photoRequired = AttendanceSetting::get('photo_required', true);
        if ($photoRequired && empty($photoDataUrl)) {
            throw ValidationException::withMessages([
                'photo' => ['Verification photo is required. Please capture a clear photo to proceed.'],
            ]);
        }

        $photoPath = null;
        if (!empty($photoDataUrl)) {
            $photoPath = $this->storeVerificationPhoto($photoDataUrl, $employee->id, $type);
        }

        // 5. Commit Transaction: Create Attendance Event & Update Location & Timesheet
        $event = DB::transaction(function () use (
            $employee,
            $device,
            $location,
            $type,
            $serverTime,
            $clientCapturedAt,
            $latitude,
            $longitude,
            $accuracy,
            $distance,
            $geofencePassed,
            $photoPath,
            $data,
            $idempotencyKey
        ) {
            $event = AttendanceEvent::create([
                'employee_id' => $employee->id,
                'device_id' => $device->id,
                'attendance_location_id' => $location?->id,
                'type' => $type,
                'status' => 'verified',
                'server_recorded_at' => $serverTime,
                'client_captured_at' => $clientCapturedAt,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'accuracy_meters' => $accuracy,
                'distance_from_location_meters' => $distance,
                'geofence_passed' => $geofencePassed,
                'photo_path' => $photoPath,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'verification_method' => $data['verification_method'] ?? 'pin',
                'idempotency_key' => $idempotencyKey,
                'metadata' => [
                    'device_name' => $device->device_name,
                    'platform' => $device->platform,
                    'browser' => $device->browser,
                ],
            ]);

            // Save location update
            if ($latitude !== null && $longitude !== null) {
                AttendanceLocationUpdate::create([
                    'employee_id' => $employee->id,
                    'device_id' => $device->id,
                    'attendance_event_id' => $event->id,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'accuracy_meters' => $accuracy,
                    'recorded_at' => $serverTime,
                ]);
            }

            $device->touchLastSeen(request()->ip());

            // Sync with existing ERP Timesheets for payroll integrity
            $this->syncToTimesheet($event);

            return $event;
        });

        $actionText = $type === 'check_in' ? 'Check In' : 'Check Out';

        return [
            'success' => true,
            'event' => $event,
            'message' => "{$actionText} recorded successfully at " . $serverTime->format('h:i A') . '.',
            'state' => $this->getEmployeeAttendanceState($employee, $serverTime),
        ];
    }

    /**
     * Record a location heartbeat update while PWA is actively open in the foreground.
     */
    public function recordHeartbeat(AttendanceDevice $device, float $latitude, float $longitude, ?float $accuracy = null): ?AttendanceLocationUpdate
    {
        $employee = $device->employee;

        if (!$device->isUsable()) {
            return null;
        }

        // Only record heartbeat if employee is currently checked in today
        $currentState = $this->getEmployeeAttendanceState($employee, now());
        if ($currentState['state'] !== 'checked_in') {
            return null;
        }

        $device->touchLastSeen(request()->ip());

        return AttendanceLocationUpdate::create([
            'employee_id' => $employee->id,
            'device_id' => $device->id,
            'attendance_event_id' => $currentState['check_in_event']?->id,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'accuracy_meters' => $accuracy,
            'recorded_at' => now(),
        ]);
    }

    /**
     * Synchronize attendance event into hrm_timesheets for HR/Payroll calculation.
     */
    public function syncToTimesheet(AttendanceEvent $event): void
    {
        try {
            $date = $event->server_recorded_at->toDateString();
            $timeStr = $event->server_recorded_at->toTimeString();

            $timesheet = Timesheet::firstOrNew([
                'employee_id' => $event->employee_id,
                'date' => $date,
            ]);

            if ($event->type === 'check_in') {
                if (empty($timesheet->clock_in)) {
                    $timesheet->clock_in = $timeStr;
                    $timesheet->clock_in_latitude = $event->latitude;
                    $timesheet->clock_in_longitude = $event->longitude;
                    $timesheet->clock_in_accuracy = $event->accuracy_meters;
                    $timesheet->status = 'submitted';
                    $timesheet->attendance_status = 'present';
                    $timesheet->location = $event->location?->name ?? 'Laijau Showroom';

                    // Check late calculation (default shift: 10:00:00)
                    $shiftStart = Carbon::parse("{$date} 10:00:00");
                    if ($event->server_recorded_at->greaterThan($shiftStart)) {
                        $timesheet->is_late = true;
                        $timesheet->late_minutes = (int) abs(round($event->server_recorded_at->diffInMinutes($shiftStart)));
                    }
                }
            } elseif ($event->type === 'check_out') {
                $timesheet->clock_out = $timeStr;
                $timesheet->clock_out_latitude = $event->latitude;
                $timesheet->clock_out_longitude = $event->longitude;
                $timesheet->clock_out_accuracy = $event->accuracy_meters;

                if (!empty($timesheet->clock_in)) {
                    $in = Carbon::parse("{$date} {$timesheet->clock_in}");
                    $out = Carbon::parse("{$date} {$timeStr}");
                    $totalHours = max(0, round($out->diffInMinutes($in) / 60, 2));
                    $timesheet->regular_hours = min(8.00, $totalHours);
                    $timesheet->overtime_hours = max(0.00, round($totalHours - 8.00, 2));
                }
            }

            $timesheet->save();
        } catch (\Throwable $e) {
            Log::warning('Could not sync attendance event to timesheet: ' . $e->getMessage());
        }
    }

    /**
     * Determine an employee's attendance state for a given date.
     */
    public function getEmployeeAttendanceState(Employee $employee, ?Carbon $date = null): array
    {
        $targetDate = $date ?? now();
        $startOfDay = $targetDate->copy()->startOfDay();
        $endOfDay = $targetDate->copy()->endOfDay();

        $events = AttendanceEvent::where('employee_id', $employee->id)
            ->where('status', 'verified')
            ->whereBetween('server_recorded_at', [$startOfDay, $endOfDay])
            ->orderBy('server_recorded_at', 'asc')
            ->get();

        $checkIn = $events->where('type', 'check_in')->last();
        $checkOut = $events->where('type', 'check_out')->last();

        $state = 'not_checked_in';
        if ($checkIn && !$checkOut) {
            $state = 'checked_in';
        } elseif ($checkIn && $checkOut) {
            $state = 'checked_out';
        }

        return [
            'state' => $state,
            'check_in_event' => $checkIn,
            'check_out_event' => $checkOut,
            'check_in_time' => $checkIn?->server_recorded_at?->format('h:i A'),
            'check_out_time' => $checkOut?->server_recorded_at?->format('h:i A'),
            'formatted_date' => $targetDate->format('l, F j, Y'),
        ];
    }

    /**
     * Get recent attendance events for an employee.
     */
    public function getEmployeeRecentHistory(Employee $employee, int $limit = 10): array
    {
        $events = AttendanceEvent::with('location')
            ->where('employee_id', $employee->id)
            ->where('status', 'verified')
            ->orderBy('server_recorded_at', 'desc')
            ->limit($limit * 2)
            ->get();

        // Group by calendar date
        $grouped = [];
        foreach ($events as $ev) {
            $d = $ev->server_recorded_at->format('Y-m-d');
            if (!isset($grouped[$d])) {
                $grouped[$d] = [
                    'date' => $ev->server_recorded_at->format('M d, Y'),
                    'day' => $ev->server_recorded_at->format('l'),
                    'check_in' => null,
                    'check_out' => null,
                    'location' => $ev->location?->name ?? 'Laijau Showroom',
                ];
            }

            if ($ev->type === 'check_in' && !$grouped[$d]['check_in']) {
                $grouped[$d]['check_in'] = $ev->server_recorded_at->format('h:i A');
            } elseif ($ev->type === 'check_out' && !$grouped[$d]['check_out']) {
                $grouped[$d]['check_out'] = $ev->server_recorded_at->format('h:i A');
            }
        }

        return array_slice(array_values($grouped), 0, $limit);
    }

    /**
     * Store an attendance verification photo securely on private disk.
     */
    public function storeVerificationPhoto(string $photoDataUrl, int $employeeId, string $context): string
    {
        // Extract base64 image data
        if (preg_match('/^data:image\/(\w+);base64,/', $photoDataUrl, $type)) {
            $data = substr($photoDataUrl, strpos($photoDataUrl, ',') + 1);
            $ext = strtolower($type[1]); // jpg, png, jpeg
            if ($ext === 'jpeg') {
                $ext = 'jpg';
            }
        } else {
            $data = $photoDataUrl;
            $ext = 'jpg';
        }

        $decoded = base64_decode($data);
        if ($decoded === false) {
            throw ValidationException::withMessages(['photo' => ['Invalid photo image payload.']]);
        }

        // Limit size to max 2MB
        if (strlen($decoded) > 2 * 1024 * 1024) {
            throw ValidationException::withMessages(['photo' => ['Verification photo size cannot exceed 2MB.']]);
        }

        $filename = Str::uuid() . "_{$context}.{$ext}";
        $subfolder = 'attendance/photos/' . date('Y/m');
        $fullPath = "{$subfolder}/{$filename}";

        Storage::disk('local')->put($fullPath, $decoded);

        return $fullPath;
    }

    /**
     * Record a rejected attendance attempt for security and audit analysis.
     */
    protected function recordRejectedEvent(
        Employee $employee,
        AttendanceDevice $device,
        string $type,
        Carbon $serverTime,
        ?float $latitude,
        ?float $longitude,
        ?float $accuracy,
        ?int $locationId,
        string $rejectionReason
    ): AttendanceEvent {
        return AttendanceEvent::create([
            'employee_id' => $employee->id,
            'device_id' => $device->id,
            'attendance_location_id' => $locationId,
            'type' => $type,
            'status' => 'rejected',
            'server_recorded_at' => $serverTime,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'accuracy_meters' => $accuracy,
            'geofence_passed' => false,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'rejection_reason' => $rejectionReason,
        ]);
    }
}
