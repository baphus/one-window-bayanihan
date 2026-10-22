<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            // Deliberately unscoped: `users_email_unique` is a hard unique index
            // on the column, so a deactivated or soft-deleted row still owns its
            // address. Narrowing this rule to live accounts would not free the
            // address — it would only move the collision to
            // RegisterViaInviteController::store(), where `User::create` raises
            // a unique violation and the invited person gets a 500 instead of an
            // account. The address is genuinely reusable only once the row is
            // gone (UserService::forceDelete()).
            'email' => ['required', 'email', Rule::unique('users', 'email')],
            'role' => ['required', 'in:'.implode(',', UserRole::staffValues())],
            'agcy_id' => ['nullable', 'exists:agencies,id'],
        ];
    }

    /**
     * Replace the framework's generic "has already been taken" with the way out.
     *
     * The uniqueness failure is only actionable once the admin knows which of
     * the two states produced it: an active colleague, who must be dealt with
     * on the user list, or one of their own deactivated accounts, which
     * reactivates in a click and cannot be re-onboarded by inviting. Both
     * routes (Reactivate, and Delete a second time to purge and re-invite)
     * already exist, so the message only has to name them.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => $this->duplicateEmailMessage(),
        ];
    }

    private function duplicateEmailMessage(): string
    {
        $email = (string) $this->input('email');

        $existing = User::withTrashed()->where('email', $email)->first();

        if ($existing === null) {
            return 'The email address '.$email.' has already been taken.';
        }

        if (! $existing->is_active || $existing->is_deleted || $existing->trashed()) {
            return 'An account already exists for '.$email.' but it is deactivated. Reactivate that user instead of sending a new invitation, or permanently delete the account first to free the address.';
        }

        return 'An active account already uses '.$email.'. Update that user instead of sending a new invitation.';
    }
}
