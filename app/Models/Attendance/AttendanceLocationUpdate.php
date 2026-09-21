<?php

namespace App\Models\Attendance;

use App\Models\Hrm\Employee;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceLocationUpdate extends Model
{
    use HasFactory;

    protected $table = 'attendance_location_updates';

    protected $fillable = [
        'employee_id',
        'device_id',
        'attendance_event_id',
        'latitude',
        'longitude',
        'accuracy_meters',
        'recorded_at',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'accuracy_meters' => 'float',
        'recorded_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(AttendanceDevice::class, 'device_id');
    }

    public function attendanceEvent(): BelongsTo
    {
        return $this->belongsTo(AttendanceEvent::class, 'attendance_event_id');
    }

    /**
     * Check if this location reading is stale (older than given minutes).
     */
    public function isStale(int $minutes = 15): bool
    {
        if (!$this->recorded_at) {
            return true;
        }

        return $this->recorded_at->diffInMinutes(now()) >= $minutes;
    }
}
