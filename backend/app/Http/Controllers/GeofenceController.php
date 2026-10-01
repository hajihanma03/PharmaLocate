<?php

namespace App\Http\Controllers;

use App\Models\Geofence;
use Illuminate\Http\JsonResponse;

class GeofenceController extends Controller
{
    /**
     * Public list of active geofence zones for map visualization (FR8).
     */
    public function index(): JsonResponse
    {
        $geofences = Geofence::where('is_active', true)
            ->with(['pharmacies:id,name,latitude,longitude'])
            ->orderBy('name')
            ->get();

        return response()->json($geofences);
    }
}
