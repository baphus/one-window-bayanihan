import { useEffect, useRef } from 'react';
import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';
import { router, usePage } from '@inertiajs/react';
import { route } from 'ziggy-js';
import { useOnboardingOptional } from './OnboardingProvider';
import { useToast } from '@/Hooks/useToast';
import { completeOnboarding, skipOnboarding, updateStep } from './api';
import { TourStep } from './types';

/** Popover step shape passed to driver.js */
interface DriveStep {
    element: string;
    popover: {
        title: string;
        description: string;
        side: 'top' | 'bottom' | 'left' | 'right';
        align: 'start' | 'center' | 'end';
    };
}

/**
 * Keep only steps whose target element is present in the DOM. Pages with
 * empty states or permission-trimmed sections simply skip those steps.
 * Returns pairs of [driveStep, originalIndex] so persistence and resume
 * always speak in full-config indexes.
 */
function presentSteps(steps: TourStep[]): { drive: DriveStep[]; originalIndexes: number[] } {
    const drive: DriveStep[] = [];
    const originalIndexes: number[] = [];

    steps.forEach((step, index) => {
        if (!document.querySelector(step.element)) {
            if (import.meta.env.DEV) {
                console.warn(`[Onboarding] Skipping step with missing anchor: ${step.element}`);
            }
            return;
        }
        drive.push({
            element: step.element,
            popover: {
                title: step.title,
                description: step.description,
                side: step.side ?? 'bottom',
                align: step.align ?? 'center',
            },
        });
        originalIndexes.push(index);
    });

    return { drive, originalIndexes };
}

const BASE_DRIVER_OPTIONS = {
    animate: true,
    overlayOpacity: 0.75,
    stagePadding: 10,
    allowClose: true,
    overlayClickBehavior: 'close' as const,
    showProgress: true,
    progressText: '{{current}} of {{total}}',
    nextBtnText: 'Next',
    prevBtnText: 'Previous',
};

const noop = () => {};

