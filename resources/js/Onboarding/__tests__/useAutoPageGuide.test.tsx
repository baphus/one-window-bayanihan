import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render } from '@testing-library/react';
import { usePage } from '@inertiajs/react';
import useAutoPageGuide from '../useAutoPageGuide';

// ── Mocks ──────────────────────────────────────────────────────────────

vi.mock('@inertiajs/react', () => ({
    usePage: vi.fn(),
}));

const mockStartPageGuide = vi.fn();
const mockUseOnboardingOptional = vi.fn();

vi.mock('../OnboardingProvider', () => ({
    useOnboardingOptional: () => mockUseOnboardingOptional(),
}));

const mockRoute = { current: vi.fn() };
vi.mock('ziggy-js', () => ({
    route: () => mockRoute,
}));

const DASHBOARD_GUIDE = {
    title: 'Dashboard',
    helpdeskSlug: 'using-dashboard-daily-monitoring',
    steps: [
        { element: '[data-tour="dashboard-header"]', title: 'Your Start', description: 'desc', side: 'bottom' },
        { element: '[data-tour="dashboard-stats"]', title: 'Stats', description: 'desc', side: 'bottom' },
        { element: '[data-tour="dashboard-work-queue"]', title: 'Queue', description: 'desc', side: 'bottom' },
    ],
};

vi.mock('../registry', () => ({
    getPageGuide: vi.fn((routeName: string) =>
        routeName === 'dashboard' ? DASHBOARD_GUIDE : null,
    ),
}));

// ── Helpers ────────────────────────────────────────────────────────────

function TestComponent() {
    useAutoPageGuide();
    return <div>test</div>;
}

function setPageProps(overrides: Record<string, unknown> = {}) {
    const mockPage = vi.mocked(usePage);
    mockPage.mockReturnValue({
        url: '/dashboard',
        props: {
            auth: { user: { role: 'CASE_MANAGER' } },
            ...overrides,
        },
    } as never);
}

function setRouteName(name: string | null) {
    mockRoute.current = vi.fn(() => name);
}

function setOnboarding(overrides: Record<string, unknown> = {}) {
    mockUseOnboardingOptional.mockReturnValue({
        phase: 'idle',
        seenGuides: [],
        startPageGuide: mockStartPageGuide,
        ...overrides,
    });
}

beforeEach(() => {
    vi.clearAllMocks();
    setPageProps();
    setRouteName('dashboard');
    setOnboarding();
    document.body.innerHTML = '';
});

afterEach(() => {
    document.body.innerHTML = '';
});

// ── Tests ──────────────────────────────────────────────────────────────

describe('useAutoPageGuide', () => {
    it('does nothing when onboarding context is absent', () => {
        mockUseOnboardingOptional.mockReturnValue(null);
        render(<TestComponent />);
        expect(mockStartPageGuide).not.toHaveBeenCalled();
    });

    it('does nothing for non-CASE_MANAGER roles', () => {
        setPageProps({ auth: { user: { role: 'AGENCY' } } });
        render(<TestComponent />);
        expect(mockStartPageGuide).not.toHaveBeenCalled();
    });

    it('does nothing during welcome tour phase', () => {
        setOnboarding({ phase: 'welcome' });
        render(<TestComponent />);
        expect(mockStartPageGuide).not.toHaveBeenCalled();
    });

    it('does nothing during touring phase', () => {
        setOnboarding({ phase: 'touring' });
        render(<TestComponent />);
        expect(mockStartPageGuide).not.toHaveBeenCalled();
    });

    it('does nothing for routes without a registered guide', () => {
        setRouteName('nonexistent');
        render(<TestComponent />);
        expect(mockStartPageGuide).not.toHaveBeenCalled();
    });

    it('does nothing if the guide is already seen via qualified key', () => {
        setOnboarding({ seenGuides: ['CASE_MANAGER:dashboard'] });
        render(<TestComponent />);
        expect(mockStartPageGuide).not.toHaveBeenCalled();
    });

    it('does nothing if the guide is already seen via legacy plain route name', () => {
        setOnboarding({ seenGuides: ['dashboard'] });
        render(<TestComponent />);
        expect(mockStartPageGuide).not.toHaveBeenCalled();
    });

    it('launches the guide when anchors are present on first visit', () => {
        document.body.innerHTML = '<div data-tour="dashboard-header"></div>';
        render(<TestComponent />);
        expect(mockStartPageGuide).toHaveBeenCalledTimes(1);
        expect(mockStartPageGuide).toHaveBeenCalledWith('dashboard', 'CASE_MANAGER:dashboard');
    });

    it('defers launch when anchors are absent and launches once they appear', () => {
        render(<TestComponent />);
        expect(mockStartPageGuide).not.toHaveBeenCalled();

        const el = document.createElement('div');
        el.setAttribute('data-tour', 'dashboard-header');
        document.body.appendChild(el);

        return new Promise<void>((resolve) => {
            setTimeout(() => {
                expect(mockStartPageGuide).toHaveBeenCalledTimes(1);
                expect(mockStartPageGuide).toHaveBeenCalledWith('dashboard', 'CASE_MANAGER:dashboard');
                resolve();
            }, 100);
        });
    });

    it('launches only once per qualified key despite re-renders', () => {
        document.body.innerHTML = '<div data-tour="dashboard-header"></div>';
        const { rerender } = render(<TestComponent />);
        expect(mockStartPageGuide).toHaveBeenCalledTimes(1);

        vi.mocked(usePage).mockReturnValue({
            url: '/dashboard',
            props: { auth: { user: { role: 'CASE_MANAGER' } } },
        } as never);
        rerender(<TestComponent />);
        expect(mockStartPageGuide).toHaveBeenCalledTimes(1);
    });

    it('respects legacy seen key even after role-qualified key is added', () => {
        setOnboarding({ seenGuides: ['CASE_MANAGER:dashboard', 'some-other-page'] });
        document.body.innerHTML = '<div data-tour="dashboard-header"></div>';
        render(<TestComponent />);
        expect(mockStartPageGuide).not.toHaveBeenCalled();
    });
});
