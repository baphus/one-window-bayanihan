import { route } from 'ziggy-js';

async function postJson(routeName: string, body?: Record<string, unknown>): Promise<void> {
    const response = await fetch(route(routeName), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: body ? JSON.stringify(body) : undefined,
    });

    if (!response.ok) {
        throw new Error(`Onboarding request failed: ${response.status}`);
    }
}

/** Mark a getting-started checklist item complete. POST /onboarding/checklist/mark */
export function markChecklistItem(itemId: string): Promise<void> {
    return postJson('onboarding.checklist.mark', { item: itemId });
}

/** Dismiss the getting-started checklist. POST /onboarding/checklist/dismiss */
export function dismissChecklist(): Promise<void> {
    return postJson('onboarding.checklist.dismiss');
}
