<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AuditModule;
use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));

            AuditLogger::verifyEmail()
                ->by($request->user())
                ->forEntity($request->user(), $request->user()->email)
                ->module(AuditModule::AUTH)
                ->write();
        }

        return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
    }
}
