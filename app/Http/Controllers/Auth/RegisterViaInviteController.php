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
        ]);

        DB::transaction(function () use ($token, $validated): void {
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
