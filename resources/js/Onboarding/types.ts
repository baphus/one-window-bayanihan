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
 * Current state of the onboarding tour.
 */
export interface TourState {
    /** Whether onboarding is required */
    required: boolean;
    /** Current step identifier (null if not started) */
    step: string | null;
    /** When onboarding was completed (null if not complete) */
    completed_at: string | null;
}
