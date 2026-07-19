import '@testing-library/jest-dom/vitest';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Index from '../Index.jsx';

vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }) => <main>{children}</main> }));
vi.mock('@inertiajs/react', () => ({ Head: () => null, router: {} }));

describe('LogViewer filters', () => {
    beforeEach(() => {
        globalThis.route = vi.fn((name) => name.includes('entries') ? '/logs/entries' : '/logs/download');
    });

    it('resets the page when a filter changes and aborts the superseded request', async () => {
        const requests = [];
        globalThis.fetch = vi.fn((url, options) => {
            requests.push({ url, signal: options.signal });
            return Promise.resolve({
                json: () => Promise.resolve({ entries: [], total: 0, per_page: 50, current_page: 1, last_page: 1 }),
            });
        });

        render(<Index dates={['2026-05-31']} />);
        await waitFor(() => expect(globalThis.fetch).toHaveBeenCalledTimes(1));

        fireEvent.change(screen.getByPlaceholderText('Search messages...'), { target: { value: 'database' } });

        await waitFor(() => expect(globalThis.fetch).toHaveBeenCalledTimes(2));
        expect(requests[0].signal.aborted).toBe(true);
        expect(requests[1].url).toContain('page=1');
        expect(requests[1].url).toContain('search=database');
    });

    it('shows the explicit unavailable indicator when no log source is available', async () => {
        globalThis.fetch = vi.fn(() => Promise.resolve({
            json: () => Promise.resolve({
                entries: [],
                total: 0,
                per_page: 50,
                current_page: 1,
                last_page: 1,
                source_available: false,
                unavailable_reason: 'Log source unavailable.',
            }),
        }));

        render(<Index dates={[]} />);

        await waitFor(() => expect(screen.getByText('Log source unavailable.')).toBeInTheDocument());
    });
});
