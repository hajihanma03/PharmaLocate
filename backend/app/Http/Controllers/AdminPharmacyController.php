<?php

namespace App\Http\Controllers;

use App\Models\Geofence;
use App\Models\Pharmacy;
use App\Models\User;
use App\Services\Tile38Service;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminPharmacyController extends Controller
{
    public function __construct(private Tile38Service $tile38) {}
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Pharmacy::with('geofences:id,name')->orderBy('name');

        if ($user->isPharmacyScoped()) {
            if (! $user->pharmacy_id) {
                return response()->json([]);
            }
            $query->where('id', $user->pharmacy_id);
        }

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Only admins can create pharmacies.'], 403);
        }

        $data = $this->validatedPharmacy($request);

        $pharmacy = Pharmacy::create($data);
        $pharmacy->load('geofences:id,name');
        $this->tile38->syncPharmacy($pharmacy);

        return response()->json($pharmacy, 201);
    }

    public function update(Request $request, Pharmacy $pharmacy): JsonResponse
    {
        $user = $request->user();

        if ($user->isPharmacyScoped() && (int) $user->pharmacy_id !== (int) $pharmacy->id) {
            return response()->json(['message' => 'You can only update your assigned pharmacy.'], 403);
        }

        $data = $this->validatedPharmacy($request, updating: true);

        $pharmacy->update($data);

        $pharmacy = $pharmacy->fresh()->load('geofences:id,name');
        $this->tile38->syncPharmacy($pharmacy);
        $this->moveFiftyMeterZone($pharmacy);

        return response()->json($pharmacy);
    }

    public function destroy(Request $request, Pharmacy $pharmacy): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Only admins can delete pharmacies.'], 403);
        }

        $pharmacyId = $pharmacy->id;
        $name = $pharmacy->name;
        $pinZones = Geofence::query()
            ->where('radius_meters', 50)
            ->whereHas('pharmacies', fn ($query) => $query->where('pharmacies.id', $pharmacyId))
            ->get();

        DB::transaction(function () use ($request, $pharmacy, $pharmacyId, $name, $pinZones) {
            foreach ($pinZones as $zone) {
                $this->tile38->removeGeofence($zone->id);
                $zone->delete();
            }
            User::where('pharmacy_id', $pharmacyId)->update(['pharmacy_id' => null]);
            AuditLogger::log($request->user(), 'pharmacy_deleted', 'pharmacy', $pharmacyId, $name);
            $pharmacy->delete();
        });

        $this->tile38->removePharmacy($pharmacyId);

        return response()->json(['message' => $name.' was deleted.']);
    }

    private function moveFiftyMeterZone(Pharmacy $pharmacy): void
    {
        if ($pharmacy->latitude === null || $pharmacy->longitude === null) {
            return;
        }

        $zones = Geofence::query()
            ->where('radius_meters', 50)
            ->whereHas('pharmacies', fn ($q) => $q->where('pharmacies.id', $pharmacy->id))
            ->get();

        foreach ($zones as $zone) {
            $zone->update([
                'center_latitude' => $pharmacy->latitude,
                'center_longitude' => $pharmacy->longitude,
                'radius_meters' => 50,
            ]);
            $this->tile38->syncGeofence($zone);
        }
    }

    private function validatedPharmacy(Request $request, bool $updating = false): array
    {
        $rules = [
            'name' => [$updating ? 'sometimes' : 'required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'operating_hours' => ['nullable', 'string', 'max:255'],
            'latitude' => [$updating ? 'sometimes' : 'required', 'numeric', 'between:-90,90'],
            'longitude' => [$updating ? 'sometimes' : 'required', 'numeric', 'between:-180,180'],
            'is_active' => ['sometimes', 'boolean'],
            'inside_tph' => ['sometimes', 'boolean'],
        ];

        return $request->validate($rules);
    }
}
