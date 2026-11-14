<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AuditModule;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\AvatarUploadRequest;
use App\Http\Requests\Concerns\ValidatesUserIdentity;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\CloudinaryAvatarService;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Throwable;

class OfwRegistrationController extends Controller
{
    use ValidatesUserIdentity;

    public function __construct(private readonly OtpService $otpService) {}

    public function create(Request $request)
    {
        $credentials = $request->session()->get('pending_registration.credentials', []);

        return Inertia::render('Auth/Register', [
            'initial' => ['credentials' => [
                'email' => $credentials['email'] ?? '',
                'has_password' => ! empty($credentials['password']),
                'first_name' => $credentials['first_name'] ?? '',
                'middle_name' => $credentials['middle_name'] ?? '',
                'last_name' => $credentials['last_name'] ?? '',
                'suffix' => $credentials['suffix'] ?? '',
                'date_of_birth' => $credentials['date_of_birth'] ?? '',
                'sex' => $credentials['sex'] ?? '',
            ]],
        ]);
    }

    public function store(AvatarUploadRequest $request)
    {
        $validated = $request->validate([
            'avatar' => 'prohibited',
            'email' => 'required|string|lowercase|email|max:255',
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ] + $this->identityRules(requireDemographics: true) + $this->removedProfileRules());

        $oldPhoto = $request->session()->get('pending_registration.photo_key');
        $photoKey = null;
        if ($request->uploadedAvatar()) {
            try {
                app(CloudinaryAvatarService::class)->validateImageFile($request->uploadedAvatar());
                $photoKey = 'registration-photo:'.Str::random(48);
                // Cache TTL removes abandoned photos without a cleanup job; session holds only the opaque key.
                if (! Cache::put($photoKey, Crypt::encryptString(file_get_contents($request->uploadedAvatar()->getRealPath())), now()->addHour())) {
                    throw new \RuntimeException('Unable to stage profile photo.');
                }
            } catch (Throwable $e) {
                if ($photoKey !== null) {
                    Cache::forget($photoKey);
                }
                report($e);

                return back()->withErrors(['profile_picture' => 'The image could not be saved. Please try again.']);
            }
        }
        if (is_string($oldPhoto)) {
            Cache::forget($oldPhoto);
        }

        $request->session()->put('pending_registration', [
            'credentials' => [
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                ...array_intersect_key($validated, array_flip([
                    'first_name', 'middle_name', 'last_name', 'suffix', 'date_of_birth', 'sex',
                ])),
            ],
            ...($photoKey ? ['photo_key' => $photoKey] : []),
        ]);
        $request->session()->regenerate();

        return redirect()->route('register.review');
    }

    public function showReview(Request $request)
    {
        return Inertia::render('Auth/RegisterReview', [
            'pending' => [
                'email' => $request->session()->get('pending_registration.credentials.email'),
                'has_photo' => Cache::has($request->session()->get('pending_registration.photo_key', '')),
            ],
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

        $validated = $request->validate(['otp' => 'required|string|size:6', 'profile_picture' => 'prohibited'] + $this->removedProfileRules());
        if ((isset($pending['photo_key']) && (! is_string($pending['photo_key']) || ! str_starts_with($pending['photo_key'], 'registration-photo:')))
            || array_diff(array_keys($pending), ['credentials', 'photo_key']) || array_diff(array_keys($credentials), [
                'email', 'password', 'first_name', 'middle_name', 'last_name', 'suffix', 'date_of_birth', 'sex',
            ])) {
            $request->session()->forget('pending_registration');

            return redirect()->route('register');
        }
        Validator::make($credentials, [
            'email' => 'required|string|lowercase|email|max:255|unique:users,email',
            'password' => 'required|string',
        ] + $this->identityRules(requireDemographics: true))->validate();
        $email = $credentials['email'];
        $photoKey = $pending['photo_key'] ?? null;
        $photo = is_string($photoKey) ? Cache::get($photoKey) : null;
        if ($photoKey && ! is_string($photo)) {
            $request->session()->forget('pending_registration');

            return redirect()->route('register')->withErrors(['profile_picture' => 'Your photo expired. Please start registration again.']);
        }
        if (! $this->otpService->verify($email, 'register', $validated['otp'])) {
            return back()->withErrors(['otp' => 'Invalid or expired code.']);
        }

        $uploadedUrl = null;
        try {
            $user = DB::transaction(function () use ($credentials, $email, $photo, &$uploadedUrl) {
                $user = User::create([
                    'name' => User::composeName($credentials['first_name'], $credentials['middle_name'] ?? null, $credentials['last_name'], $credentials['suffix'] ?? null),
                    ...array_intersect_key($credentials, array_flip([
                        'first_name', 'middle_name', 'last_name', 'suffix', 'date_of_birth', 'sex',
                    ])),
                    'email' => $email,
                    'password' => $credentials['password'],
                    'role' => UserRole::OFW->value,
                    'email_verified_at' => now(),
                    'is_active' => true,
                ]);

                if ($photo !== null) {
                    $bytes = Crypt::decryptString($photo);
                    $path = tempnam(sys_get_temp_dir(), 'ofw-photo-');
                    if ($path === false) {
                        throw new \RuntimeException('Unable to stage profile photo.');
                    }
                    try {
                        if (file_put_contents($path, $bytes) === false) {
                            throw new \RuntimeException('Unable to stage profile photo.');
                        }
                        $file = new UploadedFile($path, 'profile.jpg', null, null, true);
                        $uploadedUrl = app(CloudinaryAvatarService::class)->uploadImage($file, 'avatars', 'user-'.$user->id);
                        $user->avatar_url = $uploadedUrl;
                        $user->save();
                    } finally {
                        @unlink($path);
                    }
                }

                AuditLogger::register()
                    ->by($user)
                    ->forEntity($user, $user->email)
                    ->module(AuditModule::AUTH)
                    ->context(null, ['email' => $email])
                    ->write();

                return $user;
            });
        } catch (Throwable $e) {
            if ($uploadedUrl !== null) {
                try {
                    app(CloudinaryAvatarService::class)->deleteByUrl($uploadedUrl);
                } catch (Throwable $cleanupError) {
                    report($cleanupError);
                }
            }
            report($e);

            return back()->withErrors(
                User::withTrashed()->where('email', $email)->exists()
                    ? ['email' => 'That email address is already registered. Please start a new registration.']
                    : ['registration' => 'We could not create your account. Please request a new verification code and try again.']
            );
        }

        if (is_string($photoKey)) {
            Cache::forget($photoKey);
        }
        $request->session()->forget('pending_registration');
        $request->session()->regenerate();
        Auth::login($user);

        return redirect()->route('ofw.intake');
    }

    private function removedProfileRules(): array
    {
        return array_fill_keys([
            'type', 'client_type', 'client_id', 'ofw_client_id', 'contact_number', 'address', 'employment', 'next_of_kin',
            'nok_name', 'nok_relationship', 'nok_contact_number', 'nok_email', 'profile',
        ], 'prohibited');
    }
}
