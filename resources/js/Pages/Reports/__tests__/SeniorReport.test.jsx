import '@testing-library/jest-dom/vitest';
import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import {
  BarRows,
  DonutWithList,
  EmptyLine,
  ensureDistinctHues,
  FunnelSteps,
  ReportCard,
  StackedBars,
  topNamedWithTail,
  topNWithOther,
  topSlice,
  toSlices,
  totalOf,
} from '../sections/SeniorReport';
import { humanizeStatus } from '@/lib/statusLabels';

vi.mock('react-chartjs-2', () => ({
  Doughnut: (props) => (
    <div
      data-testid="donut-chart"
      data-labels={JSON.stringify(props.data.labels)}
      data-values={JSON.stringify(props.data.datasets[0].data)}
    />
  ),
}));

const dist = {
  labels: ['PENDING', 'PROCESSING', 'COMPLETED'],
  data: [10, 30, 60],
  colors: ['#d97706', '#0b5a8c', '#059669'],
};

describe('SeniorReport helpers', () => {
  it('normalizes distributions into slices with percents', () => {
    const slices = toSlices(dist);
    expect(slices.map((slice) => [slice.label, slice.count, slice.percent])).toEqual([
      ['PENDING', 10, 10],
      ['PROCESSING', 30, 30],
      ['COMPLETED', 60, 60],
    ]);
    expect(totalOf(slices)).toBe(100);
    expect(topSlice(slices).label).toBe('COMPLETED');
  });

  it('groups beyond top-N into a gray Other row', () => {
    const slices = toSlices({
      labels: ['A', 'B', 'C', 'D', 'E', 'F', 'G'],
      data: [50, 20, 10, 8, 6, 4, 2],
      colors: [],
    });
    const ranked = topNWithOther(slices, 5, 7);
    expect(ranked.map((slice) => slice.label)).toEqual(['A', 'B', 'C', 'D', 'E', 'Other (2 types)']);
    expect(ranked[5]).toMatchObject({ count: 6, hex: '#94a3b8' });
    expect(totalOf(ranked)).toBe(100);
  });

  it('leaves short lists untouched without an Other row', () => {
    expect(topNWithOther(toSlices(dist), 5)).toHaveLength(3);
  });
});

describe('humanizeStatus', () => {
  it.each([
    ['FOR_COMPLIANCE', 'For Compliance'],
    ['PENDING', 'Pending'],
    ['PROCESSING', 'Processing'],
    ['COMPLETED', 'Completed'],
    ['REJECTED', 'Rejected'],
    ['OPEN', 'Open'],
    ['FOR_REFERRAL', 'For referral'],
    ['CLOSED', 'Closed'],
    ['ARCHIVED', 'Archived'],
    ['DRAFT', 'Draft'],
    ['INCOMPLETE_REQUIREMENTS', 'Incomplete requirements'],
    ['OUTSIDE_MANDATE', 'Outside mandate'],
    ['DUPLICATE_REFERRAL', 'Duplicate referral'],
    ['CLIENT_WITHDREW', 'Client withdrew'],
    ['NO_SERVICE_CAPACITY', 'No service capacity'],
    ['OTHER', 'Other'],
    ['agency', 'Agency'],
    ['case_manager', 'Case manager'],
    ['system', 'System'],
    ['internal', 'Internal'],
    ['self_filed', 'Portal'],
    ['OFW', 'OFW'],
    ['NEXT_OF_KIN', 'Next of kin'],
    ['MALE', 'Male'],
    ['FEMALE', 'Female'],
    ['PWD', 'PWD'],
    ['Senior Citizen', 'Senior Citizen'],
  ])('maps raw enum %s to human label %s', (raw, expected) => {
    expect(humanizeStatus(raw)).toBe(expected);
  });

  it('passes unknown values through and never throws on empty input', () => {
    expect(humanizeStatus('SOME_FUTURE_STATUS')).toBe('SOME_FUTURE_STATUS');
    expect(humanizeStatus(null)).toBe('');
    expect(humanizeStatus(undefined)).toBe('');
    expect(humanizeStatus('')).toBe('');
  });
});

