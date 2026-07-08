import { useEffect, useRef } from 'react';
import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';
import { usePage } from '@inertiajs/react';
import { route } from 'ziggy-js';
import { useOnboarding } from './OnboardingProvider';
import { useToast } from '@/Hooks/useToast';
import { completeOnboarding } from './api';

export default function TourManager() {
    const { phase, tourConfig, endTour, currentPageIndex, setCurrentPageIndex } = useOnboarding();
    const driverRef = useRef<ReturnType<typeof driver> | null>(null);
    const toast = useToast();
    const cleaningUpRef = useRef(false);
    const { url } = usePage();

    useEffect(() => {
        if (phase !== 'touring' || !tourConfig) {
            if (driverRef.current) {
                driverRef.current.destroy();
                driverRef.current = null;
            }
            return;
        }

        // Find which page in the tour config matches the current URL.
        // The tour follows the user as they navigate — no forced navigation.
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
        // The tour stays active and will pick up when the user navigates to a tour page.
        if (matchedIndex < 0) {
            if (driverRef.current) {
                driverRef.current.destroy();
                driverRef.current = null;
            }
            return;
        }

        // Sync the context index with the URL-matched index if needed.
        // This triggers a re-render so the effect re-runs with the correct index.
        if (matchedIndex !== currentPageIndex) {
            setCurrentPageIndex(matchedIndex);
            return;
        }

        const page = tourConfig.pages[matchedIndex];
        const steps = page.steps.map((step) => ({
            element: step.element,
            popover: {
                title: step.title,
                description: step.description,
                side: step.side ?? ('bottom' as const),
                align: step.align ?? ('center' as const),
            },
        }));

        const isLastPage = matchedIndex === tourConfig.pages.length - 1;
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
                                toast.success('Tour complete!');
                                endTour();
                            })
                            .catch(() => {
                                endTour();
                            });
                    }
                    // Not last page: user completed this page's steps.
                    // No auto-navigation — the tour stays active and will
                    // re-match when the user navigates naturally.
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
    }, [phase, tourConfig, endTour, toast, currentPageIndex, setCurrentPageIndex, url]);

    return null;
}
