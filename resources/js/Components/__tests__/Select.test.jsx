import { useState } from 'react';
import { act, fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import Select from '../Select';

function Picker({ multiple = false }) {
    const [value, setValue] = useState(multiple ? ['a'] : '');
    return <><label htmlFor="choice">Choice</label><Select id="choice" name="choice" required multiple={multiple} value={value} onChange={(event) => setValue(multiple ? Array.from(event.target.selectedOptions, (option) => option.value) : event.target.value)}><option value="">Choose</option><optgroup label="Choices"><option value="a">Alpha</option><option value="b" disabled>Blocked</option><option value="c">Charlie</option></optgroup></Select></>;
}

describe('Select', () => {
    it('keeps implicit wrapping labels associated with the visible picker', () => {
        render(<label>Case age<Select defaultValue=""><option value="">Any age</option></Select></label>);
        expect(screen.getByRole('combobox')).toHaveAccessibleName('Case age');
    });
    it('uses a custom panel, skips disabled options, and preserves native change values', () => {
        render(<Picker />);
        const control = screen.getByRole('combobox', { name: 'Choice' });
        fireEvent.keyDown(control, { key: 'ArrowDown' });
        fireEvent.keyDown(control, { key: 'ArrowDown' });
        fireEvent.keyDown(control, { key: 'Enter' });
        expect(control).toHaveTextContent('Charlie');
        expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
    });

    it.each([['ArrowDown', 'Delta'], ['ArrowUp', 'Alpha']])('opens with %s relative to the selected value', (key, expected) => {
        render(<Select defaultValue="c" onChange={() => {}}><option value="a">Alpha</option><option value="b" disabled>Blocked</option><option value="c">Charlie</option><option value="d">Delta</option></Select>);
        const control = screen.getByRole('combobox');
        fireEvent.keyDown(control, { key });
        fireEvent.keyDown(control, { key: 'Enter' });
        expect(control).toHaveTextContent(expected);
    });

    it('toggles multiple values without closing and submits selected values', () => {
        const { container } = render(<form><Picker multiple /></form>);
        const control = screen.getByRole('combobox', { name: 'Choice' });
        fireEvent.click(control);
        fireEvent.mouseDown(screen.getByRole('option', { name: 'Charlie' }));
        expect(screen.getByRole('listbox')).toBeInTheDocument();
        expect(new FormData(container.querySelector('form')).getAll('choice')).toEqual(['a', 'c']);
        fireEvent.mouseDown(screen.getByRole('option', { name: 'Alpha' }));
        expect(new FormData(container.querySelector('form')).getAll('choice')).toEqual(['c']);
        fireEvent.keyDown(control, { key: 'Escape' });
        expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
    });

    it('keeps highlight scrolling inside list and does not scroll on hover', () => {
        render(<Select defaultValue="a"><option value="a">Alpha</option><option value="b">Bravo</option><option value="c">Charlie</option></Select>);
        const control = screen.getByRole('combobox');
        fireEvent.click(control);
        const list = screen.getByRole('listbox');
        const alpha = screen.getByRole('option', { name: 'Alpha' });
        const bravo = screen.getByRole('option', { name: 'Bravo' });
        const charlie = screen.getByRole('option', { name: 'Charlie' });
        list.scrollTop = 0;
        vi.spyOn(alpha, 'getBoundingClientRect').mockReturnValue({ top: 10, bottom: 30 });
        vi.spyOn(bravo, 'getBoundingClientRect').mockReturnValue({ top: 30, bottom: 50 });
        vi.spyOn(charlie, 'getBoundingClientRect').mockReturnValue({ top: 50, bottom: 70 });
        vi.spyOn(list, 'getBoundingClientRect').mockReturnValue({ top: 10, bottom: 50 });
        const scrollIntoView = vi.fn();
        alpha.scrollIntoView = scrollIntoView;
        charlie.scrollIntoView = scrollIntoView;
        fireEvent.mouseEnter(charlie);
        expect(list.scrollTop).toBe(0);
        fireEvent.keyDown(control, { key: 'ArrowDown' });
        fireEvent.keyDown(control, { key: 'ArrowDown' });
        vi.spyOn(screen.getByRole('option', { name: 'Bravo' }), 'getBoundingClientRect').mockReturnValue({ top: 50, bottom: 70 });
        fireEvent.keyDown(control, { key: 'ArrowUp' });
        expect(list.scrollTop).toBe(20);
        expect(scrollIntoView).not.toHaveBeenCalled();
    });

    it('supports uncontrolled selection, reset and required validation focus', () => {
        const onChange = vi.fn();
        const { container } = render(<form><label htmlFor="kind">Kind</label><Select id="kind" name="kind" defaultValue="" required onChange={onChange}><option value="">Choose</option><option value="1">One</option></Select></form>);
        const control = screen.getByRole('combobox', { name: 'Kind' });
        act(() => { expect(container.querySelector('form').checkValidity()).toBe(false); });
        expect(control).toHaveFocus();
        fireEvent.mouseDown(screen.getByRole('option', { name: 'One' }));
        expect(onChange).toHaveBeenCalledOnce();
        expect(control).toHaveTextContent('One');
        fireEvent.reset(container.querySelector('form'));
        expect(control).toHaveTextContent('Choose');
    });
});
