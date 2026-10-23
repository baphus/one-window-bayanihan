<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Services\CloudinaryAvatarService;
use App\Services\OnboardingService;
use App\Services\ReferenceDataService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', self::editProps($request));
    }

    /**
     * The prop set that every Profile/Edit render must carry.
     *
     * The page binds these once on mount: `useState(mfaEnabled)` inside
     * MfaSetup, and `useForm({ notifications_config: { ...notificationPrefs } })`
     * for the notification section. A partial payload is therefore not a
     * cosmetic gap — an MFA-enrolled user is shown as unenrolled, and saving
     * the form writes the all-off defaults back over the stored preferences.
     *
     * The email-change steps render this same page from a second controller,
     * so both producers build from here and cannot drift apart again.
     *
     * @return array<string, mixed>
     */
    public static function editProps(Request $request): array
    {
        $user = $request->user()->load('agency');

        return [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => session('status'),
            'mfaEnabled' => $user->mfa_enabled_at !== null,
            'defaultAgency' => app(ReferenceDataService::class)->getDefaultAgency(),
            'notificationPrefs' => $user->notifications_config ?? [
                'email_on_case_assigned' => true,
                'email_on_case_status_change' => true,
                'email_on_referral' => true,
                'in_app_notifications' => true,
            ],
        ];
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->fill($request->validated());

        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            try {
                app(CloudinaryAvatarService::class)->deleteByUrl($user->getRawOriginal('avatar_url'));
                $user->avatar_url = app(CloudinaryAvatarService::class)->uploadImage(
                    $file,
                    'avatars',
                    'user-'.$user->id,
                );
            } catch (\RuntimeException $e) {
                return Redirect::route('profile.edit')->withErrors(['avatar' => $e->getMessage()]);
            }
        }

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        // Auto-mark profile as complete if all necessary fields are filled
        app(OnboardingService::class)->isProfileIncomplete($user);

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
