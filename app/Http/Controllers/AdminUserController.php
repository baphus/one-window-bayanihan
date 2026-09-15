<?php

namespace App\Http\Controllers;

use App\Helpers\CacheHelper;
use App\Models\Agency;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserInvite;
use App\Services\OtpService;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AdminUserController extends Controller
{
    public function __construct(
        private readonly OtpService $otpService,
        private readonly UserService $users,
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
        } else {
            // Exclude OFW accounts from the default staff user list
            $query->where('role', '!=', 'OFW');
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

        $pendingInvites = UserInvite::with('agency')
            ->whereNull('consumed_at')
            ->whereNull('cancelled_at')
            ->where('expires_at', '>', now())
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Admin/User/Index', [
            'users' => $users,
            'filters' => $filters,
            'pendingInvites' => $pendingInvites,
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
        ]);

        $this->users->createUser($validated, $request->user()->id);

        return back()->with('success', 'User created successfully.');
    }

    public function invite(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|unique:users,email',
            'role' => 'required|in:ADMIN,AGENCY,CASE_MANAGER',
            'agcy_id' => 'nullable|exists:agencies,id',
        ]);

        // Also check for existing pending invite
        if ($this->users->pendingInviteFor($validated['email'])) {
            return back()->with('warning', 'An invite was already sent to this email. Use the Resend action to send again.');
        }

        $this->users->inviteUser($validated, $request->user()->id);

        return back()->with('success', 'Invitation sent to '.$validated['email']);
    }

    public function resendInvite(string $inviteId)
    {
        $invite = UserInvite::findOrFail($inviteId);

        if ($invite->isConsumed() || $invite->isCancelled()) {
            return back()->with('error', 'This invite can no longer be resent.');
        }

        // Refresh token and expiry
        $this->users->resendInvite($invite);

        return back()->with('success', 'Invitation resent to '.$invite->email);
    }

    public function cancelInvite(string $inviteId)
    {
        $invite = UserInvite::findOrFail($inviteId);

        if ($invite->isConsumed()) {
            return back()->with('error', 'This invite has already been used.');
        }

        $this->users->cancelInvite($invite);

        return back()->with('success', 'Invitation cancelled.');
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
            $updateData['password'] = $request->input('password');
        }

        $this->users->updateUser($user, $updateData, $request->user()->id);

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
            $this->users->forceDelete($user);

            return redirect()->route('admin.users.index')
                ->with('success', 'User permanently deleted.');
        }

        // Otherwise, soft-deactivate (flag-based soft delete)
        $this->users->deactivate($user);

        return redirect()->route('admin.users.index')
            ->with('success', 'User deactivated successfully.');
    }

    public function reactivate(string $id)
    {
        $user = User::withTrashed()->findOrFail($id);

        if ($user->is_active && ! $user->is_deleted) {
            return redirect()->route('admin.users.index')
                ->with('error', 'User is already active.');
        }

        $this->users->reactivate($user, auth()->id());

        return redirect()->route('admin.users.index')
            ->with('success', 'User reactivated successfully.');
    }

    public function verify(User $user)
    {
        if ($user->is_deleted || ! $user->is_active) {
            return redirect()->back()->with('error', 'Cannot verify inactive or deleted users.');
        }

        $this->users->toggleVerification($user);

        return redirect()->back()->with('success', 'User verification status updated.');
    }

    public function resetMfa(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Hash::check($request->password, $request->user()->password)) {
            throw ValidationException::withMessages([
                'password' => 'The password is incorrect.',
            ]);
        }

        $admin = $request->user();

        $this->users->resetMfa($user, $admin);

        return back()->with('success', 'MFA has been reset for this user.');
    }
}
