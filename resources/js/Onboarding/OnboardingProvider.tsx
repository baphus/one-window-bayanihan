import { createContext, useContext, useState, useEffect, useCallback, ReactNode } from 'react';
import { TourConfig } from './types';

export type Phase = 'idle' | 'welcome' | 'touring' | 'complete';

export interface OnboardingContextValue {
    /** Whether any onboarding UI should be visible */
    isOpen: boolean;
    /** Zero-based index into the flattened step list across all pages */
    currentStep: number;
    /** The active tour configuration */
    tourConfig: TourConfig | null;
    /** Current phase of the onboarding state machine */
    phase: Phase;
    /** Begin a tour with the given config, starting at step 0 */
    startTour: (config: TourConfig) => void;
    /** End the current tour and reset to idle */
    endTour: () => void;
    /** Advance to the next step (clamped to bounds) */
    nextStep: () => void;
    /** Retreat to the previous step (clamped to bounds) */
    prevStep: () => void;
    /** Jump directly to a specific step index */
    goToStep: (n: number) => void;
    /** Dismiss onboarding for the remainder of this session */
    dismissRemindLater: () => void;
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
 * Returns the total number of steps across all pages in a tour config.
 */
function totalSteps(config: TourConfig | null): number {
    if (!config) return 0;
    return config.pages.reduce((acc, page) => acc + page.steps.length, 0);
}

export default function OnboardingProvider({ children, onboardingRequired }: { children: ReactNode; onboardingRequired?: unknown }) {

    const [phase, setPhase] = useState<Phase>('idle');
    const [currentStep, setCurrentStep] = useState<number>(0);
    const [tourConfig, setTourConfig] = useState<TourConfig | null>(null);

    // On mount, check sessionStorage and onboarding_required prop
    useEffect(() => {
        const dismissed = sessionStorage.getItem('onboarding_dismissed') === 'true';
        if (dismissed) return;

        if (onboardingRequired) {
            setPhase('welcome');
        }
    }, [onboardingRequired]);

    const startTour = useCallback((config: TourConfig) => {
        setTourConfig(config);
        setCurrentStep(0);
        setPhase('touring');
    }, []);

    const endTour = useCallback(() => {
        setPhase('idle');
        setTourConfig(null);
        setCurrentStep(0);
    }, []);

    const nextStep = useCallback(() => {
        setCurrentStep((prev) => {
            const max = totalSteps(tourConfig) - 1;
            return Math.min(prev + 1, max);
        });
    }, [tourConfig]);

    const prevStep = useCallback(() => {
        setCurrentStep((prev) => Math.max(prev - 1, 0));
    }, []);

    const goToStep = useCallback((n: number) => {
        setCurrentStep(n);
    }, []);

    const dismissRemindLater = useCallback(() => {
        sessionStorage.setItem('onboarding_dismissed', 'true');
        setPhase('idle');
    }, []);

    const isOpen = phase === 'welcome' || phase === 'touring';

    const value: OnboardingContextValue = {
        isOpen,
        currentStep,
        tourConfig,
        phase,
        startTour,
        endTour,
        nextStep,
        prevStep,
        goToStep,
        dismissRemindLater,
    };

    return (
        <OnboardingContext.Provider value={value}>
            {children}
        </OnboardingContext.Provider>
    );
}
