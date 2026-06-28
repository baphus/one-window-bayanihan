import { useEffect, useRef } from 'react';
import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';
import { router, usePage } from '@inertiajs/react';
import { useOnboarding } from './OnboardingProvider';
import { useToast } from '@/Hooks/useToast';
import { completeOnboarding } from './api';

export default function TourManager() {
    const { phase, tourConfig, endTour, currentPageIndex, advancePage } = useOnboarding();
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

        const page = tourConfig.pages[currentPageIndex];
        if (!page) {
            endTour();
            return;
        }

        const expectedPath = route(page.route);
        const currentPath = url.split('?')[0];
        if (currentPath !== new URL(expectedPath, window.location.origin).pathname) {
            router.visit(expectedPath, { preserveState: false });
            return;
        }

        const steps = page.steps.map((step) => ({
            element: step.element,
            popover: {
                title: step.title,
                description: step.description,
                side: step.side ?? ('bottom' as const),
                align: step.align ?? ('center' as const),
            },
        }));

        if (steps.length === 0) {
            if (currentPageIndex < tourConfig.pages.length - 1) {
                const nextPage = tourConfig.pages[currentPageIndex + 1];
                router.visit(route(nextPage.route), {
                    preserveState: false,
                    onFinish: () => {
                        requestAnimationFrame(() => {
                            advancePage();
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
                    } else {
                        const nextPage = tourConfig.pages[currentPageIndex + 1];
                        const removeListener = router.on('success', () => {
                            if (typeof removeListener === 'function') removeListener();
                            requestAnimationFrame(() => {
                                advancePage();
                            });
                        });
                        router.visit(route(nextPage.route), {
                            preserveState: false,
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
    }, [phase, tourConfig, endTour, toast, currentPageIndex, advancePage, url]);

    return null;
}
