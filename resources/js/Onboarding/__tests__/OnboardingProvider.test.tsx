import { render, screen, fireEvent } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { usePage } from '@inertiajs/react';
import OnboardingProvider, { useOnboarding } from '../OnboardingProvider';
import { parseStepKey, type TourConfig, type TourState } from '../types';

vi.mock('@inertiajs/react', () => ({
    usePage: vi.fn(),
}));

vi.mock('../api', () => ({
    markGuideSeen: vi.fn(() => Promise.resolve()),
    markChecklistItem: vi.fn(() => Promise.resolve()),
    dismissChecklist: vi.fn(() => Promise.resolve()),
}));

vi.mock('../registry', () => ({
    getPageGuide: vi.fn((routeName: string) =>
        routeName === 'cases.index'
            ? { title: 'Cases', steps: [{ element: '[data-tour="cases-header"]', title: 'Cases', description: 'desc' }] }
            : null,
    ),
}));

const mockTourConfig: TourConfig = {
    role: 'CASE_MANAGER',
    pages: [
        {
            route: 'dashboard',
            title: 'Dashboard',
            steps: [
                { element: '[data-tour="a"]', title: 'A', description: 'a' },
                { element: '[data-tour="b"]', title: 'B', description: 'b' },
            ],
        },
        {
            route: 'cases.index',
            title: 'Cases',
            steps: [
                { element: '[data-tour="c"]', title: 'C', description: 'c' },
            ],
        },
    ],
};

/** Helper that reads context so we can assert its values in test output. */
function ContextDisplay() {
    const ctx = useOnboarding();
    return (
        <div>
            <span data-testid="phase">{ctx.phase}</span>
            <span data-testid="isOpen">{String(ctx.isOpen)}</span>
            <span data-testid="pageIndex">{ctx.currentPageIndex}</span>
            <span data-testid="resumeStep">{String(ctx.resumeStepIndex)}</span>
            <span data-testid="seenGuides">{ctx.seenGuides.join(',')}</span>
            <span data-testid="checklistItems">{Object.keys(ctx.checklistProgress.items).sort().join(',')}</span>
            <span data-testid="checklistDismissed">{String(ctx.checklistProgress.dismissed_at !== null)}</span>
            <span data-testid="activeGuide">{ctx.activePageGuide?.route ?? 'none'}</span>
            <button data-testid="startTour-btn" onClick={() => ctx.startTour(mockTourConfig)}>Start Tour</button>
            <button data-testid="resumeTour-btn" onClick={() => ctx.startTour(mockTourConfig, { page: 1, step: 0 })}>Resume Tour</button>
            <button data-testid="dismiss-btn" onClick={ctx.dismissRemindLater}>Dismiss</button>
            <button data-testid="endTour-btn" onClick={ctx.endTour}>End Tour</button>
            <button data-testid="startGuide-btn" onClick={() => ctx.startPageGuide('cases.index')}>Start Guide</button>
            <button data-testid="startMissingGuide-btn" onClick={() => ctx.startPageGuide('nope.index')}>Start Missing Guide</button>
            <button data-testid="endGuide-btn" onClick={ctx.endPageGuide}>End Guide</button>
            <button data-testid="markItem-btn" onClick={() => ctx.markChecklistItem('create-first-case')}>Mark Item</button>
            <button data-testid="dismissChecklist-btn" onClick={ctx.dismissChecklist}>Dismiss Checklist</button>
        </div>
    );
}

function renderWithProvider(onboardingRequired = false, onboardingState: TourState | null = null) {
    return render(
        <OnboardingProvider onboardingRequired={onboardingRequired} onboardingState={onboardingState}>
            <ContextDisplay />
        </OnboardingProvider>,
    );
}

beforeEach(() => {
    sessionStorage.clear();
});

