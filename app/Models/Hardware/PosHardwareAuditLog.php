<?php

namespace App\Models\Hardware;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class PosHardwareAuditLog extends Model
{
    use HasFactory;

    protected $table = 'pos_hardware_audit_logs';

    protected $fillable = [
        'user_id',
        'user_name',
        'entity_type',
        'entity_id',
        'entity_name',
        'action',
        'field_name',
        'old_value',
        'new_value',
        'details',
        'ip_address',
    ];

    protected $casts = [
        'details' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Record an audit event.
     */
    public static function record(
        string $entityType,
        ?int $entityId,
        string $entityName,
        string $action,
        ?string $fieldName = null,
        ?string $oldValue = null,
        ?string $newValue = null,
        array $details = []
    ): self {
        $user = \Filament\Facades\Filament::auth()->user()
            ?? Auth::guard('admin')->user()
            ?? Auth::guard('web')->user()
            ?? Auth::user();

        return static::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'System',
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'entity_name' => $entityName,
            'action' => $action,
            'field_name' => $fieldName,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'details' => $details,
            'ip_address' => request()?->ip(),
        ]);
    }
}
