<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AuditModule;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use App\Rules\EmploymentStartAtWorkingAge;
use App\Rules\ServedRegion;
use App\Services\AuditLogger;
use App\Services\CloudinaryAvatarService;
use App\Services\NotificationService;
use App\Services\OfwProfileService;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Throwable;

class OfwRegistrationController extends Controller
{
    public function __construct(
        private readonly OtpService $otpService,
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * Step 1. Prefills from `pending_registration` so "Back to edit" from the
     * review step is lossless. The password hash is never sent to the client —
     * an existing hash means the user must re-enter the password to submit.
     */
    public function create(Request $request)
    {
        $pending = $request->session()->get('pending_registration');
        $credentials = is_array($pending) ? ($pending['credentials'] ?? []) : [];
        $profile = is_array($pending) ? ($pending['profile'] ?? []) : [];

        return Inertia::render('Auth/Register', [
            'initial' => [
                'credentials' => [
                    'first_name' => $credentials['first_name'] ?? '',
                    'middle_name' => $credentials['middle_name'] ?? '',
                    'last_name' => $credentials['last_name'] ?? '',
                    'email' => $credentials['email'] ?? '',
                    'has_password' => ! empty($credentials['password']),
                ],
                'profile' => $profile,
            ],
        ]);
    }

    /**
     * Step 1: credentials *and* the whole profile. Nothing is written to the
     * database and no code is sent — both live in the session until the review
     * step verifies the email (see storeReview()).
     *
     * `unique:users,email` is deliberately absent: a duplicate is re-checked at
     * review time so the user isn't bounced off a long form at the last second.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255',
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ] + $this->profileRules($request->input('date_of_birth')));

        $request->session()->put('pending_registration', [
            'credentials' => [
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'last_name' => $validated['last_name'],
                'email' => strtolower(trim($validated['email'])),
                'password' => Hash::make($validated['password']),
            ],
            'profile' => [
                'type' => $validated['type'],
                'suffix' => $validated['suffix'] ?? null,
                'date_of_birth' => $validated['date_of_birth'],
                'sex' => $validated['sex'] ?? null,
                'contact_number' => $validated['contact_number'] ?? null,
                'address' => $validated['address'],
                'employment' => $validated['employment'],
            ],
        ]);
        $request->session()->regenerate();

        return redirect()->route('register.review');
    }

    /**
     * Profile rules shared by step 1 (capture) and the review step (re-check
     * against the session copy). The date of birth is passed in rather than
     * read from the request because the review submission may only carry the
     * code — the DOB it must be checked against lives in the session.
     *
     * @return array<string, mixed>
     */
    protected function profileRules(?string $dateOfBirth): array
    {
        $dob = $dateOfBirth ?: '1900-01-01';

        return [
            'type' => 'required|string|in:OFW,NEXT_OF_KIN',
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
            'employment.start_date' => ['required', 'date', 'after_or_equal:'.$dob, 'before_or_equal:today', new EmploymentStartAtWorkingAge($dateOfBirth)],
            'employment.end_date' => ['required_unless:employment.is_present,true', 'nullable', 'date', 'after_or_equal:employment.start_date', 'before_or_equal:today'],
            'employment.is_present' => ['nullable', 'boolean'],
            'employment.last_country' => ['required', 'string', 'max:255'],
            'employment.last_position' => ['required', 'string', 'max:255'],
            'employment.date_of_arrival' => ['required', 'date', 'after_or_equal:'.$dob],
        ];
    }

    /**
     * Step 2. Renders the read-only summary of everything captured in step 1
     * plus the "Send Verification Code" control.
     */
    public function showReview(Request $request)
    {
        $pending = $request->session()->get('pending_registration');
        $credentials = is_array($pending) ? ($pending['credentials'] ?? []) : [];
        $profile = is_array($pending) ? ($pending['profile'] ?? []) : [];

        return Inertia::render('Auth/RegisterReview', [
            'pendingName' => [
                'first_name' => $credentials['first_name'] ?? '',
                'middle_name' => $credentials['middle_name'] ?? '',
                'last_name' => $credentials['last_name'] ?? '',
            ],
            'pending' => $profile,
        ]);
    }

    /**
     * Issues the verification code from the review step. The named `otp`
     * limiter is applied by the route; the inline check below additionally
     * caps *sends* per address so the endpoint cannot be used as a mail cannon.
     */
    public function sendOtp(Request $request)
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

        return back()->with('success', 'A verification code has been sent to '.$email.'.');
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
    public function storeReview(Request $request)
    {
        $pending = $request->session()->get('pending_registration');

        if (! is_array($pending) || empty($pending['credentials']) || empty($pending['credentials']['first_name']) || empty($pending['credentials']['last_name'])) {
            return redirect()->route('register');
        }

        $credentials = $pending['credentials'];
        $email = $credentials['email'];

        $otpValidated = $request->validate(['otp' => 'required|string|size:6']);

        // Optional profile photo, validated up-front so a bad file never opens
        // the account-creating transaction.
        $request->validate([
            'profile_picture' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048', 'dimensions:min_width=32,min_height=32'],
        ], [
            'profile_picture.mimes' => 'Profile picture must be a JPEG, PNG, or WebP file.',
            'profile_picture.max' => 'Profile picture must not exceed 2MB.',
            'profile_picture.dimensions' => 'Profile picture must be at least 32x32 pixels.',
        ]);

        $avatarFile = $request->file('profile_picture');

        // Re-check the session copy rather than the request: the only input a
        // review submission may carry is the code.
        $profile = is_array($pending['profile'] ?? null) ? $pending['profile'] : [];

        $validated = Validator::make(
            [...$profile, 'email' => $email],
            $this->profileRules($profile['date_of_birth'] ?? null)
                + ['email' => 'required|email|unique:users,email']
        )->validate();

        $firstName = $credentials['first_name'];
        $middleName = $credentials['middle_name'] ?? null;
        $lastName = $credentials['last_name'];

        if (! $this->otpService->verify($email, 'register', $otpValidated['otp'])) {
            return back()->withErrors(['otp' => 'Invalid or expired code.']);
        }

        try {
            $user = DB::transaction(function () use ($pending, $validated, $email, $firstName, $middleName, $lastName, $avatarFile) {
                $user = User::create([
                    'name' => Str::limit(implode(' ', array_filter([$firstName, $middleName, $lastName])), 255, ''),
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
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'middle_name' => $middleName,
                    'suffix' => $validated['suffix'] ?? null,
                    'date_of_birth' => $validated['date_of_birth'] ?? null,
                    'sex' => ! empty($validated['sex']) ? strtoupper($validated['sex']) : null,
                    'email' => $email,
                    'contact_number' => $validated['contact_number'] ?? null,
                ]);

                $user->client_id = $client->id;

                // Optional profile photo. Best-effort: a Cloudinary outage must
                // not abort registration — the OFW adds a photo later on their
                // profile page. public_id is derived from the user id so a later
                // replacement overwrites the same asset instead of orphaning it.
                if ($avatarFile) {
                    try {
                        $user->avatar_url = app(CloudinaryAvatarService::class)->uploadImage(
                            $avatarFile,
                            'avatars',
                            'user-'.$user->id,
                        );
                    } catch (Throwable $e) {
                        Log::warning('Avatar upload failed during OFW registration; continuing without a photo.', [
                            'user_id' => $user->id,
                        ]);
                    }
                }

                $user->save();

                // Purposeful registration verb: the observer rows above record
                // the raw create/update, this records the business decision.
                AuditLogger::register()
                    ->by($user)
                    ->forEntity($user, $user->email)
                    ->module(AuditModule::AUTH)
                    ->context(null, ['email' => $email])
                    ->write();

                // Persist address to ClientAddress
                $service = new OfwProfileService($this->notificationService);
                $changes = [];
                $service->syncAddress($client, $validated['address'] ?? [], $changes);
                $service->syncEmployment($client, $validated['employment'] ?? [], $changes);

                return $user;
            });
        } catch (Throwable $e) {
            $this->rearmRegisterOtp($email, $otpValidated['otp']);

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
     * shape for the session-less code issued by sendOtp().
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
