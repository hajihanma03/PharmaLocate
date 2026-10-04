<?php

namespace App\Http\Controllers;

use App\Models\Inquiry;
use App\Models\Pharmacy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InquiryController extends Controller
{
    /**
     * Guest: no access. Customer: own inquiries only.
     * Staff: inquiries sent to their pharmacy. Admin: every inquiry.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Inquiry::with(['user:id,name', 'pharmacy:id,name', 'medicine:id,name']);

        if ($user->role === 'customer') {
            $query->where('user_id', $user->id);
        } elseif ($user->isPharmacyScoped()) {
            if (! $user->pharmacy_id) {
                return response()->json([]);
            }
            $query->where('pharmacy_id', $user->pharmacy_id);
        } elseif ($user->role !== 'admin') {
            return response()->json(['message' => 'You cannot view inquiries.'], 403);
        }

        return response()->json($query->orderByDesc('created_at')->get());
    }

    /**
     * Submit a new inquiry about medicine availability (FR3).
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'customer') {
            return response()->json(['message' => 'Only customer accounts can send inquiries.'], 403);
        }

        $data = $request->validate([
            'pharmacy_id' => ['required', 'integer', 'exists:pharmacies,id'],
            'medicine_id' => ['nullable', 'integer', 'exists:medicines,id'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $pharmacy = Pharmacy::query()
            ->where('id', $data['pharmacy_id'])
            ->where('is_active', true)
            ->first();

        if (! $pharmacy) {
            return response()->json(['message' => 'Choose an active pharmacy.'], 422);
        }

        if (! empty($data['medicine_id'])) {
            $stocked = DB::table('pharmacy_medicine')
                ->where('pharmacy_id', $pharmacy->id)
                ->where('medicine_id', $data['medicine_id'])
                ->exists();

            if (! $stocked) {
                return response()->json(['message' => 'That medicine is not listed at the pharmacy you selected.'], 422);
            }
        }

        $inquiry = Inquiry::create([
            'user_id' => $user->id,
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => $data['medicine_id'] ?? null,
            'message' => $data['message'],
            'status' => 'pending',
        ]);

        return response()->json($inquiry->load(['pharmacy:id,name', 'medicine:id,name']), 201);
    }

    /**
     * Staff/admin respond to an inquiry and update its status (FR4).
     */
    public function respond(Request $request, Inquiry $inquiry): JsonResponse
    {
        $user = $request->user();

        if (! in_array($user->role, ['staff', 'owner', 'admin'], true)) {
            return response()->json(['message' => 'Only pharmacy staff can respond to inquiries.'], 403);
        }

        if ($user->isPharmacyScoped() && (int) $inquiry->pharmacy_id !== (int) $user->pharmacy_id) {
            return response()->json(['message' => 'You can only respond to inquiries for your pharmacy.'], 403);
        }

        $data = $request->validate([
            'response' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['pending', 'in_progress', 'resolved'])],
        ]);

        $inquiry->update([
            'response' => $data['response'] ?? $inquiry->response,
            'status' => $data['status'],
        ]);

        return response()->json($inquiry->fresh(['user:id,name', 'pharmacy:id,name', 'medicine:id,name']));
    }

    /**
     * Staff or admin may remove an inquiry only after it has been replied to.
     * Staff can delete inquiries for their own pharmacy.
     */
    public function destroy(Request $request, Inquiry $inquiry): JsonResponse
    {
        $user = $request->user();

        if (! in_array($user->role, ['staff', 'owner', 'admin'], true)) {
            return response()->json(['message' => 'Only pharmacy staff can delete inquiries.'], 403);
        }

        if ($user->isPharmacyScoped() && (int) $inquiry->pharmacy_id !== (int) $user->pharmacy_id) {
            return response()->json(['message' => 'You can only delete inquiries for your pharmacy.'], 403);
        }

        if ($inquiry->status !== 'resolved' || blank($inquiry->response)) {
            return response()->json(['message' => 'Only replied inquiries can be deleted.'], 422);
        }

        $inquiry->delete();

        return response()->json(['message' => 'Inquiry deleted.']);
    }
}
