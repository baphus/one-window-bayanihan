<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): ?User
    {
        $this->ensureIsNotRateLimited();

        $user = User::where('email', $this->string('email')->toString())->first();
        if (! $user || ! Hash::check($this->string('password')->toString(), $user->password)) {
            RateLimiter::hit($this->throttleKey());
            event(new Failed('web', $user, $this->only('email')));

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        if (Hash::needsRehash($user->password)) {
            $user->forceFill(['password' => $this->string('password')->toString()])->save();
            $user->refresh();
        }

        RateLimiter::clear($this->throttleKey());

        // After successful credential verification, check the account is still
        // active — prevents recently deactivated/soft-deleted users from logging in
        // without revealing *why* the account was rejected (same generic message).
        if (! $user->is_active || $user->is_deleted) {
            event(new Failed('web', $user, $this->only('email')));

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        if (config('mfa.login_challenge_enabled') && $user->mfa_enabled_at !== null) {
            return $user;
        }

        Auth::guard('web')->login($user, $this->boolean('remember'));

        return null;
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
