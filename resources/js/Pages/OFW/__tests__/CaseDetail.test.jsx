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
        renderCase('IN_PROGRESS', { trackingAgencies: [
            { referralId: 'r1', name: 'OWWA', status: 'COMPLETED' },
            { referralId: 'r2', name: 'DMW', status: 'PROCESSING' },
        ] });
        expect(screen.getByText('In progress — 1 of 2 partner offices have completed their part.')).toBeInTheDocument();
    });

    it('shows real open requests independently of referral status and links to response route', () => {
        renderCase('IN_PROGRESS', { trackingAgencies: [{
            referralId: 'r1', name: 'OWWA', status: 'PROCESSING',
            openRequest: { id: 'request-1', title: 'Submit passport copy', type: 'DOCUMENT', status: 'OPEN', dueAt: '2026-02-01', requestedAt: '2026-01-20', url: '/my-cases/requests/request-1' },
        }] });

        expect(screen.getByText('Submit passport copy')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Respond to Request' })).toHaveAttribute('href', '/my-cases/requests/request-1');
        expect(screen.getByText('Action Required: Respond to an Agency Request')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'View Request and Respond' })).toHaveAttribute('href', '/my-cases/requests/request-1');
        expect(screen.getByText(/Due/)).toBeInTheDocument();
    });

    it('shows a request in progress without labeling it as action required', () => {
        renderCase('IN_PROGRESS', { trackingAgencies: [{
            referralId: 'r1', name: 'OWWA', status: 'PROCESSING',
            openRequest: { id: 'request-2', title: 'Review submitted form', type: 'INFORMATION', status: 'IN_PROGRESS', url: '/requests/2' },
        }] });

        expect(screen.getByRole('link', { name: 'View Request' })).toHaveAttribute('href', '/requests/2');
        expect(screen.getByText('In progress')).toBeInTheDocument();
        expect(screen.queryByText('IN_PROGRESS')).not.toBeInTheDocument();
        expect(screen.queryByText(/Action Required: Submit a Supporting Document/)).not.toBeInTheDocument();
        expect(screen.queryByRole('link', { name: 'Respond to Request' })).not.toBeInTheDocument();
    });

    it('keeps compliance status messaging actionable when no linked request exists', () => {
        renderCase('IN_PROGRESS', { trackingAgencies: [{ referralId: 'r1', name: 'OWWA', status: 'FOR_COMPLIANCE' }] });

        expect(screen.getByText('Action Required: Agency compliance update')).toBeInTheDocument();
        expect(screen.getByText(/No linked response request is available/)).toBeInTheDocument();
        expect(screen.queryByText('No action is needed from you right now.')).not.toBeInTheDocument();
        expect(screen.queryByRole('link', { name: 'View Request and Respond' })).not.toBeInTheDocument();
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
