<?php

namespace App\Models\Settings;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ModuleSettingAuditLog extends Model
{
    use HasFactory;

    protected $table = 'module_setting_audit_logs';

    protected $fillable = [
        'module_setting_id',
        'module',
        'group',
        'key',
        'old_value',
        'new_value',
        'action',
        'user_id',
        'user_email',
        'ip_address',
        'user_agent',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function setting(): BelongsTo
    {
        return $this->belongsTo(ModuleSetting::class, 'module_setting_id');
    }

    /**
     * Safely record an immutable audit log entry.
     * Sensitive secrets are ALWAYS redacted prior to storage.
     */
    public static function record(
        ModuleSetting $setting,
        $oldRawValue,
        $newRawValue,
        string $action = 'updated',
        ?User $actor = null,
        ?string $ip = null,
        ?string $userAgent = null
    ): self {
        $actor = $actor ?? Auth::user();

        $oldStr = is_scalar($oldRawValue) ? (string)$oldRawValue : (is_array($oldRawValue) ? json_encode($oldRawValue) : null);
        $newStr = is_scalar($newRawValue) ? (string)$newRawValue : (is_array($newRawValue) ? json_encode($newRawValue) : null);

        // Never write secrets to audit logs!
        if ($setting->is_sensitive) {
            $oldStr = !empty($oldStr) ? ModuleSetting::maskSecret($oldStr) : null;
            $newStr = !empty($newStr) ? ModuleSetting::maskSecret($newStr) : null;
        }

        return self::create([
            'module_setting_id' => $setting->id,
            'module' => $setting->module,
            'group' => $setting->group,
            'key' => $setting->key,
            'old_value' => $oldStr,
            'new_value' => $newStr,
            'action' => $action,
            'user_id' => $actor?->id,
            'user_email' => $actor?->email ?? 'system@laijau.com',
            'ip_address' => $ip ?? Request::ip() ?? '127.0.0.1',
            'user_agent' => $userAgent ?? Request::userAgent() ?? 'CLI/System',
        ]);
    }
}
