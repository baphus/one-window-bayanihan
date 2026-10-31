import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import AgencyMilestones from '../AgencyMilestones';

vi.mock('@inertiajs/react', () => ({
  Head: ({ title }) => <title>{title}</title>,
  Link: ({ children, ...props }) => <a {...props}>{children}</a>,
}));

vi.mock('@/Layouts/OfwPortalLayout', () => ({ default: ({ children }) => <div data-testid="ofw-layout">{children}</div> }));
vi.mock('@/Components/landing/AppHeader', () => ({ default: () => <header data-testid="public-header" /> }));
vi.mock('@/Components/landing/AppFooter', () => ({ default: () => <footer data-testid="public-footer" /> }));
vi.mock('@/Components/ChatBot', () => ({ default: () => <div data-testid="chatbot" /> }));

globalThis.route = (name, params) => `/${name}/${params?.tracker_number ?? ''}`;

const props = {
  trackingId: 'CASE-123',
  trackedCase: { status: 'IN_PROGRESS', caseNo: 'CASE-123' },
  agencyMilestones: { agencyName: 'Department of Migrant Workers Region VII', agencyShort: 'DMW', agencyLogoUrl: '/agency-dmw.png', status: 'PROCESSING', milestoneCount: 0, milestones: [] },
};

describe('Tracking/AgencyMilestones', () => {
  it('renders lifecycle activities newest first with client attribution and request actions', () => {
    render(<AgencyMilestones {...props} backUrl="/my-cases/1" agencyMilestones={{ ...props.agencyMilestones, milestones: [
      { id: 'sent', type: 'referral_sent', date: '2026-01-01T08:00:00Z', title: 'Referral sent — awaiting agency receipt', actor: 'System' },
      { id: 'reply', type: 'client_response', date: '2026-01-02T08:00:00Z', title: 'You submitted a response with 3 documents.', actor: 'You', by: 'Department of Migrant Workers', requestStatus: 'OPEN', actionUrl: '/my-cases/1/agencies/2/requests/3', actionLabel: 'Respond' },
    ] }} />);

    expect(screen.getAllByRole('heading', { level: 4 }).map((heading) => heading.textContent)).toEqual(['You submitted a response with 3 documents.', 'Referral sent — awaiting agency receipt']);
    expect(screen.getByText('You')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Respond' })).toHaveAttribute('href', '/my-cases/1/agencies/2/requests/3');
    expect(screen.queryByText('Requested services')).not.toBeInTheDocument();
  });
  it('uses OFW portal layout for authenticated route and wraps complete agency identity', () => {
    render(<AgencyMilestones {...props} backUrl="/my-cases/1" />);

    expect(screen.getByTestId('ofw-layout')).toBeInTheDocument();
    expect(screen.queryByTestId('public-header')).not.toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Department of Migrant Workers Region VII' })).toHaveClass('break-words');
    const logo = screen.getByRole('img', { name: 'Department of Migrant Workers Region VII logo' });
    expect(logo).toHaveAttribute('src', '/agency-dmw.png');
    fireEvent.error(logo);
    expect(screen.getByText('account_balance')).toBeInTheDocument();
    expect(screen.getByText('Referral status')).toBeInTheDocument();
    expect(screen.getByText('Case status')).toBeInTheDocument();
    expect(screen.queryByText('Requested services')).not.toBeInTheDocument();
    expect(screen.queryByText('No service request details were provided.')).not.toBeInTheDocument();
    expect(screen.getByRole('navigation', { name: 'Breadcrumb' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Case CASE-123' })).toHaveAttribute('href', '/my-cases/1');
    expect(screen.getByRole('link', { name: 'My Cases' })).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Home' })).not.toBeInTheDocument();
    expect(screen.getByText('DMW referral')).toHaveAttribute('aria-current', 'page');
    expect(screen.queryByRole('link', { name: 'DMW referral' })).not.toBeInTheDocument();
  });

  it('keeps public tracking shell and its header, footer, and chatbot', () => {
    render(<AgencyMilestones {...props} />);

    expect(screen.queryByTestId('ofw-layout')).not.toBeInTheDocument();
    expect(screen.getByTestId('public-header')).toBeInTheDocument();
    expect(screen.getByTestId('public-footer')).toBeInTheDocument();
    expect(screen.getByTestId('chatbot')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Case tracking' })).toHaveAttribute('href', '/track.show/CASE-123');
    expect(screen.queryByRole('link', { name: 'My Cases' })).not.toBeInTheDocument();
    expect(screen.getByText('Agency Milestones')).toHaveAttribute('aria-current', 'page');
  });
});
