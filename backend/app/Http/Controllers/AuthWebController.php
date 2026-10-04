<?php

namespace App\Http\Controllers;

use App\Services\SignupVerification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Session-based (cookie) authentication for the server-rendered pages.
 * The token-based AuthController is kept for API/AJAX use.
 */
class AuthWebController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            // Accepts either an email address or a username.
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $field = filter_var($credentials['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if (! Auth::attempt([$field => $credentials['login'], 'password' => $credentials['password']], $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'login' => 'Those credentials do not match our records.',
            ]);
        }

        if (! $request->user()?->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'login' => 'This account has been deactivated. Contact an administrator.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(Request $request, SignupVerification $signup): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255', 'unique:users,username'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'accepted_terms' => ['accepted'],
        ]);

        $signup->begin($data);

        return back()->with('signup_email', strtolower($data['email']));
    }

    public function confirmRegister(Request $request, SignupVerification $signup): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'code' => ['required', 'digits:6'],
        ]);

        $user = $signup->confirm($data['email'], $data['code']);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('home')->with('status', 'Welcome to PharmaLocate, '.$user->name.'!');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
