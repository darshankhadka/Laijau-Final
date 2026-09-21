<?php

namespace App\Models\Attendance;

use App\Models\Hrm\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceDevice extends Model
{
    use HasFactory;

    protected $table = 'attendance_devices';

    protected $fillable = [
        'employee_id',
        'device_token_hash',
        'device_name',
        'platform',
        'browser',
        'user_agent',
        'ip_address',
        'passkey_credential_id',
        'passkey_public_key',
        'passkey_sign_count',
        'is_active',
        'registered_at',
        'last_seen_at',
        'revoked_at',
        'revoked_by',
        'revocation_reason',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'passkey_sign_count' => 'integer',
        'registered_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function revokedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function attendanceEvents(): HasMany
    {
        return $this->hasMany(AttendanceEvent::class, 'device_id');
    }

    public function locationUpdates(): HasMany
    {
        return $this->hasMany(AttendanceLocationUpdate::class, 'device_id');
    }

    /**
     * Check if this device is trusted, active, and belongs to an enabled employee.
     */
    public function isUsable(): bool
    {
        if (!$this->is_active || $this->revoked_at !== null) {
            return false;
        }

        return $this->employee && $this->employee->attendance_access_enabled && $this->employee->status === 'active';
    }

    /**
     * Revoke this device.
     */
    public function revoke(?int $userId = null, ?string $reason = null): void
    {
        $this->update([
            'is_active' => false,
            'revoked_at' => now(),
            'revoked_by' => $userId,
            'revocation_reason' => $reason ?? 'Revoked by administrator',
        ]);
    }

    /**
     * Mark device as seen right now.
     */
    public function touchLastSeen(?string $ip = null): void
    {
        $data = ['last_seen_at' => now()];
        if ($ip) {
            $data['ip_address'] = $ip;
        }
        $this->update($data);
    }
}
