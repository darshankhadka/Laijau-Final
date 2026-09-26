<?php

namespace App\Models\Attendance;

use App\Models\Hrm\Employee;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AttendanceAuthToken extends Model
{
    use HasFactory;

    protected $table = 'attendance_auth_tokens';

    protected $fillable = [
        'employee_id',
        'token_hash',
        'device_name',
        'ip_address',
        'user_agent',
        'last_used_at',
        'expires_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * Create a persistent authentication token for an employee.
     */
    public static function createToken(Employee $employee, ?string $deviceName = null): string
    {
        $rawToken = bin2hex(random_bytes(32));
        $hash = hash('sha256', $rawToken);

        static::create([
            'employee_id' => $employee->id,
            'token_hash' => $hash,
            'device_name' => $deviceName ?? 'PWA Device',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'last_used_at' => now(),
            'expires_at' => now()->addDays(180),
        ]);

        return $rawToken;
    }

    /**
     * Validate a raw token and return the associated active Employee.
     */
    public static function validateToken(string $rawToken): ?Employee
    {
        if (empty($rawToken)) {
            return null;
        }

        $hash = hash('sha256', $rawToken);

        $record = static::with('employee')
            ->where('token_hash', $hash)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();

        if (!$record || !$record->employee) {
            return null;
        }

        $employee = $record->employee;
        if (!$employee->attendance_access_enabled || $employee->status !== 'active') {
            return null;
        }

        // Touch last used timestamp (throttled to at most once per hour to minimize DB writes)
        if (!$record->last_used_at || $record->last_used_at->diffInMinutes(now()) >= 60) {
            $record->update(['last_used_at' => now()]);
        }

        return $employee;
    }

    /**
     * Revoke a token.
     */
    public static function revokeToken(string $rawToken): void
    {
        if (empty($rawToken)) {
            return;
        }

        $hash = hash('sha256', $rawToken);
        static::where('token_hash', $hash)->delete();
    }
}
