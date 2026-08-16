import '@testing-library/jest-dom/vitest';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import ExportDialog from '../ExportDialog';

function todayMinus(days) {
    const d = new Date();
    d.setDate(d.getDate() - days);
    return d.toISOString().slice(0, 10);
}

describe('ExportDialog live row count', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('fetches the live count on open and shows the approximate row text', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ count: 42 }),
        });
        vi.stubGlobal('fetch', fetchMock);

        const countUrlBuilder = vi.fn((dateFrom, dateTo) => `/referrals/export-count?date_from=${dateFrom}&date_to=${dateTo}`);

        render(
            <ExportDialog
                open
                onClose={() => {}}
                title="Export Referrals"
                activeFilters={[{ label: 'Status', value: 'PENDING' }]}
                countUrlBuilder={countUrlBuilder}
                onExport={() => {}}
            />,
        );

        expect(await screen.findByText(/Approximately 42 rows will be exported/)).toBeInTheDocument();
        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(countUrlBuilder).toHaveBeenCalledTimes(1);
    });

    it('refetches the count when the date range changes', async () => {
        const fetchMock = vi.fn()
            .mockResolvedValueOnce({ ok: true, json: () => Promise.resolve({ count: 10 }) })
            .mockResolvedValueOnce({ ok: true, json: () => Promise.resolve({ count: 3 }) });
        vi.stubGlobal('fetch', fetchMock);

        const countUrlBuilder = vi.fn((dateFrom, dateTo) => `/referrals/export-count?date_from=${dateFrom}&date_to=${dateTo}`);

        const { container } = render(
            <ExportDialog
                open
                onClose={() => {}}
                title="Export Referrals"
                activeFilters={[]}
                countUrlBuilder={countUrlBuilder}
                onExport={() => {}}
            />,
        );

        expect(await screen.findByText(/Approximately 10 rows will be exported/)).toBeInTheDocument();

        const dateInputs = container.querySelectorAll('input[type="date"]');
        fireEvent.change(dateInputs[0], { target: { value: '2026-01-01' } });

        expect(await screen.findByText(/Approximately 3 rows will be exported/)).toBeInTheDocument();
        expect(fetchMock).toHaveBeenCalledTimes(2);
    });

    it('uses singular row text for a count of one', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ count: 1 }),
        }));

        render(
            <ExportDialog
                open
                onClose={() => {}}
                title="Export Referrals"
                activeFilters={[]}
                countUrlBuilder={() => '/referrals/export-count'}
                onExport={() => {}}
            />,
        );

        expect(await screen.findByText(/Approximately 1 row will be exported/)).toBeInTheDocument();
    });

    it('always shows the active date range as a chip, even with no page filters', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ count: 59 }),
        }));

        render(
            <ExportDialog
                open
                onClose={() => {}}
                title="Export Referrals"
                activeFilters={[]}
                countUrlBuilder={() => '/referrals/export-count'}
                onExport={() => {}}
            />,
        );

        expect(await screen.findByText(/Date range:/)).toBeInTheDocument();
        expect(screen.queryByText('All records')).not.toBeInTheDocument();
        expect(await screen.findByText(/59 rows will be exported for the selected date range/)).toBeInTheDocument();
    });

    it('shows a fallback message when the count request fails but still allows export', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false, status: 500 }));

        const onExport = vi.fn();

        render(
            <ExportDialog
                open
                onClose={() => {}}
                title="Export Referrals"
                activeFilters={[]}
                countUrlBuilder={() => '/referrals/export-count'}
                onExport={onExport}
            />,
        );

        expect(await screen.findByText(/Could not load the row count/)).toBeInTheDocument();

        const from = new Date();
        from.setDate(from.getDate() - 30);
        const dateFrom = from.toISOString().slice(0, 10);
        fireEvent.change(screen.getAllByDisplayValue(new RegExp('^\\d{4}-\\d{2}-\\d{2}$'))[0], { target: { value: '2026-01-01' } });
        fireEvent.click(screen.getByRole('button', { name: /Export/ }));

        await waitFor(() => {
            expect(onExport).toHaveBeenCalledWith({ dateFrom: '2026-01-01', dateTo: todayMinus(0) });
        });
    });
});
