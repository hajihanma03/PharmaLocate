<?php

namespace App\Http\Controllers;

use App\Models\Geofence;
use App\Models\Inquiry;
use App\Models\Pharmacy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PageController extends Controller
{
    // Default map center — Tarlac Provincial Hospital vicinity.
    private const HOSPITAL_LAT = 15.47474;

    private const HOSPITAL_LNG = 120.58669;

    public function home(): View
    {
        // A few live availability items for the landing page.
        $featured = $this->availabilityItems()->take(3);

        return view('pages.home', ['featured' => $featured]);
    }

    public function pharmacies(): View
    {
        $pharmacies = Pharmacy::where('is_active', true)->get()
            ->map(function (Pharmacy $p) {
                $p->distance_km = $this->haversineKm(
                    self::HOSPITAL_LAT,
                    self::HOSPITAL_LNG,
                    (float) $p->latitude,
                    (float) $p->longitude
                );

                return $p;
            })
            ->sortBy('distance_km')
            ->values();

        $geofences = Geofence::where('is_active', true)->get();

        return view('pages.pharmacies', [
            'pharmacies' => $pharmacies,
            'geofences' => $geofences,
            'center' => ['lat' => self::HOSPITAL_LAT, 'lng' => self::HOSPITAL_LNG],
        ]);
    }

    public function medicines(Request $request): View
    {
        $search = $request->query('search');

        $items = $this->availabilityItems($search);
        $pharmacyNames = Pharmacy::orderBy('name')->pluck('name');

        return view('pages.medicines', [
            'items' => $items,
            'pharmacyNames' => $pharmacyNames,
            'search' => $search,
        ]);
    }

    public function inquiries(): View
    {
        $inquiries = Inquiry::with(['pharmacy:id,name', 'medicine:id,name'])
            ->where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->get();

        return view('pages.inquiries', [
            'inquiries' => $inquiries,
            'pharmacies' => Pharmacy::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function storeInquiry(Request $request)
    {
        $data = $request->validate([
            'pharmacy_id' => ['nullable', 'exists:pharmacies,id'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $user = auth()->user();

        Inquiry::create([
            'user_id' => $user->id,
            'pharmacy_id' => $data['pharmacy_id'] ?? null,
            'medicine_id' => null,
            'message' => $data['message'],
            'status' => 'pending',
        ]);

        return redirect()->route('inquiries')->with('status', 'Your inquiry has been submitted. Pharmacy staff will respond soon.');
    }

    /**
     * Flatten the inventory pivot into per-(medicine, pharmacy) availability
     * rows the medicine cards render. Optional name/brand search.
     */
    private function availabilityItems(?string $search = null)
    {
        $query = DB::table('pharmacy_medicine as pm')
            ->join('medicines as m', 'm.id', '=', 'pm.medicine_id')
            ->join('pharmacies as p', 'p.id', '=', 'pm.pharmacy_id')
            ->select(
                'm.name',
                'm.brand',
                'm.description',
                'p.name as pharmacy_name',
                'pm.stock_quantity as qty',
                'pm.price',
                'pm.availability_status as status'
            )
            ->orderBy('m.name');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('m.name', 'like', "%{$search}%")
                    ->orWhere('m.brand', 'like', "%{$search}%");
            });
        }

        return $query->get();
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return round($r * 2 * asin(min(1, sqrt($a))), 2);
    }
}
