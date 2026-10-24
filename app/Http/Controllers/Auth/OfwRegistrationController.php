<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use App\Rules\EmploymentStartAtWorkingAge;
use App\Rules\ServedRegion;
use App\Services\NotificationService;
use App\Services\OfwProfileService;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Throwable;

class OfwRegistrationController extends Controller
{
    public function __construct(
        private readonly OtpService $otpService,
        private readonly NotificationService $notificationService,
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
            'suffix' => 'nullable|string|max:50',
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
                'suffix' => $validated['suffix'] ?? null,
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

    public function resendOtp(Request $request)
    {
        $pending = $request->session()->get('pending_registration');

        if (! is_array($pending) || empty($pending['credentials']['email'])) {
            return redirect()->route('register');
        }

        $email = $pending['credentials']['email'];
        $otpKey = $this->otpThrottleKey($request, $email);
        if (RateLimiter::tooManyAttempts($otpKey, 5)) {
            return back()->withErrors(['otp' => 'Too many attempts. Try again in a few minutes.']);
        }
        RateLimiter::hit($otpKey, 300);

        $this->otpService->generate($email, 'register');

        return back()->with('success', 'A new verification code has been sent.');
    }

    /**
     * Step 2: verify OTP, then create User + Client in one transaction.
     * users.role stays OFW always; the asked role lands on clients.type.
     *
     * The code is consumed by verify() *before* the transaction opens, so two
     * concurrent submissions can never both pass the check. The cost of that
     * ordering is that a rolled-back transaction would strand
     * `pending_registration` next to an already-dead code; the catch block
     * below re-arms it, which keeps the single-use guarantee for anything
     * that actually committed.
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
            'date_of_birth' => ['required', 'date', 'before_or_equal:'.now()->subYears(15)->toDateString(), 'after_or_equal:'.now()->subYears(100)->toDateString()],
            'sex' => 'nullable|string|max:50',
            'contact_number' => 'nullable|string|max:50',
            'address' => ['required', 'array'],
            'address.region' => ['required', 'string', new ServedRegion],
            'address.province' => ['nullable', 'string', 'max:255'],
            'address.city_municipality' => ['required', 'string', 'max:255'],
            'address.barangay' => ['required', 'string', 'max:255'],
            'address.street' => ['nullable', 'string'],
            'employment' => ['required', 'array'],
            'employment.employer_name' => ['required', 'string', 'max:255'],
            'employment.position' => ['nullable', 'string', 'max:255'],
            'employment.country' => ['nullable', 'string', 'max:255'],
            'employment.start_date' => ['required', 'date', 'after_or_equal:client.date_of_birth', 'before_or_equal:today', new EmploymentStartAtWorkingAge($request->input('date_of_birth'))],
            'employment.end_date' => ['required_unless:employment.is_present,true', 'nullable', 'date', 'after_or_equal:employment.start_date', 'before_or_equal:today'],
            'employment.is_present' => ['nullable', 'boolean'],
            'employment.last_country' => ['required', 'string', 'max:255'],
            'employment.last_position' => ['required', 'string', 'max:255'],
            'employment.date_of_arrival' => ['required', 'date', 'after_or_equal:client.date_of_birth'],
        ]);

        $email = $pending['credentials']['email'];

        if (! $this->otpService->verify($email, 'register', $validated['otp'])) {
            return back()->withErrors(['otp' => 'Invalid or expired code.']);
        }

        try {
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

                // Persist address to ClientAddress
                $service = new OfwProfileService($this->notificationService);
                $changes = [];
                $service->syncAddress($client, $validated['address'] ?? [], $changes);
                $service->syncEmployment($client, $validated['employment'] ?? [], $changes);

                return $user;
            });
        } catch (Throwable $e) {
            $this->rearmRegisterOtp($email, $validated['otp']);

            report($e);

            // The address is already taken in the overwhelmingly common case —
            // the step-1 `unique:users,email` check passed and someone else
            // registered in between. Otherwise it is an unexpected write
            // failure worth retrying, and the code is still live.
            return back()->withErrors(
                User::withTrashed()->where('email', $email)->exists()
                    ? ['email' => 'That email address is already registered. Please start a new registration.']
                    : ['registration' => 'We could not save your profile. Your code is still valid, so please try again.']
            );
        }

        $request->session()->forget('pending_registration');
        $request->session()->regenerate();

        Auth::login($user);

        return redirect()->route('ofw.intake');
    }

    /**
     * Put a consumed registration code back so a failed transaction leaves the
     * pending registration usable.
     *
     * Cache::add only writes when the key is absent, so a re-arm never clobbers
     * a newer code that was issued for the same address after this request
     * started. The restored entry gets one full OTP lifetime (the remaining
     * TTL of the original is not portable across cache stores) and, like
     * OtpService::generate(), a fresh attempt budget.
     */
    private function rearmRegisterOtp(string $email, string $otp): void
    {
        $keys = $this->registerOtpCacheKeys($email);

        if (! Cache::add($keys['code'], $otp, now()->addMinutes(OtpService::TTL_MINUTES))) {
            return;
        }

        Cache::forget($keys['attempts']);
    }

    /**
     * Cache keys for the registration OTP, mirroring OtpService's
     * `otp:{purpose}:{identifier}` and `otp:attempts:{purpose}:{identifier}`
     * shape for the session-less code issued by store().
     *
     * @return array{code: string, attempts: string}
     */
    private function registerOtpCacheKeys(string $email): array
    {
        return [
            'code' => 'otp:register:'.$email,
            'attempts' => 'otp:attempts:register:'.$email,
        ];
    }

    private function otpThrottleKey(Request $request, string $email): string
    {
        return 'otp:register:'.$email.'|'.$request->ip();
    }
}
