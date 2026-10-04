<?php

namespace App\Services;

use App\Mail\SignupCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class SignupVerification
{
    public function begin(array $data): void
    {
        $email = strtolower(trim($data['email']));
        $data['email'] = $email;

        if (! $this->domainAcceptsMail($email)) {
            throw ValidationException::withMessages([
                'email' => 'This email address cannot receive mail. No account was created. Use an inbox you can open so a forgotten password can be recovered.',
            ]);
        }

        if (! $this->canDeliverMail()) {
            throw ValidationException::withMessages([
                'email' => 'No account was created. PharmaLocate must email a confirmation code to prove this inbox exists, and this server is not sending email yet.',
            ]);
        }

        $code = (string) random_int(100000, 999999);
        Cache::put($this->key($email), [
            'name' => $data['name'],
            'username' => $data['username'] ?? null,
            'email' => $email,
            'password' => Crypt::encryptString($data['password']),
            'code' => Hash::make($code),
            'attempts' => 0,
        ], now()->addMinutes(15));

        try {
            Mail::to($email)->send(new SignupCodeMail($code));
        } catch (\Throwable $e) {
            Cache::forget($this->key($email));
            throw ValidationException::withMessages([
                'email' => 'We could not deliver a confirmation message to that address, so no account was created.',
            ]);
        }
    }

    public function confirm(string $email, string $code): User
    {
        $email = strtolower(trim($email));
        $pending = Cache::get($this->key($email));

        if (! is_array($pending)) {
            throw ValidationException::withMessages([
                'code' => 'That confirmation code has expired. Start signup again. No account was created.',
            ]);
        }

        if (! Hash::check($code, $pending['code'])) {
            $pending['attempts'] = (int) ($pending['attempts'] ?? 0) + 1;
            if ($pending['attempts'] >= 5) {
                Cache::forget($this->key($email));
                throw ValidationException::withMessages([
                    'code' => 'That code was incorrect too many times. Start signup again. No account was created.',
                ]);
            }
            Cache::put($this->key($email), $pending, now()->addMinutes(15));
            throw ValidationException::withMessages([
                'code' => 'That confirmation code is incorrect. No account was created.',
            ]);
        }

        if (User::where('email', $email)->exists()) {
            Cache::forget($this->key($email));
            throw ValidationException::withMessages([
                'email' => 'An account with this email already exists.',
            ]);
        }

        if (! empty($pending['username']) && User::where('username', $pending['username'])->exists()) {
            Cache::forget($this->key($email));
            throw ValidationException::withMessages([
                'username' => 'That username is already taken.',
            ]);
        }

        $user = User::create([
            'name' => $pending['name'],
            'username' => $pending['username'],
            'email' => $email,
            'password' => Crypt::decryptString($pending['password']),
            'role' => 'customer',
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();
        Cache::forget($this->key($email));

        return $user;
    }

    private function key(string $email): string
    {
        return 'signup-verify:'.hash('sha256', strtolower($email));
    }

    private function canDeliverMail(): bool
    {
        if (app()->environment('testing')) {
            return true;
        }

        return ! in_array(config('mail.default'), ['log', 'array'], true);
    }

    private function domainAcceptsMail(string $email): bool
    {
        $domain = strtolower(substr(strrchr($email, '@') ?: '', 1));
        if ($domain === '' || ! str_contains($domain, '.')) {
            return false;
        }

        if (in_array($domain, ['example.com', 'example.org', 'example.net', 'localhost', 'invalid'], true)
            || str_ends_with($domain, '.invalid')
            || str_ends_with($domain, '.test')
            || str_ends_with($domain, '.localhost')) {
            return false;
        }

        if (app()->environment('testing')) {
            return true;
        }

        return checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A');
    }
}
