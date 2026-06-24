import { router } from '@inertiajs/react';
import type { TourState } from '@/Onboarding/types';

/**
 * Fetch the current onboarding state for the authenticated user.
 * GET /onboarding/state
 */
export function getOnboardingState(): Promise<TourState> {
    return new Promise<TourState>((resolve, reject) => {
        router.get(route('onboarding.state'), {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onSuccess: (page) => {
                resolve(page.props as unknown as TourState);
            },
            onError: (errors) => {
                reject(errors);
            },
        });
    });
}

/**
 * Skip the onboarding tour for the authenticated user.
 * POST /onboarding/skip
 */
export function skipOnboarding(): Promise<void> {
    return new Promise<void>((resolve, reject) => {
        router.post(route('onboarding.skip'), {}, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => resolve(),
            onError: (errors) => reject(errors),
        });
    });
}

/**
 * Mark onboarding as complete for the authenticated user.
 * POST /onboarding/complete
 */
export function completeOnboarding(): Promise<void> {
    return new Promise<void>((resolve, reject) => {
        router.post(route('onboarding.complete'), {}, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => resolve(),
            onError: (errors) => reject(errors),
        });
    });
}

/**
 * Reset onboarding state so the user can replay the tour.
 * POST /onboarding/replay
 */
export function replayOnboarding(): Promise<void> {
    return new Promise<void>((resolve, reject) => {
        router.post(route('onboarding.replay'), {}, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => resolve(),
            onError: (errors) => reject(errors),
        });
    });
}

/**
 * Track the current step during the onboarding tour.
 * POST /onboarding/step
 */
export function updateStep(step: string): Promise<void> {
    return new Promise<void>((resolve, reject) => {
        router.post(route('onboarding.step'), { step }, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => resolve(),
            onError: (errors) => reject(errors),
        });
    });
}
