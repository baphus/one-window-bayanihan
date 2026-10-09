import { useState, useEffect, useRef, useCallback } from 'react';
import phoneCodes from '@/data/phone-codes.json';

// Lightweight E.164 normalization: digits only, 7–15 digits total.
// Returns the E.164 value when the input looks like a real number,
// otherwise the raw input so nothing the user typed is ever lost.
function toE164(input, dialCode) {
    const text = String(input ?? '').trim();
    if (text === '') {
        return { value: '', valid: true };
    }
    const dialDigits = String(dialCode).replace(/\D/g, '');
    let digits = text.replace(/\D/g, '');
    if (digits === '') {
        return { value: text, valid: false };
    }
    if (!text.startsWith('+') && !digits.startsWith(dialDigits)) {
        digits = `${dialDigits}${digits.replace(/^0+/, '')}`;
    }
    const valid = digits.length >= 7 && digits.length <= 15;
    return { value: valid ? `+${digits}` : text, valid };
}

function dialOf(country) {
    return phoneCodes.find((c) => c.code === country)?.dial_code ?? '';
}

export default function PhoneInput({
    value,
    onChange,
    defaultCountry = 'PH',
    placeholder = 'Phone number',
    error,
    className = '',
}) {
    const [countryCode, setCountryCode] = useState(defaultCountry);
    const [rawInput, setRawInput] = useState('');
    const [internalError, setInternalError] = useState('');
    const [touched, setTouched] = useState(false);
    const isUpdatingRef = useRef(false);

    // Find the selected country object
    const countries = phoneCodes;
    const selectedCountry = countries.find((c) => c.code === countryCode) || countries[0];

    // Sync raw input from the parent value prop (E.164 string)
    useEffect(() => {
        if (isUpdatingRef.current) {
            isUpdatingRef.current = false;
            return;
        }
        if (value) {
            setRawInput(value);
        }
    }, [value]);

    // Validate the current input against a given country.
    // Returns the validated/formatted number or the raw input on failure.
    const validateNumber = useCallback(
        (input, country) => {
            if (!input || input.trim() === '') {
                setInternalError('');
                return '';
            }
            const { value, valid } = toE164(input, dialOf(country));
            setInternalError(valid ? '' : 'Invalid phone number');
            return value;
        },
        [],
    );

    // Emit the E.164 value to the parent or fall back to raw input
    const emitValue = useCallback(
        (input, country) => {
            const { value } = toE164(input, dialOf(country));
            isUpdatingRef.current = true;
            onChange(value);
            return value;
        },
        [onChange],
    );

    // --- Handlers ---

    const handleCountryChange = useCallback(
        (e) => {
            const newCountry = e.target.value;
            setCountryCode(newCountry);

            // Re-validate the current input against the new country
            if (rawInput && rawInput.trim()) {
                const { valid } = toE164(rawInput, dialOf(newCountry));
                if (valid) {
                    setInternalError('');
                    emitValue(rawInput, newCountry);
                } else {
                    setInternalError('Invalid phone number');
                }
            } else {
                setInternalError('');
            }
        },
        [rawInput, emitValue],
    );

    const handleInputChange = useCallback((e) => {
        setRawInput(e.target.value);
        // Clear internal error as the user types
        if (internalError) {
            setInternalError('');
        }
    }, [internalError]);

    const handleBlur = useCallback(() => {
        setTouched(true);

        if (!rawInput || rawInput.trim() === '') {
            setInternalError('');
            isUpdatingRef.current = true;
            onChange('');
            return;
        }

        const validated = validateNumber(rawInput, countryCode);
        // If validation changed the value (e.g. stripping formatting), update display
        if (validated !== rawInput) {
            setRawInput(validated);
        }

        emitValue(rawInput, countryCode);
    }, [rawInput, countryCode, onChange, validateNumber, emitValue]);

    // Resolve which error to display: parent prop > internal validation
    const displayError = error || (touched ? internalError : '');

    return (
        <div className={className}>
            <div className="flex gap-2">
                <select
                    value={countryCode}
                    onChange={handleCountryChange}
                    className="h-10 w-[96px] shrink-0 rounded-[3px] border border-slate-300 px-2 py-2 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                >
                    {countries.map((c) => (
                        <option key={c.code} value={c.code}>
                            {c.flag} {c.dial_code}
                        </option>
                    ))}
                </select>
                <input
                    type="tel"
                    value={rawInput}
                    onChange={handleInputChange}
                    onBlur={handleBlur}
                    placeholder={placeholder}
                    className="h-10 flex-1 rounded-[3px] border border-slate-300 px-3 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                />
            </div>
            {displayError && (
                <p className="mt-1 text-[11px] text-red-500">{displayError}</p>
            )}
        </div>
    );
}
