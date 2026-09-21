<?php

namespace App\Filament\Pages;

use App\Models\Attendance\AttendanceAuditLog;
use App\Models\Attendance\AttendanceDevice;
use App\Models\Attendance\AttendanceEvent;
use App\Models\Attendance\AttendanceLocation;
use App\Models\Attendance\AttendanceLocationUpdate;
use App\Models\Attendance\AttendanceSetting;
use App\Models\Hrm\Employee;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AttendanceControlCenterPage extends Page
{
    protected string $view = 'filament.pages.attendance-control-center';
    protected Width | string | null $maxWidth = 'full';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-finger-print';
    protected static string | \UnitEnum | null $navigationGroup = 'People';
    protected static ?string $navigationLabel = 'Attendance Control Center';
    protected static ?int $navigationSort = 15;
    protected static ?string $slug = 'hrm/attendance';
    protected static ?string $title = 'Attendance Control Center';

    // Active Navigation Tab
    public string $activeTab = 'overview'; // 'overview', 'map', 'employees', 'locations', 'settings', 'audit'

    // Form states for modals
    public ?int $selectedEmployeeId = null;
    public string $newPin = '';
    public string $newPinConfirmation = '';
    public bool $showPinModal = false;

    // Location edit state
    public ?int $editingLocationId = null;
    public string $locName = '';
    public string $locCode = '';
    public float $locLatitude = 27.6976748;
    public float $locLongitude = 85.3664331;
    public int $locRadius = 100;
    public string $locAddress = '';
    public bool $locIsActive = true;
    public bool $showLocationModal = false;

    // Attendance Settings state
    public bool $attEnabled = true;
    public bool $gpsRequired = true;
    public bool $photoRequired = true;
    public bool $geofencingEnabled = true;
    public int $showroomGeofenceRadius = 100;
    public int $maxGpsAccuracy = 100;
    public int $heartbeatInterval = 180;
    public int $maxAllowedDevices = 2;
    public int $maxFailedAttempts = 5;
    public int $lockoutMinutes = 15;

    public function mount(): void
    {
        $this->loadSettingsState();
    }

    public static function getAuthenticatedUser()
    {
        return Filament::auth()->user()
            ?? Auth::guard('admin')->user()
            ?? Auth::guard('web')->user()
            ?? Auth::user();
    }

    public static function canAccess(): bool
    {
        $user = static::getAuthenticatedUser();
        if (!$user) {
            return false;
        }

        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return true;
        }

        return $user->hasRole('HR / Payroll Manager', 'admin')
            || $user->hasRole('HR Manager', 'admin')
            || $user->hasRole('Workspace Admin', 'admin')
            || $user->hasRole('admin', 'web')
            || (bool) ($user->is_admin ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function getHeading(): string | Htmlable
    {
        return 'Employee Attendance Control Center';
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function loadSettingsState(): void
    {
        $this->attEnabled = AttendanceSetting::get('attendance_enabled', true);
        $this->gpsRequired = AttendanceSetting::get('gps_required', true);
        $this->photoRequired = AttendanceSetting::get('photo_required', true);
        $this->geofencingEnabled = AttendanceSetting::get('geofencing_enabled', true);
        $defaultLoc = AttendanceLocation::where('is_default', true)->first() ?? AttendanceLocation::first();
        $this->showroomGeofenceRadius = $defaultLoc ? $defaultLoc->radius_meters : AttendanceSetting::get('geofence_radius_meters', 100);
        $this->maxGpsAccuracy = AttendanceSetting::get('max_gps_accuracy_meters', 100);
        $this->heartbeatInterval = AttendanceSetting::get('heartbeat_interval_seconds', 180);
        $this->maxAllowedDevices = AttendanceSetting::get('max_allowed_devices', 2);
        $this->maxFailedAttempts = AttendanceSetting::get('max_failed_attempts', 5);
        $this->lockoutMinutes = AttendanceSetting::get('lockout_minutes', 15);
    }

    /**
     * Get real-time attendance summary KPIs for today.
     */
    public function getAttendanceSummary(): array
    {
        $today = now()->startOfDay();
        $totalActiveEmployees = Employee::where('status', 'active')->where('attendance_access_enabled', true)->count();

        $todayEvents = AttendanceEvent::where('server_recorded_at', '>=', $today)
            ->where('status', 'verified')
            ->get();

        $employeeCheckIns = $todayEvents->where('type', 'check_in')->pluck('employee_id')->unique();
        $employeeCheckOuts = $todayEvents->where('type', 'check_out')->pluck('employee_id')->unique();

        // Currently checked in = checked in today but has not checked out yet
        $currentlyCheckedIn = $employeeCheckIns->diff($employeeCheckOuts)->count();
        $checkedOutToday = $employeeCheckOuts->count();
        $notCheckedInToday = max(0, $totalActiveEmployees - $employeeCheckIns->count());

        // Late arrivals (shift 10:00 AM)
        $lateArrivals = $todayEvents->where('type', 'check_in')->filter(function ($ev) {
            return $ev->server_recorded_at->format('H:i:s') > '10:00:00';
        })->count();

        return [
            'total_active' => $totalActiveEmployees,
            'currently_checked_in' => $currentlyCheckedIn,
            'checked_out_today' => $checkedOutToday,
            'not_checked_in_today' => $notCheckedInToday,
            'late_arrivals' => $lateArrivals,
            'total_punches_today' => $todayEvents->count(),
        ];
    }

    /**
     * Get currently checked-in employees with latest known GPS location for map.
     */
    public function getCheckedInEmployeesForMap(): Collection
    {
        $today = now()->startOfDay();
        $checkIns = AttendanceEvent::with(['employee', 'location', 'device'])
            ->where('server_recorded_at', '>=', $today)
            ->where('type', 'check_in')
            ->where('status', 'verified')
            ->latest('server_recorded_at')
            ->get()
            ->unique('employee_id');

        $checkOutIds = AttendanceEvent::where('server_recorded_at', '>=', $today)
            ->where('type', 'check_out')
            ->where('status', 'verified')
            ->pluck('employee_id')
            ->toArray();

        // Filter for currently active (not checked out)
        $activeCheckIns = $checkIns->filter(fn ($ev) => !in_array($ev->employee_id, $checkOutIds));

        return $activeCheckIns->map(function ($ev) {
            // Get latest heartbeat location update if available
            $latestLoc = AttendanceLocationUpdate::where('employee_id', $ev->employee_id)
                ->latest('recorded_at')
                ->first();

            $lat = $latestLoc ? $latestLoc->latitude : $ev->latitude;
            $lon = $latestLoc ? $latestLoc->longitude : $ev->longitude;
            $acc = $latestLoc ? $latestLoc->accuracy_meters : $ev->accuracy_meters;
            $lastUpdated = $latestLoc ? $latestLoc->recorded_at : $ev->server_recorded_at;
            $isStale = $lastUpdated ? $lastUpdated->diffInMinutes(now()) >= 15 : true;

            return [
                'employee_id' => $ev->employee_id,
                'name' => $ev->employee?->full_name ?? 'Unknown',
                'code' => $ev->employee?->employee_number ?? '',
                'check_in_time' => $ev->server_recorded_at->format('h:i A'),
                'latitude' => $lat,
                'longitude' => $lon,
                'accuracy' => $acc ? round($acc) : null,
                'last_updated_human' => $lastUpdated ? $lastUpdated->diffForHumans() : 'Just now',
                'is_stale' => $isStale,
                'photo_url' => $ev->photo_path ? route('attendance.photo', ['event' => $ev->id]) : null,
            ];
        })->filter(fn ($e) => $e['latitude'] !== null && $e['longitude'] !== null)->values();
    }

    /**
     * Get list of employees with attendance credentials & device stats.
     */
    public function getEmployeesList(): Collection
    {
        return Employee::with(['activeAttendanceDevices', 'department', 'position'])
            ->where('status', 'active')
            ->orderBy('employee_number', 'asc')
            ->get()
            ->map(function ($emp) {
                return [
                    'id' => $emp->id,
                    'code' => $emp->employee_number,
                    'name' => $emp->full_name,
                    'phone' => $emp->phone,
                    'department' => $emp->department?->name ?? 'Showroom',
                    'has_pin' => $emp->hasAttendancePin(),
                    'pin_set_at' => $emp->attendance_pin_set_at?->format('M d, Y'),
                    'is_locked' => $emp->isAttendanceLocked(),
                    'access_enabled' => $emp->attendance_access_enabled,
                    'active_devices_count' => $emp->activeAttendanceDevices->count(),
                    'devices' => $emp->activeAttendanceDevices,
                ];
            });
    }

    /**
     * Open PIN creation/reset modal.
     */
    public function openPinModal(int $employeeId): void
    {
        $this->selectedEmployeeId = $employeeId;
        $this->newPin = '';
        $this->newPinConfirmation = '';
        $this->showPinModal = true;
    }

    public function closePinModal(): void
    {
        $this->showPinModal = false;
        $this->selectedEmployeeId = null;
        $this->newPin = '';
        $this->newPinConfirmation = '';
    }

    /**
     * Save/Reset employee attendance PIN.
     */
    public function saveEmployeePin(): void
    {
        if (strlen($this->newPin) < 4 || strlen($this->newPin) > 8 || !ctype_digit($this->newPin)) {
            Notification::make()->title('Validation Error')->body('PIN must be 4 to 8 numeric digits.')->danger()->send();
            return;
        }

        if ($this->newPin !== $this->newPinConfirmation) {
            Notification::make()->title('Validation Error')->body('PIN confirmation does not match.')->danger()->send();
            return;
        }

        $employee = Employee::find($this->selectedEmployeeId);
        if (!$employee) {
            Notification::make()->title('Error')->body('Employee not found.')->danger()->send();
            return;
        }

        $isReset = $employee->hasAttendancePin();
        $employee->setAttendancePin($this->newPin);

        AttendanceAuditLog::log(
            action: $isReset ? 'pin_reset_by_admin' : 'pin_created_by_admin',
            employeeId: $employee->id,
            details: "Administrator set/reset attendance PIN for employee {$employee->employee_number} ({$employee->full_name})."
        );

        Notification::make()
            ->title('PIN Updated')
            ->body("Attendance PIN for {$employee->full_name} has been securely updated.")
            ->success()
            ->send();

        $this->closePinModal();
    }

    /**
     * Toggle employee attendance access.
     */
    public function toggleEmployeeAccess(int $employeeId): void
    {
        $employee = Employee::find($employeeId);
        if (!$employee) return;

        $newVal = !$employee->attendance_access_enabled;
        $employee->update(['attendance_access_enabled' => $newVal]);

        AttendanceAuditLog::log(
            action: 'access_toggled',
            employeeId: $employee->id,
            details: "Attendance access " . ($newVal ? 'ENABLED' : 'DISABLED') . " by administrator."
        );

        Notification::make()
            ->title('Access Updated')
            ->body("Attendance access for {$employee->full_name} is now " . ($newVal ? 'ENABLED' : 'DISABLED') . ".")
            ->info()
            ->send();
    }

    /**
     * Revoke employee device.
     */
    public function revokeDevice(int $deviceId): void
    {
        $device = AttendanceDevice::find($deviceId);
        if (!$device) return;

        $device->revoke(auth()->id(), 'Revoked by administrator from Control Center');

        AttendanceAuditLog::log(
            action: 'device_revoked_by_admin',
            employeeId: $device->employee_id,
            details: "Device '{$device->device_name}' (#{$device->id}) revoked by administrator."
        );

        Notification::make()
            ->title('Device Revoked')
            ->body("Device {$device->device_name} has been revoked and can no longer record attendance.")
            ->warning()
            ->send();
    }

    /**
     * Open Location Modal.
     */
    public function openLocationModal(?int $locationId = null): void
    {
        $this->editingLocationId = $locationId;
        if ($locationId) {
            $loc = AttendanceLocation::find($locationId);
            if ($loc) {
                $this->locName = $loc->name;
                $this->locCode = $loc->code;
                $this->locLatitude = $loc->latitude;
                $this->locLongitude = $loc->longitude;
                $this->locRadius = $loc->radius_meters;
                $this->locAddress = (string)$loc->address;
                $this->locIsActive = $loc->is_active;
            }
        } else {
            $this->locName = '';
            $this->locCode = 'LOC-' . strtoupper(substr(md5(microtime()), 0, 6));
            $this->locLatitude = 27.6976748;
            $this->locLongitude = 85.3664331;
            $this->locRadius = 100;
            $this->locAddress = 'Bohara Tol, Kageshwori Manahara 09, Kathmandu, Nepal';
            $this->locIsActive = true;
        }
        $this->showLocationModal = true;
    }

    public function closeLocationModal(): void
    {
        $this->showLocationModal = false;
        $this->editingLocationId = null;
    }

    /**
     * Save Location.
     */
    public function saveLocation(): void
    {
        if (empty($this->locName)) {
            Notification::make()->title('Validation Error')->body('Location name is required.')->danger()->send();
            return;
        }

        $data = [
            'name' => $this->locName,
            'code' => $this->locCode ?: ('LOC-' . strtoupper(substr(md5(microtime()), 0, 6))),
            'latitude' => $this->locLatitude,
            'longitude' => $this->locLongitude,
            'radius_meters' => max(10, $this->locRadius),
            'address' => $this->locAddress,
            'is_active' => $this->locIsActive,
        ];

        if ($this->editingLocationId) {
            $loc = AttendanceLocation::find($this->editingLocationId);
            $loc?->update($data);
            $msg = 'Location updated successfully.';
        } else {
            AttendanceLocation::create($data);
            $msg = 'Location created successfully.';
        }

        Notification::make()->title('Saved')->body($msg)->success()->send();
        $this->closeLocationModal();
    }

    /**
     * Save Settings.
     */
    public function saveSettings(): void
    {
        AttendanceSetting::set('attendance_enabled', $this->attEnabled, 'boolean');
        AttendanceSetting::set('gps_required', $this->gpsRequired, 'boolean');
        AttendanceSetting::set('photo_required', $this->photoRequired, 'boolean');
        AttendanceSetting::set('geofencing_enabled', $this->geofencingEnabled, 'boolean');
        AttendanceSetting::set('geofence_radius_meters', $this->showroomGeofenceRadius, 'integer');
        AttendanceSetting::set('max_gps_accuracy_meters', $this->maxGpsAccuracy, 'integer');
        AttendanceSetting::set('heartbeat_interval_seconds', $this->heartbeatInterval, 'integer');
        AttendanceSetting::set('max_allowed_devices', $this->maxAllowedDevices, 'integer');
        AttendanceSetting::set('max_failed_attempts', $this->maxFailedAttempts, 'integer');
        AttendanceSetting::set('lockout_minutes', $this->lockoutMinutes, 'integer');

        // Update default showroom location radius in database
        $defaultLoc = AttendanceLocation::where('is_default', true)->first() ?? AttendanceLocation::first();
        if ($defaultLoc) {
            $defaultLoc->update([
                'radius_meters' => max(10, $this->showroomGeofenceRadius),
            ]);
        }

        AttendanceAuditLog::log(
            action: 'settings_updated',
            details: "Attendance configuration and showroom geofence radius ({$this->showroomGeofenceRadius}m) updated by administrator."
        );

        Notification::make()->title('Settings Saved')->body('Attendance configuration updated successfully.')->success()->send();
    }

    /**
     * Set a location as the primary default active attendance location.
     */
    public function setDefaultLocation(int $locationId): void
    {
        AttendanceLocation::query()->update(['is_default' => false]);
        $loc = AttendanceLocation::find($locationId);
        if ($loc) {
            $loc->update(['is_default' => true, 'is_active' => true]);
            $this->showroomGeofenceRadius = $loc->radius_meters;
            AttendanceAuditLog::log(
                action: 'location_set_default',
                details: "Location '{$loc->name}' set as primary default attendance location."
            );
            Notification::make()->title('Default Location Set')->body("'{$loc->name}' is now the primary default attendance location.")->success()->send();
        }
    }

    /**
     * Get Today's Events stream.
     */
    public function getTodayEvents(): Collection
    {
        return AttendanceEvent::with(['employee', 'location', 'device'])
            ->where('server_recorded_at', '>=', now()->startOfDay())
            ->orderBy('server_recorded_at', 'desc')
            ->limit(50)
            ->get();
    }

    /**
     * Get Locations list.
     */
    public function getLocations(): Collection
    {
        return AttendanceLocation::orderBy('is_default', 'desc')->orderBy('name', 'asc')->get();
    }

    /**
     * Get Audit Logs.
     */
    public function getAuditLogs(): Collection
    {
        return AttendanceAuditLog::with(['user', 'employee'])
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();
    }
}
