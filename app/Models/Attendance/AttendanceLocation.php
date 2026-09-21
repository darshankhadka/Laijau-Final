<?php

namespace App\Models\Attendance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceLocation extends Model
{
    use HasFactory;

    protected $table = 'attendance_locations';

    protected $fillable = [
        'name',
        'code',
        'latitude',
        'longitude',
        'radius_meters',
        'address',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'radius_meters' => 'integer',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function attendanceEvents(): HasMany
    {
        return $this->hasMany(AttendanceEvent::class, 'attendance_location_id');
    }

    /**
     * Calculate distance from this location to given coordinates in meters using Haversine formula.
     */
    public function calculateDistanceTo(float $latitude, float $longitude): float
    {
        return self::calculateDistanceBetween($this->latitude, $this->longitude, $latitude, $longitude);
    }

    /**
     * Haversine distance in meters between two lat/lon points.
     */
    public static function calculateDistanceBetween(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2);
    }

    /**
     * Determine if given coordinates fall within this location's geofence.
     */
    public function isWithinGeofence(float $latitude, float $longitude, ?int $customRadius = null): bool
    {
        $radius = $customRadius ?? $this->radius_meters;
        $distance = $this->calculateDistanceTo($latitude, $longitude);

        return $distance <= $radius;
    }
}
