<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AvailabilityController extends Controller
{
    /**
     * Flat medicine availability rows for the frontend medicine grid (FR6).
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search');
        $pharmacyId = $request->query('pharmacy_id');

        $query = DB::table('pharmacy_medicine as pm')
            ->join('medicines as m', 'm.id', '=', 'pm.medicine_id')
            ->join('pharmacies as p', 'p.id', '=', 'pm.pharmacy_id')
            ->where('p.is_active', true)
            ->where('p.inside_tph', false)
            ->select(
                'm.id as medicine_id',
                'p.id as pharmacy_id',
                'm.name',
                DB::raw('COALESCE(NULLIF(m.description, \'\'), NULLIF(m.brand, \'\'), \'Medicine\') as type'),
                'p.name as pharmacy',
                'pm.stock_quantity as qty',
                'pm.availability_status'
            )
            ->orderBy('m.name');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('m.name', 'like', "%{$search}%")
                    ->orWhere('m.brand', 'like', "%{$search}%")
                    ->orWhere('m.description', 'like', "%{$search}%");
            });
        }

        if ($pharmacyId) {
            $query->where('p.id', $pharmacyId);
        }

        $rows = $query->get()->map(function ($row) {
            $row->status = match ($row->availability_status) {
                'low' => 'low',
                'out_of_stock' => 'out',
                default => 'available',
            };
            unset($row->availability_status);

            return $row;
        });

        return response()->json($rows);
    }
}
