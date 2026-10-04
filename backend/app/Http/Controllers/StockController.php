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

        if ($user->isPharmacyScoped()) {
            $query->where('pm.pharmacy_id', $user->pharmacy_id);
        }

        Setting::syncInventoryStatuses();

        $rows = $query->get()->map(function ($row) {
            $row->price = (float) $row->price;
            $row->stock_quantity = (int) $row->stock_quantity;
            $row->availability_status = Setting::statusForQuantity($row->stock_quantity);
            $row->status_label = match ($row->availability_status) {
                'low' => 'Low stock',
                'out_of_stock' => 'Out of stock',
                default => 'In stock',
            };

            return $row;
        });

        return response()->json($rows);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'quantity' => ['required', 'integer', 'min:1'],
            'pharmacy_id' => ['nullable', 'integer', 'exists:pharmacies,id'],
        ]);

        if ($user->isPharmacyScoped()) {
            if (! $user->pharmacy_id) {
                return response()->json(['message' => 'No pharmacy is assigned to your account.'], 422);
            }
            $pharmacyId = (int) $user->pharmacy_id;
        } elseif (empty($data['pharmacy_id'])) {
            return response()->json(['message' => 'Choose a pharmacy for this medicine.'], 422);
        } else {
            $pharmacyId = (int) $data['pharmacy_id'];
        }

        $pharmacy = Pharmacy::query()->where('id', $pharmacyId)->where('is_active', true)->first();
        if (! $pharmacy) {
            return response()->json(['message' => 'That pharmacy is not available.'], 422);
        }

        $name = trim($data['name']);
        $quantity = (int) $data['quantity'];
        $price = round((float) $data['price'], 2);

        $row = DB::transaction(function () use ($name, $quantity, $price, $pharmacy) {
            $medicine = Medicine::query()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->first();

            if (! $medicine) {
                $medicine = Medicine::create(['name' => $name]);
            }

            $existing = DB::table('pharmacy_medicine')
                ->where('pharmacy_id', $pharmacy->id)
                ->where('medicine_id', $medicine->id)
                ->lockForUpdate()
                ->first();

            $stockQuantity = $quantity + (int) ($existing->stock_quantity ?? 0);
            $status = Setting::statusForQuantity($stockQuantity);
            $now = now();

            if ($existing) {
                DB::table('pharmacy_medicine')
                    ->where('id', $existing->id)
                    ->update([
                        'stock_quantity' => $stockQuantity,
                        'price' => $price,
                        'availability_status' => $status,
                        'updated_at' => $now,
                    ]);
            } else {
                DB::table('pharmacy_medicine')->insert([
                    'pharmacy_id' => $pharmacy->id,
                    'medicine_id' => $medicine->id,
                    'stock_quantity' => $stockQuantity,
                    'price' => $price,
                    'availability_status' => $status,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            return $this->stockRow($pharmacy->id, $medicine->id, (bool) $existing);
        });

        return response()->json($row, 201);
    }

    public function update(Request $request, Pharmacy $pharmacy, Medicine $medicine): JsonResponse
    {
        $user = $request->user();

        if ($user->isPharmacyScoped() && (int) $user->pharmacy_id !== (int) $pharmacy->id) {
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

        return response()->json($this->presentStockRow($row));
    }

    public function destroy(Request $request, Pharmacy $pharmacy, Medicine $medicine): JsonResponse
    {
        $user = $request->user();

        if (! in_array($user->role, ['admin', 'staff', 'owner'], true)) {
            return response()->json(['message' => 'You cannot change inventory.'], 403);
        }

        if ($user->isPharmacyScoped() && (int) $user->pharmacy_id !== (int) $pharmacy->id) {
            return response()->json(['message' => 'You can only remove medicines from your pharmacy.'], 403);
        }

        $removed = DB::table('pharmacy_medicine')
            ->where('pharmacy_id', $pharmacy->id)
            ->where('medicine_id', $medicine->id)
            ->delete();

        if (! $removed) {
            return response()->json(['message' => 'That medicine is not in this pharmacy inventory.'], 404);
        }

        return response()->json([
            'message' => $medicine->name.' was removed from '.$pharmacy->name.'.',
        ]);
    }

    private function stockRow(int $pharmacyId, int $medicineId, bool $addedToExisting): array
    {
        $row = DB::table('pharmacy_medicine as pm')
            ->join('medicines as m', 'm.id', '=', 'pm.medicine_id')
            ->join('pharmacies as p', 'p.id', '=', 'pm.pharmacy_id')
            ->where('pm.pharmacy_id', $pharmacyId)
            ->where('pm.medicine_id', $medicineId)
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

        return $this->presentStockRow($row, $addedToExisting);
    }

    private function presentStockRow(object $row, bool $addedToExisting = false): array
    {
        return [
            'pharmacy_id' => (int) $row->pharmacy_id,
            'medicine_id' => (int) $row->medicine_id,
            'name' => $row->name,
            'category' => $row->category,
            'pharmacy' => $row->pharmacy,
            'price' => (float) $row->price,
            'stock_quantity' => (int) $row->stock_quantity,
            'availability_status' => $row->availability_status,
            'status_label' => match ($row->availability_status) {
                'low' => 'Low stock',
                'out_of_stock' => 'Out of stock',
                default => 'In stock',
            },
            'added_to_existing' => $addedToExisting,
        ];
    }
}
