import '@testing-library/jest-dom/vitest';
import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const { pageProps } = vi.hoisted(() => ({ pageProps: { avgReferralCompletion: 12.5 } }));

vi.mock('@inertiajs/react', () => ({ usePage: () => ({ props: pageProps }) }));
vi.mock('@/Components/Reports/CaseVolumeChart', () => ({ default: () => null }));
vi.mock('../ReferralTrendsSection', () => ({ default: () => null }));
vi.mock('../GeographicMapSection', () => ({ default: () => null }));

import CasesTab from '../CasesTab';

describe('CasesTab average referral completion', () => {
  beforeEach(() => {
    pageProps.avgReferralCompletion = 12.5;
  });

  it.each([
    [12.5, 'Average finish time: 12.5 days'],
    [0, 'Average finish time: 0 days'],
  ])('renders filtered average %s', (value, expected) => {
    pageProps.avgReferralCompletion = value;

    render(<CasesTab mapProps={{}} />);

    expect(screen.getByText((_, element) => element.tagName === 'P' && element.textContent === expected)).toBeInTheDocument();
  });
});
