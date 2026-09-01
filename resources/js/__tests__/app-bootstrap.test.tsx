import { render, screen } from '@testing-library/react';
import * as Sentry from '@sentry/react';
import type { ComponentType, ReactNode } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import '../app';

const bootstrap = vi.hoisted(() => ({
    options: null as null | {
        setup: (args: {
            el: HTMLElement;
            App: ComponentType;
            props: { initialPage: { props: Record<string, unknown> } };
        }) => void;
    },
    renderRoot: vi.fn(),
    routerOn: vi.fn(() => vi.fn()),
}));

vi.mock('@inertiajs/react', () => ({
    App: (): null => null,
    createInertiaApp: vi.fn((options) => {
        bootstrap.options = options;
    }),
    router: { on: bootstrap.routerOn },
    usePage: vi.fn(() => {
        throw new Error('usePage must be used within the Inertia component');
    }),
}));

vi.mock('react-dom/client', () => ({
    createRoot: vi.fn(() => ({ render: bootstrap.renderRoot })),
}));

vi.mock('@sentry/react', () => ({
    captureException: vi.fn(),
    setTag: vi.fn(),
    setUser: vi.fn(),
}));

vi.mock('@/sentry', () => ({
    initSentry: vi.fn(),
    pageRequestId: vi.fn((value) => value ?? null),
}));

vi.mock('@/Components/ToastProvider', () => ({
    default: ({ children }: { children: ReactNode }) => children,
}));

vi.mock('@/Onboarding/OnboardingProvider', () => ({
    default: ({ children }: { children: ReactNode }) => children,
}));

vi.mock('@tanstack/react-query', () => ({
    QueryClient: class QueryClient {},
    QueryClientProvider: ({ children }: { children: ReactNode }) => children,
}));

beforeEach(() => {
    bootstrap.renderRoot.mockClear();
    bootstrap.routerOn.mockClear();
    vi.spyOn(console, 'error').mockImplementation(() => undefined);
});

describe('Inertia app bootstrap', () => {
    it('keeps request/user context tracking outside usePage consumers that lack Inertia context', () => {
        const PageApp = () => <div>Rendered Inertia page</div>;

        bootstrap.options?.setup({
            el: document.createElement('div'),
            App: PageApp,
            props: {
                initialPage: {
                    props: {
                        request_id: 'request-123',
                        auth: { user: { id: 'user-123', role: 'ADMIN' } },
                    },
                },
            },
        });

        expect(bootstrap.renderRoot).toHaveBeenCalledOnce();
        render(bootstrap.renderRoot.mock.calls[0][0]);

        expect(screen.getByText('Rendered Inertia page')).toBeInTheDocument();
        expect(screen.queryByText('Something went wrong')).not.toBeInTheDocument();
        expect(Sentry.setTag).toHaveBeenCalledWith('request_id', 'request-123');
        expect(Sentry.setUser).toHaveBeenCalledWith({ id: 'user-123' });
        expect(Sentry.setTag).toHaveBeenCalledWith('user_role', 'ADMIN');
    });
});
