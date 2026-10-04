<?php

namespace App\Http\Controllers;

use App\Models\Pharmacy;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminUserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::with('pharmacy:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => $this->present($user));

        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(['staff', 'customer', 'sparx_owner', 'magic8_owner'])],
            'pharmacy_id' => ['nullable', 'integer', 'exists:pharmacies,id'],
        ]);

        [$role, $pharmacyId] = $this->resolveAssignment($data);

        $user = User::create([
            'name' => trim($data['name']),
            'username' => trim($data['username']),
            'email' => strtolower(trim($data['email'])),
            'phone' => $this->blankToNull($data['phone'] ?? null),
            'password' => $data['password'],
            'role' => $role,
            'pharmacy_id' => $pharmacyId,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->load('pharmacy:id,name');

        AuditLogger::log(
            $request->user(),
            'user_created',
            'user',
            $user->id,
            'Role: '.$user->role,
        );

        return response()->json($this->present($user), 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        if ($this->isProtectedAdmin($user)) {
            return response()->json([
                'message' => 'The administrator account cannot be changed. Only customer, staff, and pharmacy owner roles can be assigned.',
            ], 422);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', Rule::in(['staff', 'customer', 'sparx_owner', 'magic8_owner'])],
            'pharmacy_id' => ['nullable', 'integer', 'exists:pharmacies,id'],
        ]);

        [$role, $pharmacyId] = $this->resolveAssignment($data);

        $user->name = trim($data['name']);
        $user->username = trim($data['username']);
        $user->email = strtolower(trim($data['email']));
        $user->phone = $this->blankToNull($data['phone'] ?? null);
        $user->role = $role;
        $user->pharmacy_id = $pharmacyId;
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        $user->save();
        $user->load('pharmacy:id,name');

        AuditLogger::log(
            $request->user(),
            'user_updated',
            'user',
            $user->id,
            'Role: '.$user->role,
        );

        return response()->json($this->present($user));
    }

    public function setActive(Request $request, User $user): JsonResponse
    {
        if ($this->isProtectedAdmin($user)) {
            return response()->json(['message' => 'The administrator account cannot be deactivated.'], 422);
        }

        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $active = (bool) $data['is_active'];
        $user->is_active = $active;
        $user->save();

        if (! $active) {
            $user->tokens()->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        $user->load('pharmacy:id,name');

        AuditLogger::log(
            $request->user(),
            $active ? 'user_activated' : 'user_deactivated',
            'user',
            $user->id,
            $user->name,
        );

        return response()->json($this->present($user));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: string, 1: ?int}
     */
    private function resolveAssignment(array $data): array
    {
        $assignedRole = $data['role'];
        $pharmacyId = null;

        if ($assignedRole === 'staff') {
            if (empty($data['pharmacy_id'])) {
                throw ValidationException::withMessages([
                    'pharmacy_id' => 'Staff users must be assigned to a pharmacy.',
                ]);
            }
            $pharmacyId = (int) $data['pharmacy_id'];
        } elseif (in_array($assignedRole, ['sparx_owner', 'magic8_owner'], true)) {
            $pharmacyName = $assignedRole === 'sparx_owner' ? 'SpaRx Pharmacy' : 'Magic 8 Pharmacy';
            $pharmacyId = Pharmacy::query()->where('name', $pharmacyName)->value('id');
            if (! $pharmacyId) {
                throw ValidationException::withMessages([
                    'role' => $pharmacyName.' is not available for an owner account.',
                ]);
            }
            $assignedRole = 'owner';
        }

        return [$assignedRole, $pharmacyId];
    }

    private function isProtectedAdmin(User $user): bool
    {
        return $user->role === 'admin' || strcasecmp((string) $user->email, 'admin@pharmalocate.test') === 0;
    }

    private function blankToNull(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function present(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
            'pharmacy_id' => $user->pharmacy_id,
            'pharmacy' => $user->pharmacy?->name,
            'phone' => $user->phone,
            'is_active' => (bool) $user->is_active,
            'created_at' => $user->created_at,
        ];
    }
}
