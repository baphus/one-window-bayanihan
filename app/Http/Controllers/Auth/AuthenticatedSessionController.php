<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\MfaPendingState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     *
     * Validation, rate limiting, credential verification, and inactive-account
     * rejection are all delegated to LoginRequest::authenticate().
     * Audit logging flows through event listeners (LogSuccessfulLogin /
     * LogFailedLogin) — the controller itself has zero audit logic.
     */
    public function store(LoginRequest $request, MfaPendingState $pendingState): RedirectResponse
    {
        $pendingState->clear($request);
        $user = $request->authenticate();

        if ($user) {
            $pendingState->startChallenge($request, $user, $request->boolean('remember'), $request->session()->pull('url.intended'));

            return redirect()->route('mfa.challenge.show');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        app(MfaPendingState::class)->clear($request);
        $request->session()->forget(MfaPendingState::MARKER_KEY);
        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
