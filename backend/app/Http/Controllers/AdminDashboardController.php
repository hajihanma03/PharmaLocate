<?php

namespace App\Http\Controllers;

use App\Models\Geofence;
use App\Models\Inquiry;
use App\Models\Pharmacy;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $pharmacyId = $user->role === 'staff' ? $user->pharmacy_id : null;

        $pharmaciesQuery = Pharmacy::where('is_active', true);
        if ($pharmacyId) {
            $pharmaciesQuery->where('id', $pharmacyId);
        }

        $inquiriesQuery = Inquiry::query();
        if ($pharmacyId) {
            $inquiriesQuery->where('pharmacy_id', $pharmacyId);
        }

        $stockQuery = DB::table('pharmacy_medicine as pm')
            ->join('pharmacies as p', 'p.id', '=', 'pm.pharmacy_id')
            ->where('p.is_active', true);
        if ($pharmacyId) {
            $stockQuery->where('pm.pharmacy_id', $pharmacyId);
        }

        $salesQuery = Transaction::whereDate('created_at', today());
        if ($pharmacyId) {
            $salesQuery->where('pharmacy_id', $pharmacyId);
        }

        $recentInquiries = (clone $inquiriesQuery)
            ->with(['user:id,name', 'pharmacy:id,name', 'medicine:id,name'])
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get()
            ->map(fn (Inquiry $inq) => [
                'type' => $inq->response ? 'inquiry_replied' : 'inquiry_pending',
                'label' => $inq->medicine?->name ?? 'General inquiry',
                'pharmacy' => $inq->pharmacy?->name,
                'time' => $inq->updated_at?->diffForHumans(),
            ]);

        $lowStockItems = (clone $stockQuery)
            ->join('medicines as m', 'm.id', '=', 'pm.medicine_id')
            ->whereIn('pm.availability_status', ['low', 'out_of_stock'])
            ->select('m.name', 'p.name as pharmacy', 'pm.stock_quantity', 'pm.availability_status')
            ->orderBy('pm.stock_quantity')
            ->limit(3)
            ->get();

        return response()->json([
            'pharmacies_count' => $pharmaciesQuery->count(),
            'inquiries_today' => (clone $inquiriesQuery)->whereDate('created_at', today())->count(),
            'pending_inquiries' => (clone $inquiriesQuery)->where('status', 'pending')->count(),
            'medicines_tracked' => (clone $stockQuery)->distinct()->count('pm.medicine_id'),
            'low_stock_count' => (clone $stockQuery)->whereIn('pm.availability_status', ['low', 'out_of_stock'])->count(),
            'sales_today' => (float) (clone $salesQuery)->sum('total_amount'),
            'geofences' => [
                'active' => Geofence::count(),
                'assigned_pharmacies' => DB::table('geofence_pharmacy')->distinct('pharmacy_id')->count('pharmacy_id'),
            ],
            'users' => [
                'registered' => User::where('role', 'customer')->count(),
            ],
            'recent_activity' => $recentInquiries,
            'low_stock_alerts' => $lowStockItems,
        ]);
    }
}
