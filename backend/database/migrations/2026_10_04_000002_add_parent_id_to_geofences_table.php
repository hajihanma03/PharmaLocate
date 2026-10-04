<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('geofences', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('is_starting_point')->constrained('geofences')->nullOnDelete();
        });

        $zones = DB::table('geofences')->get();
        foreach ($zones as $child) {
            if ($child->is_starting_point || $child->center_latitude === null || $child->center_longitude === null) {
                continue;
            }

            $parents = $zones->filter(function ($parent) use ($child) {
                if ($parent->id === $child->id || ! $parent->is_active || ! $parent->is_starting_point) {
                    return false;
                }
                if ((int) $parent->radius_meters <= (int) $child->radius_meters) {
                    return false;
                }

                return $this->metersBetween($child, $parent) <= (int) $parent->radius_meters;
            })->sortBy(function ($parent) use ($child) {
                $hospital = preg_match('/TPH|Tarlac Provincial/i', (string) $parent->name) ? 0 : 1;

                return sprintf('%08d-%d-%08d', (int) $parent->radius_meters, $hospital, (int) round($this->metersBetween($child, $parent)));
            });

            $chosen = $parents->first();
            if ($chosen) {
                DB::table('geofences')->where('id', $child->id)->update(['parent_id' => $chosen->id]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('geofences', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
        });
    }

    private function metersBetween(object $child, object $parent): float
    {
        $earth = 6371000;
        $lat1 = deg2rad((float) $child->center_latitude);
        $lat2 = deg2rad((float) $parent->center_latitude);
        $dLat = deg2rad((float) $parent->center_latitude - (float) $child->center_latitude);
        $dLng = deg2rad((float) $parent->center_longitude - (float) $child->center_longitude);
        $a = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;

        return $earth * 2 * asin(min(1, sqrt($a)));
    }
};
