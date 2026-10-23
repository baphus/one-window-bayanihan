<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $user = $request->user();

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Invalidate all other sessions for this user to force re-login
        // on other devices after a password change.
        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', session()->getId())
            ->delete();

        // Deleting session rows alone leaves "remember me" devices logged in:
        // the recaller cookie authenticates straight from users.remember_token,
        // so every outstanding recaller has to be invalidated by rotating that
        // token. The password-hash segment of the cookie is only compared by
        // AuthenticateSession, which is not part of this stack.
        $user->setRememberToken(Str::random(60));
        $user->save();

        // This device keeps its "remember me" cookie: re-issue the recaller
        // against the new token (queued only when the request arrived with
        // one), otherwise the user would be silently signed out the next time
        // this session expires.
        /** @var SessionGuard $webGuard */
        $webGuard = Auth::guard('web');
        $webGuard->logoutOtherDevices($validated['password']);

        return back();
    }
}
