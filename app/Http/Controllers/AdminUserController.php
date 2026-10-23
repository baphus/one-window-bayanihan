<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Helpers\CacheHelper;
use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Requests\InviteAdminUserRequest;
use App\Http\Requests\StoreAdminUserRequest;
use App\Http\Requests\UpdateAdminUserRequest;
use App\Models\Agency;
use App\Models\User;
use App\Models\UserInvite;
use App\Services\OtpService;
use App\Services\UserService;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use LogicException;

class AdminUserController extends Controller
{
    use ResolvesPerPage;

    public function __construct(
        private readonly OtpService $otpService,
        private readonly UserService $users,
    ) {}

    public function index(Request $request)
    {
        $filters = $request->only(['search', 'role', 'status', 'agcy_id', 'show_deleted']);

        $query = User::with('agency');

        // By default exclude soft-deleted users unless show_deleted filter is active.
        // Deactivation is a real soft delete (UserService::deactivate), so the
        // global scope hides those rows on its own — the "including deleted"
        // view has to opt back in for the Reactivate action to be reachable.
        if (! $request->boolean('show_deleted')) {
            $query->where('is_deleted', false);
        } else {
            $query->withTrashed();
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
            $query->where('role', '!=', UserRole::OFW->value);
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

        $perPage = $this->perPage($request, 15);
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
                'case_managers' => User::where('role', UserRole::CASE_MANAGER->value)->count(),
                'agency_focals' => User::where('role', UserRole::AGENCY->value)->count(),
                'admins' => User::where('role', UserRole::ADMIN->value)->count(),
            ]),
        ]);
    }

    public function show(string $id)
    {
        // withTrashed(): the users list can surface deactivated accounts (see
        // index's show_deleted view), and their detail page has to open.
        $user = User::with('agency')->withTrashed()->findOrFail($id);

        return Inertia::render('Admin/User/Show', ['user' => $user]);
    }

    public function store(StoreAdminUserRequest $request)
    {
        $validated = $request->validated();

        $this->users->createUser($validated, $request->user()->id);

        return back()->with('success', 'User created successfully.');
    }

    public function invite(InviteAdminUserRequest $request)
    {
        $validated = $request->validated();

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
        try {
            $this->users->resendInvite($invite);
        } catch (LogicException) {
            // A registration consumed (or an admin cancelled) the invite after
            // the check above; the service re-checks under a row lock.
            return back()->with('error', 'This invite can no longer be resent.');
        }

        return back()->with('success', 'Invitation resent to '.$invite->email);
    }

    public function cancelInvite(string $inviteId)
    {
        $invite = UserInvite::findOrFail($inviteId);

        if ($invite->isConsumed()) {
            return back()->with('error', 'This invite has already been used.');
        }

        try {
            $this->users->cancelInvite($invite);
        } catch (LogicException) {
            // A registration consumed the invite after the check above; the
            // service re-checks under a row lock.
            return back()->with('error', 'This invite has already been used.');
        }

        return back()->with('success', 'Invitation cancelled.');
    }

    public function update(UpdateAdminUserRequest $request, string $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validated();

        $updateData = $validated;

        $emailChanged = $this->users->willChangeEmail($user, $validated);
        $passwordChanged = $request->filled('password');

        // Only the credential branches are re-authenticated and metered. A plain
        // profile edit (name, role, active flag, position...) carries no secret
        // and must not spend the admin's password-confirmation budget.
        if ($emailChanged || $passwordChanged) {
            $this->throttleCredentialUpdate($request);

            if ($passwordChanged) {
                // Mirrors resetMfa(): setting somebody's password is a full
                // account takeover, so the admin re-enters their OWN password to
                // do it. Without this, a hijacked admin session could seize every
                // account in the directory — other admins included — and the
                // current_password rule is guessable, hence the throttle above.
                $request->validate([
                    'password' => ['string', Password::min(8)->mixedCase()->numbers()->symbols()],
                    'current_password' => ['required', 'string', 'current_password'],
                ]);
                $updateData['password'] = $request->input('password');
            }
        }

        // The demotion/deactivation branch needs the same locked evaluation as
        // destroy(): the guard and the write have to run against the same
        // committed state, or two concurrent PATCHes could both see an active
        // colleague that the other one is in the middle of demoting. The
        // transaction spans the write, so `lockOtherActiveAdmins`' row locks
        // are only released after `updateUser` commits.
        $outcome = DB::transaction(function () use ($user, $updateData, $request, $id) {
            if ($this->updateWithdrawsLastActiveAdmin($user, $updateData)
                && ! $this->lockOtherActiveAdmins($user)) {
                return ['error' => 'Cannot demote or deactivate the last active administrator.'];
            }

            $this->users->updateUser(
                $user,
                $updateData,
                $request->user()->id,
                $request->session()->get('verified_new_email_admin_'.$id),
            );

            return ['success' => 'User updated successfully.'];
        });

        if (isset($outcome['error'])) {
            return redirect()->route('admin.users.index')
                ->with('error', $outcome['error']);
        }

        if ($emailChanged) {
            // The OTP proof is single-use. Spending it here stops a flag left
            // sitting in the session from authorising a later email change.
            $request->session()->forget('verified_new_email_admin_'.$id);
        }

        return redirect()->route('admin.users.index')
            ->with('success', $outcome['success']);
    }

    /**
     * Cap how often one admin session may attempt a credential change here.
     *
     * `throttle:password-confirm` deliberately stays off this route: that
     * limiter is five a minute keyed on the admin's id and is already shared
     * with reset-MFA, the email-change OTP steps and the self-service
     * password/MFA endpoints, while `users.update` also carries every ordinary
     * profile edit — the admin users modal PATCHes the whole record once per
     * Save, so an admin working down a list of accounts would burn that shared
     * budget on names and roles and start seeing 429s on legitimate work. The
     * legitimate three-step email change alone already spends three of the five.
     * Metering here instead keeps the brute-force ceiling on the password oracle
     * (where `current_password` is checked) without taxing normal admin traffic.
     *
     * Same inline approach as OfwRegistrationController::store(), which reuses a
     * named limiter in the controller for the same reason.
     */
    private function throttleCredentialUpdate(Request $request): void
    {
        $key = 'password-confirm:admin-user-update|'.($request->user()?->id ?? $request->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw new ThrottleRequestsException(
                'Too many credential changes. Please try again in '.max(1, (int) ceil($seconds / 60)).' minute(s).',
                null,
                ['Retry-After' => $seconds],
            );
        }

        RateLimiter::hit($key, 60);
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
        // withTrashed(): deleting an already-deactivated account is the
        // documented way to free its email address (see the duplicate-email
        // message on InviteAdminUserRequest), and that second Delete only
        // resolves if the soft-deleted row is in scope here.
        $user = User::withTrashed()->findOrFail($id);

        // Prevent deleting yourself
        if ($user->id === request()->user()->id) {
            return redirect()->route('admin.users.index')
                ->with('error', 'You cannot delete your own account.');
        }

        // The guard and the delete have to run against the same committed
        // state: two concurrent admin deletions evaluating an unlocked count
        // could both pass and both land. The transaction serialises them (see
        // lockOtherActiveAdmins), and the delete then runs inside it, so a
        // second delete re-evaluates the guard only after the first commits.
        $outcome = DB::transaction(function () use ($user) {
            // Prevent deleting the last admin
            if ($user->isAdmin() && ! $this->lockOtherActiveAdmins($user)) {
                return ['error' => 'Cannot delete the only admin user.'];
            }

            // If already inactive/deleted, permanently remove from database
            if (! $user->is_active || $user->is_deleted) {
                $this->users->forceDelete($user);

                return ['success' => 'User permanently deleted.'];
            }

            // Otherwise, soft-deactivate (flag-based soft delete)
            $this->users->deactivate($user);

            return ['success' => 'User deactivated successfully.'];
        });

        if (isset($outcome['error'])) {
            return redirect()->route('admin.users.index')
                ->with('error', $outcome['error']);
        }

        return redirect()->route('admin.users.index')
            ->with('success', $outcome['success']);
    }

    /**
     * Whether an update payload would withdraw $user from the active-admin pool.
     *
     * The admin users form always submits `role` and may submit `is_active`,
     * so the update route can do to an administrator what destroy() does:
     * demote them to another role, or flip them inactive, leaving the app
     * without any usable administrator when no other active admin exists.
     * This check is advisory — it only decides whether the caller must take
     * row locks; the authoritative count happens under `lockOtherActiveAdmins()`'s
     * `FOR UPDATE` read inside the caller's transaction.
     *
     * Active is the app's definition (`CheckUserActive`): NULL on is_active
     * still counts as active, so only an explicitly false flag means inactive.
     *
     * @param  array<string, mixed>  $data
     */
    private function updateWithdrawsLastActiveAdmin(User $user, array $data): bool
    {
        if (! $user->isAdmin() || $user->is_active === false) {
            return false;
        }

        $demotes = ($data['role'] ?? null) !== null
            && $data['role'] !== UserRole::ADMIN->value;

        $deactivates = ($data['is_active'] ?? null) === false;

        return $demotes || $deactivates;
    }

    /**
     * Lock every active-admin row and report whether one other than $user exists.
     *
     * `CheckUserActive` is the app's definition of an active account: inactive
     * means `is_active === false` or `is_deleted === true`, with NULL on either
     * column counting as active. The same predicate is applied here so the
     * guard counts only administrators that an admin could actually act as —
     * an inactive or soft-deleted admin provides no usable account and must
     * not pad the count.
     *
     * The rows are read under `FOR UPDATE` inside the caller's transaction,
     * so a second concurrent admin deletion blocks on this read until the
     * first commits and its new `is_active`/`is_deleted` values are visible;
     * Postgres then re-checks the predicate against the committed rows before
     * returning them, so the guard is always evaluated on current state.
     */
    private function lockOtherActiveAdmins(User $user): bool
    {
        $activeAdmins = User::query()
            ->where('role', UserRole::ADMIN->value)
            ->where(fn ($q) => $q->whereNull('is_active')->orWhere('is_active', true))
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', false))
            ->lockForUpdate()
            ->get(['id']);

        return $activeAdmins->contains(fn (User $admin) => $admin->id !== $user->id);
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
