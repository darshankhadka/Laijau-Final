<?php

namespace App\Models\Attendance;

use App\Models\Hrm\Employee;
use App\Models\Hrm\Timesheet;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceSession extends Model
{
    use HasFactory;

    protected $table = 'attendance_sessions';

    protected $fillable = [
        'employee_id',
        'timesheet_id',
        'date',
        'session_number',
        'status',
        'clock_in_at',
        'clock_in_time',
        'clock_in_event_id',
        'clock_in_latitude',
        'clock_in_longitude',
        'clock_in_accuracy',
        'clock_in_location_name',
        'clock_in_device_info',
        'clock_in_ip',
        'clock_out_at',
        'clock_out_time',
        'clock_out_event_id',
        'clock_out_latitude',
        'clock_out_longitude',
        'clock_out_accuracy',
        'clock_out_location_name',
        'clock_out_device_info',
        'clock_out_ip',
        'duration_minutes',
        'duration_hours',
        'duration_formatted',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'date' => 'date',
        'clock_in_at' => 'datetime',
        'clock_out_at' => 'datetime',
        'clock_in_latitude' => 'decimal:7',
        'clock_in_longitude' => 'decimal:7',
        'clock_in_accuracy' => 'decimal:2',
        'clock_out_latitude' => 'decimal:7',
        'clock_out_longitude' => 'decimal:7',
        'clock_out_accuracy' => 'decimal:2',
        'duration_minutes' => 'integer',
        'duration_hours' => 'decimal:2',
        'session_number' => 'integer',
        'metadata' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function timesheet(): BelongsTo
    {
        return $this->belongsTo(Timesheet::class, 'timesheet_id');
    }

    public function clockInEvent(): BelongsTo
    {
        return $this->belongsTo(AttendanceEvent::class, 'clock_in_event_id');
    }

    public function clockOutEvent(): BelongsTo
    {
        return $this->belongsTo(AttendanceEvent::class, 'clock_out_event_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * Compute current or final duration in minutes and hours.
     */
    public function getComputedDurationMinutes(?Carbon $referenceTime = null): int
    {
        if (!$this->clock_in_at) {
            return 0;
        }

        $endTime = $this->clock_out_at ?? ($referenceTime ?? now());
        return max(0, (int) $this->clock_in_at->diffInMinutes($endTime));
    }

    public function getComputedDurationFormatted(?Carbon $referenceTime = null): string
    {
        $minutes = $this->getComputedDurationMinutes($referenceTime);
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        return "{$hours}h {$mins}m";
    }

    public function getKathmanduClockInAttribute(): ?string
    {
        return $this->clock_in_at?->timezone('Asia/Kathmandu')->format('h:i:s A');
    }

    public function getKathmanduClockOutAttribute(): ?string
    {
        return $this->clock_out_at?->timezone('Asia/Kathmandu')->format('h:i:s A');
    }
}
