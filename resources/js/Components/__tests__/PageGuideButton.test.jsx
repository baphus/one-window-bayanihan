import { render, screen, fireEvent } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';

// ---------------------------------------------------------------------------
// Mocks
// ---------------------------------------------------------------------------
const mockStartPageGuide = vi.fn();
const mockOnboarding = {
    phase: 'idle',
    seenGuides: [],
    activePageGuide: null,
    startPageGuide: mockStartPageGuide,
};

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({
        url: '/dashboard',
        props: { auth: { user: { role: 'CASE_MANAGER' } } },
    }),
}));

vi.mock('ziggy-js', () => ({
    route: () => ({
        current: () => 'dashboard',
    }),
}));

vi.mock('@/Onboarding/OnboardingProvider', () => ({
    useOnboardingOptional: () => mockOnboarding,
}));

vi.mock('@/Onboarding/registry', () => ({
    getPageGuide: (routeName) => {
        if (routeName === 'dashboard') {
            return {
                title: 'Daily Start',
                steps: [
                    { element: '[data-tour="dashboard-header"]', title: 'Header', description: 'desc' },
                    { element: '[data-tour="dashboard-stats"]', title: 'Stats', description: 'desc' },
                    { element: '[data-tour="dashboard-work-queues"]', title: 'Queues', description: 'desc' },
                ],
            };
        }
        return null;
    },
}));

import PageGuideButton from '../PageGuideButton';

beforeEach(() => {
    mockOnboarding.phase = 'idle';
    mockOnboarding.seenGuides = [];
    mockOnboarding.activePageGuide = null;
    mockStartPageGuide.mockClear();
});

describe('PageGuideButton', () => {
    it('renders when current route has a registered guide', () => {
        render(<PageGuideButton />);

        const button = screen.getByRole('button', { name: /open the daily start page guide/i });
        expect(button).toBeInTheDocument();
        expect(button).toHaveAttribute('data-tour', 'page-guide-button');
    });

    it('has nudge/pulse class when guide is unseen', () => {
        mockOnboarding.seenGuides = [];
        render(<PageGuideButton />);

        const button = screen.getByRole('button');
        expect(button.className).toContain('owb-guide-nudge');
    });

    it('does NOT have nudge class when guide has been seen', () => {
        mockOnboarding.seenGuides = ['dashboard'];
        render(<PageGuideButton />);

        const button = screen.getByRole('button');
        expect(button.className).not.toContain('owb-guide-nudge');
    });

    it('does NOT have nudge class during active tour overlay', () => {
        mockOnboarding.phase = 'touring';
        mockOnboarding.seenGuides = [];
        render(<PageGuideButton />);

        const button = screen.getByRole('button');
        expect(button.className).not.toContain('owb-guide-nudge');
    });

    it('calls startPageGuide with current route and role-qualified key on click', () => {
        render(<PageGuideButton />);

        fireEvent.click(screen.getByRole('button'));
        expect(mockStartPageGuide).toHaveBeenCalledWith('dashboard', 'CASE_MANAGER:dashboard');
    });

    it('does NOT call startPageGuide during active overlay', () => {
        mockOnboarding.phase = 'touring';
        render(<PageGuideButton />);

        fireEvent.click(screen.getByRole('button'));
        expect(mockStartPageGuide).not.toHaveBeenCalled();
    });

    it('does NOT call startPageGuide when a page guide is already active', () => {
        mockOnboarding.activePageGuide = { route: 'cases.index' };
        render(<PageGuideButton />);

        fireEvent.click(screen.getByRole('button'));
        expect(mockStartPageGuide).not.toHaveBeenCalled();
    });
});
