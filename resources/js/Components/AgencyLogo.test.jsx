import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import AgencyLogo from '@/Components/AgencyLogo';

describe('AgencyLogo', () => {
  it('uses image when provided and falls back on load error', () => {
    render(<AgencyLogo name="Agency One" logoUrl="/agency.png" />);
    fireEvent.error(screen.getByRole('img', { name: 'Agency One logo' }));
    expect(screen.queryByRole('img')).not.toBeInTheDocument();
    expect(screen.getByText('account_balance')).toBeInTheDocument();
  });

  it('shows fallback when no logo URL exists', () => {
    render(<AgencyLogo name="Agency Two" />);
    expect(screen.getByText('account_balance')).toBeInTheDocument();
  });
});
