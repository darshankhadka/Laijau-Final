<?php

namespace App\Models\Hrm;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class Employee extends Model
{
    use HasFactory;

    protected $table = 'hrm_employees';

    protected $fillable = [
        'employee_number',
        'user_id',
        'first_name',
        'last_name',
        'cpr_encrypted',
        'cpr_masked',
        'pan_number',
        'citizenship_number',
        'marital_status',
        'branch_location',
        'basic_salary',
        'allowance_amount',
        'gross_salary',
        'ssf_enrolled',
        'ssf_number',
        'email',
        'phone',
        'address',
        'postal_code',
        'city',
        'country',
        'department_id',
        'position_id',
        'manager_id',
        'employment_type',
        'status',
        'hire_date',
        'probation_end_date',
        'termination_date',
        'bank_name',
        'bank_branch',
        'bank_account_number',
        'bank_account_name',
        'bank_reg_number',
        'iban',
        'bic_swift',
        'annual_leave_quota',
        'sick_leave_quota',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relation',
        'notes',
        'attendance_pin_hash',
        'attendance_pin_lookup_hash',
        'attendance_pin_set_at',
        'attendance_failed_attempts',
        'attendance_locked_until',
        'attendance_access_enabled',
        'can_punch_from_anywhere',
    ];

    protected $hidden = [
        'cpr_encrypted',
        'attendance_pin_hash',
        'attendance_pin_lookup_hash',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'probation_end_date' => 'date',
        'termination_date' => 'date',
        'basic_salary' => 'decimal:2',
        'allowance_amount' => 'decimal:2',
        'gross_salary' => 'decimal:2',
        'ssf_enrolled' => 'boolean',
        'annual_leave_quota' => 'decimal:2',
        'sick_leave_quota' => 'decimal:2',
        'attendance_pin_set_at' => 'datetime',
        'attendance_locked_until' => 'datetime',
        'attendance_access_enabled' => 'boolean',
        'can_punch_from_anywhere' => 'boolean',
    ];

    /**
     * Set the CPR number securely: encrypts at rest and generates a safe masked version.
     */
    public function setCpr(string $cpr): void
    {
        $clean = preg_replace('/[^0-9]/', '', $cpr);
        if (strlen($clean) === 10) {
            $formatted = substr($clean, 0, 6) . '-' . substr($clean, 6, 4);
            $this->cpr_encrypted = Crypt::encryptString($formatted);
            $this->cpr_masked = '******-' . substr($clean, 6, 4);
        } else {
            $this->cpr_encrypted = Crypt::encryptString($cpr);
            $this->cpr_masked = '******-' . substr($clean, -4);
        }
    }

