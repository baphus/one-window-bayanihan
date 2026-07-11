import { TourConfig } from '../types';

/**
 * Case Manager welcome tour — a short orientation pass. Depth lives in the
 * per-page guides behind the [?] button; this tour's job is to show where
 * everything (including help) lives.
 */
export const caseManagerTour: TourConfig = {
    role: 'CASE_MANAGER',
    pages: [
        {
            route: 'dashboard',
            title: 'Dashboard',
            steps: [
                { element: '[data-tour="dashboard-header"]', title: 'Welcome to One Window Bayanihan', description: 'This is your daily starting point — today\'s work queue with the cases and referrals that need your attention first.', side: 'bottom' as const },
                { element: '[data-tour="dashboard-stats"]', title: 'Your Work Queue', description: 'The dispatch board surfaces new referrals, pending items, overdue referrals, and aging cases. Click any tile to jump straight to the filtered list.', side: 'bottom' as const },
                { element: '[data-tour="getting-started-checklist"]', title: 'Getting-Started Checklist', description: 'Your first key actions live here — create your first case, send a referral, and explore Reports. Items tick off automatically as you complete them.', side: 'bottom' as const },
                { element: '[data-tour="sidebar-nav"]', title: 'Everything Lives Here', description: 'Cases, Drafts, Clients, Referrals, Overdue Referrals, Stakeholders, Reports, and Audit Logs — the sidebar is your map of the whole workspace.', side: 'right' as const },
                { element: '[data-tour="page-guide-button"]', title: 'Every Page Has a Guide', description: 'Click this ? button on any page for a quick walkthrough of what\'s on it. It pulses on pages you haven\'t explored yet.', side: 'bottom' as const, align: 'end' as const },
                { element: '[data-tour="chatbot-launcher"]', title: 'Ask Bayani Anytime', description: 'The AI assistant answers questions about procedures, statuses, and where to find things. The Help Center in the sidebar has in-depth articles too.', side: 'left' as const },
            ],
        },
        {
            route: 'cases.index',
            title: 'Cases',
            steps: [
                { element: '[data-tour="cases-header"]', title: 'Your Case Workspace', description: 'All cases assigned to you live here. Create new cases, export data, and track every case from intake to resolution.', side: 'bottom' as const },
                { element: '[data-tour="cases-filter"]', title: 'Search & Filters', description: 'Narrow cases by status, client type, vulnerability, category, or agency — or search by tracking ID and client name.', side: 'bottom' as const },
                { element: '[data-tour="cases-table"]', title: 'You\'re All Set!', description: 'Click any case to see its full record. That\'s the essentials — remember the ? button on each page whenever you want a deeper walkthrough.', side: 'top' as const },
            ],
        },
    ],
};
