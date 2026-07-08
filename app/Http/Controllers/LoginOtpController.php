<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResendOtpRequest;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use PragmaRX\Google2FA\Google2FA;

class LoginOtpController extends Controller
{
    public function __construct(
        private readonly OtpService $otpService,
    ) {}

    public function init(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $request->input('email'))->first();

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'The provided credentials do not match our records.',
            ]);
        }

        if (! $user->is_active || $user->is_deleted) {
            throw ValidationException::withMessages([
                'email' => 'The provided credentials do not match our records.',
            ]);
        }

        $request->session()->put('login_step', 1);

        $otp = $this->otpService->generate($user->email, 'login', $request->session()->getId());

        $emailParts = explode('@', $user->email);
        $hint = strlen($emailParts[0]) > 2
            ? substr($emailParts[0], 0, 2).str_repeat('*', strlen($emailParts[0]) - 2).'@'.$emailParts[1]
            : $user->email;

        return Inertia::render('Auth/Login', [
            'step' => 'otp',
            'email' => $user->email,
            'hint' => $hint,
            'debug_otp' => (SystemSetting::getValue('debug_otp_enabled', false) && app()->environment('local', 'testing')) ? $otp : null,
        ]);
    }

    public function resendOtp(ResendOtpRequest $request)
    {
        if ($request->session()->get('login_step') !== 1) {
            abort(403, 'Login session not initiated.');
        }

        if (! Auth::validate(['email' => $request->email, 'password' => $request->password])) {
            throw ValidationException::withMessages([
                'password' => __('auth.failed'),
            ]);
        }

        $user = User::where('email', $request->email)->first();

        if (! $user || ! $user->is_active || $user->is_deleted) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $otp = $this->otpService->generate($user->email, 'login', $request->session()->getId());

        Log::info('otp_resend_successful', ['email' => substr($user->email, 0, 2).'***@'.substr(strstr($user->email, '@'), 1)]);

        $emailParts = explode('@', $user->email);
        $hint = strlen($emailParts[0]) > 2
            ? substr($emailParts[0], 0, 2).str_repeat('*', strlen($emailParts[0]) - 2).'@'.$emailParts[1]
            : $user->email;

        return Inertia::render('Auth/Login', [
            'step' => 'otp',
            'email' => $user->email,
            'hint' => $hint,
            'debug_otp' => (SystemSetting::getValue('debug_otp_enabled', false) && app()->environment('local', 'testing')) ? $otp : null,
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $verified = $this->otpService->verify(
            $request->input('email'),
            'login',
            $request->input('otp'),
            $request->session()->getId(),
        );

        if (! $verified) {
            throw ValidationException::withMessages([
                'otp' => 'Invalid or expired OTP.',
            ]);
        }

        $user = User::where('email', $request->input('email'))->first();

        if (! $user || ! $user->is_active || $user->is_deleted) {
            throw ValidationException::withMessages([
                'email' => 'User not found.',
            ]);
        }

        if ($user->mfa_enabled_at !== null) {
            $request->session()->put('pending_mfa_user_id', $user->id);

            $emailParts = explode('@', $user->email);
            $hint = strlen($emailParts[0]) > 2
                ? substr($emailParts[0], 0, 2).str_repeat('*', strlen($emailParts[0]) - 2).'@'.$emailParts[1]
                : $user->email;

            return Inertia::render('Auth/Login', [
                'step' => 'mfa-challenge',
                'email' => $user->email,
                'hint' => $hint,
            ]);
        }

        Auth::login($user, true);

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function verifyTotp(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $pendingId = $request->session()->get('pending_mfa_user_id');

        if (! $pendingId) {
            throw ValidationException::withMessages([
                'email' => 'Session expired. Please log in again.',
            ]);
        }

        $user = User::where('email', $request->input('email'))
            ->where('id', $pendingId)
            ->first();

        if (! $user || ! $user->is_active || $user->is_deleted || $user->mfa_enabled_at === null) {
            throw ValidationException::withMessages([
                'email' => 'Unable to verify. Please log in again.',
            ]);
        }

        /** @var Google2FA $google2fa */
        $google2fa = app('pragmarx.google2fa');

        $valid = $google2fa->verifyKey($user->mfa_secret, $request->input('otp'));

        if (! $valid) {
            throw ValidationException::withMessages([
                'otp' => 'Invalid authentication code.',
            ]);
        }

        $request->session()->forget('pending_mfa_user_id');
        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function verifyRecoveryCode(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'recovery_code' => ['required', 'string'],
        ]);

        $pendingId = $request->session()->get('pending_mfa_user_id');

        if (! $pendingId) {
            throw ValidationException::withMessages([
                'email' => 'Session expired. Please log in again.',
            ]);
        }

        $user = User::where('email', $request->input('email'))
            ->where('id', $pendingId)
            ->first();

        if (! $user || ! $user->is_active || $user->is_deleted || $user->mfa_enabled_at === null) {
            throw ValidationException::withMessages([
                'email' => 'Unable to verify. Please log in again.',
            ]);
        }

        $recoveryCodes = $user->mfa_recovery_codes ?? [];

        if (! in_array($request->input('recovery_code'), $recoveryCodes)) {
            throw ValidationException::withMessages([
                'recovery_code' => 'Invalid recovery code.',
            ]);
        }

        $remainingCodes = array_values(array_filter(
            $recoveryCodes,
            fn ($code) => $code !== $request->input('recovery_code'),
        ));

        $user->mfa_recovery_codes = $remainingCodes;
        $user->save();

        $request->session()->forget('pending_mfa_user_id');
        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
