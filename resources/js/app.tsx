import '../css/app.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { lazy, useState, useEffect } from 'react';
import { createRoot } from 'react-dom/client';
import ErrorBoundary from '@/Components/ErrorBoundary';
import ToastProvider from '@/Components/ToastProvider';
import OnboardingProvider from '@/Onboarding/OnboardingProvider';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';

const globalWithReactRoots = globalThis as typeof globalThis & {
    __oneWindowReactRoots?: WeakMap<HTMLElement, ReturnType<typeof createRoot>>;
};

const reactRoots = globalWithReactRoots.__oneWindowReactRoots ??= new WeakMap<HTMLElement, ReturnType<typeof createRoot>>();

const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            staleTime: 5 * 60 * 1000,
            gcTime: 30 * 60 * 1000,
            retry: 1,
            refetchOnWindowFocus: false,
        },
    },
});

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

/**
 * Lazy DevTools component — only loaded in DEV mode.
 * Static import of @tanstack/react-query-devtools causes build issues
 * because it depends on Node.js modules that get externalized with an empty name.
 */
const DevTools = lazy(() =>
    import('@tanstack/react-query-devtools').then((m) => ({
        default: () => <m.ReactQueryDevtools initialIsOpen={false} buttonPosition="bottom-left" />,
    }))
);

/**
 * Wraps the app tree, feeding OnboardingProvider with the current page's
 * onboarding_required prop. OnboardingProvider lives above <App> in the
 * React tree (outside the Inertia page context), so we use Inertia's
 * global 'success' event to pick up the value from each page navigation.
 */
function AppWithOnboarding({ App, appProps }) {
    const [onboardingRequired, setOnboardingRequired] = useState(
        (appProps.initialPage.props as Record<string, unknown>).onboarding_required,
    );

    useEffect(() => {
        const removeListener = router.on('success', (event) => {
            const page = event.detail?.page;
            if (page?.props?.onboarding_required !== undefined) {
                setOnboardingRequired(page.props.onboarding_required);
            }
        });
        return () => {
            if (typeof removeListener === 'function') removeListener();
        };
    }, []);

    useEffect(() => {
        const removeListener = router.on('exception', (event) => {
            const exception = event.detail?.exception;
            console.warn('[Inertia] Navigation exception', {
                message: exception?.message,
                name: exception?.name,
            });
        });
        return () => {
            if (typeof removeListener === 'function') removeListener();
        };
    }, []);

    return (
        <OnboardingProvider onboardingRequired={onboardingRequired}>
            <QueryClientProvider client={queryClient}>
                <ToastProvider>
                    <App {...appProps} />
                </ToastProvider>
                {/* DevTools hidden — remove comment to restore <DevTools /> */}
            </QueryClientProvider>
        </OnboardingProvider>
    );
}

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.{jsx,tsx}');
        const page = pages[`./Pages/${name}.jsx`] ?? pages[`./Pages/${name}.tsx`];
        if (!page) {
            throw new Error(`Page not found: ${name}`);
        }
        return page();
    },
    setup({ el, App, props }) {
        let root = reactRoots.get(el);
        if (!root) {
            root = createRoot(el);
            reactRoots.set(el, root);
        }
        root.render(
            <ErrorBoundary>
                <AppWithOnboarding App={App} appProps={props} />
            </ErrorBoundary>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});
