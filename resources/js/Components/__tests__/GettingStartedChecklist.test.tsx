import { render, screen, fireEvent } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { usePage } from '@inertiajs/react';
import OnboardingProvider from '@/Onboarding/OnboardingProvider';
import GettingStartedChecklist from '../GettingStartedChecklist';
import type { TourState } from '@/Onboarding/types';

vi.mock('@inertiajs/react', () => ({
    usePage: vi.fn(),
    Link: ({ href, children, className }: { href: string; children: React.ReactNode; className?: string }) => (
        <a href={href} className={className}>{children}</a>
    ),
}));

vi.mock('ziggy-js', () => ({
    route: vi.fn((name: string) => `/${name}`),
}));

const apiMocks = vi.hoisted(() => ({
    dismissChecklist: vi.fn(() => Promise.resolve()),
    markChecklistItem: vi.fn(() => Promise.resolve()),
    markGuideSeen: vi.fn(() => Promise.resolve()),
}));
vi.mock('@/Onboarding/api', () => apiMocks);

function renderChecklist(role: string, state?: Partial<TourState>) {
    vi.mocked(usePage).mockReturnValue({
        url: '/dashboard',
        props: { auth: { user: { role } } },
    } as never);

    const onboardingState: TourState = {
        required: false,
        step: null,
        completed_at: null,
        seen_page_guides: [],
        checklist_progress: { items: {}, dismissed_at: null },
        ...state,
    };

    return render(
        <OnboardingProvider onboardingRequired={false} onboardingState={onboardingState}>
            <GettingStartedChecklist />
        </OnboardingProvider>,
    );
}

beforeEach(() => {
    vi.clearAllMocks();
    sessionStorage.clear();
});

describe('GettingStartedChecklist', () => {
    it('renders role-specific items for a case manager', () => {
        renderChecklist('CASE_MANAGER');

        expect(screen.getByText('Create your first case')).toBeInTheDocument();
        expect(screen.getByText('Send your first referral')).toBeInTheDocument();
        expect(screen.getByText('Explore Reports & Analytics')).toBeInTheDocument();
        expect(screen.getByText('0 of 4 first steps done — each one ticks off automatically.')).toBeInTheDocument();
    });

    it('renders agency items for an agency focal', () => {
        renderChecklist('AGENCY');

        expect(screen.getByText('Set up your first service')).toBeInTheDocument();
        expect(screen.getByText('Act on a referral')).toBeInTheDocument();
        expect(screen.getByText('Configure your feedback questionnaire')).toBeInTheDocument();
    });

    it('renders nothing for unknown roles', () => {
        const { container } = renderChecklist('UNKNOWN_ROLE');
        expect(container).toBeEmptyDOMElement();
    });

    it('renders nothing when dismissed', () => {
        const { container } = renderChecklist('CASE_MANAGER', {
            checklist_progress: { items: {}, dismissed_at: '2026-07-11T00:00:00Z' },
        });
        expect(container).toBeEmptyDOMElement();
    });

    it('shows completed items as done and counts progress', () => {
        renderChecklist('CASE_MANAGER', {
            checklist_progress: {
                items: { 'create-first-case': '2026-07-11T00:00:00Z', 'visit-reports': '2026-07-11T00:00:00Z' },
                dismissed_at: null,
            },
        });

        expect(screen.getByText('2 of 4 first steps done — each one ticks off automatically.')).toBeInTheDocument();
    });

    it('dismiss button hides the card and persists', () => {
        renderChecklist('CASE_MANAGER');

        fireEvent.click(screen.getByText('Dismiss'));

        expect(apiMocks.dismissChecklist).toHaveBeenCalledTimes(1);
        expect(screen.queryByText('Create your first case')).not.toBeInTheDocument();
    });

    it('shows completion state and quietly retires when all items are done', () => {
        renderChecklist('CASE_MANAGER', {
            checklist_progress: {
                items: {
                    'create-first-case': '1',
                    'send-first-referral': '1',
                    'visit-reports': '1',
                    'open-help-center': '1',
                },
                dismissed_at: null,
            },
        });

        expect(screen.getByText('You’re all set! 🎉')).toBeInTheDocument();
        // Item list collapses in the completion state
        expect(screen.queryByText('Create your first case')).not.toBeInTheDocument();
        // Retirement is persisted so the card is gone on the next visit
        expect(apiMocks.dismissChecklist).toHaveBeenCalledTimes(1);
    });
});
