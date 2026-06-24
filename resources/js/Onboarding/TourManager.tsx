import { useEffect, useRef, useState } from 'react';
import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';
import { router } from '@inertiajs/react';
import { useOnboarding } from './OnboardingProvider';
import { useToast } from '@/Hooks/useToast';
import { completeOnboarding } from './api';

/**
 * TourManager — subscribes to OnboardingProvider and initializes Driver.js
 * when the phase transitions to 'touring'.
 *
 * Supports multi-page tours: for each page in pages[], it passes only that
 * page's steps to Driver.js. On the last step of a non-final page it calls
 * router.visit() to navigate to the next page, then re-initializes Driver.js
 * with the next page's steps. On the last step of the final page it calls
 * completeOnboarding() as before.
 *
 * - Single-page configs work as before — Done → completeOnboarding() API + toast
 * - Multi-page configs visit each page's route in order
 * - "Close" / "Skip Tour" on any step just calls endTour()
 * - Component unmount / phase change → cleans up without side effects
 */
export default function TourManager() {
    const { phase, tourConfig, endTour } = useOnboarding();
    const driverRef = useRef<ReturnType<typeof driver> | null>(null);
    const [currentPageIndex, setCurrentPageIndex] = useState(0);
    const toast = useToast();

    /**
     * Separate ref from the closure-bound `cleaningUp` variable so that
     * the onDestroyed callback can distinguish a programmatic destroy
     * (user clicks Done / Close / Skip) from a React cleanup-triggered destroy.
     */
    const cleaningUpRef = useRef(false);

    // ── Reset page index when a brand-new tour starts ──────────────
    useEffect(() => {
        if (phase === 'touring' && tourConfig) {
            setCurrentPageIndex(0);
        }
    }, [phase, tourConfig]);

    useEffect(() => {
        // ── No active tour → destroy any lingering driver ──────────────
        if (phase !== 'touring' || !tourConfig) {
            if (driverRef.current) {
                driverRef.current.destroy();
                driverRef.current = null;
            }
            return;
        }

        // ── Grab the current page; bail if it doesn't exist ────────────
        const page = tourConfig.pages[currentPageIndex];
        if (!page) {
            endTour();
            return;
        }

        // ── Only steps for the current page ────────────────────────────
        const steps = page.steps.map((step) => ({
            element: step.element,
            popover: {
                title: step.title,
                description: step.description,
                side: step.side ?? ('bottom' as const),
                align: step.align ?? ('center' as const),
            },
        }));

        // ── Empty page — skip past it ──────────────────────────────────
        if (steps.length === 0) {
            if (currentPageIndex < tourConfig.pages.length - 1) {
                const nextPage = tourConfig.pages[currentPageIndex + 1];
                router.visit(route(nextPage.route), {
                    preserveState: false,
                    onFinish: () => {
                        requestAnimationFrame(() => {
                            setCurrentPageIndex((i) => i + 1);
                        });
                    },
                });
            } else {
                endTour();
            }
            return;
        }

        const isLastPage = currentPageIndex === tourConfig.pages.length - 1;

        let userCompleted = false;

        const driverObj = driver({
            animate: true,
            overlayOpacity: 0.75,
            stagePadding: 10,
            allowClose: true,
            overlayClickBehavior: 'close',
            showButtons: ['next', 'previous', 'close'],
            doneBtnText: 'Done',
            nextBtnText: 'Next',
            prevBtnText: 'Previous',
            steps,

            onNextClick: (_element, _step, opts) => {
                if (opts.driver.hasNextStep()) {
                    opts.driver.moveNext();
                } else {
                    userCompleted = true;
                    opts.driver.destroy();
                }
            },

            onDestroyed: () => {
                driverRef.current = null;

                if (cleaningUpRef.current) return;

                if (userCompleted) {
                    if (isLastPage) {
                        completeOnboarding()
                            .then(() => {
                                toast.success('Tour complete!');
                                endTour();
                            })
                            .catch(() => {
                                endTour();
                            });
                    } else {
                        const nextPage = tourConfig.pages[currentPageIndex + 1];
                        router.visit(route(nextPage.route), {
                            preserveState: false,
                            onFinish: () => {
                                requestAnimationFrame(() => {
                                    setCurrentPageIndex((i) => i + 1);
                                });
                            },
                        });
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
                    opts.driver.destroy();
                };

                popover.footer.appendChild(skipBtn);
            },
        });

        driverRef.current = driverObj;
        driverObj.drive();

        return () => {
            cleaningUpRef.current = true;

            if (driverRef.current) {
                driverRef.current.destroy();
                driverRef.current = null;
            }

            cleaningUpRef.current = false;
        };
    }, [phase, tourConfig, endTour, toast, currentPageIndex]);

    return null;
}
