import { createContext, useContext, useState, useCallback, useMemo, ReactNode } from 'react';
import { ChecklistProgress, TourState } from './types';
import * as api from './api';

export interface OnboardingContextValue {
    /** Getting-started checklist progress (merged server + optimistic) */
    checklistProgress: ChecklistProgress;
    /** Mark a checklist item complete (optimistic + persisted) */
    markChecklistItem: (itemId: string) => void;
    /** Dismiss the checklist (optimistic + persisted) */
    dismissChecklist: () => void;
}

const OnboardingContext = createContext<OnboardingContextValue | null>(null);

/**
 * Access the onboarding context. Throws if used outside OnboardingProvider.
 */
export function useOnboarding(): OnboardingContextValue {
    const ctx = useContext(OnboardingContext);
    if (!ctx) {
        throw new Error('useOnboarding must be used within <OnboardingProvider>');
    }
    return ctx;
}

/**
 * Non-throwing variant for leaf components (visit tracking) that may
 * render in layouts tested without the app shell. Returns null when no
 * provider is present; callers no-op.
 */
export function useOnboardingOptional(): OnboardingContextValue | null {
    return useContext(OnboardingContext);
}

const EMPTY_PROGRESS: ChecklistProgress = { items: {}, dismissed_at: null };

export default function OnboardingProvider({
    children,
    onboardingState,
}: {
    children: ReactNode;
    onboardingState?: TourState | null;
}) {
    // Optimistic local copies of persisted UX state. Server state (from the
    // shared Inertia prop) refreshes on every navigation; local marks are
    // merged in so the UI never flickers back while a request is in flight.
    const [localItems, setLocalItems] = useState<Record<string, string>>({});
    const [localDismissedAt, setLocalDismissedAt] = useState<string | null>(null);

    const checklistProgress = useMemo<ChecklistProgress>(() => {
        const server = onboardingState?.checklist_progress ?? EMPTY_PROGRESS;
        return {
            items: { ...(server.items ?? {}), ...localItems },
            dismissed_at: server.dismissed_at ?? localDismissedAt,
        };
    }, [onboardingState, localItems, localDismissedAt]);

    const markChecklistItem = useCallback((itemId: string) => {
        setLocalItems((prev) => (prev[itemId] ? prev : { ...prev, [itemId]: new Date().toISOString() }));
        api.markChecklistItem(itemId).catch(() => {
            // Non-critical UX state.
        });
    }, []);

    const dismissChecklist = useCallback(() => {
        setLocalDismissedAt(new Date().toISOString());
        api.dismissChecklist().catch(() => {
            // Non-critical UX state.
        });
    }, []);

    const value: OnboardingContextValue = {
        checklistProgress,
        markChecklistItem,
        dismissChecklist,
    };

    return (
        <OnboardingContext.Provider value={value}>
            {children}
        </OnboardingContext.Provider>
    );
}
