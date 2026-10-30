import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import DateRangePicker from '../DateRangePicker';

vi.mock('@/Components/Select', () => ({ default: (props) => <select {...props} /> }));

describe('report date filter', () => {
  it('keeps presets in one control and hides custom inputs until selected', () => {
    const onQuickRangeSelect = vi.fn();
    const props = { fromDateISO: '2026-10-02', toDateISO: '2026-10-31', quickRange: '30_DAYS', onQuickRangeSelect, onFromChange: vi.fn(), onToChange: vi.fn() };
    const { rerender } = render(<DateRangePicker {...props} />);
    expect(screen.queryByLabelText('Start date')).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /reset/i })).not.toBeInTheDocument();
    fireEvent.change(screen.getByLabelText('Date range'), { target: { value: 'CUSTOM' } });
    expect(onQuickRangeSelect).toHaveBeenCalledWith('CUSTOM');
    rerender(<DateRangePicker {...props} quickRange="CUSTOM" />);
    expect(screen.getByLabelText('Start date')).toHaveValue('2026-10-02');
    expect(screen.getByLabelText('End date')).toHaveValue('2026-10-31');
  });
});
