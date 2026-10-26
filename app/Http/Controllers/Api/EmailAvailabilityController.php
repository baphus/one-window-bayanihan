<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public duplicate-email probe for the register page (step "Your Account").
 *
 * Mirrors the review-time duplicate check in OfwRegistrationController
 * (`User::withTrashed()->where('email', ...)->exists()`) so the register
 * wizard blocks a registered address before the user fills four steps,
 * instead of learning about the collision at the OTP review step.
 *
 * Deliberately not part of the session-authenticated web group: this runs on
 * the public register form, throttled by the `email-availability` limiter.
 */
class EmailAvailabilityController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $email = strtolower(trim((string) $request->query('email')));

        return response()->json([
            'taken' => User::withTrashed()->where('email', $email)->exists(),
        ]);
    }
}
