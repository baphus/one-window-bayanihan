<?php

namespace App\Http\Controllers;

use App\Http\Requests\AvatarUploadRequest;
use App\Services\CloudinaryAvatarService;
use App\Services\OfwProfileService;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class OfwProfileController extends Controller
{
    public function __construct(private readonly OfwProfileService $profileService) {}

    public function edit(Request $request)
    {
        return Inertia::render('OFW/Profile', [
            'user' => $request->user()->only(['id', 'name', 'email', 'contact_number', 'avatar_url']),
        ]);
    }

    public function storeAvatar(AvatarUploadRequest $request): RedirectResponse
    {
        $user = $request->user();
        $file = $request->uploadedAvatar();

        if (! $file) {
            return redirect()->route('ofw.profile.edit')
                ->withErrors(['profile_picture' => 'Please choose a photo to upload.']);
        }

        try {
            $user->avatar_url = app(CloudinaryAvatarService::class)->uploadImage(
                $file,
                'avatars',
                'user-'.$user->id,
            );
        } catch (\RuntimeException $e) {
            return redirect()->route('ofw.profile.edit')->withErrors(['profile_picture' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return redirect()->route('ofw.profile.edit')->withErrors([
                'profile_picture' => 'The image could not be uploaded. Please try again later.',
            ]);
        }

        $user->save();

        return redirect()->route('ofw.profile.edit')->with('success', 'Profile picture updated successfully.');
    }

    public function destroyAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();

        try {
            app(CloudinaryAvatarService::class)->deleteByUrl($user->getRawOriginal('avatar_url'));
        } catch (\Throwable $e) {
            report($e);
        }

        $user->avatar_url = null;
        $user->save();

        return redirect()->route('ofw.profile.edit')->with('success', 'Profile picture removed.');
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'current_password' => [
                'nullable',
                Rule::requiredIf(fn () => filled($request->input('password'))),
                Rule::when(fn () => filled($request->input('password')), ['current_password']),
            ],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'contact_number' => ['sometimes', 'nullable', 'string', 'max:50'],
        ] + array_fill_keys([
            'name', 'email', 'first_name', 'middle_name', 'last_name', 'date_of_birth', 'sex', 'suffix',
            'address', 'employment', 'next_of_kin', 'type', 'client_type', 'client_id', 'ofw_client_id',
            'nok_name', 'nok_relationship', 'nok_contact_number', 'nok_email',
        ], 'prohibited'));

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
            $user->save();

            DB::table('sessions')
                ->where('user_id', $user->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();

            $user->setRememberToken(Str::random(60));
            $user->save();

            /** @var SessionGuard $webGuard */
            $webGuard = Auth::guard('web');
            $webGuard->logoutOtherDevices($validated['password']);
        }

        $this->profileService->updateAccount($user, $validated);

        return redirect()->route('ofw.profile.edit')->with('success', 'Profile updated successfully.');
    }
}
