<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\User;
use App\Services\DefaultAgencyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['search', 'role', 'status', 'agcy_id']);

        $query = User::with('agency');

        if ($search = $request->search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhere('position', 'ilike', "%{$search}%")
                    ->orWhere('department', 'ilike', "%{$search}%")
                    ->orWhere('contact_number', 'ilike', "%{$search}%");
            });
        }

        if ($role = $request->role) {
            $query->where('role', $role);
        }

        if ($request->has('status')) {
            $query->where('is_active', $request->boolean('status'));
        }

        if ($agcyId = $request->agcy_id) {
            $query->where('agcy_id', $agcyId);
        }

        if ($mfaStatus = $request->mfa_status) {
            if ($mfaStatus === 'enabled') {
                $query->whereNotNull('mfa_enabled_at');
            } elseif ($mfaStatus === 'disabled') {
                $query->whereNull('mfa_enabled_at');
            }
        }

        $perPage = min((int) ($request->per_page ?? 15), 100);
        $users = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $agencies = Agency::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'logo_url', 'short']);

        return Inertia::render('Admin/User/Index', [
            'users' => $users,
            'filters' => $filters,
            'agencies' => Inertia::lazy(fn () => $agencies),
            'stats' => [
                'total' => User::count(),
                'active' => User::where('is_active', true)->count(),
                'case_managers' => User::where('role', 'CASE_MANAGER')->count(),
                'agency_focals' => User::where('role', 'AGENCY')->count(),
                'admins' => User::where('role', 'ADMIN')->count(),
            ],
        ]);
    }

    public function show(string $id)
    {
        $user = User::with('agency')->findOrFail($id);

        return Inertia::render('Admin/User/Show', ['user' => $user]);
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
            'position' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'office_location' => 'nullable|string|max:500',
            'bio' => 'nullable|string|max:2000',
            'emergency_contact' => 'nullable|json',
        ]);

        $agcyId = $validated['agcy_id'] ?? null;
        if (! $agcyId && $validated['role'] === 'AGENCY') {
            $agcyId = app(DefaultAgencyService::class)->getDefaultAgency()?->id;
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'agcy_id' => $agcyId,
            'contact_number' => $validated['contact_number'] ?? null,
            'position' => $validated['position'] ?? null,
            'department' => $validated['department'] ?? null,
            'office_location' => $validated['office_location'] ?? null,
            'bio' => $validated['bio'] ?? null,
            'emergency_contact' => $validated['emergency_contact'] ?? null,
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
            'position' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'office_location' => 'nullable|string|max:500',
            'bio' => 'nullable|string|max:2000',
            'emergency_contact' => 'nullable|json',
            'is_active' => 'boolean',
        ]);

        $updateData = $validated;

        if ($request->filled('password')) {
            $request->validate(['password' => ['string', Password::min(8)->mixedCase()->numbers()->symbols()]]);
            $updateData['password'] = Hash::make($request->input('password'));
        }

        if ($user->email !== $validated['email']) {
            $user->email_verified_at = null;
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

    public function verify(User $user)
    {
        if ($user->is_deleted || ! $user->is_active) {
            return redirect()->back()->with('error', 'Cannot verify inactive or deleted users.');
        }

        $user->email_verified_at = $user->email_verified_at ? null : now();
        $user->save();

        return redirect()->back()->with('success', 'User verification status updated.');
    }
}
