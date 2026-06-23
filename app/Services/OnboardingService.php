<?php

namespace App\Services;

use App\Models\User;

class OnboardingService
{
    /**
     * Check if onboarding is required for a user.
     * Returns true if onboarding_completed_at is null.
     */
    public function isOnboardingRequired(User $user): bool
    {
        return is_null($user->onboarding_completed_at);
    }

    /**
     * Mark onboarding as complete.
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
     * Skip onboarding (same as mark complete).
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
     * Returns an array with 'required', 'step', and 'completed_at' keys.
     */
    public function getOnboardingState(User $user): array
    {
        return [
            'required' => $this->isOnboardingRequired($user),
            'step' => $user->onboarding_step,
            'completed_at' => $user->onboarding_completed_at?->toISOString(),
        ];
    }

    /**
     * Reset onboarding state (for replay).
     * Sets both onboarding_completed_at and onboarding_step to null.
     */
    public function resetOnboarding(User $user): void
    {
        $user->update([
            'onboarding_completed_at' => null,
            'onboarding_step' => null,
        ]);
    }
}
