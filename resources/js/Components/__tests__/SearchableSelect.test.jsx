import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import SearchableSelect from '../SearchableSelect';

describe('SearchableSelect scrolling', () => {
    it('keeps keyboard scrolling in list and hover from scrolling page', async () => {
        render(<SearchableSelect value="a" onChange={() => {}} options={[
            { value: 'a', label: 'Alpha' },
            { value: 'b', label: 'Bravo' },
            { value: 'c', label: 'Charlie' },
        ]} />);
        const input = screen.getByRole('combobox');
        fireEvent.focus(input);
        const list = screen.getByRole('listbox');
        const alpha = screen.getByRole('option', { name: 'Alpha' });
        const bravo = screen.getByRole('option', { name: 'Bravo' });
        const charlie = screen.getByRole('option', { name: 'Charlie' });
        vi.spyOn(list, 'getBoundingClientRect').mockReturnValue({ top: 10, bottom: 50 });
        vi.spyOn(alpha, 'getBoundingClientRect').mockReturnValue({ top: 10, bottom: 30 });
        vi.spyOn(bravo, 'getBoundingClientRect').mockReturnValue({ top: 30, bottom: 50 });
        vi.spyOn(charlie, 'getBoundingClientRect').mockReturnValue({ top: 50, bottom: 70 });
        const scrollIntoView = vi.fn();
        charlie.scrollIntoView = scrollIntoView;
        const scrollTo = vi.spyOn(window, 'scrollTo');

        fireEvent.mouseEnter(bravo);
        expect(list.scrollTop).toBe(0);
        vi.spyOn(charlie, 'getBoundingClientRect').mockReturnValue({ top: 50, bottom: 70 });
        fireEvent.keyDown(input, { key: 'ArrowDown' });
        await waitFor(() => expect(list.scrollTop).toBe(20));
        expect(scrollIntoView).not.toHaveBeenCalled();
        expect(scrollTo).not.toHaveBeenCalled();
    });
});
