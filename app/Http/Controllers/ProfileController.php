<?php

namespace App\Http\Controllers;

use App\Enums\AuditModule;
use App\Http\Requests\AvatarUploadRequest;
use App\Http\Requests\ProfileUpdateRequest;
use App\Services\AuditLogger;
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
            'email_change_step' => $request->session()->get('email_change_step'),
            'email_change_hint' => $request->session()->get('email_change_hint'),
        ];
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->fill($request->validated());

        // Legacy path: the staff profile page now posts to storeAvatar(), but
        // the `avatar` field stays accepted here for backward compatibility.
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
     * Upload or replace the authenticated user's profile picture.
     *
     * Delete-before-upload is deliberate (same ordering as
     * ClientController::storeAvatar): a failed upload leaves the user with no
     * photo rather than a broken image. The public_id is derived from the user
     * id so a replacement overwrites the same Cloudinary asset.
     */
    public function storeAvatar(AvatarUploadRequest $request): RedirectResponse
    {
        $user = $request->user();
        $file = $request->uploadedAvatar();

        if (! $file) {
            return Redirect::route('profile.edit')->withErrors(['profile_picture' => 'Please choose a photo to upload.']);
        }

        try {
            app(CloudinaryAvatarService::class)->deleteByUrl($user->getRawOriginal('avatar_url'));
            $user->avatar_url = app(CloudinaryAvatarService::class)->uploadImage(
                $file,
                'avatars',
                'user-'.$user->id,
            );
        } catch (\RuntimeException $e) {
            return Redirect::route('profile.edit')->withErrors(['profile_picture' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return Redirect::route('profile.edit')->withErrors([
                'profile_picture' => 'The image could not be uploaded. Please try again later.',
            ]);
        }

        $user->save();

        return Redirect::route('profile.edit')->with('success', 'Profile picture updated successfully.');
    }

    /**
     * Remove the authenticated user's profile picture.
     */
    public function destroyAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();

        try {
            app(CloudinaryAvatarService::class)->deleteByUrl($user->getRawOriginal('avatar_url'));
        } catch (\Throwable $e) {
            // deleteByUrl() is already best-effort; this covers anything that
            // escapes it so a removal request never 500s.
            report($e);
        }

        $user->avatar_url = null;
        $user->save();

        return Redirect::route('profile.edit')->with('success', 'Profile picture removed.');
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
        $userId = $user->id;

        Auth::logout();

        $user->delete();

        AuditLogger::deleteAccount()
            ->byId($userId)
            ->forEntity($user, $user->name)
            ->module(AuditModule::USER)
            ->write();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
