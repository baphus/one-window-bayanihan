import { TourConfig } from '../types';

/**
 * System Admin welcome tour — a short orientation pass. Depth lives in the
 * per-page guides behind the [?] button; the admin sidebar alone has ~20
 * destinations, so this tour deliberately stays at the map level.
 */
export const adminTour: TourConfig = {
    role: 'ADMIN',
    pages: [
        {
            route: 'dashboard',
            title: 'Dashboard',
            steps: [
                { element: '[data-tour="dashboard-header"]', title: 'Welcome to One Window Bayanihan', description: 'Your admin overview — system-wide queues, referral movement, and administrative activity in one place.', side: 'bottom' as const },
                { element: '[data-tour="dashboard-stats"]', title: 'System Pulse', description: 'Total cases, referrals, active agencies, and overdue referrals across the whole system.', side: 'bottom' as const },
                { element: '[data-tour="dashboard-work-queues"]', title: 'Work Queues', description: 'Where admins should look first: open cases, pending and processing referrals, compliance items, and overdue referrals. Each tile links to the filtered view.', side: 'bottom' as const },
                { element: '[data-tour="dashboard-admin-tools"]', title: 'Admin Tools', description: 'Quick access to Users, Agencies, Services, Reports, Audit Logs, and Sessions — the day-to-day management surfaces.', side: 'left' as const },
                { element: '[data-tour="getting-started-checklist"]', title: 'Getting-Started Checklist', description: 'Your first key actions — add a user, register an agency, review System Settings. Items tick off automatically as you complete them.', side: 'bottom' as const },
                { element: '[data-tour="sidebar-nav"]', title: 'The Full Map', description: 'Everything is in the sidebar: case operations, agency management, system health logs, taxonomies, data export, maintenance, and security settings.', side: 'right' as const },
                { element: '[data-tour="page-guide-button"]', title: 'Every Page Has a Guide', description: 'With ~20 admin pages, this ? button is your friend — click it on any page for a quick walkthrough. It pulses on pages you haven\'t explored yet.', side: 'bottom' as const, align: 'end' as const },
                { element: '[data-tour="sidebar-help"]', title: 'The Help Center', description: 'In-depth articles including admin guides — system settings, user management, taxonomies, and monitoring. It opens in a new tab.', side: 'right' as const },
                { element: '[data-tour="chatbot-launcher"]', title: 'Ask Bayani Anytime', description: 'The AI assistant answers questions about system behavior and procedures — right from any page.', side: 'left' as const },
            ],
        },
        {
            route: 'admin.users.index',
            title: 'Users',
            steps: [
                { element: '[data-tour="users-header"]', title: 'User Management', description: 'Create accounts, assign roles (Admin, Case Manager, Agency Focal), and control access — usually the first place a new admin works.', side: 'bottom' as const },
                { element: '[data-tour="users-table"]', title: 'You\'re All Set!', description: 'Manage accounts, reset passwords, and deactivate users from this directory. Remember the ? button on each page for a deeper walkthrough.', side: 'top' as const },
            ],
        },
    ],
};
