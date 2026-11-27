import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import IdentityFields from '../IdentityFields';

it('limits account DOB to age 16 while allowing exact birthday', () => {
  const setData = vi.fn();
  render(<IdentityFields data={{ date_of_birth: '' }} setData={setData} />);
  const input = screen.getByLabelText('Date of birth');
  const minimum = new Date();
  minimum.setFullYear(minimum.getFullYear() - 16);
  const value = `${minimum.getFullYear()}-${String(minimum.getMonth() + 1).padStart(2, '0')}-${String(minimum.getDate()).padStart(2, '0')}`;
  expect(input.max).toBe(value);
  input.value = '2011-01-01';
  expect(input.validity.rangeOverflow).toBe(true);
  fireEvent.invalid(input);
  expect(input.validationMessage).toBe('Must be at least 16 years old.');
  fireEvent.change(input, { target: { value } });
  expect(input.validity.valid).toBe(true);
  expect(input.validationMessage).toBe('');
  fireEvent.change(input, { target: { value: '' } });
  expect(input.validity.valid).toBe(true);
  expect(setData).toHaveBeenCalledWith('date_of_birth', value);
});
