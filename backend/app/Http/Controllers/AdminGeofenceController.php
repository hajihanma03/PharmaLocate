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
    public function index(Request $request): JsonResponse
    {
        $query = Geofence::with(['pharmacies:id,name,latitude,longitude,is_active'])
            ->orderBy('name');

        $user = $request->user();
        if ($user->isPharmacyScoped()) {
            $pharmacyId = $user->pharmacy_id;
            $query->where(function ($outer) use ($pharmacyId) {
                $outer->where(function ($own) use ($pharmacyId) {
                    $own->whereHas('pharmacies', fn ($q) => $q->where('pharmacies.id', $pharmacyId))
                        ->whereDoesntHave('pharmacies', fn ($q) => $q->where('pharmacies.id', '!=', $pharmacyId));
                })->orWhere(function ($hospital) use ($pharmacyId) {
                    $hospital->whereHas('pharmacies', fn ($q) => $q->where('pharmacies.id', $pharmacyId))
                        ->where(function ($name) {
                            $name->where('is_starting_point', true)
                                ->orWhere('name', 'like', '%TPH%')
                                ->orWhere('name', 'like', '%Tarlac Provincial%');
                        });
                });
            });
        }

        $zones = $query->get();

        if ($user->isPharmacyScoped()) {
            $pharmacyId = $user->pharmacy_id;
            $zones->each(function (Geofence $zone) use ($pharmacyId) {
                $zone->setRelation(
                    'pharmacies',
                    $zone->pharmacies->where('id', $pharmacyId)->values(),
                );
            });
        }

        return response()->json($zones);
    }

    public function store(Request $request): JsonResponse
    {
        if ($request->user()->isPharmacyScoped()) {
            return $this->storeStaffPharmacyZone($request);
        }

        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Only admins can create geofences.'], 403);
        }

        $pharmacyIds = $request->validate([
            'pharmacy_ids' => ['sometimes', 'array'],
            'pharmacy_ids.*' => ['integer', 'exists:pharmacies,id'],
        ])['pharmacy_ids'] ?? [];

        $storeZone = (int) $request->input('radius_meters') === 50;
        if ($storeZone && count($pharmacyIds) !== 1) {
            return response()->json([
                'message' => 'A 50 meter zone must be tied to one pharmacy.',
            ], 422);
        }

        $data = $this->validatedGeofence($request, storeZone: $storeZone);
        if ($storeZone) {
            $data['is_starting_point'] = false;
        }
        if ($rejected = $this->rejectInvalidStartingRadius($request, $data)) {
            return $rejected;
        }
        if ($rejected = $this->rejectPlainGeofenceRadius($data)) {
            return $rejected;
        }

        if ($storeZone) {
            $parent = $this->resolveNestedParent($request, $data);
            if ($parent instanceof JsonResponse) {
                return $parent;
            }
            $data['parent_id'] = $parent->id;
        }

        $geofence = Geofence::create($data);
        if (! empty($data['is_starting_point'])) {
            $pharmacyIds = $this->startingPointPharmacyIds($geofence, $pharmacyIds);
        }

        if ($pharmacyIds) {
            $geofence->pharmacies()->syncWithoutDetaching($pharmacyIds);
        }
        $this->rehomeNestedZones($geofence, $pharmacyIds);
        if ($rejected = $this->syncNestedGeofences($request, $geofence)) {
            return $rejected;
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

        $wantsStartingPoint = $request->boolean('is_starting_point');
        $storeZone = (int) $geofence->radius_meters === 50 && ! $wantsStartingPoint;
        $data = $this->validatedGeofence($request, updating: true, storeZone: $storeZone);
        if ($storeZone) {
            $data['is_starting_point'] = false;
        }
        if ($rejected = $this->rejectInvalidStartingRadius($request, $data)) {
            return $rejected;
        }
        if ($rejected = $this->rejectPlainGeofenceRadius($data)) {
            return $rejected;
        }
        if ((int) ($data['radius_meters'] ?? $geofence->radius_meters) === 50 && empty($data['is_starting_point'])) {
            $probe = $geofence->replicate()->fill($data);
            $parent = $this->containingStartingPoint($probe);
            if (! $parent) {
                return response()->json([
                    'message' => 'Place this 50 meter geofence inside a starting point.',
                ], 422);
            }
            $data['parent_id'] = $parent->id;
        }

        $geofence->update($data);

        if ($request->has('pharmacy_ids')) {
            $pharmacyIds = $request->validate([
                'pharmacy_ids' => ['array'],
                'pharmacy_ids.*' => ['integer', 'exists:pharmacies,id'],
            ])['pharmacy_ids'];
            if ($geofence->is_starting_point) {
                $claimed = $this->pharmacyIdsClaimedByOtherStartingPoints($geofence->id);
                $pharmacyIds = collect($pharmacyIds)
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->reject(fn (int $id) => in_array($id, $claimed, true))
                    ->values()
                    ->all();
                $this->releaseUnselectedNestedZones($geofence, $pharmacyIds);
            }
            $geofence->pharmacies()->sync($pharmacyIds);
            $this->rehomeNestedZones($geofence, $pharmacyIds);
        }
        if ($rejected = $this->syncNestedGeofences($request, $geofence)) {
            return $rejected;
        }

        $geofence = $this->loadGeofence($geofence);
        $this->tile38->syncGeofence($geofence);

        return response()->json($geofence);
    }

    public function destroy(Request $request, Geofence $geofence): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Only admins can delete geofences.'], 403);
        }

        $id = $geofence->id;
        $name = $geofence->name;
        $pharmacyIds = $geofence->pharmacies()->pluck('pharmacies.id');
        $ownPinZone = (int) $geofence->radius_meters === 50;
        $children = Geofence::query()
            ->where('parent_id', $geofence->id)
            ->with('pharmacies:id,name,latitude,longitude')
            ->get();
        $released = $children
            ->flatMap(fn (Geofence $child) => $child->pharmacies)
            ->unique('id')
            ->map(fn (Pharmacy $pharmacy) => [
                'id' => $pharmacy->id,
                'name' => $pharmacy->name,
                'latitude' => $pharmacy->latitude,
                'longitude' => $pharmacy->longitude,
            ])
            ->values();

        foreach ($children as $child) {
            $childPharmacyIds = $child->pharmacies->pluck('id');
            $this->tile38->removeGeofence($child->id);
            $child->delete();
            if ($childPharmacyIds->isNotEmpty()) {
                Pharmacy::whereIn('id', $childPharmacyIds)->each(function (Pharmacy $pharmacy) {
                    $pharmacy->geofences()->detach();
                });
            }
        }

        $this->tile38->removeGeofence($id);
        $geofence->delete();

        if ($ownPinZone && $pharmacyIds->isNotEmpty()) {
            Pharmacy::whereIn('id', $pharmacyIds)->each(function (Pharmacy $pharmacy) {
                $pharmacy->geofences()->detach();
            });
        }

        return response()->json([
            'message' => $name.' was deleted.',
            'released' => $released,
        ]);
    }

    public function assignNested(Request $request, Geofence $geofence): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Only admins can nest geofences.'], 403);
        }

        if (! $geofence->is_starting_point) {
            return response()->json([
                'message' => 'Choose a starting point. A geofence nests inside a starting point.',
            ], 422);
        }

        $child = Geofence::findOrFail($request->validate([
            'geofence_id' => ['required', 'integer', 'exists:geofences,id'],
        ])['geofence_id']);

        if ($rejected = $this->rejectNestedChild($geofence, $child)) {
            return $rejected;
        }

        $child->update(['parent_id' => $geofence->id]);

        return response()->json([
            'message' => $child->name.' is nested inside '.$geofence->name.'.',
            'geofence' => $this->loadGeofence($geofence),
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

    private function storeStaffPharmacyZone(Request $request): JsonResponse
    {
        $pharmacy = Pharmacy::find($request->user()->pharmacy_id);
        if (! $pharmacy) {
            return response()->json(['message' => 'No pharmacy is assigned to your account.'], 422);
        }

        $data = $request->validate([
            'center_latitude' => ['sometimes', 'numeric', 'between:-90,90'],
            'center_longitude' => ['sometimes', 'numeric', 'between:-180,180'],
        ]);

        $hasLat = array_key_exists('center_latitude', $data);
        $hasLng = array_key_exists('center_longitude', $data);
        if ($hasLat !== $hasLng) {
            return response()->json(['message' => 'Set both latitude and longitude for the pharmacy.'], 422);
        }

        $latitude = $hasLat ? (float) $data['center_latitude'] : $pharmacy->latitude;
        $longitude = $hasLng ? (float) $data['center_longitude'] : $pharmacy->longitude;

        if ($latitude === null || $longitude === null) {
            return response()->json(['message' => 'Click the map or enter the pharmacy latitude and longitude.'], 422);
        }

        $pharmacy->update([
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);
        $this->tile38->syncPharmacy($pharmacy);

        $existing = Geofence::query()
            ->where('radius_meters', 50)
            ->whereHas('pharmacies', fn ($q) => $q->where('pharmacies.id', $pharmacy->id))
            ->first();

        if ($existing) {
            $existing->update([
                'name' => $pharmacy->name.' — 50 m',
                'description' => '50 meter zone around '.$pharmacy->name,
                'center_latitude' => $latitude,
                'center_longitude' => $longitude,
                'radius_meters' => 50,
                'is_active' => true,
                'parent_id' => $this->containingStartingPoint(new Geofence([
                    'center_latitude' => $latitude,
                    'center_longitude' => $longitude,
                    'radius_meters' => 50,
                ]))?->id,
            ]);
            $geofence = $this->loadGeofence($existing);
            $this->tile38->syncGeofence($geofence);

            return response()->json($geofence);
        }

        $geofence = Geofence::create([
            'name' => $pharmacy->name.' — 50 m',
            'description' => '50 meter zone around '.$pharmacy->name,
            'center_latitude' => $latitude,
            'center_longitude' => $longitude,
            'radius_meters' => 50,
            'is_active' => true,
            'parent_id' => $this->containingStartingPoint(new Geofence([
                'center_latitude' => $latitude,
                'center_longitude' => $longitude,
                'radius_meters' => 50,
            ]))?->id,
        ]);
        $geofence->pharmacies()->sync([$pharmacy->id]);

        $geofence = $this->loadGeofence($geofence);
        $this->tile38->syncGeofence($geofence);

        return response()->json($geofence, 201);
    }

    private function validatedGeofence(Request $request, bool $updating = false, bool $storeZone = false): array
    {
        $radiusRule = $storeZone
            ? ['sometimes', 'integer', 'in:50']
            : ['sometimes', 'integer', 'min:500', 'max:10000'];

        return $request->validate([
            'name' => [$updating ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'center_latitude' => [$updating ? 'sometimes' : 'required', 'numeric', 'between:-90,90'],
            'center_longitude' => [$updating ? 'sometimes' : 'required', 'numeric', 'between:-180,180'],
            'radius_meters' => $radiusRule,
            'is_active' => ['sometimes', 'boolean'],
            'is_starting_point' => ['sometimes', 'boolean'],
        ]);
    }

    private function rejectInvalidStartingRadius(Request $request, array $data): ?JsonResponse
    {
        if (! $request->boolean('is_starting_point') && empty($data['is_starting_point'])) {
            return null;
        }

        $radius = (int) ($data['radius_meters'] ?? $request->input('radius_meters'));
        if ($radius < 500 || $radius > 1000) {
            return response()->json([
                'message' => 'A starting point radius must be between 500 and 1000 meters.',
            ], 422);
        }

        return null;
    }

    private function syncNestedGeofences(Request $request, Geofence $geofence): ?JsonResponse
    {
        if (! $request->exists('nested_geofence_ids')) {
            return null;
        }

        if (! $geofence->is_starting_point) {
            return response()->json([
                'message' => 'Only a starting point has nested geofences.',
            ], 422);
        }

        $ids = $request->validate([
            'nested_geofence_ids' => ['array'],
            'nested_geofence_ids.*' => ['integer', 'exists:geofences,id'],
        ])['nested_geofence_ids'];

        $children = Geofence::whereIn('id', $ids)->get();
        foreach ($children as $child) {
            if ($rejected = $this->rejectNestedChild($geofence, $child)) {
                return $rejected;
            }
        }

        $clear = Geofence::where('parent_id', $geofence->id);
        if ($ids) {
            $clear->whereNotIn('id', $ids);
        }
        $clear->update(['parent_id' => null]);

        if ($ids) {
            Geofence::whereIn('id', $ids)->update(['parent_id' => $geofence->id]);
        }

        return null;
    }

    private function rejectNestedChild(Geofence $parent, Geofence $child): ?JsonResponse
    {
        if ($child->id === $parent->id || $child->is_starting_point) {
            return response()->json([
                'message' => 'Choose a geofence, such as a 50 meter zone. A starting point is not nested inside another starting point.',
            ], 422);
        }

        if ($child->center_latitude === null || $child->center_longitude === null || ! $parent->containsPoint((float) $child->center_latitude, (float) $child->center_longitude)) {
            return response()->json([
                'message' => $child->name.' is outside '.$parent->name.'. Move that geofence inside the starting point, then assign it here.',
            ], 422);
        }

        return null;
    }

    private function containingStartingPoint(Geofence $child): ?Geofence
    {
        return Geofence::query()
            ->where('is_active', true)
            ->where('is_starting_point', true)
            ->where('id', '!=', $child->id)
            ->get()
            ->filter(function (Geofence $parent) use ($child) {
                return (int) $parent->radius_meters > (int) $child->radius_meters
                    && $child->center_latitude !== null
                    && $parent->containsPoint((float) $child->center_latitude, (float) $child->center_longitude);
            })
            ->sortBy(function (Geofence $parent) use ($child) {
                $hospital = preg_match('/TPH|Tarlac Provincial/i', (string) $parent->name) ? 0 : 1;
                $meters = (int) round(Geofence::haversineKm(
                    (float) $child->center_latitude,
                    (float) $child->center_longitude,
                    (float) $parent->center_latitude,
                    (float) $parent->center_longitude,
                ) * 1000);

                return sprintf('%08d-%d-%08d', (int) $parent->radius_meters, $hospital, $meters);
            })
            ->first();
    }

    private function rejectPlainGeofenceRadius(array $data): ?JsonResponse
    {
        if (! empty($data['is_starting_point'])) {
            return null;
        }

        if ((int) ($data['radius_meters'] ?? 0) !== 50) {
            return response()->json([
                'message' => 'A nested pin is 50 meters. A starting point is 500–1,000 meters.',
            ], 422);
        }

        return null;
    }

    private function resolveNestedParent(Request $request, array $data): Geofence|JsonResponse
    {
        $parent = $request->filled('parent_id')
            ? Geofence::find($request->integer('parent_id'))
            : $this->containingStartingPoint(new Geofence($data));

        if (! $parent || ! $parent->is_starting_point) {
            return response()->json([
                'message' => 'Click inside a starting point. A 50 meter geofence nests there.',
            ], 422);
        }

        if ($data['center_latitude'] === null || ! $parent->containsPoint((float) $data['center_latitude'], (float) $data['center_longitude'])) {
            return response()->json([
                'message' => 'Place this geofence inside '.$parent->name.'.',
            ], 422);
        }

        return $parent;
    }

    private function startingPointPharmacyIds(Geofence $geofence, array $requestedIds): array
    {
        $claimed = $this->pharmacyIdsClaimedByOtherStartingPoints($geofence->id);

        return collect($requestedIds)
            ->merge($this->pharmacyIdsInside($geofence))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->reject(fn (int $id) => in_array($id, $claimed, true))
            ->values()
            ->all();
    }

    private function pharmacyIdsClaimedByOtherStartingPoints(int $exceptId): array
    {
        $fromStartingPoints = Geofence::query()
            ->where('is_starting_point', true)
            ->where('id', '!=', $exceptId)
            ->with('pharmacies:id')
            ->get()
            ->flatMap(fn (Geofence $zone) => $zone->pharmacies->pluck('id'));

        $fromPins = Geofence::query()
            ->where('radius_meters', 50)
            ->whereNotNull('parent_id')
            ->where('parent_id', '!=', $exceptId)
            ->whereIn('parent_id', function ($query) {
                $query->select('id')->from('geofences')->where('is_starting_point', true);
            })
            ->with('pharmacies:id')
            ->get()
            ->flatMap(fn (Geofence $zone) => $zone->pharmacies->pluck('id'));

        return $fromStartingPoints
            ->merge($fromPins)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function releaseUnselectedNestedZones(Geofence $startingPoint, array $pharmacyIds): void
    {
        $keep = collect($pharmacyIds)->map(fn ($id) => (int) $id);

        Geofence::query()
            ->where('radius_meters', 50)
            ->where('parent_id', $startingPoint->id)
            ->with('pharmacies:id')
            ->get()
            ->each(function (Geofence $zone) use ($startingPoint, $keep) {
                $ids = $zone->pharmacies->pluck('id')->map(fn ($id) => (int) $id);
                if ($ids->intersect($keep)->isNotEmpty()) {
                    return;
                }

                $this->tile38->removeGeofence($zone->id);
                $zone->delete();
                Pharmacy::whereIn('id', $ids)->each(function (Pharmacy $pharmacy) use ($startingPoint) {
                    $pharmacy->geofences()->detach($startingPoint->id);
                });
            });
    }

    private function rehomeNestedZones(Geofence $startingPoint, array $pharmacyIds): void
    {
        if (! $startingPoint->is_starting_point || $pharmacyIds === []) {
            return;
        }

        Geofence::query()
            ->where('radius_meters', 50)
            ->where('id', '!=', $startingPoint->id)
            ->whereHas('pharmacies', fn ($query) => $query->whereIn('pharmacies.id', $pharmacyIds))
            ->get()
            ->each(function (Geofence $zone) use ($startingPoint) {
                if ($zone->center_latitude === null || $zone->center_longitude === null) {
                    return;
                }
                if (! $startingPoint->containsPoint((float) $zone->center_latitude, (float) $zone->center_longitude)) {
                    return;
                }
                if ((int) $zone->parent_id === (int) $startingPoint->id) {
                    return;
                }
                $zone->update(['parent_id' => $startingPoint->id]);
                $this->tile38->syncGeofence($zone);
            });
    }

    private function pharmacyIdsInside(Geofence $geofence): array
    {
        return Geofence::query()
            ->where('radius_meters', 50)
            ->where('parent_id', $geofence->id)
            ->with('pharmacies:id')
            ->get()
            ->flatMap(fn (Geofence $zone) => $zone->pharmacies->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function loadGeofence(Geofence $geofence): Geofence
    {
        return $geofence->fresh()->load(['pharmacies:id,name,latitude,longitude,is_active']);
    }
}
