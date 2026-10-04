<?php

namespace App\Http\Controllers;

use App\Models\Geofence;
use App\Models\Inquiry;
use App\Models\Pharmacy;
use App\Models\Transaction;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminExportController extends Controller
{
    public function export(Request $request): Response|StreamedResponse
    {
        $user = $request->user();
        $pharmacyId = null;

        if ($user->isPharmacyScoped()) {
            $pharmacyId = $user->pharmacy_id;
            if (! $pharmacyId) {
                return response()->json(['message' => 'No pharmacy is assigned to your account.'], 422);
            }
            $scope = 'transactions';
            $format = 'csv';
        } else {
            $data = $request->validate([
                'scope' => ['required', Rule::in(['full', 'inventory', 'transactions', 'inquiries', 'pharmacies', 'users'])],
                'format' => ['required', Rule::in(['json', 'csv'])],
            ]);
            $scope = $data['scope'];
            $format = $data['format'];
        }

        $payload = $this->buildPayload($scope, $pharmacyId);
        $filename = 'pharmalocate-'.$scope.'-'.now()->format('Y-m-d_His').'.'.$format;

        AuditLogger::log($request->user(), 'export_downloaded', 'export', null, $scope.'/'.$format);

        if ($format === 'json') {
            return response(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 200, [
                'Content-Type' => 'application/json',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]);
        }

        return $this->csvResponse($scope, $payload, $filename, $pharmacyId);
    }

    /** @return array<string, mixed>|list<array<string, mixed>> */
    private function buildPayload(string $scope, ?int $pharmacyId = null): array
    {
        return match ($scope) {
            'inventory' => $this->inventoryRows($pharmacyId),
            'transactions' => $this->transactionRows($pharmacyId),
            'inquiries' => $this->inquiryRows(),
            'pharmacies' => [
                'pharmacies' => Pharmacy::with('medicines')->get(),
                'geofences' => Geofence::with('pharmacies:id,name')->get(),
            ],
            'users' => User::select('id', 'name', 'username', 'email', 'role', 'pharmacy_id', 'phone', 'created_at')
                ->orderBy('name')
                ->get()
                ->toArray(),
            default => [
                'exported_at' => now()->toIso8601String(),
                'counts' => [
                    'users' => User::count(),
                    'pharmacies' => Pharmacy::count(),
                    'medicines' => DB::table('medicines')->count(),
                    'inventory_rows' => DB::table('pharmacy_medicine')->count(),
                    'inquiries' => Inquiry::count(),
                    'transactions' => Transaction::count(),
                    'geofences' => Geofence::count(),
                ],
                'inventory' => $this->inventoryRows(),
                'recent_transactions' => Transaction::with(['pharmacy:id,name', 'items.medicine:id,name'])
                    ->orderByDesc('created_at')
                    ->limit(50)
                    ->get(),
                'recent_inquiries' => Inquiry::with(['user:id,name', 'pharmacy:id,name', 'medicine:id,name'])
                    ->orderByDesc('created_at')
                    ->limit(50)
                    ->get(),
            ],
        };
    }

    /** @return list<array<string, mixed>> */
    private function inventoryRows(?int $pharmacyId = null): array
    {
        $query = DB::table('pharmacy_medicine as pm')
            ->join('medicines as m', 'm.id', '=', 'pm.medicine_id')
            ->join('pharmacies as p', 'p.id', '=', 'pm.pharmacy_id')
            ->select(
                'p.name as pharmacy',
                'm.name as medicine',
                'pm.stock_quantity',
                'pm.price',
                'pm.availability_status',
            )
            ->when($pharmacyId, fn ($q) => $q->where('p.id', $pharmacyId))
            ->orderBy('p.name')
            ->orderBy('m.name')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        return $query;
    }

    /** @return list<array<string, mixed>> */
    private function transactionRows(?int $pharmacyId = null): array
    {
        return Transaction::with(['pharmacy:id,name', 'user:id,name', 'items.medicine:id,name'])
            ->when($pharmacyId, fn ($q) => $q->where('pharmacy_id', $pharmacyId))
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Transaction $tx) {
                return [
                    'id' => $tx->id,
                    'pharmacy' => $tx->pharmacy?->name,
                    'staff' => $tx->user?->name,
                    'total_amount' => $tx->total_amount,
                    'created_at' => $tx->created_at?->toDateTimeString(),
                    'items' => $tx->items->map(fn ($item) => [
                        'medicine' => $item->medicine?->name,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'line_total' => $item->line_total,
                    ])->all(),
                ];
            })
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function inquiryRows(): array
    {
        return Inquiry::with(['user:id,name', 'pharmacy:id,name', 'medicine:id,name'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Inquiry $inq) => [
                'id' => $inq->id,
                'customer' => $inq->user?->name,
                'pharmacy' => $inq->pharmacy?->name,
                'medicine' => $inq->medicine?->name,
                'message' => $inq->message,
                'response' => $inq->response,
                'status' => $inq->status,
                'created_at' => $inq->created_at?->toDateTimeString(),
            ])
            ->all();
    }

    /** @param array<string, mixed>|list<array<string, mixed>> $payload */
    private function csvResponse(string $scope, array $payload, string $filename, ?int $pharmacyId = null): StreamedResponse
    {
        $rows = match ($scope) {
            'inventory' => $this->inventoryCsvRows($pharmacyId),
            'inquiries' => $this->rowsWithSplitTimestamp($payload, 'created_at'),
            'users' => $this->rowsWithSplitTimestamp($payload, 'created_at', ['updated_at']),
            'pharmacies' => $this->flattenPharmaciesForCsv($payload),
            default => $this->transactionCsvRows($pharmacyId),
        };

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            if ($rows === []) {
                fputcsv($out, ['date', 'time', 'message']);
                fputcsv($out, ['', '', 'No data']);
                fclose($out);

                return;
            }
            fputcsv($out, array_keys($rows[0]));
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($v) => is_scalar($v) || $v === null ? $v : json_encode($v), $row));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** @return list<array<string, mixed>> */
    private function transactionCsvRows(?int $pharmacyId = null): array
    {
        $query = Transaction::with(['pharmacy:id,name', 'user:id,name', 'items.medicine:id,name'])
            ->when($pharmacyId, fn ($q) => $q->where('pharmacy_id', $pharmacyId))
            ->orderByDesc('created_at');

        return $query->get()->map(function (Transaction $tx) {
            $items = $tx->items->map(function ($item) {
                $name = $item->medicine?->name ?? 'Item';

                return $name.' x'.$item->quantity;
            })->implode('; ');

            return [
                ...$this->csvDateTime($tx->created_at),
                'transaction_id' => $tx->id,
                'pharmacy' => $tx->pharmacy?->name,
                'staff' => $tx->user?->name,
                'items' => $items,
                'total_amount' => $tx->total_amount,
            ];
        })->all();
    }

    /** @param array<string, mixed> $payload */
    private function flattenPharmaciesForCsv(array $payload): array
    {
        $rows = [];
        foreach ($payload['pharmacies'] as $pharmacy) {
            $rows[] = [
                ...$this->csvDateTime($pharmacy->created_at),
                'type' => 'pharmacy',
                'name' => $pharmacy->name,
                'address' => $pharmacy->address,
                'latitude' => $pharmacy->latitude,
                'longitude' => $pharmacy->longitude,
                'is_active' => $pharmacy->is_active ? '1' : '0',
            ];
        }
        foreach ($payload['geofences'] as $geofence) {
            $rows[] = [
                ...$this->csvDateTime($geofence->created_at),
                'type' => 'geofence',
                'name' => $geofence->name,
                'address' => $geofence->description,
                'latitude' => $geofence->center_latitude,
                'longitude' => $geofence->center_longitude,
                'is_active' => $geofence->is_active ? '1' : '0',
            ];
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    private function inventoryCsvRows(?int $pharmacyId = null): array
    {
        return DB::table('pharmacy_medicine as pm')
            ->join('medicines as m', 'm.id', '=', 'pm.medicine_id')
            ->join('pharmacies as p', 'p.id', '=', 'pm.pharmacy_id')
            ->select(
                'p.name as pharmacy',
                'm.name as medicine',
                'pm.stock_quantity',
                'pm.price',
                'pm.availability_status',
                'pm.updated_at',
            )
            ->when($pharmacyId, fn ($q) => $q->where('p.id', $pharmacyId))
            ->orderBy('p.name')
            ->orderBy('m.name')
            ->get()
            ->map(function ($row) {
                return [
                    ...$this->csvDateTime($row->updated_at),
                    'pharmacy' => $row->pharmacy,
                    'medicine' => $row->medicine,
                    'stock_quantity' => $row->stock_quantity,
                    'price' => $row->price,
                    'availability_status' => $row->availability_status,
                ];
            })
            ->all();
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<string> $drop
     * @return list<array<string, mixed>>
     */
    private function rowsWithSplitTimestamp(array $rows, string $timestampKey, array $drop = []): array
    {
        return array_map(function (array $row) use ($timestampKey, $drop) {
            $stamp = $this->csvDateTime($row[$timestampKey] ?? null);
            unset($row[$timestampKey]);
            foreach ($drop as $key) {
                unset($row[$key]);
            }

            return $stamp + $row;
        }, $rows);
    }

    /** @return array{date: string, time: string} */
    private function csvDateTime(mixed $value): array
    {
        if ($value === null || $value === '') {
            return ['date' => '', 'time' => ''];
        }

        // Sales are stored in UTC. Show the clock time in Tarlac (Asia/Manila),
        // which is when the medicine was actually rung up in POS.
        $dt = Carbon::parse($value)->utc()->timezone('Asia/Manila');

        // Leading tab keeps Excel from turning the date into a serial that displays as ########,
        // and keeps the clock time from being rewritten as 24-hour.
        return [
            'date' => "\t".$dt->format('m/d/Y'),
            'time' => "\t".$dt->format('g:i A'),
        ];
    }
}