export default function TourManager(): null {
    // Optional so layouts rendered without the app shell (tests) no-op.
    const onboarding = useOnboardingOptional();
    const phase = onboarding?.phase ?? 'idle';
    const tourConfig = onboarding?.tourConfig ?? null;
    const endTour = onboarding?.endTour ?? noop;
    const currentPageIndex = onboarding?.currentPageIndex ?? 0;
    const setCurrentPageIndex = onboarding?.setCurrentPageIndex ?? noop;
    const resumeStepIndex = onboarding?.resumeStepIndex ?? null;
    const clearResumeStep = onboarding?.clearResumeStep ?? noop;
    const activePageGuide = onboarding?.activePageGuide ?? null;
    const endPageGuide = onboarding?.endPageGuide ?? noop;
    const driverRef = useRef<ReturnType<typeof driver> | null>(null);
    // Toast is a nice-to-have on tour completion; layouts rendered without
    // the app shell (Helpdesk pages in tests) have no ToastProvider.
    let toastSafe: { success: (msg: string) => void };
    try {
        toastSafe = useToast();
    } catch {
        toastSafe = { success: noop };
    }
    const toast = toastSafe;
    const cleaningUpRef = useRef(false);
    const { url } = usePage();

    // ── Layer 1: welcome tour ────────────────────────────────────────
    useEffect(() => {
        if (phase !== 'touring' || !tourConfig) {
            return;
        }

        // Find which page in the tour config matches the current URL.
        const currentPath = url.split('?')[0];
        const matchedIndex = tourConfig.pages.findIndex((p) => {
            try {
                const pageUrl = route(p.route);
                const pagePath = pageUrl.startsWith('http')
                    ? new URL(pageUrl).pathname
                    : pageUrl;
                return currentPath === pagePath;
            } catch {
                return false;
            }
        });

        // If the current page isn't in the tour config, don't show any overlay.
        // The tour stays active and picks up when the user reaches a tour page.
        if (matchedIndex < 0) {
            if (driverRef.current) {
                driverRef.current.destroy();
                driverRef.current = null;
            }
            return;
        }

        // Sync the context index with the URL-matched index if needed.
        if (matchedIndex !== currentPageIndex) {
            setCurrentPageIndex(matchedIndex);
            return;
        }

        const page = tourConfig.pages[matchedIndex];
        const { drive: steps, originalIndexes } = presentSteps(page.steps);
        if (steps.length === 0) {
            return;
        }

        const isLastPage = matchedIndex === tourConfig.pages.length - 1;
        const nextPage = isLastPage ? null : tourConfig.pages[matchedIndex + 1];
        let userCompleted = false;

        // Map a resume position (full-config step index) to the filtered list.
        let startAt = 0;
        if (resumeStepIndex !== null) {
            const filtered = originalIndexes.findIndex((i) => i >= resumeStepIndex);
            startAt = filtered >= 0 ? filtered : 0;
            clearResumeStep();
        }

        const driverObj = driver({
            ...BASE_DRIVER_OPTIONS,
            showButtons: ['next', 'previous', 'close'],
            doneBtnText: isLastPage ? 'Done' : `Next: ${nextPage!.title} →`,
            steps,

            onHighlighted: (_element, _step, opts) => {
                const active = opts.state.activeIndex ?? 0;
                const original = originalIndexes[active] ?? 0;
                updateStep(`${matchedIndex}:${original}`).catch(() => {
                    // Progress persistence is best-effort.
                });
            },

            onNextClick: (_element, _step, opts) => {
                if (opts.driver.hasNextStep()) {
                    opts.driver.moveNext();
                } else {
                    userCompleted = true;
                    opts.driver.destroy();
                }
            },

            onCloseClick: (_element, _step, opts) => {
                opts.driver.destroy();
            },

            onDestroyed: () => {
                driverRef.current = null;

                if (cleaningUpRef.current) return;

                if (userCompleted) {
                    if (isLastPage) {
                        completeOnboarding()
                            .then(() => {
                                toast.success('Tour complete! Use the ? button on any page for a refresher.');
                                endTour();
                            })
                            .catch(() => {
                                endTour();
                            });
                    } else if (nextPage) {
                        // Auto-navigate to the next tour page; the tour stays
                        // active and re-matches once the new URL renders.
                        router.visit(route(nextPage.route));
                    }
                } else {
                    endTour();
                }
            },

            onPopoverRender: (popover, opts) => {
                if (popover.footer.querySelector('.driver-popover-skip-btn')) {
                    return;
                }

                const skipBtn = document.createElement('button');
                skipBtn.textContent = 'Skip Tour';
                skipBtn.className = 'driver-popover-skip-btn';
                skipBtn.onclick = () => {
                    // Explicit skip: persist so the welcome modal stops nagging.
                    skipOnboarding().catch(() => {});
                    opts.driver.destroy();
                };

                popover.footer.appendChild(skipBtn);
            },
        });

        driverRef.current = driverObj;
        requestAnimationFrame(() => {
            if (driverRef.current === driverObj) {
                driverObj.drive(startAt);
            }
        });

        return () => {
            cleaningUpRef.current = true;

            if (driverRef.current) {
                driverRef.current.destroy();
                driverRef.current = null;
            }

            cleaningUpRef.current = false;
        };
        // resumeStepIndex/clearResumeStep are intentionally NOT dependencies:
        // consuming the one-shot resume index inside the effect must not
        // re-run it (the re-run would rebuild the driver at step 0 and undo
        // the resume). startTour changes phase/pageIndex, which re-runs the
        // effect when a new resume position is set.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [phase, tourConfig, endTour, toast, currentPageIndex, setCurrentPageIndex, url]);

    // ── Layer 2: per-page guide ──────────────────────────────────────
    useEffect(() => {
        // The welcome tour owns the overlay while it is active.
        if (!activePageGuide || phase === 'touring') {
            return;
        }

        // Page guides are scoped to the page they were opened on. If the
        // user navigated away (back/forward, link) while one was active,
        // close it instead of re-driving its steps on a foreign page.
        let currentRoute: string | null = null;
        try {
            currentRoute = route().current() ?? null;
        } catch {
            currentRoute = null;
        }
        if (currentRoute !== activePageGuide.route) {
            endPageGuide();
            return;
        }

        const { guide } = activePageGuide;
        const { drive: steps } = presentSteps(guide.steps);
        if (steps.length === 0) {
            endPageGuide();
            return;
        }

        // Append a "Read more" link to the final step when the guide maps
        // to a Helpdesk article. driver.js renders descriptions as HTML.
        if (guide.helpdeskSlug) {
            const last = steps[steps.length - 1];
            let articleUrl = `/help/${guide.helpdeskSlug}`;
            try {
                articleUrl = route('helpdesk.show', { slug: guide.helpdeskSlug });
            } catch {
                // Fall back to the literal path if Ziggy can't resolve.
            }
            last.popover.description += `<div class="driver-popover-readmore"><a href="${articleUrl}">Read more in the Help Center →</a></div>`;
        }

        let finished = false;

        const driverObj = driver({
            ...BASE_DRIVER_OPTIONS,
            showButtons: ['next', 'previous', 'close'],
            doneBtnText: 'Done',
            steps,

            onNextClick: (_element, _step, opts) => {
                if (opts.driver.hasNextStep()) {
                    opts.driver.moveNext();
                } else {
                    finished = true;
                    opts.driver.destroy();
                }
            },

            onCloseClick: (_element, _step, opts) => {
                opts.driver.destroy();
            },

            onDestroyed: () => {
                driverRef.current = null;
                if (cleaningUpRef.current) return;
                void finished;
                endPageGuide();
            },
        });

        driverRef.current = driverObj;
        requestAnimationFrame(() => {
            if (driverRef.current === driverObj) {
                driverObj.drive();
            }
        });

        return () => {
            cleaningUpRef.current = true;

            if (driverRef.current) {
                driverRef.current.destroy();
                driverRef.current = null;
            }

            cleaningUpRef.current = false;
        };
    }, [activePageGuide, phase, endPageGuide, url]);

    return null;
}
