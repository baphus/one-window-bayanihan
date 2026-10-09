<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\MfaPendingState;
use Illuminate\Http\RedirectResponse;
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

        // OFW users go directly to their portal
        if ($request->user()->isOfw()) {
            return redirect()->intended(route('ofw.dashboard', absolute: false));
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /*
     * Logout is a closure in routes/auth.php:95 that also writes the audit row,
     * clears the MFA pending state and invalidates the session. There is
     * deliberately no destroy() method here — a second logout implementation
     * drifts from the routed one. Change the route, not this class.
     */
}
