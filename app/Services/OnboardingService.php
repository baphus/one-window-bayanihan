<?php

namespace App\Services;

use App\Models\User;

class OnboardingService
{
    /**
     * The profile fields considered "necessary" for a complete profile.
     * A user's profile is deemed complete when all these fields are non-empty.
     */
    public const NECESSARY_PROFILE_FIELDS = [
        'position',
        'department',
        'office_location',
        'contact_number',
        'bio',
        'timezone',
    ];

    /**
     * Check if onboarding tour is required for a user.
     * Returns true if onboarding_completed_at is null.
     */
    public function isOnboardingRequired(User $user): bool
    {
        return is_null($user->onboarding_completed_at);
    }

    /**
     * Mark onboarding tour as complete.
     * Sets onboarding_completed_at to now, onboarding_step to null.
     */
    public function markOnboardingComplete(User $user): void
    {
        $user->update([
            'onboarding_completed_at' => now(),
            'onboarding_step' => null,
        ]);
    }

    /**
     * Skip onboarding tour (same as mark complete).
     */
    public function skipOnboarding(User $user): void
    {
        $this->markOnboardingComplete($user);
    }

    /**
     * Update the current onboarding step for a user.
     * Pass null to reset the step counter.
     */
    public function updateStep(User $user, ?string $step): void
    {
        $user->update([
            'onboarding_step' => $step,
        ]);
    }

    /**
     * Get the current onboarding state for a user.
     * Returns an array with 'required', 'step', 'completed_at',
     * 'profile_incomplete', and 'profile_completed_at' keys.
     */
    public function getOnboardingState(User $user): array
    {
        return [
            'required' => $this->isOnboardingRequired($user),
            'step' => $user->onboarding_step,
            'completed_at' => $user->onboarding_completed_at?->toISOString(),
            'profile_incomplete' => $this->isProfileIncomplete($user),
            'profile_completed_at' => $user->profile_completed_at?->toISOString(),
        ];
    }

    /**
     * Reset onboarding tour state (for replay).
     * Sets both onboarding_completed_at and onboarding_step to null.
     */
    public function resetOnboarding(User $user): void
    {
        $user->update([
            'onboarding_completed_at' => null,
            'onboarding_step' => null,
        ]);
    }

    // ─────────────────────────────────────────────
    //  Profile Completion (first-time info prompt)
    // ─────────────────────────────────────────────

    /**
     * Check if the user's profile is incomplete.
     * Returns true if profile_completed_at is null AND at least one
     * necessary field is empty. If all fields are filled but
     * profile_completed_at is null, auto-marks as complete.
     */
    public function isProfileIncomplete(User $user): bool
    {
        // If already explicitly marked complete, profile is not incomplete
        if (! is_null($user->profile_completed_at)) {
            return false;
        }

        // Check if all necessary fields are filled
        $hasEmptyField = false;
        foreach (self::NECESSARY_PROFILE_FIELDS as $field) {
            $value = $user->$field;
            if (is_null($value) || $value === '' || $value === []) {
                $hasEmptyField = true;
                break;
            }
        }

        // Also check emergency_contact (JSON object) separately
        if (! $hasEmptyField) {
            $ec = $user->emergency_contact;
            if (is_null($ec) || $ec === [] || (is_array($ec) && empty(array_filter($ec)))) {
                $hasEmptyField = true;
            }
        }

        // If all fields are filled but profile_completed_at is null, auto-mark complete
        if (! $hasEmptyField) {
            $this->markProfileComplete($user);

            return false;
        }

        return true;
    }

    /**
     * Mark the user's profile as complete.
     * Sets profile_completed_at to now().
     */
    public function markProfileComplete(User $user): void
    {
        $user->update([
            'profile_completed_at' => now(),
        ]);
    }

    /**
     * Skip the profile info prompt (marks complete without filling).
     */
    public function skipProfile(User $user): void
    {
        $this->markProfileComplete($user);
    }
}
