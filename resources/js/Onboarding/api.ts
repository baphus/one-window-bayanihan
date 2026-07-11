import { route } from 'ziggy-js';
import type { TourState } from '@/Onboarding/types';

/**
 * Read the XSRF token Laravel sets as a cookie on every response.
 */
function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function postJson(routeName: string, body?: Record<string, unknown>): Promise<void> {
    const response = await fetch(route(routeName), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: body ? JSON.stringify(body) : undefined,
    });

    if (!response.ok) {
        throw new Error(`Onboarding request failed: ${response.status}`);
    }
}

/**
 * Fetch the current onboarding state for the authenticated user.
 * GET /onboarding/state
 */
export async function getOnboardingState(): Promise<TourState> {
    const response = await fetch(route('onboarding.state'), {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (!response.ok) {
        throw new Error(`Onboarding state request failed: ${response.status}`);
    }

    return response.json() as Promise<TourState>;
}

/** Skip the onboarding tour. POST /onboarding/skip */
export function skipOnboarding(): Promise<void> {
    return postJson('onboarding.skip');
}

/** Mark onboarding as complete. POST /onboarding/complete */
export function completeOnboarding(): Promise<void> {
    return postJson('onboarding.complete');
}

/** Reset onboarding state so the user can replay the tour. POST /onboarding/replay */
export function replayOnboarding(): Promise<void> {
    return postJson('onboarding.replay');
}

/**
 * Persist the current tour position ("<pageIndex>:<stepIndex>").
 * POST /onboarding/step
 */
export function updateStep(step: string): Promise<void> {
    return postJson('onboarding.step', { step });
}

/** Mark a page guide as seen. POST /onboarding/guide-seen */
export function markGuideSeen(routeName: string): Promise<void> {
    return postJson('onboarding.guide-seen', { route: routeName });
}

/** Mark a getting-started checklist item complete. POST /onboarding/checklist/mark */
export function markChecklistItem(itemId: string): Promise<void> {
    return postJson('onboarding.checklist.mark', { item: itemId });
}

/** Dismiss the getting-started checklist. POST /onboarding/checklist/dismiss */
export function dismissChecklist(): Promise<void> {
    return postJson('onboarding.checklist.dismiss');
}
