import { createContext, useContext, useState, useEffect, useCallback, useMemo, ReactNode } from 'react';
import { ChecklistProgress, PageGuide, TourConfig, TourState } from './types';
import { getPageGuide } from './registry';
import * as api from './api';

export type Phase = 'idle' | 'welcome' | 'touring' | 'complete';

export interface ActivePageGuide {
    /** Route name the guide belongs to */
    route: string;
    /** The guide config being driven */
    guide: PageGuide;
}

export interface OnboardingContextValue {
    /** Whether any onboarding UI should be visible */
    isOpen: boolean;
    /** The active welcome-tour configuration */
    tourConfig: TourConfig | null;
    /** Current phase of the welcome-tour state machine */
    phase: Phase;
    /** Zero-based index of the current page in the tour (persists across Inertia navigations) */
    currentPageIndex: number;
    /** One-shot step index to resume at when the tour next drives (consumed by TourManager) */
    resumeStepIndex: number | null;
    /** Begin a welcome tour, optionally resuming at a position */
    startTour: (config: TourConfig, at?: { page: number; step: number }) => void;
    /** End the current tour and reset to idle */
    endTour: () => void;
    /** Set the current page index directly */
    setCurrentPageIndex: (n: number) => void;
    /** Clear the one-shot resume step index after it is consumed */
    clearResumeStep: () => void;
    /** Dismiss onboarding for the remainder of this session */
    dismissRemindLater: () => void;

    /** The page guide currently being driven, or null */
    activePageGuide: ActivePageGuide | null;
    /** Launch the registered guide for a route (marks it seen). `seenAs` overrides the persisted identity. */
    startPageGuide: (route: string, seenAs?: string) => void;
    /** End the active page guide */
    endPageGuide: () => void;
    /** Route names whose guides the user has seen */
    seenGuides: string[];
    /** Mark a page guide seen (optimistic + persisted) without opening it */
    markGuideSeen: (route: string) => void;

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
 * Non-throwing variant for leaf components (PageGuideButton, TourManager,
 * visit tracking) that may render in layouts tested without the app shell.
 * Returns null when no provider is present; callers no-op.
 */
export function useOnboardingOptional(): OnboardingContextValue | null {
    return useContext(OnboardingContext);
}

const EMPTY_PROGRESS: ChecklistProgress = { items: {}, dismissed_at: null };

export default function OnboardingProvider({
    children,
    onboardingRequired,
    onboardingState,
}: {
    children: ReactNode;
    onboardingRequired?: unknown;
    onboardingState?: TourState | null;
}) {
    const [phase, setPhase] = useState<Phase>('idle');
    const [tourConfig, setTourConfig] = useState<TourConfig | null>(null);
    const [currentPageIndex, setCurrentPageIndexState] = useState<number>(0);
    const [resumeStepIndex, setResumeStepIndex] = useState<number | null>(null);

    const [activePageGuide, setActivePageGuide] = useState<ActivePageGuide | null>(null);

    // Optimistic local copies of persisted UX state. Server state (from the
    // shared Inertia prop) refreshes on every navigation; local marks are
    // merged in so the UI never flickers back while a request is in flight.
    const [localSeen, setLocalSeen] = useState<string[]>([]);
    const [localItems, setLocalItems] = useState<Record<string, string>>({});
    const [localDismissedAt, setLocalDismissedAt] = useState<string | null>(null);

    // On mount, check sessionStorage and onboarding_required prop.
    // Only enter 'welcome' from idle — during a replayed tour the shared
    // prop flips false→true mid-tour (replay resets completed_at), and the
    // modal must not tear down the active tour.
    useEffect(() => {
        const dismissed = sessionStorage.getItem('onboarding_dismissed') === 'true';
        if (dismissed) return;

        if (onboardingRequired) {
            setPhase((prev) => (prev === 'idle' ? 'welcome' : prev));
        }
    }, [onboardingRequired]);

    const setCurrentPageIndex = useCallback((n: number) => {
        setCurrentPageIndexState(n);
    }, []);

    const startTour = useCallback((config: TourConfig, at?: { page: number; step: number }) => {
        setTourConfig(config);
        setCurrentPageIndexState(at?.page ?? 0);
        setResumeStepIndex(at?.step ?? null);
        setPhase('touring');
    }, []);

    const endTour = useCallback(() => {
        setPhase('idle');
        setTourConfig(null);
        setCurrentPageIndexState(0);
        setResumeStepIndex(null);
    }, []);

    const clearResumeStep = useCallback(() => {
        setResumeStepIndex(null);
    }, []);

    const dismissRemindLater = useCallback(() => {
        sessionStorage.setItem('onboarding_dismissed', 'true');
        setPhase('idle');
    }, []);

    const seenGuides = useMemo(() => {
        const server = onboardingState?.seen_page_guides ?? [];
        return Array.from(new Set([...server, ...localSeen]));
    }, [onboardingState, localSeen]);

    const markGuideSeen = useCallback((routeName: string) => {
        setLocalSeen((prev) => (prev.includes(routeName) ? prev : [...prev, routeName]));
        api.markGuideSeen(routeName).catch(() => {
            // Non-critical UX state — never surface an error for this.
        });
    }, []);

    const startPageGuide = useCallback((routeName: string, seenAs?: string) => {
        const guide = getPageGuide(routeName);
        if (!guide) return;
        markGuideSeen(seenAs ?? routeName);
        setActivePageGuide({ route: routeName, guide });
    }, [markGuideSeen]);

    const endPageGuide = useCallback(() => {
        setActivePageGuide(null);
    }, []);

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

    const isOpen = phase === 'welcome' || phase === 'touring' || activePageGuide !== null;

    const value: OnboardingContextValue = {
        isOpen,
        tourConfig,
        phase,
        currentPageIndex,
        resumeStepIndex,
        startTour,
        endTour,
        setCurrentPageIndex,
        clearResumeStep,
        dismissRemindLater,
        activePageGuide,
        startPageGuide,
        endPageGuide,
        seenGuides,
        markGuideSeen,
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