describe('SeniorReport visuals', () => {
  it('renders bars with labels and counts', () => {
    render(<BarRows items={toSlices(dist)} />);
    expect(screen.getByText('PENDING')).toBeInTheDocument();
    expect(screen.getByText('60')).toBeInTheDocument();
  });

  it('renders a donut with an adjacent count-and-percent list', () => {
    render(<DonutWithList slices={toSlices(dist)} />);
    const chart = screen.getByTestId('donut-chart');
    expect(JSON.parse(chart.getAttribute('data-labels'))).toEqual(['PENDING', 'PROCESSING', 'COMPLETED']);
    expect(JSON.parse(chart.getAttribute('data-values'))).toEqual([10, 30, 60]);
    expect(screen.getByText('COMPLETED')).toBeInTheDocument();
    expect(screen.getByText('60 (60%)')).toBeInTheDocument();
  });

  it('renders the list alone when slices exceed the 5-slice pie ceiling', () => {
    const slices = toSlices({
      labels: ['A', 'B', 'C', 'D', 'E', 'F'],
      data: [1, 1, 1, 1, 1, 1],
      colors: [],
    });
    render(<DonutWithList slices={slices} />);
    expect(screen.queryByTestId('donut-chart')).not.toBeInTheDocument();
    expect(screen.getByText('F')).toBeInTheDocument();
  });

  it('renders every slice with showAll instead of capping at five', () => {
    const slices = toSlices({
      labels: ['A', 'B', 'C', 'D', 'E', 'F', 'G'],
      data: [50, 20, 10, 8, 6, 4, 2],
      colors: [],
    });
    render(<DonutWithList slices={slices} showAll />);
    const chart = screen.getByTestId('donut-chart');
    expect(JSON.parse(chart.getAttribute('data-values'))).toEqual([50, 20, 10, 8, 6, 4, 2]);
    for (const label of ['A', 'B', 'C', 'D', 'E', 'F', 'G']) {
      expect(screen.getByText(label)).toBeInTheDocument();
    }
    expect(screen.getByText('2 (2%)')).toBeInTheDocument();
  });

  it('keeps remainder buckets out of ranked headlines', () => {
    const slices = [
      { key: 'Saudi Arabia', label: 'Saudi Arabia', count: 66, hex: '#0b5a8c', percent: 11 },
      { key: 'Japan', label: 'Japan', count: 65, hex: '#0891b2', percent: 11 },
      { key: 'South Korea', label: 'South Korea', count: 64, hex: '#059669', percent: 11 },
      { key: 'Singapore', label: 'Singapore', count: 63, hex: '#d97706', percent: 11 },
      { key: 'Kuwait', label: 'Kuwait', count: 61, hex: '#ea580c', percent: 10 },
      { key: '__other', label: 'Other', count: 276, hex: '#94a3b8', percent: 46 },
    ];
    const { top, tailCount } = topNamedWithTail(slices, 5);
    expect(top.map((slice) => slice.label)).toEqual([
      'Saudi Arabia',
      'Japan',
      'South Korea',
      'Singapore',
      'Kuwait',
    ]);
    expect(topSlice(top).label).toBe('Saudi Arabia');
    expect(tailCount).toBe(0);
  });

  it('aggregates a genuine long tail into an honest count', () => {
    const slices = toSlices({
      labels: ['A', 'B', 'C', 'D', 'E', 'F', 'G'],
      data: [66, 65, 64, 63, 61, 40, 36],
      colors: [],
    });
    const { top, tailCount, tailCountries } = topNamedWithTail(slices, 5);
    expect(top.map((slice) => slice.label)).toEqual(['A', 'B', 'C', 'D', 'E']);
    expect(tailCount).toBe(76);
    expect(tailCountries).toBe(2);
  });

  it('prefers the backend distinct count for tail types when provided', () => {
    const slices = toSlices({
      labels: ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'],
      data: [73, 65, 64, 62, 58, 57, 55, 50, 48, 40],
      colors: [],
    });
    const { top, tailCount, tailCountries } = topNamedWithTail(slices, 5, 10);
    expect(top.map((slice) => slice.label)).toEqual(['A', 'B', 'C', 'D', 'E']);
    expect(topSlice(top).label).toBe('A');
    expect(tailCount).toBe(57 + 55 + 50 + 48 + 40);
    expect(tailCountries).toBe(5);
  });

  it('fills missing hues without reusing a hue for adjacent slices', () => {
    const slices = [
      { key: 'a', label: 'A', count: 30, hex: '#0b5a8c', percent: 30 },
      { key: 'b', label: 'B', count: 25, hex: '#0b5a8c', percent: 25 },
      { key: 'c', label: 'C', count: 20, hex: null, percent: 20 },
      { key: 'd', label: 'D', count: 15, hex: null, percent: 15 },
    ];
    const fixed = ensureDistinctHues(slices);
    expect(fixed).toHaveLength(4);
    expect(fixed[0].hex).toBe('#0b5a8c');
    const hues = fixed.map((slice) => slice.hex);
    expect(hues.every((hue) => typeof hue === 'string' && hue.length > 0)).toBe(true);
    hues.forEach((hue, index) => {
      if (index > 0) expect(hue).not.toBe(hues[index - 1]);
    });
  });

  it('humanizes slice labels through toSlices while keeping keys raw', () => {
    const slices = toSlices(dist, { humanizeLabels: true });
    expect(slices.map((slice) => slice.label)).toEqual(['Pending', 'Processing', 'Completed']);
    expect(slices.map((slice) => slice.key)).toEqual(['PENDING', 'PROCESSING', 'COMPLETED']);
    expect(slices.map((slice) => slice.count)).toEqual([10, 30, 60]);
  });

  it('renders the referral funnel in pipeline order with an exit row', () => {
    const slices = toSlices(
      {
        labels: ['PENDING', 'PROCESSING', 'FOR_COMPLIANCE', 'COMPLETED', 'REJECTED'],
        data: [96, 164, 41, 301, 18],
        colors: [],
      },
      { humanizeLabels: true },
    );
    render(<FunnelSteps slices={slices} />);
    const rows = screen.getAllByText(/Pending|Processing|For Compliance|Completed/);
    expect(rows.map((node) => node.textContent)).toEqual([
      'Pending',
      'Processing',
      'For Compliance',
      'Completed',
    ]);
    expect(screen.getByText('Left the pipeline: Rejected')).toBeInTheDocument();
    expect(screen.getByText('18')).toBeInTheDocument();
  });

  it('renders stacked agency bars with per-segment counts', () => {
    render(
      <StackedBars
        rows={[
          {
            key: 'owwa',
            label: 'OWWA',
            total: 216,
            segments: [
              { key: 'active', label: 'Active', count: 84, hex: '#0b5a8c' },
              { key: 'finished', label: 'Finished', count: 132, hex: '#059669' },
            ],
          },
        ]}
      />,
    );
    expect(screen.getByText('OWWA')).toBeInTheDocument();
    expect(screen.getByText('216')).toBeInTheDocument();
    expect(screen.getByText('Active: 84 · Finished: 132')).toBeInTheDocument();
  });

  it('left-aligns funnel steps and stacked segments instead of centering them', () => {
    const funnelSlices = toSlices(
      {
        labels: ['PENDING', 'PROCESSING', 'FOR_COMPLIANCE', 'COMPLETED', 'REJECTED'],
        data: [96, 164, 41, 301, 18],
        colors: [],
      },
      { humanizeLabels: true },
    );
    const { container: funnelContainer } = render(<FunnelSteps slices={funnelSlices} />);
    const funnelBars = funnelContainer.querySelectorAll('div[style*="width"]');
    expect(funnelBars.length).toBeGreaterThan(0);
    funnelBars.forEach((bar) => {
      expect(bar.parentElement.className).not.toMatch(/justify-center|mx-auto/);
    });

    const { container: stackedContainer } = render(
      <StackedBars
        rows={[
          {
            key: 'owwa',
            label: 'OWWA',
            total: 216,
            segments: [
              { key: 'active', label: 'Active', count: 84, hex: '#0b5a8c' },
              { key: 'finished', label: 'Finished', count: 132, hex: '#059669' },
            ],
          },
        ]}
      />,
    );
    const track = stackedContainer.querySelector('.overflow-hidden');
    expect(track.className).not.toMatch(/justify-center|mx-auto/);
    const segments = track.querySelectorAll(':scope > div');
    const widths = Array.from(segments).map((segment) =>
      parseFloat(segment.style.width.replace('%', '')),
    );
    // Single row at peak fills its whole track with no centering gap.
    expect(widths.reduce((sum, width) => sum + width, 0)).toBeCloseTo(100);
  });

  it('renders card titles, takeaways, and empty lines plainly', () => {
    render(
      <ReportCard title="Who we serve" takeaway="Most clients are aged 26–40.">
        <EmptyLine message="No age data to show yet." />
      </ReportCard>,
    );
    expect(screen.getByText('Who we serve')).toBeInTheDocument();
    expect(screen.getByText('Most clients are aged 26–40.')).toBeInTheDocument();
    expect(screen.getByText('No age data to show yet.')).toBeInTheDocument();
  });
});
