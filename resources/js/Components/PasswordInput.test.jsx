import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import PasswordInput from '@/Components/PasswordInput';

vi.mock('@/Components/PasswordStrengthMeter', () => ({
  default: ({ value, rules, confirmation }) => <div data-testid="strength-guide" data-value={value} data-rules={JSON.stringify(rules)} data-confirmation={confirmation} />,
}));
vi.mock('@/Components/InputError', () => ({ default: () => null }));

describe('PasswordInput', () => {
  it('shows Required without policy guide on mandatory existing-password fields', () => {
    render(<PasswordInput id="pw" label="Current password" value="" onChange={() => {}} required rules={{ min_length: 8 }} />);
    expect(screen.getByText('Required')).toBeInTheDocument();
    expect(screen.getByLabelText(/Current password/)).toBeRequired();
    expect(screen.queryByTestId('strength-guide')).not.toBeInTheDocument();
  });

  it('shows policy guide only when explicitly requested for a new password', () => {
    render(<PasswordInput id="pw" label="New password" value="" onChange={() => {}} required showStrengthMeter rules={{ min_length: 8 }} />);
    expect(screen.getByText('Required')).toBeInTheDocument();
    expect(screen.getByTestId('strength-guide')).toHaveAttribute('data-rules', JSON.stringify({ min_length: 8 }));
  });

  it('keeps optional field optional and allows editable input', () => {
    const onChange = vi.fn();
    render(<PasswordInput id="pw" label="Current password" value="old" onChange={onChange} />);
    expect(screen.queryByText('Required')).not.toBeInTheDocument();
    expect(screen.queryByTestId('strength-guide')).not.toBeInTheDocument();
    fireEvent.change(screen.getByLabelText('Current password'), { target: { value: 'new' } });
    expect(onChange).toHaveBeenCalledWith('new');
  });
});
