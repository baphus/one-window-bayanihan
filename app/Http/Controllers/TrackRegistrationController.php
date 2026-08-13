<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TrackingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class TrackRegistrationController extends Controller
{
    public function __construct(
        private readonly TrackingService $trackingService,
    ) {}

    /**
     * Create an OFW user account from the track-case portal.
     *
     * Identity was already proven by the OTP exchange in TrackController@verifyOtp,
     * which stored the verified (tracker, email) pair in the tracking session
     * binding. This mirrors IntakeRegistrationController: same password rules,
     * same OFW user shape, same auto-login — but keyed off the tracking binding
     * instead of the intake email session, so an OFW who skipped account creation
     * after filing can still create one later.
     */
    public function store(Request $request)
    {
        $binding = $request->session()->get(TrackingService::SESSION_KEY);

        if (! is_array($binding) || empty($binding['tracker_number']) || empty($binding['email'])) {
            return response()->json([
                'error' => 'Session expired. Please verify your case again.',
            ], 422);
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);

        $email = strtolower(trim($binding['email']));

        $case = $this->trackingService->findCaseByTracker($binding['tracker_number']);

        if (! $case) {
            // Generic error, indistinguishable from other failures, to prevent
            // tracker number enumeration (mirrors TrackController).
            return response()->json([
                'error' => 'Unable to process request. Please check your details and try again.',
            ], 422);
        }

        $client = $case->client;

        if (! $client) {
            return response()->json([
                'error' => 'No intake submission found for this email. Please submit your case first.',
            ], 422);
        }

        // Idempotent: an OFW account that already exists for the verified email
        // is simply signed in again (mirrors IntakeRegistrationController).
        $existingUser = User::where('email', $email)
            ->where('role', 'OFW')
            ->where('is_deleted', false)
            ->first();

        if ($existingUser) {
            Auth::login($existingUser);

            return response()->json([
                'success' => true,
                'redirect' => route('ofw.dashboard'),
            ]);
        }

        $user = User::create([
            'name' => trim(($client->first_name ?? '').' '.($client->last_name ?? '')),
            'email' => $email,
            'password' => Hash::make($validated['password']),
            'role' => 'OFW',
            'client_id' => $client->id,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        Auth::login($user);

        return response()->json([
            'success' => true,
            'redirect' => route('ofw.dashboard'),
        ]);
    }
}
