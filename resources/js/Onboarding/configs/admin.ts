import { TourConfig } from '../types';

export const adminTour: TourConfig = {
    role: 'ADMIN',
    pages: [
        {
            route: 'dashboard',
            title: 'Dashboard',
            steps: [
                { element: '[data-tour="dashboard-header"]', title: 'Welcome', description: 'Your admin dashboard with system-wide overview and monitoring capabilities.', side: 'bottom' as const },
                { element: '[data-tour="dashboard-admin-system"]', title: 'System Health', description: 'Monitor overall system status, active alerts, and health checks across services.', side: 'bottom' as const },
                { element: '[data-tour="admin-recent-cases"]', title: 'Recent Cases', description: 'View the most recently created cases across the system. Click through to the full case directory for detailed management.', side: 'bottom' as const },
                { element: '[data-tour="admin-recent-activity"]', title: 'Recent Activity', description: 'Track system-wide activity including case creation, updates, user logins, and referrals in real time.', side: 'left' as const },
            ],
        },
        {
            route: 'cases.index',
            title: 'Cases',
            steps: [
                { element: '[data-tour="cases-header"]', title: 'Case Management', description: 'Review and manage all client cases across the entire system.', side: 'bottom' as const },
                { element: '[data-tour="cases-table"]', title: 'Case Directory', description: 'Browse, search, and filter the complete case list. View case details, track progress, and manage referrals.', side: 'top' as const },
            ],
        },
        {
            route: 'admin.agencies.index',
            title: 'Agencies',
            steps: [
                { element: '[data-tour="agencies-header"]', title: 'Agency Management', description: 'Manage all partner agencies, their contact information, and service capabilities.', side: 'bottom' as const },
                { element: '[data-tour="agencies-table"]', title: 'Agency Directory', description: 'View, search, and manage agencies. Add new agencies, edit details, or deactivate as needed.', side: 'top' as const },
            ],
        },
        {
            route: 'admin.users.index',
            title: 'Users',
            steps: [
                { element: '[data-tour="users-header"]', title: 'User Management', description: 'Manage all system users, assign roles, and control agency access permissions.', side: 'bottom' as const },
                { element: '[data-tour="users-table"]', title: 'User Directory', description: 'View and manage user accounts. Create new users, edit profiles, assign roles, and control account status.', side: 'top' as const },
            ],
        },
        {
            route: 'admin.system-settings.index',
            title: 'System Settings',
            steps: [
                { element: '[data-tour="settings-header"]', title: 'System Configuration', description: 'Manage global system settings including application info, referral thresholds, OTP debug modes, and AI chatbot configuration.', side: 'bottom' as const },
                { element: '[data-tour="settings-form"]', title: 'Configuration Panel', description: 'Adjust system parameters such as referral overdue thresholds, OTP debug toggles, and chatbot provider settings.', side: 'top' as const },
            ],
        },
        {
            route: 'admin.helpdesk.articles.index',
            title: 'Help Center',
            steps: [
                { element: '[data-tour="helpdesk-header"]', title: 'Knowledge Base', description: 'Manage the helpdesk knowledge base articles that guide users through the system.', side: 'bottom' as const },
                { element: '[data-tour="helpdesk-articles"]', title: 'Article Directory', description: 'Create, edit, publish, and manage help articles. Organize content to assist end users effectively.', side: 'top' as const },
            ],
        },
    ],
};
