import { Children, forwardRef, isValidElement, useEffect, useId, useImperativeHandle, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

function readOptions(children, groupDisabled = false, group = '') {
    return Children.toArray(children).flatMap((child) => {
        if (!isValidElement(child)) return [];
        if (child.type === 'option') {
            return [{ value: String(child.props.value ?? child.props.children ?? ''), label: child.props.children, disabled: groupDisabled || child.props.disabled, group }];
        }
        return readOptions(child.props.children, groupDisabled || child.props.disabled, child.type === 'optgroup' ? child.props.label : group);
    });
}

/** Native option/value API with a custom, keyboard-accessible option panel. */
const Select = forwardRef(function Select({ children, value, defaultValue, onChange, multiple = false, disabled = false, className = '', id, onKeyDown, onFocus, onBlur, autoFocus, ...props }, ref) {
    const generatedId = useId();
    const controlId = id ?? generatedId;
    const listId = `${controlId}-options`;
    const nativeRef = useRef(null);
    const buttonRef = useRef(null);
    const panelRef = useRef(null);
    const [open, setOpen] = useState(false);
    const [active, setActive] = useState(-1);
    const [position, setPosition] = useState({});
    const [internalValue, setInternalValue] = useState(defaultValue);
    const options = readOptions(children);
    const current = value !== undefined ? value : internalValue;
    const selected = multiple
        ? (current ?? []).map(String)
        : [String(current ?? options.find((option) => !option.disabled)?.value ?? '')];
    const enabled = options.map((option, index) => !option.disabled ? index : -1).filter((index) => index >= 0);
    const selectedOptions = options.filter((option) => selected.includes(option.value));
    useImperativeHandle(ref, () => buttonRef.current);

    useEffect(() => {
        if (!open) return;
        function place() {
            const rect = buttonRef.current.getBoundingClientRect();
            const below = window.innerHeight - rect.bottom;
            const above = rect.top;
            const upward = below < 200 && above > below;
            setPosition({ position: 'fixed', left: Math.max(8, Math.min(rect.left, window.innerWidth - rect.width - 8)), width: rect.width, maxHeight: Math.max(40, Math.min(240, (upward ? above : below) - 12)), ...(upward ? { bottom: window.innerHeight - rect.top + 4 } : { top: rect.bottom + 4 }) });
        }
        function outside(event) {
            if (!buttonRef.current?.contains(event.target) && !panelRef.current?.contains(event.target)) setOpen(false);
        }
        place();
        document.addEventListener('mousedown', outside);
        window.addEventListener('resize', place);
        window.addEventListener('scroll', place, true);
        return () => {
            document.removeEventListener('mousedown', outside);
            window.removeEventListener('resize', place);
            window.removeEventListener('scroll', place, true);
        };
    }, [open]);

    useEffect(() => {
        if (open) panelRef.current?.querySelector(`[data-index="${active}"]`)?.scrollIntoView?.({ block: 'nearest' });
    }, [open, active]);

    useEffect(() => {
        const form = nativeRef.current?.form;
        function reset() { setInternalValue(defaultValue); setOpen(false); }
        form?.addEventListener('reset', reset);
        return () => form?.removeEventListener('reset', reset);
    }, [defaultValue]);

    function choose(index) {
        const option = options[index];
        if (!option || option.disabled) return;
        const next = multiple
            ? selected.includes(option.value) ? selected.filter((item) => item !== option.value) : [...selected, option.value]
            : option.value;
        const native = nativeRef.current;
        Array.from(native.options).forEach((item) => { item.selected = multiple ? next.includes(item.value) : item.value === next; });
        if (value === undefined) setInternalValue(next);
        native.dispatchEvent(new Event('change', { bubbles: true }));
        if (!multiple) setOpen(false);
        buttonRef.current?.focus();
    }

    function keyboard(event) {
        onKeyDown?.(event);
        if (event.defaultPrevented) return;
        const index = enabled.indexOf(active);
        if (['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
            event.preventDefault();
            setOpen(true);
            const currentIndex = index >= 0 ? index : enabled.find((item) => selected.includes(options[item].value)) ?? -1;
            const currentPosition = enabled.indexOf(currentIndex);
            setActive(event.key === 'Home' ? enabled[0] : event.key === 'End' ? enabled.at(-1) : enabled[Math.max(0, Math.min(enabled.length - 1, currentPosition + (event.key === 'ArrowDown' ? 1 : -1)))] ?? -1);
        } else if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            if (open) choose(active);
            else { setOpen(true); setActive(enabled.find((item) => selected.includes(options[item].value)) ?? enabled[0] ?? -1); }
        } else if (event.key === 'Escape') {
            event.preventDefault();
            setOpen(false);
        } else if (event.key === 'Tab') {
            setOpen(false);
        } else if (event.key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey) {
            const match = [...enabled.filter((item) => item > active), ...enabled.filter((item) => item <= active)].find((item) => String(options[item].label).toLowerCase().startsWith(event.key.toLowerCase()));
            if (match !== undefined) { event.preventDefault(); setActive(match); if (!open && !multiple) choose(match); }
        }
    }

    return (
        <>
            <button
                {...Object.fromEntries(Object.entries(props).filter(([key]) => key.startsWith('aria-') || key.startsWith('data-')))}
                ref={buttonRef}
                id={controlId}
                type="button"
                role="combobox"
                aria-expanded={open && !disabled}
                aria-controls={listId}
                aria-haspopup="listbox"
                aria-required={props.required || undefined}
                aria-activedescendant={open && active >= 0 ? `${listId}-${active}` : undefined}
                disabled={disabled}
                autoFocus={autoFocus}
                onFocus={onFocus}
                onBlur={(event) => { setOpen(false); onBlur?.(event); }}
                onKeyDown={keyboard}
                onClick={() => { setOpen(!open); setActive(enabled.find((item) => selected.includes(options[item].value)) ?? enabled[0] ?? -1); }}
                className={`inline-flex ${/(^|\s)h-/.test(className) ? '' : 'h-10'} ${/(^|\s)w-/.test(className) ? '' : 'w-full'} items-center justify-between gap-2 rounded-[3px] border border-slate-300 bg-white px-3 pr-8 text-left text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400 ${className}`}
            >
                <span className="min-w-0 truncate">{selectedOptions.length ? selectedOptions.map((option) => option.label).reduce((items, label, index) => index ? [...items, ', ', label] : [label], []) : '\u00a0'}</span>
                <span aria-hidden="true" className="material-symbols-outlined shrink-0 text-[16px] text-slate-400">{open ? 'expand_less' : 'expand_more'}</span>
            </button>
            <select
                {...props}
                ref={nativeRef}
                value={value !== undefined ? value : internalValue}
                multiple={multiple}
                disabled={disabled}
                onChange={onChange}
                tabIndex={-1}
                aria-hidden="true"
                className="sr-only"
                onInvalid={(event) => { props.onInvalid?.(event); event.preventDefault(); buttonRef.current?.focus(); setOpen(true); }}
            >{children}</select>
            {open && !disabled && createPortal(
                <ul ref={panelRef} onClick={(event) => event.stopPropagation()} onMouseDown={(event) => event.stopPropagation()} id={listId} role="listbox" aria-label={props['aria-label'] ?? 'Options'} aria-multiselectable={multiple || undefined} style={position} className="z-[10000] m-0 overflow-y-auto rounded-md border border-slate-200 bg-white py-1 shadow-lg owb-scroll-wide">
                    {options.map((option, index) => (
                        <li key={`${option.value}-${index}`} role="presentation">
                            {option.group && options[index - 1]?.group !== option.group && <div className="px-3 py-1 text-[11px] font-semibold text-slate-500">{option.group}</div>}
                            <div id={`${listId}-${index}`} data-index={index} role="option" aria-selected={selected.includes(option.value)} aria-disabled={option.disabled || undefined} onMouseDown={(event) => { event.preventDefault(); choose(index); }} onMouseEnter={() => !option.disabled && setActive(index)} className={`flex items-center gap-2 px-3 py-1.5 text-[12px] ${option.disabled ? 'cursor-not-allowed text-slate-400' : selected.includes(option.value) ? 'cursor-pointer bg-indigo-50 font-semibold text-indigo-700' : index === active ? 'cursor-pointer bg-slate-100 text-slate-800' : 'cursor-pointer text-slate-700 hover:bg-slate-50'}`}>
                                {multiple && <span aria-hidden="true" className="material-symbols-outlined text-[16px]">{selected.includes(option.value) ? 'check_box' : 'check_box_outline_blank'}</span>}
                                {option.label}
                            </div>
                        </li>
                    ))}
                </ul>, document.body,
            )}
        </>
    );
});

export default Select;
