<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class SettingAuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'user_email',
        'setting_key',
        'old_value',
        'new_value',
        'action',
        'ip_address',
        'user_agent',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record a setting modification into the audit log safely.
     * Sensitive values MUST be masked before passing to this method.
     */
    public static function logChange(string $key, $oldValue, $newValue, string $action = 'updated'): self
    {
        $user = Auth::user();

        // Sensitive keys that must be masked
        $sensitiveKeys = [
            'esewa_secret_key',
            'khalti_secret_key',
            'connectips_secret_key',
            'smtp_password',
        ];

        $oldStr = is_scalar($oldValue) ? (string)$oldValue : json_encode($oldValue);
        $newStr = is_scalar($newValue) ? (string)$newValue : json_encode($newValue);

        if (in_array($key, $sensitiveKeys, true)) {
            $oldStr = !empty($oldStr) ? Setting::maskSecret($oldStr) : 'not set';
            $newStr = !empty($newStr) ? Setting::maskSecret($newStr) : 'not set';
        }

        return self::create([
            'user_id' => $user?->id,
            'user_email' => $user?->email ?? 'system@laijau.com',
            'setting_key' => $key,
            'old_value' => $oldStr,
            'new_value' => $newStr,
            'action' => $action,
            'ip_address' => Request::ip() ?? '127.0.0.1',
            'user_agent' => Request::userAgent() ?? 'CLI/System',
        ]);
    }
}
