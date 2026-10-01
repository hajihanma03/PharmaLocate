<?php

namespace App\Http\Controllers;

use App\Models\Pharmacy;
use App\Services\Tile38Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPharmacyController extends Controller
{
    public function __construct(private Tile38Service $tile38) {}
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Pharmacy::with('geofences:id,name')->orderBy('name');

        if ($user->role === 'staff') {
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

        if ($user->role === 'staff' && $user->pharmacy_id !== $pharmacy->id) {
            return response()->json(['message' => 'You can only update your assigned pharmacy.'], 403);
        }

        $data = $this->validatedPharmacy($request, updating: true);

        $pharmacy->update($data);

        $pharmacy = $pharmacy->fresh()->load('geofences:id,name');
        $this->tile38->syncPharmacy($pharmacy);

        return response()->json($pharmacy);
    }

    public function destroy(Request $request, Pharmacy $pharmacy): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Only admins can deactivate pharmacies.'], 403);
        }

        $pharmacy->update(['is_active' => false]);

        $pharmacy = $pharmacy->fresh();
        $this->tile38->syncPharmacy($pharmacy);

        return response()->json(['message' => 'Pharmacy deactivated.', 'pharmacy' => $pharmacy]);
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
