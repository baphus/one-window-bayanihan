<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AuditModule;
use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\MfaPendingState;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class PasswordController extends Controller
{
    public function editTemporary(Request $request)
    {
        abort_unless($request->user()->isOfw() && $request->user()->must_change_password, 403);

        return Inertia::render('Auth/ChangeTemporaryPassword');
    }

    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $temporary = $request->routeIs('password.temporary.update');
        if ($temporary) {
            abort_unless($request->user()->isOfw() && $request->user()->must_change_password, 403);
        }

        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed', 'different:current_password'],
        ]);

        $user = $request->user();
        $hadValidMfaMarker = app(MfaPendingState::class)->hasValidMarker($request, $user);

        $user->password = Hash::make($validated['password']);
        $user->must_change_password = false;
        $user->save();
        if ($temporary) {
            $request->session()->regenerate();
        }

        $request->session()->forget(MfaPendingState::CONFIRMATION_KEY);

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

        if ($user->mfa_enabled_at !== null && $hadValidMfaMarker) {
            app(MfaPendingState::class)->markAuthenticated($request, $user);
        }

        AuditLogger::changePassword()
            ->by($user)
            ->forEntity($user, $user->name)
            ->module(AuditModule::AUTH)
            ->write();

        return $temporary ? redirect()->route('dashboard') : back();
    }
}
