<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\User;
use App\Services\IntakeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class IntakeRegistrationController extends Controller
{
    public function __construct(
        private readonly IntakeService $intakeService,
    ) {}

    /**
     * Create an OFW user account linked to the client created during intake,
     * then log them in and redirect to the OFW portal.
     */
    public function store(Request $request)
    {
        $verifiedEmail = $request->session()->get('intake_verified_email');

        if (! $verifiedEmail) {
            return response()->json([
                'error' => 'Session expired. Please complete the intake form again.',
            ], 422);
        }

        $validated = $request->validate([
            'password' => ['required', 'string', Password::min(8)->mixedCase()->numbers()],
        ]);

        // Find the client created during intake submission
        $client = $this->findClientByEmail($verifiedEmail);

        if (! $client) {
            return response()->json([
                'error' => 'No intake submission found for this email. Please submit your case first.',
            ], 422);
        }

        // Check if an OFW user already exists for this email
        $existingUser = User::where('email', $verifiedEmail)
            ->where('role', 'OFW')
            ->first();

        if ($existingUser) {
            // Already has an account — just log them in
            Auth::login($existingUser);

            $request->session()->forget('intake_verified_email');

            return response()->json([
                'success' => true,
                'redirect' => route('ofw.dashboard'),
            ]);
        }

        // Create the OFW user account
        $user = User::create([
            'name' => trim(($client->first_name ?? '').' '.($client->last_name ?? '')),
            'email' => $verifiedEmail,
            'password' => Hash::make($validated['password']),
            'role' => 'OFW',
            'client_id' => $client->id,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        // Log them in
        Auth::login($user);

        $request->session()->forget('intake_verified_email');

        return response()->json([
            'success' => true,
            'redirect' => route('ofw.dashboard'),
        ]);
    }

    /**
     * Find a client record by email match.
     */
    private function findClientByEmail(string $email): ?Client
    {
        // Client.email is plaintext, so the match can run in the database.
        // LOWER(TRIM()) keeps it case- and whitespace-insensitive, mirroring
        // the previous PHP-side strtolower(trim()) comparison without loading
        // every non-deleted client into memory.
        return Client::where('is_deleted', false)
            ->whereRaw('LOWER(TRIM(email)) = ?', [strtolower(trim($email))])
            ->first();
    }
}
