<?php

namespace App\Models\Attendance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AttendanceSetting extends Model
{
    use HasFactory;

    protected $table = 'attendance_settings';

    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
    ];

    const CACHE_KEY = 'attendance_settings_map';

    /**
     * Get typed value of a setting.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $map = Cache::rememberForever(self::CACHE_KEY, function () {
            return self::pluck('value', 'key')->toArray();
        });

        if (!array_key_exists($key, $map)) {
            return $default;
        }

        $raw = $map[$key];

        return match ($key) {
            'attendance_enabled',
            'gps_required',
            'photo_required',
            'geofencing_enabled',
            'track_early_departure',
            'overtime_enabled',
            'allow_manual_checkout',
            'require_passkey_optional' => (bool) $raw,

            'max_gps_accuracy_meters',
            'heartbeat_interval_seconds',
            'max_allowed_devices',
            'max_failed_attempts',
            'lockout_minutes',
            'geofence_radius_meters',
            'shift_grace_minutes',
            'late_threshold_minutes',
            'break_minutes' => (int) $raw,

            'standard_daily_hours',
            'standard_weekly_hours',
            'overtime_multiplier',
            'annual_home_leave_days',
            'annual_sick_leave_days',
            'annual_festival_leave_days' => (float) $raw,

            'working_days',
            'off_days' => is_string($raw) ? json_decode($raw, true) : (array) $raw,

            default => $raw,
        };
    }

    /**
     * Set a setting value and clear cache.
     */
    public static function set(string $key, mixed $value, string $type = 'string', ?string $description = null): void
    {
        $valToStore = is_bool($value)
            ? ($value ? '1' : '0')
            : (is_array($value) ? json_encode(array_values($value)) : (string) $value);

        self::updateOrCreate(
            ['key' => $key],
            [
                'value' => $valToStore,
                'type' => $type,
                'description' => $description,
            ]
        );

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Clear the cached settings map.
     */
    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
