/**
 * A single step in a tour.
 * The `element` is a CSS selector matching a `data-tour="*"` attribute.
 */
export interface TourStep {
    /** CSS selector for the target element (data-tour attribute) */
    element: string;
    /** Popover content */
    title: string;
    description: string;
    /** Popover positioning relative to the element */
    side?: 'top' | 'bottom' | 'left' | 'right';
    align?: 'start' | 'center' | 'end';
}

/**
 * A page within a role's tour flow.
 * The tour navigates through pages in order.
 */
export interface TourPage {
    /** Route name for Inertia navigation */
    route: string;
    /** Display title for progress indicator */
    title: string;
    /** Steps on this page */
    steps: TourStep[];
}

/**
 * Complete tour configuration for a role.
 */
export interface TourConfig {
    /** Role this config applies to */
    role: 'CASE_MANAGER' | 'AGENCY' | 'ADMIN';
    /** Pages in order the tour will visit */
    pages: TourPage[];
}

/**
 * A per-page contextual guide, launched from the [?] button.
 * Registered in the guide registry keyed by Ziggy route name.
 */
export interface PageGuide {
    /** Display title of the guide */
    title: string;
    /** Steps driven on the page */
    steps: TourStep[];
    /** Helpdesk article slug for the final "Read more" link */
    helpdeskSlug?: string;
}

/**
 * Getting-started checklist progress as persisted on the user.
 */
export interface ChecklistProgress {
    /** Completed item ids mapped to completion timestamps */
    items: Record<string, string>;
    /** When the user dismissed the checklist (null if visible) */
    dismissed_at: string | null;
}

/**
 * Current state of the onboarding tour.
 */
export interface TourState {
    /** Whether onboarding is required */
    required: boolean;
    /** Current step identifier "<pageIndex>:<stepIndex>" (null if not started) */
    step: string | null;
    /** When onboarding was completed (null if not complete) */
    completed_at: string | null;
    /** Route names whose page guides the user has seen */
    seen_page_guides?: string[];
    /** Getting-started checklist progress */
    checklist_progress?: ChecklistProgress;
}

/**
 * Parse a persisted step key "<pageIndex>:<stepIndex>".
 * Returns null for missing, malformed, or out-of-range values so callers
 * treat corrupt/legacy keys as "no saved progress".
 */
export function parseStepKey(step: string | null | undefined, config?: TourConfig | null): { page: number; step: number } | null {
    if (!step) return null;
    const match = /^(\d+):(\d+)$/.exec(step);
    if (!match) return null;
    const page = parseInt(match[1], 10);
    const stepIndex = parseInt(match[2], 10);
    if (config) {
        if (page >= config.pages.length) return null;
        if (stepIndex >= config.pages[page].steps.length) return null;
    }
    return { page, step: stepIndex };
}
