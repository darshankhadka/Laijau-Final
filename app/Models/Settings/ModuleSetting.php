<?php

namespace App\Models\Settings;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ModuleSetting extends Model
{
    use HasFactory;

    protected $table = 'module_settings';

    protected $fillable = [
        'module',
        'group',
        'key',
        'value',
        'value_type',
        'default_value',
        'description',
        'validation_rules',
        'options',
        'sort_order',
        'is_enabled',
        'is_public',
        'is_sensitive',
        'is_editable',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'options' => 'array',
        'is_enabled' => 'boolean',
        'is_public' => 'boolean',
        'is_sensitive' => 'boolean',
        'is_editable' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(ModuleSettingAuditLog::class, 'module_setting_id');
    }

    /**
     * Get the strongly-typed, decrypted value for runtime consumption.
     */
    public function getTypedValueAttribute()
    {
        $raw = $this->value;

        if ($raw === null || $raw === '') {
            return self::castValueToType($this->default_value, $this->value_type);
        }

        if ($this->is_sensitive && str_starts_with((string)$raw, 'enc:')) {
            try {
                $raw = Crypt::decryptString(substr((string)$raw, 4));
            } catch (\Throwable $e) {
                return self::castValueToType($this->default_value, $this->value_type);
            }
        }

        return self::castValueToType($raw, $this->value_type);
    }

    /**
     * Cast any raw representation to the target type.
     */
    public static function castValueToType($val, string $type)
    {
        if ($val === null) {
            return null;
        }

        return match ($type) {
            'boolean' => filter_var($val, FILTER_VALIDATE_BOOLEAN),
            'integer', 'duration' => (int)$val,
            'decimal', 'percentage', 'currency' => (float)$val,
            'json', 'multi-select' => is_array($val) ? $val : (json_decode((string)$val, true) ?: []),
            default => (string)$val,
        };
    }

    /**
     * Prepare a value for storage in the database (encrypting if sensitive).
     */
    public static function prepareValueForStorage($val, string $type, bool $isSensitive = false): ?string
    {
        if ($val === null) {
            return null;
        }

        if (is_array($val)) {
            $formatted = json_encode($val);
        } elseif ($type === 'boolean') {
            $formatted = filter_var($val, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
        } else {
            $formatted = (string)$val;
        }

        if ($isSensitive && !empty($formatted)) {
            // Do not double-encrypt if already encrypted
            if (!str_starts_with($formatted, 'enc:')) {
                return 'enc:' . Crypt::encryptString(trim($formatted));
            }
        }

        return $formatted;
    }

    /**
     * Mask a sensitive secret value for safe display in logs or UI.
     */
    public static function maskSecret(?string $secret, int $visibleStart = 4, int $visibleEnd = 4): string
    {
        if (empty($secret)) {
            return '';
        }

        // If it starts with enc:, decrypt first to properly mask prefix/suffix
        if (str_starts_with($secret, 'enc:')) {
            try {
                $secret = Crypt::decryptString(substr($secret, 4));
            } catch (\Throwable $e) {
                return str_repeat('•', 12);
            }
        }

        $len = strlen($secret);
        if ($len <= ($visibleStart + $visibleEnd)) {
            return str_repeat('•', 12);
        }

        $prefix = substr($secret, 0, $visibleStart);
        $suffix = substr($secret, -$visibleEnd);

        return $prefix . str_repeat('•', 8) . $suffix;
    }

    /**
     * Validate a candidate value against this setting's validation rules.
     */
    public function validateValue($candidateValue): void
    {
        $rules = $this->validation_rules;
        if (empty($rules)) {
            return;
        }

        $ruleArray = is_array($rules) ? $rules : explode('|', (string)$rules);
        $validator = Validator::make(
            ['value' => $candidateValue],
            ['value' => $ruleArray],
            [],
            ['value' => "Setting '{$this->module}.{$this->key}'"]
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}
