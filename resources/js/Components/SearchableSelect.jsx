import { useState, useRef, useEffect } from 'react';

// Words that carry no distinguishing meaning in PSGC place names. PSGC stores
// highly urbanised cities as "City of Cebu", but users type "Cebu City", so a
// plain substring match returned "No results found" for the most natural input.
// Stripping these lets either word order match.
const PLACE_NOISE_WORDS = new Set(['city', 'municipality', 'of', 'the']);

function significantTokens(text) {
    return String(text ?? '')
        .toLowerCase()
        .replace(/[^a-z0-9\s]/g, ' ')
        .split(/\s+/)
        .filter((t) => t && !PLACE_NOISE_WORDS.has(t));
}

/**
 * True when the option label matches the query. Falls back from a plain
 * substring test to a token-subset test so "Cebu City" finds "City of Cebu"
 * and "Lapu-Lapu City" finds "City of Lapu-Lapu".
 */
export function matchesQuery(label, query) {
    const q = String(query ?? '').trim().toLowerCase();
    if (!q) return true;

    const lower = String(label ?? '').toLowerCase();
    if (lower.includes(q)) return true;

    const queryTokens = significantTokens(q);
    if (queryTokens.length === 0) return false;

    const labelTokens = significantTokens(label);

    return queryTokens.every((qt) => labelTokens.some((lt) => lt.startsWith(qt)));
}

/**
 * Searchable dropdown that also supports freeform typing when `allowCustom` is true.
 *
 * - allowCustom=false (default): classic combobox — select from the list only.
 * - allowCustom=true: user can type a value that isn't in the list. When the
 *   typed text doesn't match any option exactly, a "Use '…'" pseudo-option
 *   appears at the bottom. Pressing Enter or clicking it submits the typed text.
 */
export default function SearchableSelect({ value, onChange, options = [], placeholder = 'Select...', disabled = false, allowCustom = false }) {
    const [query, setQuery] = useState('');
    const [open, setOpen] = useState(false);
    const [highlightedIndex, setHighlightedIndex] = useState(-1);
    const wrapperRef = useRef(null);
    const inputRef = useRef(null);
    const listRef = useRef(null);

    const selectedOption = options.find((o) => o.value === value);

    const filtered = query.trim()
        ? options.filter((o) => matchesQuery(o.label, query))
        : options;

    // When allowCustom is on, the typed query may not match any option exactly.
    const hasExactMatch = query.trim()
        ? options.some((o) => o.label.toLowerCase() === query.trim().toLowerCase())
        : !!selectedOption;

    // When allowCustom is active and there's typed text with no exact match,
    // we offer a "Use '<query>'" option appended to the end of the filtered list.
    const showCustomOption = allowCustom && query.trim() && !hasExactMatch;
    const totalItems = filtered.length + (showCustomOption ? 1 : 0);

    useEffect(() => {
        function handleClickOutside(e) {
            if (wrapperRef.current && !wrapperRef.current.contains(e.target)) {
                setOpen(false);
                setQuery('');
            }
        }
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    useEffect(() => {
        if (open && highlightedIndex >= 0 && listRef.current) {
            const el = listRef.current.children[highlightedIndex];
            if (el) el.scrollIntoView({ block: 'nearest' });
        }
    }, [highlightedIndex, open]);

    function handleSelect(optionValue) {
        onChange(optionValue);
        setQuery('');
        setOpen(false);
        setHighlightedIndex(-1);
    }

    function handleInputFocus() {
        if (disabled) return;
        setOpen(true);
        setHighlightedIndex(-1);
        setQuery('');
    }

    function handleInputChange(e) {
        setQuery(e.target.value);
        setHighlightedIndex(0);
        if (!open) setOpen(true);
    }

    function handleKeyDown(e) {
        if (!open) {
            if (e.key === 'ArrowDown' || e.key === 'Enter') {
                e.preventDefault();
                setOpen(true);
                setHighlightedIndex(0);
            }
            return;
        }

        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                setHighlightedIndex((i) => Math.min(i + 1, totalItems - 1));
                break;
            case 'ArrowUp':
                e.preventDefault();
                setHighlightedIndex((i) => Math.max(i - 1, 0));
                break;
            case 'Enter':
                e.preventDefault();
                if (highlightedIndex >= 0) {
                    if (showCustomOption && highlightedIndex === filtered.length) {
                        // The "Use '…'" pseudo-option is highlighted
                        handleSelect(query.trim());
                    } else if (filtered[highlightedIndex]) {
                        handleSelect(filtered[highlightedIndex].value);
                    }
                }
                break;
            case 'Escape':
                setOpen(false);
                setQuery('');
                setHighlightedIndex(-1);
                inputRef.current?.blur();
                break;
            case 'Tab':
                setOpen(false);
                setQuery('');
                break;
        }
    }

    // Display: when open show the query; otherwise show the selected label or raw value.
    const displayValue = open
        ? query
        : (selectedOption?.label ?? (allowCustom && value ? value : ''));

    return (
        <div ref={wrapperRef} className="relative">
            <input
                ref={inputRef}
                type="text"
                value={displayValue}
                onChange={handleInputChange}
                onFocus={handleInputFocus}
                onKeyDown={handleKeyDown}
                placeholder={placeholder}
                disabled={disabled}
                autoComplete="off"
                className={`h-10 w-full rounded-[3px] border border-slate-300 px-3 pr-8 text-[13px] outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 ${
                    disabled ? 'bg-slate-50 text-slate-400 cursor-not-allowed' : 'bg-white text-slate-700'
                }`}
            />
            <span className="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 material-symbols-outlined text-[16px] text-slate-400">
                {open ? 'expand_less' : 'expand_more'}
            </span>

            {open && !disabled && (
                <ul ref={listRef} className="absolute z-50 mt-1 w-full max-h-48 overflow-y-auto rounded-md border border-slate-200 bg-white py-1 shadow-lg owb-scroll-wide">
                    {filtered.length > 0 ? (
                        filtered.map((option, idx) => (
                            <li
                                key={option.value}
                                onMouseDown={(e) => { e.preventDefault(); handleSelect(option.value); }}
                                onMouseEnter={() => setHighlightedIndex(idx)}
                                className={`px-3 py-1.5 text-[12px] cursor-pointer transition-colors ${
                                    option.value === value
                                        ? 'bg-indigo-50 text-indigo-700 font-semibold'
                                        : idx === highlightedIndex
                                            ? 'bg-slate-100 text-slate-800'
                                            : 'text-slate-700 hover:bg-slate-50'
                                }`}
                            >
                                {option.label}
                            </li>
                        ))
                    ) : !showCustomOption ? (
                        <li className="px-3 py-2 text-[11px] text-slate-400 italic">No results found</li>
                    ) : null}

                    {showCustomOption && (
                        <li
                            onMouseDown={(e) => { e.preventDefault(); handleSelect(query.trim()); }}
                            onMouseEnter={() => setHighlightedIndex(filtered.length)}
                            className={`px-3 py-1.5 text-[12px] cursor-pointer transition-colors border-t border-slate-100 ${
                                highlightedIndex === filtered.length
                                    ? 'bg-indigo-50 text-indigo-700'
                                    : 'text-slate-500 hover:bg-slate-50'
                            }`}
                        >
                            Use &ldquo;{query.trim()}&rdquo;
                        </li>
                    )}
                </ul>
            )}
        </div>
    );
}
