import { render, screen, fireEvent } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { usePage } from '@inertiajs/react';
import OnboardingProvider, { useOnboarding } from '../OnboardingProvider';
import type { TourConfig } from '../types';

vi.mock('@inertiajs/react', () => ({
    usePage: vi.fn(),
}));

const mockTourConfig: TourConfig = {
    role: 'CASE_MANAGER',
    pages: [
        {
            route: 'dashboard',
            title: 'Dashboard',
            steps: [
                {
                    element: '[data-tour="welcome"]',
                    title: 'Welcome',
                    description: 'Welcome step',
                },
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
            <button
                data-testid="startTour-btn"
                onClick={() => ctx.startTour(mockTourConfig)}
            >
                Start Tour
            </button>
            <button data-testid="dismiss-btn" onClick={ctx.dismissRemindLater}>
                Dismiss
            </button>
        </div>
    );
}

function renderWithProvider(onboardingRequired = false) {
    return render(
        <OnboardingProvider onboardingRequired={onboardingRequired}>
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
            props: { onboarding_required: false },
        });
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

    it('startTour transitions to touring phase', () => {
        renderWithProvider(true);
        // After mount with onboarding_required=true, phase should be 'welcome'
        expect(screen.getByTestId('phase')).toHaveTextContent('welcome');

        fireEvent.click(screen.getByTestId('startTour-btn'));

        expect(screen.getByTestId('phase')).toHaveTextContent('touring');
        expect(screen.getByTestId('isOpen')).toHaveTextContent('true');
    });

    it('dismissRemindLater sets sessionStorage and resets to idle', () => {
        renderWithProvider(true);
        expect(screen.getByTestId('phase')).toHaveTextContent('welcome');

        fireEvent.click(screen.getByTestId('dismiss-btn'));

        expect(sessionStorage.getItem('onboarding_dismissed')).toBe('true');
        expect(screen.getByTestId('phase')).toHaveTextContent('idle');
    });
});
