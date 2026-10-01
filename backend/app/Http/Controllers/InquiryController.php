<?php

namespace App\Http\Controllers;

use App\Models\Inquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InquiryController extends Controller
{
    /**
     * List inquiries. Customers see their own; staff/admin see all.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Inquiry::with(['user:id,name', 'pharmacy:id,name', 'medicine:id,name']);

        if ($user->role === 'customer') {
            $query->where('user_id', $user->id);
        } elseif ($user->role === 'staff') {
            $query->where('pharmacy_id', $user->pharmacy_id);
        }

        $inquiries = $query
            ->orderByDesc('created_at')
            ->get();

        return response()->json($inquiries);
    }

    /**
     * Submit a new inquiry about medicine availability (FR3).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pharmacy_id' => ['nullable', 'exists:pharmacies,id'],
            'medicine_id' => ['nullable', 'exists:medicines,id'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $user = $request->user();

        $inquiry = Inquiry::create([
            'user_id' => $user->id,
            'pharmacy_id' => $data['pharmacy_id'] ?? null,
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

        if (! in_array($user->role, ['staff', 'admin'], true)) {
            return response()->json(['message' => 'Only pharmacy staff can respond to inquiries.'], 403);
        }

        if ($user->role === 'staff' && $inquiry->pharmacy_id !== $user->pharmacy_id) {
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
}
