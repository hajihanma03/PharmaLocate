<?php

namespace App\Http\Controllers;

use App\Models\Geofence;
use App\Models\Pharmacy;
use App\Services\Tile38Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PharmacyController extends Controller
{
    public function __construct(private Tile38Service $tile38) {}

    /**
     * List active pharmacies. With ?lat=&lng=, sort nearest-first and — when
     * active geofences exist — return only pharmacies assigned to zones that
     * contain the user's location. Uses Tile38 when enabled; haversine fallback.
     */
    public function index(Request $request): JsonResponse
    {
        $pharmacies = Pharmacy::publicLocator()->get();

        $lat = $request->query('lat');
        $lng = $request->query('lng');

        if (is_numeric($lat) && is_numeric($lng)) {
            $lat = (float) $lat;
            $lng = (float) $lng;

            $activeGeofences = Geofence::where('is_active', true)->get();

            if ($activeGeofences->isNotEmpty()) {
                $matchingGeofenceIds = $this->matchingGeofenceIds($activeGeofences, $lat, $lng);

                if ($matchingGeofenceIds->isEmpty()) {
                    return response()->json([]);
                }

                $allowedPharmacyIds = DB::table('geofence_pharmacy')
                    ->whereIn('geofence_id', $matchingGeofenceIds)
                    ->pluck('pharmacy_id')
                    ->unique();

                $pharmacies = $pharmacies->whereIn('id', $allowedPharmacyIds)->values();
            }

            $tile38Distances = $this->tile38->pharmacyDistancesKm($lat, $lng);

            $pharmacies = $pharmacies
                ->map(function (Pharmacy $pharmacy) use ($lat, $lng, $tile38Distances) {
                    if ($tile38Distances !== null && isset($tile38Distances[$pharmacy->id])) {
                        $pharmacy->distance_km = $tile38Distances[$pharmacy->id];
                    } else {
                        $pharmacy->distance_km = round(Geofence::haversineKm(
                            $lat,
                            $lng,
                            (float) $pharmacy->latitude,
                            (float) $pharmacy->longitude,
                        ), 2);
                    }

                    return $pharmacy;
                })
                ->sortBy('distance_km')
                ->values();
        }

        return response()->json($pharmacies);
    }

    /**
     * Show one pharmacy with its real-time medicine availability (FR6).
     */
    public function show(Pharmacy $pharmacy): JsonResponse
    {
        return response()->json($pharmacy->load('medicines', 'geofences'));
    }

    private function matchingGeofenceIds($activeGeofences, float $lat, float $lng)
    {
        $tile38Ids = $this->tile38->geofenceIdsContainingPoint($lat, $lng);

        if ($tile38Ids !== null) {
            return collect($tile38Ids);
        }

        return $activeGeofences
            ->filter(fn (Geofence $zone) => $zone->containsPoint($lat, $lng))
            ->pluck('id');
    }
}
