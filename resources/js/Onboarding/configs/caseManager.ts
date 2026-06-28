import { TourConfig } from '../types';

export const caseManagerTour: TourConfig = {
    role: 'CASE_MANAGER',
    pages: [
        {
            route: 'dashboard',
            title: 'Dashboard',
            steps: [
                { element: '[data-tour="dashboard-header"]', title: 'Welcome', description: 'Your personalized dashboard with key information at a glance, including greetings and quick-search.', side: 'bottom' as const },
                { element: '[data-tour="dashboard-stats"]', title: 'Key Metrics', description: 'Monitor your active cases, clients served, pending referrals, and average resolution time.', side: 'bottom' as const },
                { element: '[data-tour="dashboard-chart"]', title: 'Case Trends', description: 'Visualize case volume trends, referral status distribution, and geographic breakdowns by province and agency.', side: 'bottom' as const },
                { element: '[data-tour="dashboard-recent"]', title: 'Recent Cases', description: 'Quickly access your most recently updated cases. Click any row to view full case details.', side: 'left' as const },
            ],
        },
        {
            route: 'cases.index',
            title: 'Cases',
            steps: [
                { element: '[data-tour="cases-header"]', title: 'All Cases', description: 'Browse and search all cases assigned to you. Export data or create new cases from here.', side: 'bottom' as const },
                { element: '[data-tour="cases-filter"]', title: 'Filters & Search', description: 'Narrow down cases by status, client type, vulnerability, category, author, or agency. Use the search bar to find by tracking ID or client name.', side: 'bottom' as const },
                { element: '[data-tour="cases-table"]', title: 'Case List', description: 'Each row shows case details including client info, status, and latest activity. Click View to open or use column controls to customize your view.', side: 'left' as const },
            ],
        },
        {
            route: 'cases.create',
            title: 'Create Case',
            steps: [
                { element: '[data-tour="case-create-steps"]', title: 'Step Guide', description: 'Follow the 3-step wizard — Client Profile, Case Setup, and Case Narrative — to register a new case with confidence.', side: 'right' as const },
                { element: '[data-tour="case-create-form"]', title: 'Case Form', description: 'Fill in client information, employment details, next of kin, case category, and narrative to create a complete case record.', side: 'bottom' as const },
            ],
        },
        {
            route: 'referrals.index',
            title: 'Referrals',
            steps: [
                { element: '[data-tour="referrals-header"]', title: 'Referral Management', description: 'Track and manage all referrals sent to partner agencies. Filter by status or type to find specific referrals.', side: 'bottom' as const },
                { element: '[data-tour="referrals-table"]', title: 'Referral List', description: 'Each row shows referral details, agency, service required, and status. Click View to see full referral progress and milestones.', side: 'left' as const },
            ],
        },
        {
            route: 'clients.index',
            title: 'Clients',
            steps: [
                { element: '[data-tour="clients-header"]', title: 'Client Directory', description: 'View all registered clients and their associated cases. Search by name or filter by client type and sex.', side: 'bottom' as const },
                { element: '[data-tour="clients-table"]', title: 'Client List', description: 'Browse client records with key details at a glance. Click View Details to see full client profile and case history.', side: 'left' as const },
            ],
        },
    ],
};
