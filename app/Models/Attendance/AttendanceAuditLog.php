<?php

namespace App\Models\Attendance;

use App\Models\Hrm\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceAuditLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'attendance_audit_logs';

    protected $fillable = [
        'user_id',
        'employee_id',
        'action',
        'entity_type',
        'entity_id',
        'previous_values',
        'new_values',
        'ip_address',
        'user_agent',
        'details',
        'created_at',
    ];

    protected $casts = [
        'previous_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * Record an audit event cleanly.
     */
    public static function log(
        string $action,
        ?int $employeeId = null,
        ?int $userId = null,
        ?string $details = null,
        ?array $previousValues = null,
        ?array $newValues = null,
        ?string $entityType = null,
        ?int $entityId = null
    ): self {
        return self::create([
            'user_id' => $userId ?? auth('admin')->id() ?? auth('web')->id(),
            'employee_id' => $employeeId,
            'action' => $action,
            'details' => $details,
            'previous_values' => $previousValues,
            'new_values' => $newValues,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
}
