import '@testing-library/jest-dom/vitest';
import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import OtpInput from '../OtpInput';

afterEach(cleanup);

describe('OtpInput', () => {
    it('keeps one accessible input, renders six slots, sanitizes input and retains leading zeroes', () => {
        const onChange = vi.fn();
        const { container } = render(<OtpInput label="Authentication code" value="012" onChange={onChange} />);
        const input = screen.getByRole('textbox', { name: 'Authentication code, 6 digits' });
        expect(input).toHaveAttribute('autocomplete', 'one-time-code');
        expect(input).toHaveAttribute('inputmode', 'numeric');
        expect(container.querySelectorAll('[aria-hidden="true"] span')).toHaveLength(6);
        fireEvent.change(input, { target: { value: '01x23456' } });
        expect(onChange).toHaveBeenCalledWith('012345');
        expect(container.querySelectorAll('[aria-hidden="true"] span')[0]).toHaveTextContent('0');
    });

    it('accepts formatted paste without maxLength truncating the native clipboard value', () => {
        const onChange = vi.fn();
        render(<OtpInput label="Code" value="" onChange={onChange} />);
        const input = screen.getByRole('textbox', { name: 'Code, 6 digits' });
        expect(input).not.toHaveAttribute('maxlength');
        fireEvent.paste(input, { clipboardData: { getData: () => '123 456' } });
        expect(onChange).toHaveBeenCalledWith('123456');
    });

    it('associates errors with input, shows focused error and disabled states', () => {
        const { rerender, container } = render(<OtpInput label="Verification code" value="1234" onChange={vi.fn()} error="Code expired" />);
        const input = screen.getByRole('textbox', { name: 'Verification code, 6 digits' });
        input.focus();
        input.setSelectionRange(2, 2);
        fireEvent.select(input);
        expect(container.querySelectorAll('[aria-hidden="true"] span')[2].className).toContain('ring-2');
        expect(input).toHaveAttribute('aria-invalid', 'true');
        expect(input).toHaveAttribute('aria-describedby', expect.stringContaining('-error'));
        expect(screen.getByRole('alert')).toHaveTextContent('Code expired');
        rerender(<OtpInput label="Verification code" value="1234" onChange={vi.fn()} disabled />);
        expect(input).toBeDisabled();
        expect(container.querySelectorAll('[aria-hidden="true"] span')[0].className).toContain('bg-slate-100');
    });
});
