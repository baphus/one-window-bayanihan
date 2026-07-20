<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
     */
    public function store(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            AuditLog::create([
                'action' => 'LOGIN_FAILED',
                'module' => 'auth',
                'entity_id' => $user?->id,
                'description' => 'Failed sign-in attempt for '.$request->email.': invalid credentials',
                'user_id' => null,
                'timestamp' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent() ?? '',
                'request_id' => $request->attributes->get('correlation_id') ?? $request->header('X-Request-ID') ?? (string) Str::uuid(),
            ]);

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        if (! $user->is_active || $user->is_deleted) {
            AuditLog::create([
                'action' => 'LOGIN_FAILED',
                'module' => 'auth',
                'entity_id' => $user->id,
                'description' => 'Failed sign-in attempt for '.$request->email.': inactive or deleted account',
                'user_id' => null,
                'timestamp' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent() ?? '',
                'request_id' => $request->attributes->get('correlation_id') ?? $request->header('X-Request-ID') ?? (string) Str::uuid(),
            ]);

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        Auth::login($user, true);

        $request->session()->regenerate();

        AuditLog::create([
            'action' => 'LOGIN',
            'module' => 'auth',
            'entity_id' => $user->id,
            'description' => null,
            'user_id' => $user->id,
            'timestamp' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent() ?? '',
            'request_id' => $request->attributes->get('correlation_id') ?? $request->header('X-Request-ID') ?? (string) Str::uuid(),
        ]);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
