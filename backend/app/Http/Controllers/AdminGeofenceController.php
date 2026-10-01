<?php

namespace App\Http\Controllers;

use App\Models\Geofence;
use App\Models\Pharmacy;
use App\Services\Tile38Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminGeofenceController extends Controller
{
    public function __construct(private Tile38Service $tile38) {}
    public function index(): JsonResponse
    {
        $geofences = Geofence::with(['pharmacies:id,name,latitude,longitude,is_active'])
            ->orderBy('name')
            ->get();

        return response()->json($geofences);
    }

    public function store(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Only admins can create geofences.'], 403);
        }

        $data = $this->validatedGeofence($request);
        $pharmacyIds = $request->validate([
            'pharmacy_ids' => ['sometimes', 'array'],
            'pharmacy_ids.*' => ['integer', 'exists:pharmacies,id'],
        ])['pharmacy_ids'] ?? [];

        $geofence = Geofence::create($data);

        if ($pharmacyIds) {
            $geofence->pharmacies()->syncWithoutDetaching($pharmacyIds);
        }

        $geofence = $this->loadGeofence($geofence);
        $this->tile38->syncGeofence($geofence);

        return response()->json($geofence, 201);
    }

    public function update(Request $request, Geofence $geofence): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Only admins can update geofences.'], 403);
        }

        $data = $this->validatedGeofence($request, updating: true);

        $geofence->update($data);

        if ($request->has('pharmacy_ids')) {
            $pharmacyIds = $request->validate([
                'pharmacy_ids' => ['array'],
                'pharmacy_ids.*' => ['integer', 'exists:pharmacies,id'],
            ])['pharmacy_ids'];
            $geofence->pharmacies()->sync($pharmacyIds);
        }

        $geofence = $this->loadGeofence($geofence);
        $this->tile38->syncGeofence($geofence);

        return response()->json($geofence);
    }

    public function destroy(Request $request, Geofence $geofence): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Only admins can deactivate geofences.'], 403);
        }

        $geofence->update(['is_active' => false]);

        $geofence = $this->loadGeofence($geofence);
        $this->tile38->syncGeofence($geofence);

        return response()->json([
            'message' => 'Geofence deactivated.',
            'geofence' => $geofence,
        ]);
    }

    public function attachPharmacy(Request $request, Geofence $geofence): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Only admins can assign pharmacies to geofences.'], 403);
        }

        $data = $request->validate([
            'pharmacy_id' => ['required', 'integer', 'exists:pharmacies,id'],
        ]);

        $pharmacy = Pharmacy::findOrFail($data['pharmacy_id']);
        $geofence->pharmacies()->syncWithoutDetaching([$pharmacy->id]);

        return response()->json([
            'message' => 'Pharmacy assigned to geofence.',
            'geofence' => $this->loadGeofence($geofence),
        ]);
    }

    public function detachPharmacy(Request $request, Geofence $geofence, Pharmacy $pharmacy): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Only admins can remove pharmacy assignments.'], 403);
        }

        if (! $geofence->pharmacies()->where('pharmacy_id', $pharmacy->id)->exists()) {
            return response()->json(['message' => 'Pharmacy is not assigned to this geofence.'], 404);
        }

        $geofence->pharmacies()->detach($pharmacy->id);

        return response()->json([
            'message' => 'Pharmacy removed from geofence.',
            'geofence' => $this->loadGeofence($geofence),
        ]);
    }

    private function validatedGeofence(Request $request, bool $updating = false): array
    {
        return $request->validate([
            'name' => [$updating ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'center_latitude' => [$updating ? 'sometimes' : 'required', 'numeric', 'between:-90,90'],
            'center_longitude' => [$updating ? 'sometimes' : 'required', 'numeric', 'between:-180,180'],
            'radius_meters' => ['sometimes', 'integer', 'min:500', 'max:10000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function loadGeofence(Geofence $geofence): Geofence
    {
        return $geofence->fresh()->load(['pharmacies:id,name,latitude,longitude,is_active']);
    }
}
