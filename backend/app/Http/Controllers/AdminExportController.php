<?php

namespace App\Http\Controllers;

use App\Models\Geofence;
use App\Models\Inquiry;
use App\Models\Pharmacy;
use App\Models\Transaction;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminExportController extends Controller
{
    public function export(Request $request): Response|StreamedResponse
    {
        $data = $request->validate([
            'scope' => ['required', Rule::in(['full', 'inventory', 'transactions', 'inquiries', 'pharmacies', 'users'])],
            'format' => ['required', Rule::in(['json', 'csv'])],
        ]);

        $scope = $data['scope'];
        $format = $data['format'];
        $payload = $this->buildPayload($scope);
        $filename = 'pharmalocate-'.$scope.'-'.now()->format('Y-m-d_His').'.'.$format;

        AuditLogger::log($request->user(), 'export_downloaded', 'export', null, $scope.'/'.$format);

        if ($format === 'json') {
            return response(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 200, [
                'Content-Type' => 'application/json',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]);
        }

        return $this->csvResponse($scope, $payload, $filename);
    }

    /** @return array<string, mixed>|list<array<string, mixed>> */
    private function buildPayload(string $scope): array
    {
        return match ($scope) {
            'inventory' => $this->inventoryRows(),
            'transactions' => $this->transactionRows(),
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
    private function inventoryRows(): array
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
            )
            ->orderBy('p.name')
            ->orderBy('m.name')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function transactionRows(): array
    {
        return Transaction::with(['pharmacy:id,name', 'user:id,name', 'items.medicine:id,name'])
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
    private function csvResponse(string $scope, array $payload, string $filename): StreamedResponse
    {
        $rows = match ($scope) {
            'inventory', 'inquiries' => $payload,
            'transactions' => $this->flattenTransactionsForCsv($payload),
            'users' => $payload,
            'pharmacies' => $this->flattenPharmaciesForCsv($payload),
            default => $this->inventoryRows(),
        };

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            if ($rows === []) {
                fputcsv($out, ['message']);
                fputcsv($out, ['No data']);
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

    /** @param list<array<string, mixed>> $transactions */
    private function flattenTransactionsForCsv(array $transactions): array
    {
        $rows = [];
        foreach ($transactions as $tx) {
            foreach ($tx['items'] as $item) {
                $rows[] = [
                    'transaction_id' => $tx['id'],
                    'pharmacy' => $tx['pharmacy'],
                    'staff' => $tx['staff'],
                    'total_amount' => $tx['total_amount'],
                    'created_at' => $tx['created_at'],
                    'medicine' => $item['medicine'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => $item['line_total'],
                ];
            }
            if ($tx['items'] === []) {
                $rows[] = [
                    'transaction_id' => $tx['id'],
                    'pharmacy' => $tx['pharmacy'],
                    'staff' => $tx['staff'],
                    'total_amount' => $tx['total_amount'],
                    'created_at' => $tx['created_at'],
                    'medicine' => '',
                    'quantity' => '',
                    'unit_price' => '',
                    'line_total' => '',
                ];
            }
        }

        return $rows;
    }

    /** @param array<string, mixed> $payload */
    private function flattenPharmaciesForCsv(array $payload): array
    {
        $rows = [];
        foreach ($payload['pharmacies'] as $pharmacy) {
            $rows[] = [
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
}
