<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SignupVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new customer account. Staff/admin accounts are created by
     * an admin, not through public registration.
     */
    public function register(Request $request, SignupVerification $signup): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255', 'unique:users,username'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'accepted_terms' => ['accepted'],
        ]);

        $signup->begin($data);

        return response()->json([
            'verification_required' => true,
            'email' => strtolower($data['email']),
            'message' => 'Enter the 6-digit code sent to this email. The account is created only after that code is confirmed.',
        ], 202);
    }

    public function confirmRegister(Request $request, SignupVerification $signup): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'code' => ['required', 'digits:6'],
        ]);

        $user = $signup->confirm($data['email'], $data['code']);
        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    /**
     * Log in with username/email + password and receive an API token (Sanctum).
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login' => ['required_without:email', 'string'],
            'email' => ['required_without:login', 'email'],
            'password' => ['required', 'string'],
        ]);

        $login = $data['login'] ?? $data['email'];
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $user = User::where($field, $login)->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'login' => ['This account has been deactivated. Contact an administrator.'],
            ]);
        }

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user' => $user->load('pharmacy:id,name'),
            'token' => $token,
        ]);
    }

    /**
     * Return the currently authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user()->load('pharmacy'));
    }

    /**
     * Revoke the current access token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function completeTour(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->tour_completed_at) {
            $user->tour_completed_at = now();
            $user->save();
        }

        return response()->json([
            'tour_completed_at' => $user->tour_completed_at,
        ]);
    }
}
