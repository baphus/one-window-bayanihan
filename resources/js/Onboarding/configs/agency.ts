import { TourConfig } from '../types';

export const agencyTour: TourConfig = {
    role: 'AGENCY',
    pages: [
        {
            route: 'dashboard',
            title: 'Dashboard',
            steps: [
                { element: '[data-tour="dashboard-agency-metrics"]', title: 'Performance Metrics', description: 'Monitor your agency\'s referral performance with key indicators including total referrals, pending, processing, and completed counts.', side: 'bottom' as const, align: 'start' as const },
                { element: '[data-tour="dashboard-agency-referrals"]', title: 'Recent Referrals', description: 'Review the latest referrals assigned to your agency and track their current status from this overview table.', side: 'top' as const },
            ],
        },
        {
            route: 'referrals.index',
            title: 'Referred Cases',
            steps: [
                { element: '[data-tour="referrals-header"]', title: 'Referrals Overview', description: 'Browse all referrals assigned to your agency. Use search and filters to quickly find specific cases.', side: 'bottom' as const, align: 'start' as const },
                { element: '[data-tour="referrals-table"]', title: 'Referrals List', description: 'View referral details, accept or reject pending requests, and monitor the progress of each referral.', side: 'top' as const },
            ],
        },
        {
            route: 'agency.services.index',
            title: 'Services',
            steps: [
                { element: '[data-tour="services-header"]', title: 'Services Overview', description: 'Define and manage the services your agency offers to case managers and their clients.', side: 'bottom' as const, align: 'start' as const },
                { element: '[data-tour="services-list"]', title: 'Service Actions', description: 'Add new services, update service details, set required documents, or remove services from the list.', side: 'top' as const },
            ],
        },
    ],
};
