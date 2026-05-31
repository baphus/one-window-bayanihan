<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class AdminUserController extends Controller
{
    public function index()
    {
        $users = User::with('agency')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $agencies = Agency::where('is_active', true)->orderBy('name')->get();

        return Inertia::render('Admin/User/Index', [
            'users' => $users,
            'agencies' => $agencies,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', 'string', Password::min(8)->mixedCase()->numbers()->symbols()],
            'role' => 'required|in:ADMIN,AGENCY,CASE_MANAGER',
            'agcy_id' => 'nullable|exists:agencies,id',
            'contact_number' => 'nullable|string',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'agcy_id' => $validated['agcy_id'] ?? null,
            'contact_number' => $validated['contact_number'] ?? null,
            'is_active' => true,
        ]);

        return redirect()->route('admin.users.index')
            ->with('success', 'User created successfully.');
    }

    public function update(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$id,
            'role' => 'required|in:ADMIN,AGENCY,CASE_MANAGER',
            'agcy_id' => 'nullable|exists:agencies,id',
            'contact_number' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $updateData = $validated;

        if ($request->filled('password')) {
            $request->validate(['password' => ['string', Password::min(8)->mixedCase()->numbers()->symbols()]]);
            $updateData['password'] = Hash::make($request->input('password'));
        }

        $user->update($updateData);

        return redirect()->route('admin.users.index')
            ->with('success', 'User updated successfully.');
    }

    public function destroy(string $id)
    {
        $user = User::findOrFail($id);
        $user->is_active = false;
        $user->is_deleted = true;
        $user->save();

        return redirect()->route('admin.users.index')
            ->with('success', 'User deactivated successfully.');
    }
}