describe('OnboardingProvider', () => {
    it('renders children without error', () => {
        vi.mocked(usePage).mockReturnValue({
            props: { errors: {}, onboarding_required: false },
        } as never);
        render(
            <OnboardingProvider>
                <div>child content</div>
            </OnboardingProvider>,
        );
        expect(screen.getByText('child content')).toBeInTheDocument();
    });

    it('starts in idle phase when onboarding is not required', () => {
        renderWithProvider(false);
        expect(screen.getByTestId('phase')).toHaveTextContent('idle');
        expect(screen.getByTestId('isOpen')).toHaveTextContent('false');
    });

    it('transitions to welcome phase when onboarding is required', () => {
        renderWithProvider(true);
        expect(screen.getByTestId('phase')).toHaveTextContent('welcome');
        expect(screen.getByTestId('isOpen')).toHaveTextContent('true');
    });

    it('startTour transitions to touring phase at page 0 with no resume step', () => {
        renderWithProvider(true);
        fireEvent.click(screen.getByTestId('startTour-btn'));

        expect(screen.getByTestId('phase')).toHaveTextContent('touring');
        expect(screen.getByTestId('pageIndex')).toHaveTextContent('0');
        expect(screen.getByTestId('resumeStep')).toHaveTextContent('null');
    });

    it('startTour with a position resumes at that page and step', () => {
        renderWithProvider(true);
        fireEvent.click(screen.getByTestId('resumeTour-btn'));

        expect(screen.getByTestId('phase')).toHaveTextContent('touring');
        expect(screen.getByTestId('pageIndex')).toHaveTextContent('1');
        expect(screen.getByTestId('resumeStep')).toHaveTextContent('0');
    });

    it('dismissRemindLater sets sessionStorage and resets to idle', () => {
        renderWithProvider(true);
        fireEvent.click(screen.getByTestId('dismiss-btn'));

        expect(sessionStorage.getItem('onboarding_dismissed')).toBe('true');
        expect(screen.getByTestId('phase')).toHaveTextContent('idle');
    });

    it('endTour resets touring state', () => {
        renderWithProvider(true);
        fireEvent.click(screen.getByTestId('resumeTour-btn'));
        fireEvent.click(screen.getByTestId('endTour-btn'));

        expect(screen.getByTestId('phase')).toHaveTextContent('idle');
        expect(screen.getByTestId('pageIndex')).toHaveTextContent('0');
        expect(screen.getByTestId('resumeStep')).toHaveTextContent('null');
    });

    it('startPageGuide opens a registered guide and marks it seen', () => {
        renderWithProvider(false);
        fireEvent.click(screen.getByTestId('startGuide-btn'));

        expect(screen.getByTestId('activeGuide')).toHaveTextContent('cases.index');
        expect(screen.getByTestId('seenGuides')).toHaveTextContent('cases.index');
        expect(screen.getByTestId('isOpen')).toHaveTextContent('true');
    });

    it('startPageGuide is a no-op for unregistered routes', () => {
        renderWithProvider(false);
        fireEvent.click(screen.getByTestId('startMissingGuide-btn'));

        expect(screen.getByTestId('activeGuide')).toHaveTextContent('none');
    });

    it('endPageGuide closes the active guide', () => {
        renderWithProvider(false);
        fireEvent.click(screen.getByTestId('startGuide-btn'));
        fireEvent.click(screen.getByTestId('endGuide-btn'));

        expect(screen.getByTestId('activeGuide')).toHaveTextContent('none');
        expect(screen.getByTestId('isOpen')).toHaveTextContent('false');
    });

    it('merges server seen guides with local optimistic marks', () => {
        renderWithProvider(false, {
            required: false,
            step: null,
            completed_at: null,
            seen_page_guides: ['reports.index'],
            checklist_progress: { items: {}, dismissed_at: null },
        });

        fireEvent.click(screen.getByTestId('startGuide-btn'));
        expect(screen.getByTestId('seenGuides')).toHaveTextContent('reports.index,cases.index');
    });

    it('merges server checklist items with local optimistic marks', () => {
        renderWithProvider(false, {
            required: false,
            step: null,
            completed_at: null,
            seen_page_guides: [],
            checklist_progress: { items: { 'visit-reports': '2026-07-11T00:00:00Z' }, dismissed_at: null },
        });

        fireEvent.click(screen.getByTestId('markItem-btn'));
        expect(screen.getByTestId('checklistItems')).toHaveTextContent('create-first-case,visit-reports');
    });

    it('dismissChecklist marks the checklist dismissed', () => {
        renderWithProvider(false);
        expect(screen.getByTestId('checklistDismissed')).toHaveTextContent('false');

        fireEvent.click(screen.getByTestId('dismissChecklist-btn'));
        expect(screen.getByTestId('checklistDismissed')).toHaveTextContent('true');
    });
});

describe('parseStepKey', () => {
    it('parses valid keys', () => {
        expect(parseStepKey('0:0')).toEqual({ page: 0, step: 0 });
        expect(parseStepKey('2:1')).toEqual({ page: 2, step: 1 });
    });

    it('rejects malformed keys', () => {
        expect(parseStepKey(null)).toBeNull();
        expect(parseStepKey(undefined)).toBeNull();
        expect(parseStepKey('')).toBeNull();
        expect(parseStepKey('legacy-step-3')).toBeNull();
        expect(parseStepKey('1:')).toBeNull();
        expect(parseStepKey(':1')).toBeNull();
        expect(parseStepKey('1:2:3')).toBeNull();
        expect(parseStepKey('-1:2')).toBeNull();
    });

    it('rejects out-of-range keys when given a config', () => {
        expect(parseStepKey('9:0', mockTourConfig)).toBeNull();
        expect(parseStepKey('0:9', mockTourConfig)).toBeNull();
        expect(parseStepKey('1:0', mockTourConfig)).toEqual({ page: 1, step: 0 });
    });
});
