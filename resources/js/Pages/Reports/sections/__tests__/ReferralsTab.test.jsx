import '@testing-library/jest-dom/vitest';
import { render, screen, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

const { pageProps } = vi.hoisted(() => ({ pageProps: {
    agencyScorecard: [
        { agency: 'OWWA', total: 3, completed: 1 },
        { agency: 'DMW', total: 2, completed: 1 },
    ],
    mostUsedServices: [{ name: 'EDSP', agency: 'OWWA', count: 2 }],
} }));
vi.mock('@inertiajs/react', () => ({ usePage: () => ({ props: pageProps }) }));
vi.mock('react-chartjs-2', () => ({
    Bar: ({ data, options }) => <div data-testid="bar-chart" data-chart={JSON.stringify({ data, options })} />,
    Doughnut: () => null,
}));

import ReferralsTab from '../ReferralsTab';

describe('ReferralsTab', () => {
    it('shows backlog age groups, totals and status counts', () => {
        pageProps.openReferralBacklog = [
            { label: '0–7 days', pending: 2, processing: 1, for_compliance: 3 },
            { label: '8–14 days', pending: 0, processing: 1, for_compliance: 0 },
            { label: '15–30 days', pending: 0, processing: 0, for_compliance: 0 },
            { label: '31+ days', pending: 1, processing: 0, for_compliance: 0 },
        ];
        render(<ReferralsTab />);
        const section = screen.getByRole('heading', { name: 'Current open-referral backlog' }).closest('section');
        expect(within(section).getByText('Age since referral creation. Current snapshot; selected date range does not apply.')).toBeInTheDocument();
        expect(within(section).getByText('0–7 days')).toBeInTheDocument();
        expect(within(section).getByText('6')).toBeInTheDocument();
        expect(within(section).getByText('Pending: 2 · Processing: 1 · For compliance: 3').closest('.sr-only')).toBeInTheDocument();
        expect(within(section).getByText('31+ days')).toBeInTheDocument();
        const chart = JSON.parse(within(section).getByTestId('bar-chart').getAttribute('data-chart'));
        expect(chart.options.indexAxis).toBe('y');
        expect(chart.options.scales.x.stacked).toBe(true);
        expect(chart.data.datasets.map((dataset) => dataset.data)).toEqual([[2, 0, 0, 1], [1, 1, 0, 0], [3, 0, 0, 0]]);
    });

    it('shows loading before backlog data arrives', () => {
        delete pageProps.openReferralBacklog;
        render(<ReferralsTab />);
        const section = screen.getByRole('heading', { name: 'Current open-referral backlog' }).closest('section');
        expect(section.querySelector('.animate-pulse')).toBeInTheDocument();
        expect(within(section).queryByText('No open referrals to show yet.')).not.toBeInTheDocument();
    });

    it('shows an empty backlog message after data arrives', () => {
        pageProps.openReferralBacklog = [{ label: '0–7 days', pending: 0, processing: 0, for_compliance: 0 }];
        render(<ReferralsTab />);
        expect(screen.getByText('No open referrals to show yet.')).toBeInTheDocument();
    });

    it('shows applied services and distinct agency bars without a rejected section', () => {
        render(<ReferralsTab />);
        const services = screen.getByRole('heading', { name: 'Most used services' }).closest('section');
        expect(within(services).getByText('EDSP — OWWA')).toBeInTheDocument();
        expect(within(services).getByText('2')).toBeInTheDocument();
        const agencies = screen.getByRole('heading', { name: 'Referral picture per agency' }).closest('section');
        const chart = JSON.parse(within(agencies).getByTestId('bar-chart').getAttribute('data-chart'));
        expect(chart.options.indexAxis).toBe('x');
        expect(chart.data.datasets[0].data).toEqual([3, 2]);
        expect(chart.data.datasets[0].backgroundColor[0]).not.toBe(chart.data.datasets[0].backgroundColor[1]);
        expect(screen.queryByRole('heading', { name: 'Rejected referrals' })).not.toBeInTheDocument();
    });
});
