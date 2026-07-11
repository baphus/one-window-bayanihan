import { TourConfig } from '../types';

/**
 * Agency Focal welcome tour — a short orientation pass. Depth lives in the
 * per-page guides behind the [?] button.
 */
export const agencyTour: TourConfig = {
    role: 'AGENCY',
    pages: [
        {
            route: 'dashboard',
            title: 'Dashboard',
            steps: [
                { element: '[data-tour="dashboard-header"]', title: 'Welcome to One Window Bayanihan', description: 'Your agency dashboard shows referral performance at a glance every time you sign in.', side: 'bottom' as const },
                { element: '[data-tour="dashboard-stats"]', title: 'Your Work Queue', description: 'The dispatch board surfaces referrals that need movement first — pending, processing, and overdue items. Click any tile to jump to the filtered list.', side: 'bottom' as const, align: 'start' as const },
                { element: '[data-tour="getting-started-checklist"]', title: 'Getting-Started Checklist', description: 'Your first key actions — set up your services, act on a referral, and configure your feedback questionnaire. Items tick off automatically.', side: 'bottom' as const },
                { element: '[data-tour="sidebar-nav"]', title: 'Everything Lives Here', description: 'Referred Cases, Overdue Referrals, Services, Feedbacks, SERVQUAL Config, and Reports — the sidebar is your map of the workspace.', side: 'right' as const },
                { element: '[data-tour="page-guide-button"]', title: 'Every Page Has a Guide', description: 'Click this ? button on any page for a quick walkthrough of what\'s on it. It pulses on pages you haven\'t explored yet.', side: 'bottom' as const, align: 'end' as const },
                { element: '[data-tour="sidebar-help"]', title: 'The Help Center', description: 'In-depth articles on referral processing, milestones, services, and feedback. It opens in a new tab so you never lose your place.', side: 'right' as const },
                { element: '[data-tour="chatbot-launcher"]', title: 'Ask Bayani Anytime', description: 'The AI assistant answers questions about referral statuses and procedures — right from any page.', side: 'left' as const },
            ],
        },
        {
            route: 'referrals.index',
            title: 'Referred Cases',
            steps: [
                { element: '[data-tour="referrals-header"]', title: 'Your Referral Workspace', description: 'Every referral sent to your agency arrives here. Use search and filters to find specific cases quickly.', side: 'bottom' as const, align: 'start' as const },
                { element: '[data-tour="referrals-table"]', title: 'You\'re All Set!', description: 'Accept or reject pending referrals, add milestones, and track progress to completion. Remember the ? button on each page for a deeper walkthrough.', side: 'top' as const },
            ],
        },
    ],
};
