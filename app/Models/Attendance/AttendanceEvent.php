<?php

namespace App\Models\Attendance;

use App\Models\Hrm\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceEvent extends Model
{
    use HasFactory;

    protected $table = 'attendance_events';

    protected $fillable = [
        'employee_id',
        'device_id',
        'attendance_location_id',
        'type',
        'status',
        'server_recorded_at',
        'client_captured_at',
        'latitude',
        'longitude',
        'accuracy_meters',
        'distance_from_location_meters',
        'geofence_passed',
        'photo_path',
        'ip_address',
        'user_agent',
        'verification_method',
        'idempotency_key',
        'rejection_reason',
        'is_adjusted',
        'adjusted_by',
        'adjusted_at',
        'adjustment_notes',
        'metadata',
    ];

    protected $casts = [
        'server_recorded_at' => 'datetime',
        'client_captured_at' => 'datetime',
        'adjusted_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
        'accuracy_meters' => 'float',
        'distance_from_location_meters' => 'float',
        'geofence_passed' => 'boolean',
        'is_adjusted' => 'boolean',
        'metadata' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(AttendanceDevice::class, 'device_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(AttendanceLocation::class, 'attendance_location_id');
    }

    public function adjustedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }

    public function locationUpdates(): HasMany
    {
        return $this->hasMany(AttendanceLocationUpdate::class, 'attendance_event_id');
    }

    /**
     * Get authorized photo URL for this event.
     */
    public function getSecurePhotoUrlAttribute(): ?string
    {
        if (empty($this->photo_path)) {
            return null;
        }

        return route('attendance.photo', ['event' => $this->id]);
    }
}
