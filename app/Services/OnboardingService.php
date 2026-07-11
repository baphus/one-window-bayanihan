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
     * Parse a persisted onboarding step key of the form "<pageIndex>:<stepIndex>".
     * Returns ['page' => int, 'step' => int] or null when the value is
     * missing, malformed, or negative. Legacy/corrupt values fall back to null
     * so callers treat them as "no saved progress".
     */
    public function parseStep(?string $step): ?array
    {
        if ($step === null || ! preg_match('/^(\d+):(\d+)$/', $step, $m)) {
            return null;
        }

        return ['page' => (int) $m[1], 'step' => (int) $m[2]];
    }

    /**
     * Get the current onboarding state for a user.
     * Returns an array with 'required', 'step', 'completed_at',
     * 'profile_incomplete', 'profile_completed_at', 'seen_page_guides',
     * and 'checklist_progress' keys.
     */
    public function getOnboardingState(User $user): array
    {
        return [
            'required' => $this->isOnboardingRequired($user),
            'step' => $user->onboarding_step,
            'completed_at' => $user->onboarding_completed_at?->toISOString(),
            'profile_incomplete' => $this->isProfileIncomplete($user),
            'profile_completed_at' => $user->profile_completed_at?->toISOString(),
            'seen_page_guides' => $user->seen_page_guides ?? [],
            'checklist_progress' => $user->checklist_progress ?? ['items' => [], 'dismissed_at' => null],
        ];
    }

    // ─────────────────────────────────────────────
    //  Page Guides (per-page contextual help)
    // ─────────────────────────────────────────────

    /**
     * Hard caps preventing unbounded growth of the UX-state JSON columns —
     * ~30 real route names and ~4 checklist ids exist; anything beyond the
     * cap is a client bug or abuse and is silently ignored.
     */
    public const MAX_SEEN_GUIDES = 100;

    public const MAX_CHECKLIST_ITEMS = 50;

    /**
     * Mark a page guide as seen for the user. Idempotent and capped.
     * $route is the Ziggy route name of the guided page.
     */
    public function markGuideSeen(User $user, string $route): void
    {
        $seen = $user->seen_page_guides ?? [];
        if (in_array($route, $seen, true) || count($seen) >= self::MAX_SEEN_GUIDES) {
            return;
        }

        $seen[] = $route;
        $user->update(['seen_page_guides' => array_values($seen)]);
    }

    // ─────────────────────────────────────────────
    //  Getting-Started Checklist
    // ─────────────────────────────────────────────

    /**
     * Mark a checklist item complete for the user. Idempotent — the first
     * completion timestamp wins. Never throws on persistence failure when
     * called via markChecklistItemQuietly().
     */
    public function markChecklistItem(User $user, string $itemId): void
    {
        $progress = $user->checklist_progress ?? ['items' => [], 'dismissed_at' => null];
        $items = $progress['items'] ?? [];

        if (isset($items[$itemId]) || count($items) >= self::MAX_CHECKLIST_ITEMS) {
            return;
        }

        $items[$itemId] = now()->toISOString();
        $progress['items'] = $items;
        $user->update(['checklist_progress' => $progress]);
    }

    /**
     * Best-effort checklist marking for use inside domain action success
     * paths — a marking failure must never break the primary action.
     */
    public function markChecklistItemQuietly(?User $user, string $itemId): void
    {
        if (! $user) {
            return;
        }

        try {
            $this->markChecklistItem($user, $itemId);
        } catch (\Throwable) {
            // Swallow — checklist marking is non-critical UX state.
        }
    }

    /**
     * Dismiss the getting-started checklist for the user.
     */
    public function dismissChecklist(User $user): void
    {
        $progress = $user->checklist_progress ?? ['items' => [], 'dismissed_at' => null];
        $progress['dismissed_at'] = now()->toISOString();
        $user->update(['checklist_progress' => $progress]);
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
