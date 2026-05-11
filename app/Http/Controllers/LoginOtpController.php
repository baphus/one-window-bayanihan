<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

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

        if (!$user || !Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'The provided credentials do not match our records.',
            ]);
        }

        $otp = $this->otpService->generate($user->email, 'login');

        logger("OTP for {$user->email} (login): {$otp}");

        $emailParts = explode('@', $user->email);
        $hint = strlen($emailParts[0]) > 2
            ? substr($emailParts[0], 0, 2) . str_repeat('*', strlen($emailParts[0]) - 2) . '@' . $emailParts[1]
            : $user->email;

        return \Inertia\Inertia::render('Auth/Login', [
            'step' => 'otp',
            'email' => $user->email,
            'hint' => $hint,
            'debug_otp' => SystemSetting::getValue('debug_otp_enabled', false) ? $otp : null,
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
        );

        if (!$verified) {
            throw ValidationException::withMessages([
                'otp' => 'Invalid or expired OTP.',
            ]);
        }

        $user = User::where('email', $request->input('email'))->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => 'User not found.',
            ]);
        }

        Auth::login($user, true);

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
