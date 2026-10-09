import { render, screen, fireEvent } from '@testing-library/react';
import { describe, it, expect, vi } from 'vitest';
import OnboardingProvider, { useOnboarding } from '../OnboardingProvider';
import type { TourState } from '../types';

vi.mock('../api', () => ({
    markChecklistItem: vi.fn(() => Promise.resolve()),
    dismissChecklist: vi.fn(() => Promise.resolve()),
}));

/** Helper that reads context so we can assert its values in test output. */
function ContextDisplay() {
    const ctx = useOnboarding();
    return (
        <div>
            <span data-testid="checklistItems">{Object.keys(ctx.checklistProgress.items).sort().join(',')}</span>
            <span data-testid="checklistDismissed">{String(ctx.checklistProgress.dismissed_at !== null)}</span>
            <button data-testid="markItem-btn" onClick={() => ctx.markChecklistItem('create-first-case')}>Mark Item</button>
            <button data-testid="dismissChecklist-btn" onClick={ctx.dismissChecklist}>Dismiss Checklist</button>
        </div>
    );
}

function renderWithProvider(onboardingState: TourState | null = null) {
    return render(
        <OnboardingProvider onboardingState={onboardingState}>
            <ContextDisplay />
        </OnboardingProvider>,
    );
}

describe('OnboardingProvider', () => {
    it('renders children without error', () => {
        render(
            <OnboardingProvider>
                <div>child content</div>
            </OnboardingProvider>,
        );
        expect(screen.getByText('child content')).toBeInTheDocument();
    });

    it('merges server checklist items with local optimistic marks', () => {
        renderWithProvider({
            required: false,
            step: null,
            completed_at: null,
            checklist_progress: { items: { 'visit-reports': '2026-07-11T00:00:00Z' }, dismissed_at: null },
        });

        fireEvent.click(screen.getByTestId('markItem-btn'));
        expect(screen.getByTestId('checklistItems')).toHaveTextContent('create-first-case,visit-reports');
    });

    it('dismissChecklist marks the checklist dismissed', () => {
        renderWithProvider();
        expect(screen.getByTestId('checklistDismissed')).toHaveTextContent('false');

        fireEvent.click(screen.getByTestId('dismissChecklist-btn'));
        expect(screen.getByTestId('checklistDismissed')).toHaveTextContent('true');
    });
});
