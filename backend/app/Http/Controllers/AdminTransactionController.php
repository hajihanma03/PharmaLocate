<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use App\Models\Pharmacy;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminTransactionController extends Controller
{
    public function products(Request $request): JsonResponse
    {
        $pharmacyId = $this->resolvePharmacyId($request);

        if (! $pharmacyId) {
            return response()->json(['message' => 'Pharmacy is required.'], 422);
        }

        $this->assertPharmacyAccess($request, $pharmacyId);

        $rows = DB::table('pharmacy_medicine as pm')
            ->join('medicines as m', 'm.id', '=', 'pm.medicine_id')
            ->where('pm.pharmacy_id', $pharmacyId)
            ->select(
                'pm.medicine_id',
                'm.name',
                'pm.price',
                'pm.stock_quantity',
                'pm.availability_status',
            )
            ->orderBy('m.name')
            ->get()
            ->map(fn ($row) => [
                'medicine_id' => (int) $row->medicine_id,
                'name' => $row->name,
                'price' => (float) $row->price,
                'stock_quantity' => (int) $row->stock_quantity,
                'availability_status' => $row->availability_status,
            ]);

        return response()->json($rows);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $pharmacyId = $user->isPharmacyScoped()
            ? $user->pharmacy_id
            : ($request->query('pharmacy_id') ? (int) $request->query('pharmacy_id') : null);

        if ($pharmacyId) {
            $this->assertPharmacyAccess($request, $pharmacyId);
        }

        $limit = min(50, max(1, (int) $request->query('limit', 10)));

        $query = Transaction::with([
            'pharmacy:id,name',
            'user:id,name',
            'items.medicine:id,name',
        ])->orderByDesc('created_at');

        if ($pharmacyId) {
            $query->where('pharmacy_id', $pharmacyId);
        }

        return response()->json($query->limit($limit)->get());
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $rules = [
            'items' => ['required', 'array', 'min:1'],
            'items.*.medicine_id' => ['required', 'integer', 'exists:medicines,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];

        if ($user->role === 'admin') {
            $rules['pharmacy_id'] = ['required', 'integer', 'exists:pharmacies,id'];
        }

        $data = $request->validate($rules);

        $pharmacyId = $user->isPharmacyScoped()
            ? (int) $user->pharmacy_id
            : (int) $data['pharmacy_id'];

        $this->assertPharmacyAccess($request, $pharmacyId);

        try {
            $transaction = DB::transaction(function () use ($user, $pharmacyId, $data) {
            $lineItems = [];
            $total = 0.0;

            foreach ($data['items'] as $item) {
                $medicineId = (int) $item['medicine_id'];
                $qty = (int) $item['quantity'];

                $stock = DB::table('pharmacy_medicine')
                    ->where('pharmacy_id', $pharmacyId)
                    ->where('medicine_id', $medicineId)
                    ->lockForUpdate()
                    ->first();

                if (! $stock) {
                    throw new \RuntimeException('Medicine is not stocked at this pharmacy.');
                }

                if ((int) $stock->stock_quantity < $qty) {
                    $medicine = Medicine::find($medicineId);
                    throw new \RuntimeException(
                        'Insufficient stock for '.($medicine?->name ?? 'medicine').". Available: {$stock->stock_quantity}.",
                    );
                }

                $unitPrice = (float) $stock->price;
                $lineTotal = round($unitPrice * $qty, 2);
                $total += $lineTotal;

                $lineItems[] = [
                    'medicine_id' => $medicineId,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'new_qty' => (int) $stock->stock_quantity - $qty,
                ];
            }

            $transaction = Transaction::create([
                'pharmacy_id' => $pharmacyId,
                'user_id' => $user->id,
                'total_amount' => round($total, 2),
            ]);

            foreach ($lineItems as $line) {
                TransactionItem::create([
                    'transaction_id' => $transaction->id,
                    'medicine_id' => $line['medicine_id'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'line_total' => $line['line_total'],
                ]);

                DB::table('pharmacy_medicine')
                    ->where('pharmacy_id', $pharmacyId)
                    ->where('medicine_id', $line['medicine_id'])
                    ->update([
                        'stock_quantity' => $line['new_qty'],
                        'availability_status' => Setting::statusForQuantity($line['new_qty']),
                        'updated_at' => now(),
                    ]);
            }

            return $transaction;
        });
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        AuditLogger::log(
            $user,
            'transaction_created',
            'transaction',
            $transaction->id,
            'Total: '.$transaction->total_amount,
        );

        return response()->json(
            $transaction->load(['pharmacy:id,name', 'user:id,name', 'items.medicine:id,name']),
            201,
        );
    }

    private function resolvePharmacyId(Request $request): ?int
    {
        $user = $request->user();

        if ($user->isPharmacyScoped()) {
            return $user->pharmacy_id ? (int) $user->pharmacy_id : null;
        }

        $pharmacyId = $request->query('pharmacy_id');

        return is_numeric($pharmacyId) ? (int) $pharmacyId : null;
    }

    private function assertPharmacyAccess(Request $request, int $pharmacyId): void
    {
        $user = $request->user();

        if ($user->isPharmacyScoped() && (int) $user->pharmacy_id !== $pharmacyId) {
            abort(403, 'You can only access your assigned pharmacy.');
        }

        if (! Pharmacy::where('id', $pharmacyId)->where('is_active', true)->exists()) {
            abort(404, 'Pharmacy not found.');
        }
    }
}
