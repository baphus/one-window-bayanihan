<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserInvite;
use App\Services\OnboardingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class RegisterViaInviteController extends Controller
{
    public function show(string $token)
    {
        $invite = $this->findByToken($token);

        if (! $invite) {
            abort(404, 'This invitation link is invalid.');
        }

        if ($invite->isExpired()) {
            abort(410, 'This invitation has expired. Please contact your administrator for a new invite.');
        }

        return Inertia::render('Auth/RegisterViaInvite', [
            'invite' => [
                'token' => $token,
                'email' => $invite->email,
                'role' => $invite->role,
                'agency' => $invite->agency?->only('id', 'name'),
            ],
        ]);
    }

    public function store(Request $request, string $token)
    {
        $invite = $this->findByToken($token);

        if (! $invite || $invite->isExpired()) {
            abort(410, 'This invitation has expired or is invalid.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'password' => ['required', 'string', Password::min(8)->mixedCase()->numbers()->symbols()],
            'password_confirmation' => 'required|string',
            'position' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'contact_number' => 'nullable|string|max:20',
            'profile_picture' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048', 'dimensions:min_width=32,min_height=32'],
        ], [
            'profile_picture.mimes' => 'Profile picture must be a JPEG, PNG, or WebP file.',
            'profile_picture.max' => 'Profile picture must not exceed 2MB.',
            'profile_picture.dimensions' => 'Profile picture must be at least 32x32 pixels.',
        ]);

        $avatarFile = $request->file('profile_picture');

        DB::transaction(function () use ($token, $validated, $avatarFile): void {
            // Re-read under a row lock. Between the check above and this
            // transaction the invite may have been consumed by a duplicate
            // POST or cancelled by an administrator; creating the account and
            // consuming the invite must land together or a crash leaves a live
            // invite next to an account that can never be registered again.
            $lockedInvite = $this->findByToken($token, lockForUpdate: true);

            if (! $lockedInvite || $lockedInvite->isExpired()) {
                abort(410, 'This invitation has expired or is invalid.');
            }

            $user = User::create([
                'name' => $validated['name'],
                'email' => $lockedInvite->email,
                'password' => Hash::make($validated['password']),
                'role' => $lockedInvite->role,
                'agcy_id' => $lockedInvite->agcy_id,
                'position' => $validated['position'] ?? null,
                'department' => $validated['department'] ?? null,
                'contact_number' => $validated['contact_number'] ?? null,
                'is_active' => true,
            ]);

            // Set email_verified_at outside mass-assignment since it's not fillable
            $user->email_verified_at = now();

            // Optional profile photo. Best-effort: a Cloudinary outage must not
            // prevent registration — the user adds a photo later on their
            // profile page. public_id is derived from the user id so a later
            // replacement overwrites the same asset instead of orphaning it.
            if ($avatarFile) {
                try {
                    $user->avatar_url = app(CloudinaryAvatarService::class)->uploadImage(
                        $avatarFile,
                        'avatars',
                        'user-'.$user->id,
                    );
                } catch (\Throwable $e) {
                    Log::warning('Avatar upload failed during invite registration; continuing without a photo.', [
                        'user_id' => $user->id,
                    ]);
                }
            }

            $user->save();

            // Consume the invite
            $lockedInvite->update(['consumed_at' => now()]);
        });

        // Mark onboarding for the inviter (only once the registration above
        // has actually committed; a 410 or a failed create must not count).
        app(OnboardingService::class)
            ->markChecklistItemQuietly($invite->invitedBy, 'add-first-user');

        return redirect()->route('login')
            ->with('success', 'Registration complete! Please log in with your credentials.');
    }

    private function findByToken(string $token, bool $lockForUpdate = false): ?UserInvite
    {
        $query = UserInvite::where('token_hash', hash('sha256', $token))
            ->whereNull('consumed_at')
            ->whereNull('cancelled_at');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }
}
