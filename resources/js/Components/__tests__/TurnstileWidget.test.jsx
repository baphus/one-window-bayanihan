import { render, act } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({
        props: { turnstile: { enabled: true, site_key: 'test-site-key' } },
    }),
}));

import TurnstileWidget from '@/Components/TurnstileWidget';

const SCRIPT_ID = 'cf-turnstile-script';

function stubTurnstile(widgetId) {
    const api = {
        render: vi.fn(() => widgetId),
        remove: vi.fn(),
    };
    window.turnstile = api;

    return api;
}

function clearScriptTag() {
    document.getElementById(SCRIPT_ID)?.remove();
}

describe('TurnstileWidget', () => {
    beforeEach(() => {
        delete window.turnstile;
        clearScriptTag();
    });

    afterEach(() => {
        vi.useRealTimers();
        delete window.turnstile;
        clearScriptTag();
    });

    // The regression this file exists for. The effect had four exit paths and
    // only one returned the cleanup, so a widget rendered against an
    // already-loaded API was never unregistered. Cloudflare then logs
    // "Cannot find Widget <id>, consider using turnstile.remove()" and the
    // orphan keeps its slot in the widget registry.
    it('removes the widget on unmount when the API is already loaded', () => {
        const api = stubTurnstile('widget-already-loaded');

        const { unmount } = render(<TurnstileWidget onToken={vi.fn()} onExpire={vi.fn()} />);
        expect(api.render).toHaveBeenCalledTimes(1);

        unmount();

        expect(api.remove).toHaveBeenCalledWith('widget-already-loaded');
    });

    it('removes the widget on unmount when the API arrives while polling', async () => {
        vi.useFakeTimers();

        // A script tag already in the document but no window.turnstile yet is
        // the branch that polls rather than attaching its own onload handler.
        const script = document.createElement('script');
        script.id = SCRIPT_ID;
        document.head.appendChild(script);

        const { unmount } = render(<TurnstileWidget onToken={vi.fn()} onExpire={vi.fn()} />);

        const api = stubTurnstile('widget-polled');
        await act(async () => {
            await vi.advanceTimersByTimeAsync(150);
        });

        expect(api.render).toHaveBeenCalledTimes(1);

        unmount();

        expect(api.remove).toHaveBeenCalledWith('widget-polled');
    });

    it('removes the widget on unmount when it loaded the script itself', () => {
        const { unmount } = render(<TurnstileWidget onToken={vi.fn()} onExpire={vi.fn()} />);

        const injected = document.getElementById(SCRIPT_ID);
        expect(injected).not.toBeNull();

        const api = stubTurnstile('widget-script-loaded');
        act(() => {
            injected.onload();
        });
        expect(api.render).toHaveBeenCalledTimes(1);

        unmount();

        expect(api.remove).toHaveBeenCalledWith('widget-script-loaded');
    });

    // EmailStep unmounts the widget when `otpSent` flips and remounts it on
    // "Back", so the mount/unmount/mount cycle is the normal path, not an edge
    // case. A stale widget id would make the second mount a no-op.
    it('renders a fresh widget after an unmount/remount cycle', () => {
        const api = stubTurnstile('widget-first');

        const first = render(<TurnstileWidget onToken={vi.fn()} onExpire={vi.fn()} />);
        first.unmount();

        api.render.mockReturnValue('widget-second');
        const second = render(<TurnstileWidget onToken={vi.fn()} onExpire={vi.fn()} />);

        expect(api.render).toHaveBeenCalledTimes(2);

        second.unmount();

        expect(api.remove).toHaveBeenNthCalledWith(1, 'widget-first');
        expect(api.remove).toHaveBeenNthCalledWith(2, 'widget-second');
    });

    // Cloudflare returns undefined from render() when it cannot render at all
    // (bad sitekey, container already occupied). A strict !== null guard would
    // treat that as a live id and hand remove(undefined) back to Cloudflare —
    // emitting the exact console error this component was fixed to stop.
    it('does not call remove when the widget failed to render', () => {
        const api = stubTurnstile(undefined);

        const { unmount } = render(<TurnstileWidget onToken={vi.fn()} onExpire={vi.fn()} />);
        expect(api.render).toHaveBeenCalledTimes(1);

        unmount();

        expect(api.remove).not.toHaveBeenCalled();
    });

    it('renders normally on a later mount after a failed render', () => {
        const api = stubTurnstile(undefined);

        const first = render(<TurnstileWidget onToken={vi.fn()} onExpire={vi.fn()} />);
        first.unmount();

        api.render.mockReturnValue('widget-recovered');
        const second = render(<TurnstileWidget onToken={vi.fn()} onExpire={vi.fn()} />);
        second.unmount();

        expect(api.render).toHaveBeenCalledTimes(2);
        expect(api.remove).toHaveBeenCalledExactlyOnceWith('widget-recovered');
    });

    // If the API object disappears before unmount (script removed, SPA
    // teardown) there is nothing to unregister — but the id must still be
    // cleared, or the next mount early-returns and latches the widget off.
    it('clears the widget id even when the API has gone at unmount', () => {
        const api = stubTurnstile('widget-orphaned');

        const first = render(<TurnstileWidget onToken={vi.fn()} onExpire={vi.fn()} />);
        delete window.turnstile;
        first.unmount();

        window.turnstile = api;
        const second = render(<TurnstileWidget onToken={vi.fn()} onExpire={vi.fn()} />);

        expect(api.render).toHaveBeenCalledTimes(2);

        second.unmount();
    });

    it('stops polling on unmount so a late API load cannot render an orphan', async () => {
        vi.useFakeTimers();

        const script = document.createElement('script');
        script.id = SCRIPT_ID;
        document.head.appendChild(script);

        const { unmount } = render(<TurnstileWidget onToken={vi.fn()} onExpire={vi.fn()} />);
        unmount();

        const api = stubTurnstile('widget-too-late');
        await act(async () => {
            await vi.advanceTimersByTimeAsync(500);
        });

        expect(api.render).not.toHaveBeenCalled();
    });
});
