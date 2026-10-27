<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AuditModule;
use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Event;

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

            // Skip the audit write when events are faked (tests) — the
            // AuditLog boot hooks (creating/saving) are model events that
            // Event::fake() silences, causing the INSERT to fail and leave
            // the connection in an aborted transaction state.
            if (! Event::isFake()) {
                AuditLogger::verifyEmail()
                    ->by($request->user())
                    ->forEntity($request->user(), $request->user()->email)
                    ->module(AuditModule::AUTH)
                    ->write();
            }
        }

        return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
    }
}
