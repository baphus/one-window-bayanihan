import '@testing-library/jest-dom/vitest';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const { dispatchAsyncExport } = vi.hoisted(() => ({ dispatchAsyncExport: vi.fn() }));

vi.mock('@/lib/async-export', () => ({ dispatchAsyncExport }));
vi.mock('@/Hooks/useToast', () => ({
  useToast: () => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() }),
}));

globalThis.route = (name, params) => `/${name}?${new URLSearchParams(params).toString()}`;

import ExportButtons from '../ExportButtons';

const props = { fromDateISO: '2026-01-01', toDateISO: '2026-06-30' };

// Both report export endpoints queue a job and answer with JSON. Opening them
// in a tab (the previous behaviour) produced no download and no feedback.
describe('reports export buttons', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    dispatchAsyncExport.mockResolvedValue({ id: 'doc-1', status: 'pending' });
    window.open = vi.fn();
  });

  it('queues the excel export through the async helper instead of opening a tab', async () => {
    render(<ExportButtons {...props} />);

    fireEvent.click(screen.getByRole('button', { name: /Export Excel/ }));

    await waitFor(() => expect(dispatchAsyncExport).toHaveBeenCalledTimes(1));
    expect(dispatchAsyncExport.mock.calls[0][0]).toContain('reports.export-excel');
    expect(window.open).not.toHaveBeenCalled();
  });

  it('queues the pdf export through the async helper instead of opening a tab', async () => {
    render(<ExportButtons {...props} />);

    fireEvent.click(screen.getByRole('button', { name: /Export PDF/ }));

    await waitFor(() => expect(dispatchAsyncExport).toHaveBeenCalledTimes(1));
    expect(dispatchAsyncExport.mock.calls[0][0]).toContain('reports.export-pdf');
    expect(window.open).not.toHaveBeenCalled();
  });

  it('forwards the active report filters to the export request', async () => {
    render(
      <ExportButtons
        {...props}
        dateScope="CLOSED"
        province="Cebu"
        city="Mandaue"
        agencyId="agency-7"
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: /Export Excel/ }));

    await waitFor(() => expect(dispatchAsyncExport).toHaveBeenCalledTimes(1));
    const url = dispatchAsyncExport.mock.calls[0][0];
    expect(url).toContain('from=2026-01-01');
    expect(url).toContain('to=2026-06-30');
    expect(url).toContain('date_scope=CLOSED');
    expect(url).toContain('agency_id=agency-7');
  });

  it('does not dispatch while filter changes are unapplied', () => {
    render(<ExportButtons {...props} disabled />);

    fireEvent.click(screen.getByRole('button', { name: /Export Excel/ }));

    expect(dispatchAsyncExport).not.toHaveBeenCalled();
  });
});
