<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AuditModule;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Throwable;

class OfwRegistrationController extends Controller
{
    public function __construct(private readonly OtpService $otpService) {}

    public function create(Request $request)
    {
        $credentials = $request->session()->get('pending_registration.credentials', []);

        return Inertia::render('Auth/Register', [
            'initial' => ['credentials' => [
                'email' => $credentials['email'] ?? '',
                'has_password' => ! empty($credentials['password']),
            ]],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|string|lowercase|email|max:255',
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ] + $this->removedProfileRules());

        $request->session()->put('pending_registration', [
            'credentials' => [
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ],
        ]);
        $request->session()->regenerate();

        return redirect()->route('register.review');
    }

    public function showReview(Request $request)
    {
        return Inertia::render('Auth/RegisterReview', [
            'pending' => ['email' => $request->session()->get('pending_registration.credentials.email')],
        ]);
    }

    public function sendOtp(Request $request)
    {
        $email = $request->session()->get('pending_registration.credentials.email');
        if (! is_string($email) || $email === '') {
            return redirect()->route('register');
        }

        $otpKey = 'otp:register:'.$email.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($otpKey, 5)) {
            return back()->withErrors(['otp' => 'Too many attempts. Try again in a few minutes.']);
        }
        RateLimiter::hit($otpKey, 300);
        $this->otpService->generate($email, 'register');

        return back()->with('success', 'A verification code has been sent to '.$email.'.');
    }

    public function storeReview(Request $request)
    {
        $pending = $request->session()->get('pending_registration');
        $credentials = is_array($pending) ? ($pending['credentials'] ?? []) : [];
        if (empty($credentials['email']) || empty($credentials['password'])) {
            return redirect()->route('register');
        }

        $validated = $request->validate(['otp' => 'required|string|size:6'] + $this->removedProfileRules());
        if (array_diff(array_keys($pending), ['credentials']) || array_diff(array_keys($credentials), ['email', 'password'])) {
            $request->session()->forget('pending_registration');

            return redirect()->route('register');
        }
        Validator::make($credentials, [
            'email' => 'required|string|lowercase|email|max:255|unique:users,email',
            'password' => 'required|string',
        ])->validate();
        $email = $credentials['email'];
        if (! $this->otpService->verify($email, 'register', $validated['otp'])) {
            return back()->withErrors(['otp' => 'Invalid or expired code.']);
        }

        try {
            $user = DB::transaction(function () use ($credentials, $email) {
                $user = User::create([
                    'name' => UserRole::OFW->value,
                    'email' => $email,
                    'password' => $credentials['password'],
                    'role' => UserRole::OFW->value,
                    'email_verified_at' => now(),
                    'is_active' => true,
                ]);

                AuditLogger::register()
                    ->by($user)
                    ->forEntity($user, $user->email)
                    ->module(AuditModule::AUTH)
                    ->context(null, ['email' => $email])
                    ->write();

                return $user;
            });
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(
                User::withTrashed()->where('email', $email)->exists()
                    ? ['email' => 'That email address is already registered. Please start a new registration.']
                    : ['registration' => 'We could not create your account. Please request a new verification code and try again.']
            );
        }

        $request->session()->forget('pending_registration');
        $request->session()->regenerate();
        Auth::login($user);

        return redirect()->route('ofw.intake');
    }

    private function removedProfileRules(): array
    {
        return array_fill_keys([
            'type', 'client_type', 'client_id', 'ofw_client_id', 'first_name', 'middle_name', 'last_name',
            'suffix', 'date_of_birth', 'sex', 'contact_number', 'address', 'employment', 'next_of_kin',
            'nok_name', 'nok_relationship', 'nok_contact_number', 'nok_email', 'profile', 'profile_picture',
        ], 'prohibited');
    }
}
