<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

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
     * Get the current onboarding state for a user.
     * Returns an array with the 'checklist_progress' key.
     */
    public function getOnboardingState(User $user): array
    {
        return [
            'checklist_progress' => $user->checklist_progress ?? ['items' => [], 'dismissed_at' => null],
        ];
    }

    // ─────────────────────────────────────────────
    //  Getting-Started Checklist
    // ─────────────────────────────────────────────

    /**
     * Hard cap preventing unbounded growth of the checklist JSON column —
     * ~4 checklist ids exist; anything beyond the cap is a client bug or
     * abuse and is silently ignored.
     */
    public const MAX_CHECKLIST_ITEMS = 50;

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
        } catch (\Throwable $e) {
            // Checklist marking is non-critical UX state — never break the
            // primary action, but keep a debug trail for diagnosis.
            Log::debug('OnboardingService: checklist marking failed', [
                'user_id' => $user->getKey(),
                'item' => $itemId,
                'exception' => $e->getMessage(),
            ]);
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
}
