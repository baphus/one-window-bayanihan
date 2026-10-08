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
 * Current onboarding state shared via Inertia props.
 * (Tour fields are gone; the server payload still carries this shape.)
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
