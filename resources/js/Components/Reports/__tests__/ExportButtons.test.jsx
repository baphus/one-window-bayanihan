import '@testing-library/jest-dom/vitest';
import { fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import ExportButtons from '../ExportButtons';

globalThis.route = (name, params) => `/${name}?${new URLSearchParams(params).toString()}`;
const props = { fromDateISO: '2026-01-01', toDateISO: '2026-06-30' };

describe('reports print action', () => {
  beforeEach(() => {
    vi.spyOn(window, 'open').mockImplementation(() => null).mockClear();
  });

  it('offers only PDF printing and forwards all active filters', () => {
    render(<ExportButtons {...props} dateScope="case_created_at" province="Cebu" city="Mandaue" agencyId="agency-7" />);
    expect(screen.queryByRole('button', { name: /excel/i })).not.toBeInTheDocument();
    expect(screen.getAllByRole('button')).toHaveLength(1);
    fireEvent.click(screen.getByRole('button', { name: 'Print / Save PDF' }));
    expect(window.open).toHaveBeenCalledWith('/reports.print?from=2026-01-01&to=2026-06-30&date_scope=case_created_at&province=Cebu&city=Mandaue&agency_id=agency-7', '_blank', 'noopener,noreferrer');
  });

  it('does not open print preview when disabled', () => {
    render(<ExportButtons {...props} disabled />);
    fireEvent.click(screen.getByRole('button', { name: 'Print / Save PDF' }));
    expect(window.open).not.toHaveBeenCalled();
  });
});
