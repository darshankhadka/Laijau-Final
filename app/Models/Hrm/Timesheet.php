<?php

namespace App\Models\Hrm;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Timesheet extends Model
{
    use HasFactory;

    protected $table = 'hrm_timesheets';

    protected $fillable = [
        'employee_id',
        'date',
        'shift_name',
        'shift_start_time',
        'shift_end_time',
        'clock_in',
        'clock_in_latitude',
        'clock_in_longitude',
        'clock_in_accuracy',
        'clock_out',
        'clock_out_latitude',
        'clock_out_longitude',
        'clock_out_accuracy',
        'break_minutes',
        'regular_hours',
        'overtime_hours',
        'is_late',
        'late_minutes',
        'is_early_departure',
        'early_departure_minutes',
        'location',
        'notes',
        'status',
        'attendance_status',
        'is_missing_punch',
        'correction_requested',
        'correction_notes',
        'device_info',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'date' => 'date',
        'regular_hours' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'break_minutes' => 'integer',
        'is_late' => 'boolean',
        'late_minutes' => 'integer',
        'is_early_departure' => 'boolean',
        'early_departure_minutes' => 'integer',
        'is_missing_punch' => 'boolean',
        'correction_requested' => 'boolean',
        'clock_in_latitude' => 'decimal:7',
        'clock_in_longitude' => 'decimal:7',
        'clock_out_latitude' => 'decimal:7',
        'clock_out_longitude' => 'decimal:7',
        'approved_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Calculate hours, late arrival, early departure, and overtime.
     */
    public function recalculateHours(): void
    {
        $settings = app(\App\Services\Settings\SettingsService::class);
        $taxConfig = PayrollTaxConfiguration::resolveForFiscalYear();
        $standardDayHours = (float) (\App\Models\Attendance\AttendanceSetting::get('standard_daily_hours', $taxConfig->standard_daily_hours ?: 8.00));

        // 1. Shift & Late Arrival Detection
        if ($this->clock_in) {
            $shiftStart = $this->shift_start_time ?: \App\Models\Attendance\AttendanceSetting::get('shift_start_time', '10:00:00');
            $shiftStartTime = Carbon::parse($this->date->format('Y-m-d') . ' ' . $shiftStart);
            $clockInTime = Carbon::parse($this->date->format('Y-m-d') . ' ' . $this->clock_in);

            $graceMinutes = (int) \App\Models\Attendance\AttendanceSetting::get('shift_grace_minutes', $settings->getInteger('hrm', 'shift_grace_minutes', 15));
            $shiftStartWithGrace = (clone $shiftStartTime)->addMinutes($graceMinutes);

            if ($clockInTime->greaterThan($shiftStartWithGrace)) {
                $this->is_late = true;
                $this->late_minutes = abs((int)$clockInTime->diffInMinutes($shiftStartTime));
            } else {
                $this->is_late = false;
                $this->late_minutes = 0;
            }
        }

        // 2. Early Departure & Worked Hours
        if ($this->clock_in && $this->clock_out) {
            $in = strtotime($this->clock_in);
            $out = strtotime($this->clock_out);
            $diffSeconds = max(0, $out - $in);
            $breakSeconds = ($this->break_minutes ?? (int) \App\Models\Attendance\AttendanceSetting::get('break_minutes', 0)) * 60;
            $workedSeconds = max(0, $diffSeconds - $breakSeconds);
            $totalHours = round($workedSeconds / 3600, 2);

            $shiftEnd = $this->shift_end_time ?: \App\Models\Attendance\AttendanceSetting::get('shift_end_time', '19:00:00');
            $shiftEndTime = Carbon::parse($this->date->format('Y-m-d') . ' ' . $shiftEnd);
            $clockOutTime = Carbon::parse($this->date->format('Y-m-d') . ' ' . $this->clock_out);

            if (\App\Models\Attendance\AttendanceSetting::get('track_early_departure', true) && $clockOutTime->lessThan($shiftEndTime)) {
                $this->is_early_departure = true;
                $this->early_departure_minutes = abs((int)$shiftEndTime->diffInMinutes($clockOutTime));
            } else {
                $this->is_early_departure = false;
                $this->early_departure_minutes = 0;
            }

            if ($totalHours > $standardDayHours) {
                $this->regular_hours = $standardDayHours;
                $this->overtime_hours = round($totalHours - $standardDayHours, 2);
            } else {
                $this->regular_hours = $totalHours;
                $this->overtime_hours = 0.00;
            }

            // Attendance Status classification
            if ($totalHours >= 7.0) {
                $this->attendance_status = 'present';
            } elseif ($totalHours >= 3.5) {
                $this->attendance_status = 'half_day';
            } else {
                $this->attendance_status = 'present';
            }

            $this->is_missing_punch = false;
        } elseif ($this->clock_in && !$this->clock_out) {
            // Clocked in but not out yet
            $this->regular_hours = 0.00;
            $this->overtime_hours = 0.00;
            $this->attendance_status = 'present';

            // If past midnight or shift ended hours ago, flag missing punch
            $shiftEnd = Carbon::parse($this->date->format('Y-m-d') . ' ' . ($this->shift_end_time ?: '19:00:00'));
            if (now()->greaterThan((clone $shiftEnd)->addHours(4))) {
                $this->is_missing_punch = true;
            }
        }
    }
}
