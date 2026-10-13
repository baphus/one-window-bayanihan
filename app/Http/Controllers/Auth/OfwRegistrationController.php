<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class OfwRegistrationController extends Controller
{
    public function __construct(
        private readonly OtpService $otpService,
    ) {}

    public function create()
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Step 1: credentials only. No User row yet — validated credentials plus
     * an OTP challenge live in the session until the profile step completes.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);

        $email = strtolower(trim($validated['email']));

        // OTP sends are the abuse vector here (Phase 2 routes stay untouched);
        // reuse the named otp limiter inline instead of adding route middleware.
        $otpKey = $this->otpThrottleKey($request, $email);
        if (RateLimiter::tooManyAttempts($otpKey, 5)) {
            return back()->withErrors(['email' => 'Too many attempts. Try again in a few minutes.']);
        }
        RateLimiter::hit($otpKey, 300);

        $this->otpService->generate($email, 'register');

        $request->session()->put('pending_registration', [
            'credentials' => [
                'name' => $validated['name'],
                'email' => $email,
                'password' => Hash::make($validated['password']),
            ],
            'register_otp_verified' => false,
        ]);
        $request->session()->regenerate();

        return redirect()->route('register.profile');
    }

    public function showProfile()
    {
        return Inertia::render('Auth/RegisterProfile');
    }

    /**
     * Step 2: verify OTP, then create User + Client in one transaction.
     * users.role stays OFW always; the asked role lands on clients.type.
     */
    public function storeProfile(Request $request)
    {
        $pending = $request->session()->get('pending_registration');

        if (! is_array($pending) || empty($pending['credentials'])) {
            return redirect()->route('register');
        }

        $validated = $request->validate([
            'otp' => 'required|string|size:6',
            'type' => 'required|string|in:OFW,NEXT_OF_KIN',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'suffix' => 'nullable|string|max:50',
            'date_of_birth' => 'nullable|date',
            'sex' => 'nullable|string|max:50',
            'contact_number' => 'nullable|string|max:50',
        ]);

        $email = $pending['credentials']['email'];

        if (! $this->otpService->verify($email, 'register', $validated['otp'])) {
            return back()->withErrors(['otp' => 'Invalid or expired code.']);
        }

        $user = DB::transaction(function () use ($pending, $validated, $email) {
            $user = User::create([
                'name' => $pending['credentials']['name'],
                'email' => $email,
                'password' => $pending['credentials']['password'],
                'role' => UserRole::OFW->value,
                'email_verified_at' => now(),
                'is_active' => true,
                'profile_completed_at' => now(),
            ]);

            $client = Client::create([
                'type' => $validated['type'],
                'user_id' => $user->id,
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'suffix' => $validated['suffix'] ?? null,
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'sex' => ! empty($validated['sex']) ? strtoupper($validated['sex']) : null,
                'email' => $email,
                'contact_number' => $validated['contact_number'] ?? null,
            ]);

            $user->client_id = $client->id;
            $user->save();

            return $user;
        });

        $request->session()->forget('pending_registration');
        $request->session()->regenerate();

        Auth::login($user);

        return redirect()->route('ofw.dashboard');
    }

    private function otpThrottleKey(Request $request, string $email): string
    {
        return 'otp:register:'.$email.'|'.$request->ip();
    }
}