    /**
     * Decrypt CPR number for authorized administrative tasks.
     */
    public function getDecryptedCpr(): ?string
    {
        if (empty($this->cpr_encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->cpr_encrypted);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function getDisplayNameAttribute(): string
    {
        $pos = $this->position ? " ({$this->position->title})" : '';
        return "{$this->employee_number} - {$this->full_name}{$pos}";
    }

    /**
     * Generate the next sequential employee number: LAI-0001, LAI-0002, etc.
     */
    public static function generateNextEmployeeNumber(): string
    {
        $prefix = 'LAI-';
        if (class_exists(\App\Services\Settings\SettingsService::class)) {
            $prefix = app(\App\Services\Settings\SettingsService::class)->getString('hrm', 'employee_id_prefix', 'LAI-');
        }
        $prefix = rtrim($prefix, '-') . '-';

        return app(\App\Services\DocumentSequenceService::class)->next(
            'employee',
            $prefix,
            4,
            fn ($p) => \App\Services\DocumentSequenceService::determineMaxFromTable('hrm_employees', 'employee_number', $p)
        );
    }

    public function payrollItems(): HasMany
    {
        return $this->hasMany(PayrollRunItem::class, 'employee_id');
    }

    public function latestPayrollItem(): HasOne
    {
        return $this->hasOne(PayrollRunItem::class, 'employee_id')->latestOfMany();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(Employee::class, 'manager_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(EmploymentContract::class, 'employee_id');
    }

    public function activeContract(): HasOne
    {
        return $this->hasOne(EmploymentContract::class, 'employee_id')
            ->where('status', 'active')
            ->latestOfMany();
    }

    public function timesheets(): HasMany
    {
        return $this->hasMany(Timesheet::class, 'employee_id');
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'employee_id');
    }

    public function holidayBalances(): HasMany
    {
        return $this->hasMany(HolidayBalance::class, 'employee_id');
    }

    public function currentHolidayBalance(): HasOne
    {
        return $this->hasOne(HolidayBalance::class, 'employee_id')
            ->where('holiday_year', (int)date('Y'))
            ->latestOfMany();
    }

    public function expenseClaims(): HasMany
    {
        return $this->hasMany(ExpenseClaim::class, 'employee_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class, 'employee_id');
    }

    public function performanceReviews(): HasMany
    {
        return $this->hasMany(PerformanceReview::class, 'employee_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Attendance PWA Relationships & Methods
    |--------------------------------------------------------------------------
    */

    public function attendanceAuthTokens(): HasMany
    {
        return $this->hasMany(\App\Models\Attendance\AttendanceAuthToken::class, 'employee_id');
    }

    public function attendanceDevices(): HasMany
    {
        return $this->hasMany(\App\Models\Attendance\AttendanceDevice::class, 'employee_id');
    }

    public function activeAttendanceDevices(): HasMany
    {
        return $this->hasMany(\App\Models\Attendance\AttendanceDevice::class, 'employee_id')
            ->where('is_active', true)
            ->whereNull('revoked_at');
    }

    public function attendanceEvents(): HasMany
    {
        return $this->hasMany(\App\Models\Attendance\AttendanceEvent::class, 'employee_id');
    }

    public function latestAttendanceEvent(): HasOne
    {
        return $this->hasOne(\App\Models\Attendance\AttendanceEvent::class, 'employee_id')->latestOfMany('server_recorded_at');
    }

    public function attendanceLocationUpdates(): HasMany
    {
        return $this->hasMany(\App\Models\Attendance\AttendanceLocationUpdate::class, 'employee_id');
    }

    public function latestLocationUpdate(): HasOne
    {
        return $this->hasOne(\App\Models\Attendance\AttendanceLocationUpdate::class, 'employee_id')->latestOfMany('recorded_at');
    }

    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(\App\Models\Attendance\AttendanceSession::class, 'employee_id')->orderBy('clock_in_at', 'desc');
    }

    public function openAttendanceSession(): HasOne
    {
        return $this->hasOne(\App\Models\Attendance\AttendanceSession::class, 'employee_id')->where('status', 'open');
    }

    /**
     * Check if employee has an attendance PIN configured.
     */
    public function hasAttendancePin(): bool
    {
        return !empty($this->attendance_pin_hash);
    }

    /**
     * Determine if employee is allowed to punch in from anywhere (geofence exempt).
     * Specifically and strictly authorized for Darshan Jung Khadka.
     */
    public function canPunchFromAnywhere(): bool
    {
        $isDarshan = strtolower(trim($this->first_name . ' ' . $this->last_name)) === 'darshan jung khadka'
            || $this->employee_number === 'LJ-EMP-001'
            || strtolower((string)$this->email) === 'admin@laijau.com';

        if ($isDarshan) {
            return true;
        }

        return (bool) ($this->can_punch_from_anywhere ?? false);
    }

    /**
     * Compute a deterministic salted hash for fast O(1) PIN lookup.
     */
    public static function hashPinForLookup(string $pin): string
    {
        return hash_hmac('sha256', trim($pin), (string) config('app.key'));
    }

    /**
     * Set/hash a new attendance PIN.
     */
    public function setAttendancePin(string $pin): void
    {
        $cleanPin = trim($pin);
        $this->update([
            'attendance_pin_hash' => \Illuminate\Support\Facades\Hash::make($cleanPin),
            'attendance_pin_lookup_hash' => static::hashPinForLookup($cleanPin),
            'attendance_pin_set_at' => now(),
            'attendance_failed_attempts' => 0,
            'attendance_locked_until' => null,
        ]);
    }

    /**
     * Verify attendance PIN.
     */
    public function verifyAttendancePin(string $pin): bool
    {
        if (empty($this->attendance_pin_hash)) {
            return false;
        }

        return \Illuminate\Support\Facades\Hash::check($pin, $this->attendance_pin_hash);
    }

    /**
     * Determine if attendance is currently locked due to failed attempts.
     */
    public function isAttendanceLocked(): bool
    {
        if (!$this->attendance_locked_until) {
            return false;
        }

        if (now()->greaterThanOrEqualTo($this->attendance_locked_until)) {
            $this->update([
                'attendance_locked_until' => null,
                'attendance_failed_attempts' => 0,
            ]);
            return false;
        }

        return true;
    }

    /**
     * Record a failed PIN attempt and lock out if threshold reached.
     */
    public function recordFailedPinAttempt(): bool
    {
        $maxAttempts = \App\Models\Attendance\AttendanceSetting::get('max_failed_attempts', 5);
        $lockoutMinutes = \App\Models\Attendance\AttendanceSetting::get('lockout_minutes', 15);

        $attempts = $this->attendance_failed_attempts + 1;

        if ($attempts >= $maxAttempts) {
            $this->update([
                'attendance_failed_attempts' => $attempts,
                'attendance_locked_until' => now()->addMinutes($lockoutMinutes),
            ]);
            return true; // Locked
        }

        $this->update(['attendance_failed_attempts' => $attempts]);
        return false;
    }

    /**
     * Reset failed PIN attempts.
     */
    public function clearFailedPinAttempts(): void
    {
        if ($this->attendance_failed_attempts > 0 || $this->attendance_locked_until !== null) {
            $this->update([
                'attendance_failed_attempts' => 0,
                'attendance_locked_until' => null,
            ]);
        }
    }
}

