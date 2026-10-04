<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Geofence extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'center_latitude',
        'center_longitude',
        'radius_meters',
        'is_active',
        'is_starting_point',
        'parent_id',
    ];

    protected $casts = [
        'center_latitude' => 'float',
        'center_longitude' => 'float',
        'radius_meters' => 'integer',
        'is_active' => 'boolean',
        'is_starting_point' => 'boolean',
        'parent_id' => 'integer',
    ];

    public function pharmacies(): BelongsToMany
    {
        return $this->belongsToMany(Pharmacy::class)->withTimestamps();
    }

    public function containsPoint(float $lat, float $lng): bool
    {
        $distanceKm = self::haversineKm(
            $lat,
            $lng,
            (float) $this->center_latitude,
            (float) $this->center_longitude,
        );

        return ($distanceKm * 1000) <= $this->radius_meters;
    }

    public static function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthRadius * 2 * asin(min(1, sqrt($a)));
    }
}
