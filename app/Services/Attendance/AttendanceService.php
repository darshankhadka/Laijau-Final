<?php

namespace App\Services\Attendance;

use App\Models\Attendance\AttendanceAuditLog;
use App\Models\Attendance\AttendanceDevice;
use App\Models\Attendance\AttendanceEvent;
use App\Models\Attendance\AttendanceLocation;
use App\Models\Attendance\AttendanceLocationUpdate;
use App\Models\Attendance\AttendanceSession;
use App\Models\Attendance\AttendanceSetting;
use App\Models\Hrm\Employee;
use App\Models\Hrm\Timesheet;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    /**
     * Authenticate an employee using their unique attendance PIN.
     *
     * @throws ValidationException
     */
    public function authenticateByPin(string $pin, ?string $employeeHint = null): Employee
    {
        $cleanPin = trim($pin);

        if (empty($cleanPin)) {
            throw ValidationException::withMessages([
                'pin' => ['Please enter your attendance PIN.'],
            ]);
        }

        // 1. Fast O(1) lookup using deterministic salted hash
        $lookupHash = Employee::hashPinForLookup($cleanPin);
        $employee = Employee::where('attendance_pin_lookup_hash', $lookupHash)->first();

        // 2. Fallback / backfill for existing records that have attendance_pin_hash but no lookup hash yet
        if (!$employee) {
            $unindexedEmployees = Employee::whereNotNull('attendance_pin_hash')
                ->whereNull('attendance_pin_lookup_hash')
                ->get();

            foreach ($unindexedEmployees as $candidate) {
                if (Hash::check($cleanPin, $candidate->attendance_pin_hash)) {
                    $candidate->update(['attendance_pin_lookup_hash' => $lookupHash]);
                    $employee = $candidate;
                    break;
                }
            }
        }

        // 3. Fallback support if employee provided employee number or phone along with PIN
        if (!$employee && !empty($employeeHint)) {
            $cleanHint = strtoupper(trim($employeeHint));
            $candidate = Employee::whereRaw('UPPER(employee_number) = ?', [$cleanHint])
                ->orWhere('phone', preg_replace('/[^0-9]/', '', $employeeHint))
                ->first();

            if ($candidate && $candidate->verifyAttendancePin($cleanPin)) {
                $candidate->update(['attendance_pin_lookup_hash' => $lookupHash]);
                $employee = $candidate;
            } elseif ($candidate) {
                $locked = $candidate->recordFailedPinAttempt();
                $msg = $locked
                    ? 'Too many failed PIN attempts. Account temporarily locked.'
                    : 'Invalid attendance PIN. Please try again.';

                throw ValidationException::withMessages(['pin' => [$msg]]);
            }
        }

        // If employee not resolved
        if (!$employee) {
            throw ValidationException::withMessages([
                'pin' => ['Invalid attendance PIN. Please check the credentials provided by the showroom administrator.'],
            ]);
        }

        // Validate employee access status
        if (!$employee->attendance_access_enabled || $employee->status !== 'active') {
            throw ValidationException::withMessages([
                'pin' => ['Attendance access has been disabled for this employee. Please contact the showroom administrator.'],
            ]);
        }

        // Validate lockout
        if ($employee->isAttendanceLocked()) {
            $minutes = $employee->attendance_locked_until ? ceil(now()->diffInSeconds($employee->attendance_locked_until) / 60) : 15;
            throw ValidationException::withMessages([
                'pin' => ["Account temporarily locked due to consecutive failed attempts. Please retry in {$minutes} minutes or ask Admin to reset your PIN."],
            ]);
        }

        // Verify with bcrypt for cryptographic defense-in-depth
        if (!$employee->verifyAttendancePin($cleanPin)) {
            $locked = $employee->recordFailedPinAttempt();
            $msg = $locked
                ? 'Too many failed PIN attempts. Account temporarily locked.'
                : 'Invalid attendance PIN. Please try again.';

            throw ValidationException::withMessages(['pin' => [$msg]]);
        }

        // Success: Clear any previous failed attempts
        $employee->clearFailedPinAttempts();

        return $employee;
    }

    /**
     * Generate a unique numeric 6-digit PIN that is not currently assigned to any employee.
     */
    public function generateUniquePin(): string
    {
        $attempts = 0;
        do {
            $pin = (string) random_int(100000, 999999);
            $lookupHash = Employee::hashPinForLookup($pin);
            $exists = Employee::where('attendance_pin_lookup_hash', $lookupHash)->exists();
            $attempts++;
        } while ($exists && $attempts < 100);

        return $pin;
    }

    /**
     * Set or reset an employee's unique attendance PIN.
     *
     * @throws ValidationException
     */
    public function setEmployeePin(Employee $employee, string $pin): void
    {
        $cleanPin = trim($pin);

        if (strlen($cleanPin) < 4 || strlen($cleanPin) > 8 || !ctype_digit($cleanPin)) {
            throw ValidationException::withMessages([
                'pin' => ['PIN must be between 4 and 8 numeric digits.'],
            ]);
        }

        // Verify uniqueness
        $lookupHash = Employee::hashPinForLookup($cleanPin);
        $duplicate = Employee::where('attendance_pin_lookup_hash', $lookupHash)
            ->where('id', '!=', $employee->id)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'pin' => ['This PIN is already assigned to another employee. Please generate or enter a unique PIN.'],
            ]);
        }

        $employee->setAttendancePin($cleanPin);

        AttendanceAuditLog::log(
            action: 'pin_configured',
            employeeId: $employee->id,
            details: "Unique attendance PIN configured for employee {$employee->employee_number} ({$employee->full_name})."
        );
    }

    /**
     * Change employee attendance PIN from employee self-service.
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

        $this->setEmployeePin($employee, $newPin);

        AttendanceAuditLog::log(
            action: 'pin_changed_by_employee',
            employeeId: $employee->id,
            details: 'Employee changed their attendance PIN from PWA.'
        );
    }

    /**
     * Submit an attendance event (Clock In or Clock Out) with GPS location validation.
     *
     * @throws ValidationException
     */
    public function submitAttendance(
        Employee $employee,
        string $type,
        array $data,
        ?AttendanceDevice $device = null
    ): array {
        if (!$employee->attendance_access_enabled || $employee->status !== 'active') {
            throw ValidationException::withMessages([
                'employee' => ['Attendance access is disabled for this account.'],
            ]);
        }

        if (!AttendanceSetting::get('attendance_enabled', true)) {
            throw ValidationException::withMessages([
                'system' => ['The Laijau attendance system is currently offline for maintenance.'],
            ]);
        }

        // Authoritative server timestamp (Nepal Time)
        $serverTime = now();

        // 1. Idempotency Check (prevent duplicate clicks)
        $idempotencyKey = $data['idempotency_key'] ?? null;
        if (!empty($idempotencyKey)) {
            $existing = AttendanceEvent::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return [
                    'success' => true,
                    'event' => $existing,
                    'message' => 'Attendance event already recorded.',
                    'state' => $this->getEmployeeAttendanceState($employee, $serverTime),
                ];
            }
        }

        // 2. Validate Authoritative State Progression
        // Check if employee currently has an active open session
        $openSession = AttendanceSession::where('employee_id', $employee->id)
            ->where('status', 'open')
            ->first();

        if (!$openSession && $type === 'check_out') {
            // Self-healing fallback: Check if a check_in event without check_out exists today
            $todayStart = $serverTime->copy()->timezone('Asia/Kathmandu')->startOfDay();
            $todayEnd = $serverTime->copy()->timezone('Asia/Kathmandu')->endOfDay();
            $lastEvent = AttendanceEvent::where('employee_id', $employee->id)
                ->where('status', 'verified')
                ->whereBetween('server_recorded_at', [$todayStart, $todayEnd])
                ->latest('server_recorded_at')
                ->first();

            if ($lastEvent && $lastEvent->type === 'check_in') {
                $openSession = AttendanceSession::create([
                    'employee_id' => $employee->id,
                    'date' => $serverTime->copy()->timezone('Asia/Kathmandu')->toDateString(),
                    'session_number' => 1,
                    'status' => 'open',
                    'clock_in_at' => $lastEvent->server_recorded_at,
                    'clock_in_time' => $lastEvent->server_recorded_at->format('H:i:s'),
                    'clock_in_event_id' => $lastEvent->id,
                    'clock_in_latitude' => $lastEvent->latitude,
                    'clock_in_longitude' => $lastEvent->longitude,
                    'clock_in_accuracy' => $lastEvent->accuracy_meters,
                    'clock_in_location_name' => $lastEvent->location?->name ?? 'Laijau Showroom',
                ]);
                $lastEvent->update(['session_id' => $openSession->id]);
            }
        }

        if ($type === 'check_in') {
            if ($openSession) {
                throw ValidationException::withMessages([
                    'type' => ['You already have an active Clock In session. Please Clock Out before starting a new session.'],
                ]);
            }
        } elseif ($type === 'check_out') {
            if (!$openSession) {
                throw ValidationException::withMessages([
                    'type' => ['You must Clock In first before Clocking Out.'],
                ]);
            }
        } else {
            throw ValidationException::withMessages([
                'type' => ['Invalid attendance event type.'],
            ]);
        }

        // 3. GPS Coordinates & Accuracy Validation
        $latitude = isset($data['latitude']) && is_numeric($data['latitude']) ? (float)$data['latitude'] : null;
        $longitude = isset($data['longitude']) && is_numeric($data['longitude']) ? (float)$data['longitude'] : null;
        $accuracy = isset($data['accuracy_meters']) && is_numeric($data['accuracy_meters']) ? (float)$data['accuracy_meters'] : null;
        $clientCapturedAt = isset($data['client_captured_at']) ? Carbon::parse($data['client_captured_at']) : null;

        $maxAccuracy = (float) AttendanceSetting::get('max_gps_accuracy_meters', 100);
        $canPunchAnywhere = $employee->canPunchFromAnywhere();

        // Strict GPS requirement: coordinates must be present and valid for regular staff
        if ($latitude === null || $longitude === null || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            if (!$canPunchAnywhere) {
                $this->recordRejectedEvent(
                    $employee,
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
        }

        // GPS Accuracy check (exempt for employees authorized to punch from anywhere)
        if (($latitude !== null && $longitude !== null) && ($accuracy === null || $accuracy > $maxAccuracy)) {
            if (!$canPunchAnywhere) {
                $measuredText = $accuracy !== null ? "±{$accuracy}m" : 'unknown';
                $this->recordRejectedEvent(
                    $employee,
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
        }

        // 4. Geofencing Validation
        $geofencingEnabled = AttendanceSetting::get('geofencing_enabled', true);
        $location = AttendanceLocation::where('is_active', true)->where('is_default', true)->first()
            ?? AttendanceLocation::where('is_active', true)->first();

        $distance = null;
        $geofencePassed = true;

        if ($location && $latitude !== null && $longitude !== null) {
            $distance = $location->calculateDistanceTo($latitude, $longitude);

            if ($geofencingEnabled && !$location->isWithinGeofence($latitude, $longitude)) {
                if (!$canPunchAnywhere) {
                    $geofencePassed = false;
                    $radius = $location->radius_meters;

                    $this->recordRejectedEvent(
                        $employee,
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
                } else {
                    $geofencePassed = true;
                }
            }
        }

        // 5. Commit Transaction: Record Verified Attendance Event & Timesheet Sync
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
            $data,
            $idempotencyKey,
            $canPunchAnywhere
        ) {
            $event = AttendanceEvent::create([
                'employee_id' => $employee->id,
                'device_id' => $device?->id,
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
                'photo_path' => null, // Photo requirement completely removed
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'verification_method' => $data['verification_method'] ?? 'pin',
                'idempotency_key' => $idempotencyKey,
                'metadata' => [
                    'source' => 'pwa_pin',
                    'remote_punch_authorized' => $canPunchAnywhere,
                ],
            ]);

            $todayDate = $serverTime->copy()->timezone('Asia/Kathmandu')->toDateString();
            $timeString = $serverTime->copy()->timezone('Asia/Kathmandu')->format('H:i:s');
            $session = null;

            if ($type === 'check_in') {
                $sessionCount = AttendanceSession::where('employee_id', $employee->id)
                    ->where('date', $todayDate)
                    ->count();

                $session = AttendanceSession::create([
                    'employee_id' => $employee->id,
                    'date' => $todayDate,
                    'session_number' => $sessionCount + 1,
                    'status' => 'open',
                    'clock_in_at' => $serverTime,
                    'clock_in_time' => $timeString,
                    'clock_in_event_id' => $event->id,
                    'clock_in_latitude' => $latitude,
                    'clock_in_longitude' => $longitude,
                    'clock_in_accuracy' => $accuracy,
                    'clock_in_location_name' => $location?->name ?? 'Laijau Showroom',
                    'clock_in_device_info' => request()->userAgent(),
                    'clock_in_ip' => request()->ip(),
                    'metadata' => [
                        'remote_punch_authorized' => $canPunchAnywhere,
                        'verification_method' => $data['verification_method'] ?? 'pin',
                    ],
                ]);

                $event->update(['session_id' => $session->id]);
            } elseif ($type === 'check_out') {
                $session = AttendanceSession::where('employee_id', $employee->id)
                    ->where('status', 'open')
                    ->latest('clock_in_at')
                    ->first();

                if ($session) {
                    $diffMinutes = max(0, (int) $session->clock_in_at->diffInMinutes($serverTime));
                    $diffHours = round($diffMinutes / 60, 2);
                    $hours = floor($diffMinutes / 60);
                    $mins = $diffMinutes % 60;
                    $formattedDuration = "{$hours}h {$mins}m";

                    $session->update([
                        'status' => 'completed',
                        'clock_out_at' => $serverTime,
                        'clock_out_time' => $timeString,
                        'clock_out_event_id' => $event->id,
                        'clock_out_latitude' => $latitude,
                        'clock_out_longitude' => $longitude,
                        'clock_out_accuracy' => $accuracy,
                        'clock_out_location_name' => $location?->name ?? 'Laijau Showroom',
                        'clock_out_device_info' => request()->userAgent(),
                        'clock_out_ip' => request()->ip(),
                        'duration_minutes' => $diffMinutes,
                        'duration_hours' => $diffHours,
                        'duration_formatted' => $formattedDuration,
                    ]);

                    $event->update(['session_id' => $session->id]);
                }
            }

            // Save location update for map tracking
            if ($latitude !== null && $longitude !== null) {
                AttendanceLocationUpdate::create([
                    'employee_id' => $employee->id,
                    'device_id' => $device?->id,
                    'attendance_event_id' => $event->id,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'accuracy_meters' => $accuracy,
                    'recorded_at' => $serverTime,
                ]);
            }

            // Synchronize with ERP Timesheets for HR & Payroll integrity
            $this->syncToTimesheet($event, $session);

            return $event;
        });

        $actionText = $type === 'check_in' ? 'Clock In' : 'Clock Out';

        return [
            'success' => true,
            'event' => $event,
            'message' => "{$actionText} recorded successfully at " . $serverTime->copy()->timezone('Asia/Kathmandu')->format('h:i A') . '.',
            'state' => $this->getEmployeeAttendanceState($employee, $serverTime),
        ];
    }

    /**
     * Record a location heartbeat while employee is actively clocked in.
     */
    public function recordHeartbeat(Employee $employee, float $latitude, float $longitude, ?float $accuracy = null): ?AttendanceLocationUpdate
    {
        $currentState = $this->getEmployeeAttendanceState($employee, now());
        if ($currentState['state'] !== 'checked_in') {
            return null;
        }

        return AttendanceLocationUpdate::create([
            'employee_id' => $employee->id,
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
    public function syncToTimesheet(AttendanceEvent $event, ?AttendanceSession $currentSession = null): void
    {
        try {
            $date = $event->server_recorded_at->copy()->timezone('Asia/Kathmandu')->toDateString();

            $timesheet = Timesheet::firstOrNew([
                'employee_id' => $event->employee_id,
                'date' => $date,
            ]);

            if (!$timesheet->exists) {
                $timesheet->location = $event->location?->name ?? 'Laijau Showroom';
                $timesheet->shift_name = 'Showroom Retail Shift';
                $timesheet->shift_start_time = AttendanceSetting::get('shift_start_time', '10:00:00');
                $timesheet->shift_end_time = AttendanceSetting::get('shift_end_time', '19:00:00');
                $timesheet->status = 'submitted';
                $timesheet->attendance_status = 'present';
                $timesheet->save();
            }

            if ($currentSession) {
                $currentSession->update(['timesheet_id' => $timesheet->id]);
            }

            // Fetch all sessions for this employee and date
            $allSessions = AttendanceSession::where('employee_id', $event->employee_id)
                ->where('date', $date)
                ->orderBy('session_number', 'asc')
                ->get();

            $firstSession = $allSessions->first();
            $completedSessions = $allSessions->where('status', 'completed');
            $lastCompleted = $completedSessions->last();

            // First Clock In of the day
            if ($firstSession) {
                $timesheet->clock_in = $firstSession->clock_in_time;
                $timesheet->clock_in_latitude = $firstSession->clock_in_latitude;
                $timesheet->clock_in_longitude = $firstSession->clock_in_longitude;
                $timesheet->clock_in_accuracy = $firstSession->clock_in_accuracy;

                $shiftStartStr = $timesheet->shift_start_time ?: AttendanceSetting::get('shift_start_time', '10:00:00');
                if (strlen($shiftStartStr) === 5) {
                    $shiftStartStr .= ':00';
                }
                $shiftStart = Carbon::parse("{$date} {$shiftStartStr}");
                $graceMinutes = (int) AttendanceSetting::get('shift_grace_minutes', 15);
                $shiftStartWithGrace = (clone $shiftStart)->addMinutes($graceMinutes);

                if ($firstSession->clock_in_at->greaterThan($shiftStartWithGrace)) {
                    $timesheet->is_late = true;
                    $timesheet->late_minutes = (int) abs(round($firstSession->clock_in_at->diffInMinutes($shiftStart)));
                } else {
                    $timesheet->is_late = false;
                    $timesheet->late_minutes = 0;
                }
            }

            // Last Clock Out of the day
            if ($lastCompleted) {
                $timesheet->clock_out = $lastCompleted->clock_out_time;
                $timesheet->clock_out_latitude = $lastCompleted->clock_out_latitude;
                $timesheet->clock_out_longitude = $lastCompleted->clock_out_longitude;
                $timesheet->clock_out_accuracy = $lastCompleted->clock_out_accuracy;

                // Early departure check if no session is currently open
                if ($allSessions->where('status', 'open')->isEmpty()) {
                    $shiftEndStr = $timesheet->shift_end_time ?: AttendanceSetting::get('shift_end_time', '19:00:00');
                    if (strlen($shiftEndStr) === 5) {
                        $shiftEndStr .= ':00';
                    }
                    $shiftEnd = Carbon::parse("{$date} {$shiftEndStr}");
                    if (AttendanceSetting::get('track_early_departure', true) && $lastCompleted->clock_out_at->lessThan($shiftEnd)) {
                        $timesheet->is_early_departure = true;
                        $timesheet->early_departure_minutes = (int) abs(round($shiftEnd->diffInMinutes($lastCompleted->clock_out_at)));
                    } else {
                        $timesheet->is_early_departure = false;
                        $timesheet->early_departure_minutes = 0;
                    }
                }
            }

            // Aggregated totals across all sessions
            $totalWorkedMinutes = (int) $completedSessions->sum('duration_minutes');
            $totalWorkedHours = round($totalWorkedMinutes / 60, 2);

            $timesheet->total_sessions = $allSessions->count();
            $timesheet->total_worked_minutes = $totalWorkedMinutes;
            $timesheet->total_worked_hours = $totalWorkedHours;

            $stdDailyHours = (float) AttendanceSetting::get('standard_daily_hours', 8.00);
            $timesheet->regular_hours = min($stdDailyHours, $totalWorkedHours);
            if (AttendanceSetting::get('overtime_enabled', true) && $totalWorkedHours > $stdDailyHours) {
                $timesheet->overtime_hours = max(0.00, round($totalWorkedHours - $stdDailyHours, 2));
            } else {
                $timesheet->overtime_hours = 0.00;
            }

            $timesheet->status = $timesheet->status ?: 'submitted';
            if ($totalWorkedHours >= 7.0) {
                $timesheet->attendance_status = 'present';
            } elseif ($totalWorkedHours >= 3.5) {
                $timesheet->attendance_status = 'half_day';
            } else {
                $timesheet->attendance_status = 'present';
            }

            $timesheet->sessions_summary = $allSessions->map(function ($s) {
                return [
                    'session_id' => $s->id,
                    'session_number' => $s->session_number,
                    'status' => $s->status,
                    'clock_in_at' => $s->clock_in_at?->timezone('Asia/Kathmandu')->toDateTimeString(),
                    'clock_in_time' => $s->clock_in_at?->timezone('Asia/Kathmandu')->format('h:i A'),
                    'clock_in_latitude' => $s->clock_in_latitude,
                    'clock_in_longitude' => $s->clock_in_longitude,
                    'clock_in_accuracy' => $s->clock_in_accuracy,
                    'clock_in_location' => $s->clock_in_location_name,
                    'clock_out_at' => $s->clock_out_at?->timezone('Asia/Kathmandu')->toDateTimeString(),
                    'clock_out_time' => $s->clock_out_at?->timezone('Asia/Kathmandu')->format('h:i A'),
                    'clock_out_latitude' => $s->clock_out_latitude,
                    'clock_out_longitude' => $s->clock_out_longitude,
                    'clock_out_accuracy' => $s->clock_out_accuracy,
                    'clock_out_location' => $s->clock_out_location_name,
                    'duration_minutes' => $s->duration_minutes,
                    'duration_hours' => (float) $s->duration_hours,
                    'duration_formatted' => $s->duration_formatted,
                ];
            })->values()->toArray();

            $timesheet->save();
        } catch (\Throwable $e) {
            Log::warning('Could not sync attendance event to timesheet: ' . $e->getMessage());
        }
    }

    /**
     * Authoritative calculation of employee's attendance state for a given date.
     * Returns:
     * - 'not_checked_in' -> Show Clock In (no session today or ready for next session)
     * - 'checked_in'     -> Show Clock Out (active open session)
     * - 'checked_out'    -> Previous session completed, ready for next Clock In
     */
    public function getEmployeeAttendanceState(Employee $employee, ?Carbon $date = null): array
    {
        $targetDate = ($date ?? now())->timezone('Asia/Kathmandu');
        $dateStr = $targetDate->toDateString();

        $allSessions = AttendanceSession::with(['clockInEvent', 'clockOutEvent'])
            ->where('employee_id', $employee->id)
            ->where('date', $dateStr)
            ->orderBy('session_number', 'asc')
            ->get();

        $openSession = $allSessions->where('status', 'open')->last();
        $completedSessions = $allSessions->where('status', 'completed');
        $firstSession = $allSessions->first();
        $lastCompleted = $completedSessions->last();

        $totalCompletedMinutes = (int) $completedSessions->sum('duration_minutes');
        $totalCompletedHours = round($totalCompletedMinutes / 60, 2);

        $state = 'not_checked_in';
        $currentSessionData = null;
        $activeDurationMinutes = 0;
        $activeDurationFormatted = '0h 0m';

        if ($openSession) {
            $state = 'checked_in';
            $activeDurationMinutes = max(0, (int) $openSession->clock_in_at->diffInMinutes(now()));
            $hours = floor($activeDurationMinutes / 60);
            $mins = $activeDurationMinutes % 60;
            $activeDurationFormatted = "{$hours}h {$mins}m";

            $currentSessionData = [
                'id' => $openSession->id,
                'session_number' => $openSession->session_number,
                'status' => 'open',
                'clock_in_time' => $openSession->clock_in_at?->timezone('Asia/Kathmandu')->format('h:i A'),
                'clock_in_iso' => $openSession->clock_in_at?->toIso8601String(),
                'clock_in_latitude' => $openSession->clock_in_latitude,
                'clock_in_longitude' => $openSession->clock_in_longitude,
                'clock_in_location' => $openSession->clock_in_location_name,
                'active_duration_minutes' => $activeDurationMinutes,
                'active_duration_formatted' => $activeDurationFormatted,
            ];
        } elseif ($completedSessions->isNotEmpty()) {
            $state = 'checked_out';
        }

        // Today's total worked minutes (completed + active if open)
        $totalWorkedMinutesToday = $totalCompletedMinutes + $activeDurationMinutes;
        $todayHours = floor($totalWorkedMinutesToday / 60);
        $todayMins = $totalWorkedMinutesToday % 60;
        $totalWorkedFormatted = "{$todayHours}h {$todayMins}m";

        $sessionsList = $allSessions->map(function ($s) {
            return [
                'id' => $s->id,
                'session_number' => $s->session_number,
                'status' => $s->status,
                'clock_in_time' => $s->clock_in_at?->timezone('Asia/Kathmandu')->format('h:i A'),
                'clock_in_iso' => $s->clock_in_at?->toIso8601String(),
                'clock_in_location' => $s->clock_in_location_name ?? 'Laijau Showroom',
                'clock_in_latitude' => $s->clock_in_latitude,
                'clock_in_longitude' => $s->clock_in_longitude,
                'clock_out_time' => $s->clock_out_at?->timezone('Asia/Kathmandu')->format('h:i A'),
                'clock_out_iso' => $s->clock_out_at?->toIso8601String(),
                'clock_out_location' => $s->clock_out_location_name ?? 'Laijau Showroom',
                'duration_minutes' => $s->isOpen() ? max(0, (int)$s->clock_in_at->diffInMinutes(now())) : $s->duration_minutes,
                'duration_formatted' => $s->isOpen() ? $s->getComputedDurationFormatted(now()) : $s->duration_formatted,
            ];
        })->values()->toArray();

        // Also fetch raw events for backwards compatibility
        $checkInEvent = $openSession?->clockInEvent ?? $firstSession?->clockInEvent;
        $checkOutEvent = $lastCompleted?->clockOutEvent;

        return [
            'state' => $state,
            'is_open' => $openSession !== null,
            'current_session' => $currentSessionData,
            'total_sessions_today' => $allSessions->count(),
            'completed_sessions_today' => $completedSessions->count(),
            'next_session_number' => $allSessions->count() + 1,
            'today_sessions' => $sessionsList,
            'check_in_event' => $checkInEvent,
            'check_out_event' => $checkOutEvent,
            'check_in_time' => $openSession ? $openSession->clock_in_at?->timezone('Asia/Kathmandu')->format('h:i A') : $firstSession?->clock_in_at?->timezone('Asia/Kathmandu')->format('h:i A'),
            'check_out_time' => $lastCompleted?->clock_out_at?->timezone('Asia/Kathmandu')->format('h:i A'),
            'check_in_iso' => $openSession?->clock_in_at?->toIso8601String(),
            'duration_minutes' => $openSession ? $activeDurationMinutes : $totalCompletedMinutes,
            'duration_formatted' => $openSession ? $activeDurationFormatted : $totalWorkedFormatted,
            'total_worked_minutes' => $totalWorkedMinutesToday,
            'total_worked_hours' => round($totalWorkedMinutesToday / 60, 2),
            'total_worked_formatted' => $totalWorkedFormatted,
            'formatted_date' => $targetDate->format('l, F j, Y'),
        ];
    }

    /**
     * Get recent attendance history for an employee with multi-session breakdowns.
     */
    public function getEmployeeRecentHistory(Employee $employee, int $limit = 10): array
    {
        $startDate = now()->timezone('Asia/Kathmandu')->subDays(30)->startOfDay();

        $sessions = AttendanceSession::where('employee_id', $employee->id)
            ->where('date', '>=', $startDate->toDateString())
            ->orderBy('date', 'desc')
            ->orderBy('session_number', 'asc')
            ->get();

        $grouped = [];
        foreach ($sessions as $session) {
            $d = $session->date->format('Y-m-d');
            if (!isset($grouped[$d])) {
                $grouped[$d] = [
                    'date' => $session->date->format('M d, Y'),
                    'day' => $session->date->format('l'),
                    'raw_date' => $d,
                    'total_sessions' => 0,
                    'first_in' => null,
                    'last_out' => null,
                    'total_minutes' => 0,
                    'total_hours' => 0.00,
                    'total_formatted' => '0h 0m',
                    'location' => $session->clock_in_location_name ?? 'Laijau Showroom',
                    'sessions' => [],
                ];
            }

            $grouped[$d]['total_sessions']++;
            if (!$grouped[$d]['first_in'] && $session->clock_in_at) {
                $grouped[$d]['first_in'] = $session->clock_in_at->timezone('Asia/Kathmandu')->format('h:i A');
            }
            if ($session->clock_out_at) {
                $grouped[$d]['last_out'] = $session->clock_out_at->timezone('Asia/Kathmandu')->format('h:i A');
            }
            $grouped[$d]['total_minutes'] += $session->duration_minutes;

            $grouped[$d]['sessions'][] = [
                'session_number' => $session->session_number,
                'status' => $session->status,
                'in' => $session->clock_in_at?->timezone('Asia/Kathmandu')->format('h:i A'),
                'out' => $session->clock_out_at?->timezone('Asia/Kathmandu')->format('h:i A') ?? 'Active',
                'duration' => $session->isOpen() ? $session->getComputedDurationFormatted(now()) : $session->duration_formatted,
                'location' => $session->clock_in_location_name ?? 'Laijau Showroom',
            ];
        }

        foreach ($grouped as &$item) {
            $h = floor($item['total_minutes'] / 60);
            $m = $item['total_minutes'] % 60;
            $item['total_hours'] = round($item['total_minutes'] / 60, 2);
            $item['total_formatted'] = "{$h}h {$m}m";
            $item['check_in'] = $item['first_in'] ?? '—';
            $item['check_out'] = $item['last_out'] ?? ($item['first_in'] ? 'Working' : '—');
        }

        return array_slice(array_values($grouped), 0, $limit);
    }

    /**
     * Compute aggregated timesheet totals for an employee across daily, weekly, monthly, or custom periods.
     */
    public function getEmployeeTimesheetSummary(
        Employee $employee,
        string $period = 'monthly',
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): array {
        $now = now()->timezone('Asia/Kathmandu');

        switch ($period) {
            case 'daily':
                $start = $startDate ?? $now->copy()->startOfDay();
                $end = $endDate ?? $now->copy()->endOfDay();
                break;
            case 'weekly':
                $start = $startDate ?? $now->copy()->startOfWeek();
                $end = $endDate ?? $now->copy()->endOfWeek();
                break;
            case 'monthly':
                $start = $startDate ?? $now->copy()->startOfMonth();
                $end = $endDate ?? $now->copy()->endOfMonth();
                break;
            case 'custom':
            default:
                $start = $startDate ?? $now->copy()->subDays(30)->startOfDay();
                $end = $endDate ?? $now->copy()->endOfDay();
                break;
        }

        $sessions = AttendanceSession::where('employee_id', $employee->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('date', 'desc')
            ->orderBy('session_number', 'asc')
            ->get();

        $timesheets = Timesheet::where('employee_id', $employee->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('date', 'desc')
            ->get();

        $completedSessions = $sessions->where('status', 'completed');
        $totalMinutes = (int) $completedSessions->sum('duration_minutes');
        $totalHours = round($totalMinutes / 60, 2);

        $totalRegularHours = (float) $timesheets->sum('regular_hours');
        $totalOvertimeHours = (float) $timesheets->sum('overtime_hours');
        $daysPresent = $timesheets->where('attendance_status', 'present')->count();
        $daysHalfDay = $timesheets->where('attendance_status', 'half_day')->count();
        $totalLateMinutes = (int) $timesheets->sum('late_minutes');

        $avgHoursPerDay = $daysPresent > 0 ? round($totalHours / $daysPresent, 2) : 0.00;

        return [
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'code' => $employee->employee_number,
                'department' => $employee->department?->name ?? 'Showroom',
                'position' => $employee->position?->title ?? 'Staff',
            ],
            'period' => $period,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'formatted_period' => $start->format('M d, Y') . ' — ' . $end->format('M d, Y'),
            'metrics' => [
                'total_sessions' => $sessions->count(),
                'completed_sessions' => $completedSessions->count(),
                'open_sessions' => $sessions->where('status', 'open')->count(),
                'total_worked_hours' => $totalHours,
                'total_worked_minutes' => $totalMinutes,
                'total_worked_formatted' => floor($totalMinutes / 60) . 'h ' . ($totalMinutes % 60) . 'm',
                'regular_hours' => $totalRegularHours,
                'overtime_hours' => $totalOvertimeHours,
                'days_present' => $daysPresent,
                'days_half_day' => $daysHalfDay,
                'total_late_minutes' => $totalLateMinutes,
                'average_hours_per_day' => $avgHoursPerDay,
            ],
            'sessions' => $sessions,
            'timesheets' => $timesheets,
        ];
    }

    /**
     * Record a rejected attendance attempt for security and audit analysis.
     */
    public function recordRejectedEvent(
        Employee $employee,
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
            'attendance_location_id' => $locationId,
            'type' => $type,
            'status' => 'rejected',
            'server_recorded_at' => $serverTime,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'accuracy_meters' => $accuracy,
            'geofence_passed' => false,
            'photo_path' => null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'rejection_reason' => $rejectionReason,
        ]);
    }
}
