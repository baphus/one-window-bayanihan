/**
 * Getting-started checklist definitions per role.
 *
 * `marking` describes how an item completes:
 * - 'action': marked server-side when the domain action first succeeds
 *   (see OnboardingService::markChecklistItemQuietly call sites).
 * - 'visit': marked client-side when the user loads the target route
 *   (see useChecklistVisitTracking).
 */
export interface ChecklistItemDef {
    /** Stable id persisted in users.checklist_progress */
    id: string;
    /** Card label */
    label: string;
    /** Material Symbols icon name */
    icon: string;
    /** Ziggy route the item links to (where the action happens) */
    route: string;
    /**
     * Optional query-string params appended to the link. Lets an item land on
     * a page with the target action already open (e.g. `?open=create` so the
     * admin pages auto-open their "new record" modal).
     */
    params?: Record<string, string>;
    /**
     * Open the link in a new browser tab instead of navigating in place.
     * Used for the Help Center item, which points at an external-facing page
     * the user should be able to browse without leaving the dashboard.
     */
    newTab?: boolean;
    /** How the item is marked complete */
    marking: 'action' | 'visit';
}

export const checklistByRole: Record<string, ChecklistItemDef[]> = {
    CASE_MANAGER: [
        { id: 'create-first-case', label: 'Create your first case', icon: 'create_new_folder', route: 'cases.create', marking: 'action' },
        { id: 'send-first-referral', label: 'Send your first referral', icon: 'send', route: 'referrals.create', marking: 'action' },
        { id: 'visit-reports', label: 'Explore Reports & Analytics', icon: 'monitoring', route: 'reports.index', marking: 'visit' },
        { id: 'open-help-center', label: 'Browse the Help Center', icon: 'help', route: 'helpdesk.index', newTab: true, marking: 'visit' },
    ],
    AGENCY: [
        { id: 'add-first-service', label: 'Set up your first service', icon: 'medical_services', route: 'agency.services.index', params: { open: 'create' }, marking: 'action' },
        { id: 'act-on-referral', label: 'Act on a referral', icon: 'assignment_turned_in', route: 'referrals.index', marking: 'action' },
        { id: 'configure-servqual', label: 'Configure your feedback questionnaire', icon: 'tune', route: 'survey.forms.index', marking: 'action' },
        { id: 'open-help-center', label: 'Browse the Help Center', icon: 'help', route: 'helpdesk.index', newTab: true, marking: 'visit' },
    ],
    ADMIN: [
        { id: 'add-first-user', label: 'Add a user account', icon: 'person_add', route: 'admin.users.index', params: { open: 'create' }, marking: 'action' },
        { id: 'register-agency', label: 'Register a partner agency', icon: 'account_balance', route: 'admin.agencies.index', params: { open: 'create' }, marking: 'action' },
        { id: 'review-system-settings', label: 'Review System Settings', icon: 'settings', route: 'admin.system-settings.index', marking: 'visit' },
        { id: 'visit-reports', label: 'Explore Reports & Analytics', icon: 'monitoring', route: 'reports.index', marking: 'visit' },
    ],
};

/** Get the checklist definitions for a role (empty for unknown roles). */
export function getChecklist(role: string | undefined | null): ChecklistItemDef[] {
    return (role && checklistByRole[role]) || [];
}
