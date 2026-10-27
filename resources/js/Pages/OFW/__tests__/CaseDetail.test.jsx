import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import CaseDetail from '../CaseDetail';
import { NEUTRAL_STATUS_LINE } from '@/lib/caseStatusLine';

vi.mock('@inertiajs/react', () => ({
    Head: ({ title }) => <title>{title}</title>,
    Link: ({ children, ...props }) => <a {...props}>{children}</a>,
    usePage: () => ({ url: '/my-cases', props: { auth: { user: { name: 'Test OFW' } } } }),
    router: { post: vi.fn(), reload: vi.fn() },
}));

vi.mock('@/Layouts/OfwPortalLayout', () => ({
    default: ({ children }) => <div>{children}</div>,
}));

vi.mock('@/Components/CitizenTimeline', () => ({ default: () => <div /> }));

globalThis.route = (name, params) => `/${name}/${Array.isArray(params) ? params.join('/') : params ?? ''}`;

function renderCase(status, overrides = {}) {
    return render(
        <CaseDetail
            trackingId="OWB-1234"
            trackedCase={{
                clientName: 'Juan Dela Cruz',
                createdAt: '2026-01-05T00:00:00Z',
                updatedAt: '2026-01-06T00:00:00Z',
                categories: [],
                status,
            }}
            caseOverview={{}}
            trackingAgencies={overrides.trackingAgencies ?? []}
            rejectedCount={overrides.rejectedCount ?? 0}
        />,
    );
}

describe('OFW/CaseDetail status line', () => {
    it('tells a newly submitted OFW that a case manager is reviewing the case', () => {
        renderCase('BEING_REVIEWED');

        expect(screen.getByText('Your case is being reviewed by a case manager.')).toBeInTheDocument();
    });

    it('does not show "Case status is currently unavailable" for a pending-review case', () => {
        renderCase('BEING_REVIEWED');

        expect(screen.queryByText(/Case status is currently unavailable/i)).not.toBeInTheDocument();
    });

    it('points an in-progress case with no referrals at upcoming referrals', () => {
        renderCase('IN_PROGRESS');

        expect(screen.getByText('Your case is being reviewed. Referrals to partner offices will appear here.')).toBeInTheDocument();
    });

    it('reports completed partner offices once referrals exist', () => {
        renderCase('IN_PROGRESS', {
            trackingAgencies: [
                { referralId: 'r1', name: 'OWWA', status: 'COMPLETED' },
                { referralId: 'r2', name: 'DMW', status: 'PROCESSING' },
            ],
        });

        expect(screen.getByText('In progress — 1 of 2 partner offices have completed their part.')).toBeInTheDocument();
    });

    it('confirms a resolved case', () => {
        renderCase('RESOLVED');

        expect(screen.getByText('This case has been resolved.')).toBeInTheDocument();
    });

    it('shows the neutral fallback instead of an error for an unrecognized status', () => {
        renderCase('SOME_FUTURE_STATUS');

        expect(screen.getByText(NEUTRAL_STATUS_LINE)).toBeInTheDocument();
        expect(screen.queryByText(/unavailable/i)).not.toBeInTheDocument();
    });
});
