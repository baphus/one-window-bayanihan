import '@testing-library/jest-dom/vitest';
import { fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

globalThis.route = (name, params) => `/${name}?${new URLSearchParams(params).toString()}`;

import ExportButtons from '../ExportButtons';

const props = { fromDateISO: '2026-01-01', toDateISO: '2026-06-30' };

describe('reports export buttons', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    window.location = { href: '' };
  });

  it('navigates to the excel export URL directly', () => {
    render(<ExportButtons {...props} />);

    fireEvent.click(screen.getByRole('button', { name: /Export Excel/ }));

    expect(window.location.href).toContain('reports.export-excel');
  });

  it('navigates to the pdf export URL directly', () => {
    render(<ExportButtons {...props} />);

    fireEvent.click(screen.getByRole('button', { name: /Export PDF/ }));

    expect(window.location.href).toContain('reports.export-pdf');
  });

  it('forwards the active report filters to the export request', () => {
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

    const url = window.location.href;
    expect(url).toContain('from=2026-01-01');
    expect(url).toContain('to=2026-06-30');
    expect(url).toContain('date_scope=CLOSED');
    expect(url).toContain('agency_id=agency-7');
  });

  it('does not navigate when disabled', () => {
    render(<ExportButtons {...props} disabled />);

    fireEvent.click(screen.getByRole('button', { name: /Export Excel/ }));

    expect(window.location.href).toBe('');
  });
});
