import { useEffect, useRef } from 'react';
import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';
import { useOnboarding } from './OnboardingProvider';
import { useToast } from '@/Hooks/useToast';
import { completeOnboarding } from './api';

/**
 * TourManager — subscribes to OnboardingProvider and initializes Driver.js
 * when the phase transitions to 'touring'.
 *
 * It flattens all steps from all pages of the TourConfig into a single
 * Driver.js step list and drives the tour.
 *
 * - "Done" on the last step → calls completeOnboarding() API + toast
 * - "Close" on any step → just calls endTour() (no API)
 * - "Skip Tour" button (injected via onPopoverRender) → just calls endTour()
 * - Component unmount / phase change → cleans up without side effects
 */
export default function TourManager() {
    const { phase, tourConfig, endTour } = useOnboarding();
    const driverRef = useRef<ReturnType<typeof driver> | null>(null);
    const toast = useToast();

    /**
     * Separate ref from the closure-bound `cleaningUp` variable so that
     * the onDestroyed callback can distinguish a programmatic destroy
     * (user clicks Done / Close / Skip) from a React cleanup-triggered destroy.
     */
    const cleaningUpRef = useRef(false);

    useEffect(() => {
        // ── No active tour → destroy any lingering driver ──────────────
        if (phase !== 'touring' || !tourConfig) {
            if (driverRef.current) {
                driverRef.current.destroy();
                driverRef.current = null;
            }
            return;
        }

        // ── Flatten all steps from all pages ───────────────────────────
        // All current role configs only have one page (dashboard), but the
        // TourConfig type supports multiple pages for future multi-page tours.
        const steps = tourConfig.pages.flatMap((page) =>
            page.steps.map((step) => ({
                element: step.element,
                popover: {
                    title: step.title,
                    description: step.description,
                    side: step.side ?? ('bottom' as const),
                    align: step.align ?? ('center' as const),
                },
            })),
        );

        if (steps.length === 0) {
            endTour();
            return;
        }

        // ── Closure-scoped flags ───────────────────────────────────────
        let reachedLastStep = false;
        let wasClosedOrSkipped = false;

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

            // ── Fires when the close (X) button is clicked ─────────────
            onCloseClick: () => {
                wasClosedOrSkipped = true;
            },

            // ── Fires just before the driver is destroyed ──────────────
            // At this point the driver state is still intact, so isLastStep()
            // returns the correct value. Use it + the close/skip flag to
            // decide whether the user completed the tour or cancelled it.
            onDestroyStarted: () => {
                if (!wasClosedOrSkipped && driverObj.isLastStep()) {
                    reachedLastStep = true;
                }
            },

            // ── Fires after destroy — perform side effects ─────────────
            onDestroyed: () => {
                driverRef.current = null;

                // Ignore side effects during React cleanup
                if (cleaningUpRef.current) return;

                if (reachedLastStep) {
                    // User clicked "Done" on the final step
                    completeOnboarding()
                        .then(() => {
                            toast.success('Tour complete!');
                            endTour();
                        })
                        .catch(() => {
                            // API failed — still end the tour locally
                            endTour();
                        });
                } else {
                    // User closed or skipped the tour
                    endTour();
                }
            },

            // ── Inject a "Skip Tour" button into each popover ──────────
            onPopoverRender: (popover, opts) => {
                if (popover.footer.querySelector('.driver-popover-skip-btn')) {
                    return;
                }

                const skipBtn = document.createElement('button');
                skipBtn.textContent = 'Skip Tour';
                skipBtn.className = 'driver-popover-skip-btn';
                skipBtn.onclick = () => {
                    wasClosedOrSkipped = true;
                    opts.driver.destroy();
                };

                popover.footer.appendChild(skipBtn);
            },
        });

        driverRef.current = driverObj;
        driverObj.drive();

        // ── Cleanup on unmount or dependency change ────────────────────
        return () => {
            cleaningUpRef.current = true;

            if (driverRef.current) {
                // onDestroyStarted → onDestroyed fire synchronously here, but
                // cleaningUpRef.current is already true so side effects are skipped.
                driverRef.current.destroy();
                driverRef.current = null;
            }

            cleaningUpRef.current = false;
        };
    }, [phase, tourConfig, endTour, toast]);

    return null;
}
