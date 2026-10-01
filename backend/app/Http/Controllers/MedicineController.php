<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MedicineController extends Controller
{
    /**
     * List medicines. Supports ?search= to filter by name, and eager-loads
     * which pharmacies stock each medicine with live availability (FR6).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Medicine::query()->with('pharmacies:id,name,address');

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('brand', 'like', "%{$search}%");
        }

        return response()->json($query->orderBy('name')->get());
    }

    public function show(Medicine $medicine): JsonResponse
    {
        return response()->json($medicine->load('pharmacies:id,name,address'));
    }
}
