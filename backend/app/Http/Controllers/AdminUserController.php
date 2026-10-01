<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::with('pharmacy:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'pharmacy_id' => $user->pharmacy_id,
                'pharmacy' => $user->pharmacy?->name,
                'phone' => $user->phone,
                'created_at' => $user->created_at,
            ]);

        return response()->json($users);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(['admin', 'staff', 'customer'])],
            'pharmacy_id' => ['nullable', 'integer', 'exists:pharmacies,id'],
        ]);

        if ($data['role'] === 'staff' && empty($data['pharmacy_id'])) {
            return response()->json(['message' => 'Staff users must be assigned to a pharmacy.'], 422);
        }

        if ($user->id === $request->user()->id && $data['role'] !== 'admin') {
            return response()->json(['message' => 'You cannot change your own administrator role.'], 422);
        }

        if ($user->role === 'admin' && $data['role'] !== 'admin') {
            $adminCount = User::where('role', 'admin')->count();
            if ($adminCount <= 1) {
                return response()->json(['message' => 'Cannot demote the only administrator account.'], 422);
            }
        }

        $user->role = $data['role'];
        $user->pharmacy_id = $data['role'] === 'staff' ? (int) $data['pharmacy_id'] : null;
        $user->save();
        $user->load('pharmacy:id,name');

        AuditLogger::log(
            $request->user(),
            'user_updated',
            'user',
            $user->id,
            'Role: '.$user->role,
        );

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
            'pharmacy_id' => $user->pharmacy_id,
            'pharmacy' => $user->pharmacy?->name,
        ]);
    }
}
