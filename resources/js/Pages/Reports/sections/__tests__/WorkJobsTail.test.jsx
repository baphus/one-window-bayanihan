import '@testing-library/jest-dom/vitest';
import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

const { pageProps } = vi.hoisted(() => ({ pageProps: {
    employmentDistribution: {
        labels: ['UAE', 'Taiwan', 'Japan', 'Qatar', 'Kuwait', 'Singapore', 'Hong Kong'],
        data: [16, 13, 12, 10, 4, 3, 2],
    },
    employmentOccupationBreakdown: {
        labels: ['Supervisor', 'Technician', 'Driver', 'Clerk', 'Laborer', 'Nurse'],
        data: [16, 12, 11, 10, 5, 4],
        total_distinct: 6,
    },
} }));

vi.mock('@inertiajs/react', () => ({ usePage: () => ({ props: pageProps }) }));
vi.mock('react-chartjs-2', () => ({
    Bar: (props) => <div data-testid="bar-chart" data-labels={JSON.stringify(props.data.labels)} />,
    Doughnut: () => <div data-testid="donut-chart" />,
}));
vi.mock('@/Components/Reports/CaseVolumeChart', () => ({ default: () => null }));
vi.mock('../ReferralTrendsSection', () => ({ default: () => null }));
vi.mock('../GeographicMapSection', () => ({ default: () => null }));

import CasesTab from '../CasesTab';

describe('CasesTab work and jobs tail', () => {
    it('collapses the tail until asked, then shows every remaining country', () => {
        render(<CasesTab mapProps={{}} />);

        // Top five only: the two tail countries are hidden behind the toggle.
        expect(screen.queryByText('Singapore')).not.toBeInTheDocument();
        const toggle = screen.getByRole('button', { name: /more cases across 2 other countries/i });

        fireEvent.click(toggle);

        expect(screen.getByText('Singapore')).toBeInTheDocument();
        expect(screen.getByText('Hong Kong')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Show fewer' })).toHaveAttribute('aria-expanded', 'true');

        fireEvent.click(screen.getByRole('button', { name: 'Show fewer' }));

        expect(screen.queryByText('Singapore')).not.toBeInTheDocument();
    });
});
