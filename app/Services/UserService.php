<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\AuditModule;
use App\Mail\EmailChangedNotification;
use App\Mail\UserInviteMail;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserInvite;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class UserService
{
    public function __construct(
        private readonly DefaultAgencyService $defaultAgencies,
    ) {}

    public function createUser(array $data, ?string $actorId = null): User
    {
        return DB::transaction(function () use ($data, $actorId) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => $data['role'],
                'agcy_id' => $this->resolveAgencyId($data),
                'email_verified_at' => now(),
                'is_active' => true,
            ]);

            AuditLog::create([
                'action' => AuditAction::CREATE->value,
                'module' => AuditModule::USER->value,
                'entity_id' => $user->id,
                'new_value' => ['name' => $user->name, 'email' => $user->email, 'role' => $user->role],
                'description' => 'User created directly by administrator',
                'user_id' => $actorId ?? auth()->id(),
                'timestamp' => now(),
            ]);

            return $user;
        });
    }

    public function pendingInviteFor(string $email): ?UserInvite
    {
        return UserInvite::where('email', $email)
            ->whereNull('consumed_at')
            ->whereNull('cancelled_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * @return array{invite: UserInvite, token: string}
     */
    public function inviteUser(array $data, string $actorId): array
    {
        $result = DB::transaction(function () use ($data, $actorId) {
            $token = Str::random(64);

            $invite = UserInvite::create([
                'email' => $data['email'],
                'role' => $data['role'],
                'agcy_id' => $this->resolveAgencyId($data),
                'token' => $token,
                'expires_at' => now()->addDays(7),
                'created_by' => $actorId,
            ]);

            return ['invite' => $invite, 'token' => $token];
        });

        Mail::to($data['email'])->queue(new UserInviteMail($result['invite'], $result['token']));

        return $result;
    }

    public function resendInvite(UserInvite $invite): UserInvite
    {
        $invite->update([
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
            'consumed_at' => null,
            'cancelled_at' => null,
        ]);

        Mail::to($invite->email)->queue(new UserInviteMail($invite, $invite->token));

        return $invite;
    }

    public function cancelInvite(UserInvite $invite): UserInvite
    {
        $invite->update(['cancelled_at' => now()]);

        return $invite;
    }

    public function updateUser(User $user, array $data, ?string $actorId = null): User
    {
        $emailChangeMail = null;

        $user = DB::transaction(function () use ($user, $data, $actorId, &$emailChangeMail) {
            if (! empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }

            $emailChanged = isset($data['email']) && $user->email !== $data['email'];

            if ($emailChanged) {
                // Admin bypass: admins can update email directly without OTP.
                $oldEmail = $user->email;
                $user->email_verified_at = now();
                $user->email = $data['email'];
                $user->save();

                AuditLog::create([
                    'action' => AuditAction::UPDATE->value,
                    'module' => AuditModule::USER->value,
                    // Account-credential change: must appear in the Security view
                    // (matches EmailChangeController).
                    'category' => AuditCategory::SECURITY,
                    'entity_id' => $user->id,
                    'old_value' => ['email' => $oldEmail],
                    'new_value' => ['email' => $data['email']],
                    'description' => 'Email changed from '.$oldEmail.' to '.$data['email'].' by administrator',
                    'user_id' => $actorId ?? auth()->id(),
                    'timestamp' => now(),
                ]);

                $emailChangeMail = [
                    'oldEmail' => $oldEmail,
                    'newEmail' => $data['email'],
                    'userName' => $user->name,
                ];
            }

            unset($data['email']);
            $user->update($data);

            return $user;
        });

        if ($emailChangeMail !== null) {
            Mail::to($emailChangeMail['oldEmail'])->queue(
                new EmailChangedNotification(
                    $emailChangeMail['oldEmail'],
                    $emailChangeMail['newEmail'],
                    $emailChangeMail['userName'],
                )
            );
        }

        return $user;
    }

    public function deactivate(User $user): User
    {
        return DB::transaction(function () use ($user) {
            $user->is_active = false;
            $user->is_deleted = true;
            $user->save();

            $this->invalidateSessions($user);

            return $user;
        });
    }

    public function forceDelete(User $user): void
    {
        DB::transaction(function () use ($user) {
            $this->invalidateSessions($user);
            $user->forceDelete();
        });
    }

    public function reactivate(User $user, ?string $actorId = null): User
    {
        return DB::transaction(function () use ($user, $actorId) {
            $user->is_active = true;
            $user->is_deleted = false;
            $user->deleted_at = null;
            $user->save();

            AuditLog::create([
                'action' => AuditAction::UPDATE->value,
                'module' => AuditModule::USER->value,
                'entity_id' => $user->id,
                'user_id' => $actorId ?? auth()->id(),
                'timestamp' => now(),
            ]);

            return $user;
        });
    }

    public function toggleVerification(User $user): User
    {
        $user->email_verified_at = $user->email_verified_at ? null : now();
        $user->save();

        return $user;
    }

    public function resetMfa(User $target, User $admin): User
    {
        return DB::transaction(function () use ($target, $admin) {
            $target->mfa_secret = null;
            $target->mfa_recovery_codes = null;
            $target->mfa_enabled_at = null;
            $target->save();

            // Kill active sessions for the target user to force re-login
            $this->invalidateSessions($target);

            SecurityAuditLogger::log('mfa', sprintf('%s admin-reset MFA for %s', $admin->name, $target->name));

            return $target;
        });
    }

    public function invalidateSessions(User $user): void
    {
        DB::table('sessions')->where('user_id', $user->id)->delete();
    }

    private function resolveAgencyId(array $data): ?string
    {
        $agcyId = $data['agcy_id'] ?? null;

        if (! $agcyId && ($data['role'] ?? null) === 'AGENCY') {
            $agcyId = $this->defaultAgencies->getDefaultAgency()?->id;
        }

        return $agcyId;
    }
}
