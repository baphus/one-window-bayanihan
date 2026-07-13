<?php

namespace App\Http\Controllers;

use App\Helpers\CacheHelper;
use App\Mail\EmailChangedNotification;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\DefaultAgencyService;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AdminUserController extends Controller
{
    public function __construct(
        private readonly OtpService $otpService,
    ) {}

    public function index(Request $request)
    {
        $filters = $request->only(['search', 'role', 'status', 'agcy_id', 'show_deleted']);

        $query = User::with('agency');

        // By default exclude soft-deleted users unless show_deleted filter is active
        if (! $request->boolean('show_deleted')) {
            $query->where('is_deleted', false);
        }

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
            'agencies' => $agencies,
            'stats' => CacheHelper::safeRemember('admin:user_stats', 120, fn () => [
                'total' => User::count(),
                'active' => User::where('is_active', true)->count(),
                'case_managers' => User::where('role', 'CASE_MANAGER')->count(),
                'agency_focals' => User::where('role', 'AGENCY')->count(),
                'admins' => User::where('role', 'ADMIN')->count(),
            ]),
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
            'email_verified_at' => now(),
        ]);

        app(\App\Services\OnboardingService::class)
            ->markChecklistItemQuietly($request->user(), 'add-first-user');

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

        $emailChanged = $user->email !== $validated['email'];

        if ($emailChanged) {
            $verifiedEmail = $request->session()->get('verified_new_email_admin_'.$id);

            if (! $verifiedEmail || $verifiedEmail !== $validated['email']) {
                throw ValidationException::withMessages([
                    'email' => 'Email change requires OTP verification. Please complete the verification flow before saving.',
                ]);
            }

            $oldEmail = $user->email;
            $user->email_verified_at = now();
            $user->email = $validated['email'];

            $request->session()->forget('verified_new_email_admin_'.$id);

            $user->save();

            Mail::to($oldEmail)->queue(
                new EmailChangedNotification($oldEmail, $validated['email'], $user->name)
            );

            AuditLog::create([
                'action' => 'UPDATE',
                'module' => 'email',
                'entity_id' => $user->id,
                'old_value' => ['email' => $oldEmail],
                'new_value' => ['email' => $validated['email']],
                'user_id' => $request->user()->id,
                'timestamp' => now(),
            ]);
        }

        unset($updateData['email']);
        $user->update($updateData);

        return redirect()->route('admin.users.index')
            ->with('success', 'User updated successfully.');
    }

    public function sendEmailChangeOtp(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'admin_password' => ['required', 'string', 'current_password'],
            'new_email' => ['required', 'email', Rule::unique('users', 'email')->ignore($id)],
        ]);

        $newEmail = $validated['new_email'];

        $otp = $this->otpService->generate(
            $newEmail,
            'admin_email_change',
            $request->session()->getId().'_'.$id,
        );

        $request->session()->put('pending_admin_email_change_'.$id, $newEmail);

        return back()->with([
            'email_change_step' => 'otp',
            'email_change_hint' => $this->maskEmail($newEmail),
            'email_change_debug_otp' => (SystemSetting::getValue('debug_otp_enabled', false) && app()->environment('local', 'staging', 'testing')) ? $otp : null,
        ]);
    }

    public function verifyEmailChangeOtp(Request $request, string $id)
    {
        $pendingEmail = $request->session()->get('pending_admin_email_change_'.$id);

        if (! $pendingEmail) {
            throw ValidationException::withMessages([
                'otp' => 'No pending email change found. Please start again.',
            ]);
        }

        $request->validate(['otp' => ['required', 'string', 'size:6']]);

        $verified = $this->otpService->verify(
            $pendingEmail,
            'admin_email_change',
            $request->input('otp'),
            $request->session()->getId().'_'.$id,
        );

        if (! $verified) {
            throw ValidationException::withMessages([
                'otp' => 'Invalid or expired OTP. Please request a new code.',
            ]);
        }

        $request->session()->put('verified_new_email_admin_'.$id, $pendingEmail);
        $request->session()->forget('pending_admin_email_change_'.$id);

        return back()->with([
            'email_change_step' => 'verified',
            'success' => 'Email verified. Click Update to save changes.',
        ]);
    }

    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);

        if (strlen($parts[0]) <= 2) {
            return $email;
        }

        return substr($parts[0], 0, 2).'***@'.$parts[1];
    }

    public function destroy(string $id)
    {
        $user = User::findOrFail($id);

        // Prevent deleting yourself
        if ($user->id === request()->user()->id) {
            return redirect()->route('admin.users.index')
                ->with('error', 'You cannot delete your own account.');
        }

        // Prevent deleting the last admin
        if ($user->isAdmin() && User::where('role', 'ADMIN')->where('is_deleted', false)->count() <= 1) {
            return redirect()->route('admin.users.index')
                ->with('error', 'Cannot delete the only admin user.');
        }

        // If already inactive/deleted, permanently remove from database
        if (! $user->is_active || $user->is_deleted) {
            // Kill sessions
            DB::table('sessions')->where('user_id', $user->id)->delete();

            // Force delete from database
            $user->forceDelete();

            return redirect()->route('admin.users.index')
                ->with('success', 'User permanently deleted.');
        }

        // Otherwise, soft-deactivate (flag-based soft delete)
        $user->is_active = false;
        $user->is_deleted = true;
        $user->save();

        // Kill all active sessions for the deactivated user
        DB::table('sessions')->where('user_id', $user->id)->delete();

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
