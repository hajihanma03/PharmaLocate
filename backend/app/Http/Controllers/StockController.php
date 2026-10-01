<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use App\Models\Pharmacy;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StockController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = DB::table('pharmacy_medicine as pm')
            ->join('medicines as m', 'm.id', '=', 'pm.medicine_id')
            ->join('pharmacies as p', 'p.id', '=', 'pm.pharmacy_id')
            ->where('p.is_active', true)
            ->select(
                'pm.pharmacy_id',
                'pm.medicine_id',
                'm.name',
                'm.description as category',
                'p.name as pharmacy',
                'pm.price',
                'pm.stock_quantity',
                'pm.availability_status'
            )
            ->orderBy('m.name');

        if ($user->role === 'staff') {
            $query->where('pm.pharmacy_id', $user->pharmacy_id);
        }

        $rows = $query->get()->map(function ($row) {
            $row->price = (float) $row->price;
            $row->stock_quantity = (int) $row->stock_quantity;
            $row->status_label = match ($row->availability_status) {
                'low' => 'Low stock',
                'out_of_stock' => 'Out of stock',
                default => 'In stock',
            };

            return $row;
        });

        return response()->json($rows);
    }

    public function update(Request $request, Pharmacy $pharmacy, Medicine $medicine): JsonResponse
    {
        $user = $request->user();

        if ($user->role === 'staff' && $user->pharmacy_id !== $pharmacy->id) {
            return response()->json(['message' => 'You can only update stock for your pharmacy.'], 403);
        }

        $data = $request->validate([
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'availability_status' => ['nullable', Rule::in(['available', 'low', 'out_of_stock'])],
        ]);

        $exists = DB::table('pharmacy_medicine')
            ->where('pharmacy_id', $pharmacy->id)
            ->where('medicine_id', $medicine->id)
            ->exists();

        if (! $exists) {
            return response()->json(['message' => 'Medicine not stocked at this pharmacy.'], 404);
        }

        $qty = $data['stock_quantity'];
        $status = $data['availability_status'] ?? Setting::statusForQuantity($qty);

        DB::table('pharmacy_medicine')
            ->where('pharmacy_id', $pharmacy->id)
            ->where('medicine_id', $medicine->id)
            ->update([
                'stock_quantity' => $qty,
                'price' => $data['price'] ?? DB::raw('price'),
                'availability_status' => $status,
                'updated_at' => now(),
            ]);

        $row = DB::table('pharmacy_medicine as pm')
            ->join('medicines as m', 'm.id', '=', 'pm.medicine_id')
            ->join('pharmacies as p', 'p.id', '=', 'pm.pharmacy_id')
            ->where('pm.pharmacy_id', $pharmacy->id)
            ->where('pm.medicine_id', $medicine->id)
            ->select(
                'pm.pharmacy_id',
                'pm.medicine_id',
                'm.name',
                'm.description as category',
                'p.name as pharmacy',
                'pm.price',
                'pm.stock_quantity',
                'pm.availability_status'
            )
            ->first();

        $row->price = (float) $row->price;
        $row->status_label = match ($row->availability_status) {
            'low' => 'Low stock',
            'out_of_stock' => 'Out of stock',
            default => 'In stock',
        };

        return response()->json($row);
    }
}
