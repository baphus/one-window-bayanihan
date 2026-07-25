<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class OfwProfileController extends Controller
{
    /**
     * Show the OFW profile edit page.
     */
    public function edit()
    {
        $user = request()->user();

        return Inertia::render('OFW/Profile', [
            'user' => $user->only(['id', 'name', 'email', 'contact_number']),
        ]);
    }

    /**
     * Update the OFW's password and/or contact number.
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'contact_number' => ['nullable', 'string', 'max:20'],
        ]);

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        if (array_key_exists('contact_number', $validated)) {
            $user->contact_number = $validated['contact_number'];

            // Also update the linked client record's contact number
            if ($user->client_id) {
                $user->client()->update([
                    'contact_number' => $validated['contact_number'],
                ]);
            }
        }

        $user->save();

        return redirect()->route('ofw.profile.edit')->with('success', 'Profile updated successfully.');
    }
}
