<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    const CACHE_KEY = 'store_settings_all';

    protected static function booted()
    {
        static::saved(function () {
            Cache::forget(self::CACHE_KEY);
        });

        static::deleted(function () {
            Cache::forget(self::CACHE_KEY);
        });
    }

    /**
     * Return all settings as a cached associative key-value array.
     */
    public static function allCached(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return self::pluck('value', 'key')->toArray();
        });
    }

    /**
     * Helper to easily get settings from cache.
     */
    public static function get(string $key, $default = null)
    {
        $all = self::allCached();
        if (array_key_exists($key, $all)) {
            return $all[$key];
        }
        return $default;
    }

    /**
     * Helper to update or create setting and flush cache.
     */
    public static function set(string $key, $value)
    {
        Cache::forget(self::CACHE_KEY);
        return self::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    /**
     * Store a sensitive secret safely encrypted at rest using AES-256-CBC.
     * Skips updating if the provided value is a masked placeholder (contains bullets).
     */
    public static function setSecret(string $key, ?string $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        // If the value is a masked string, do not overwrite the existing secret
        if (self::isSecretMasked($value)) {
            return self::where('key', $key)->first();
        }

        $trimmed = trim($value);
        $encrypted = 'enc:' . Crypt::encryptString($trimmed);

        return self::set($key, $encrypted);
    }

    /**
     * Retrieve and decrypt a sensitive secret.
     */
    public static function getSecret(string $key, $default = null): ?string
    {
        $raw = self::get($key);

        if (empty($raw)) {
            return $default;
        }

        if (str_starts_with($raw, 'enc:')) {
            try {
                return Crypt::decryptString(substr($raw, 4));
            } catch (\Throwable $e) {
                return $default;
            }
        }

        // Legacy unencrypted fallback
        return $raw;
    }

    /**
     * Mask a sensitive secret for safe administrative display (e.g. sk_live_••••••••••••abcd).
     */
    public static function maskSecret(?string $secret, int $visibleStart = 10, int $visibleEnd = 4): string
    {
        if (empty($secret)) {
            return '';
        }

        $len = strlen($secret);
        if ($len <= ($visibleStart + $visibleEnd)) {
            return str_repeat('•', 12);
        }

        $prefix = substr($secret, 0, $visibleStart);
        $suffix = substr($secret, -$visibleEnd);

        return $prefix . str_repeat('•', 12) . $suffix;
    }

    /**
     * Check whether a string appears to be a masked secret.
     */
    public static function isSecretMasked(?string $value): bool
    {
        if (empty($value)) {
            return false;
        }

        return str_contains($value, '•') || str_contains($value, '••••');
    }
}
