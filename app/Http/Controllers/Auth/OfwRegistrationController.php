<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class OfwRegistrationController extends Controller
{
    public function create()
    {
        return Inertia::render('Auth/Register');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);

        $email = strtolower(trim($validated['email']));

        // Check for existing user (any role)
        $existingUser = User::where('email', $email)->first();
        if ($existingUser) {
            return back()->withErrors(['email' => 'This email is already registered. Please log in instead.']);
        }

        // Check for existing client by email — link if found
        $client = Client::where('is_deleted', false)
            ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
            ->first();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $email,
            'password' => Hash::make($validated['password']),
            'role' => UserRole::OFW->value,
            'client_id' => $client?->id,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        Auth::login($user);

        return redirect()->route('ofw.dashboard');
    }
}
