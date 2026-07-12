import { useState, useEffect, useRef, useCallback } from 'react';
import { parsePhoneNumber } from 'libphonenumber-js';
import phoneCodes from '@/data/phone-codes.json';

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
            try {
                const phoneNumber = parsePhoneNumber(input, country);
                if (phoneNumber && phoneNumber.isValid()) {
                    setInternalError('');
                    return phoneNumber.number; // E.164 format
                }
                setInternalError('Invalid phone number');
                return input;
            } catch {
                // Graceful fallback when parsePhoneNumber cannot handle the input
                return input;
            }
        },
        [],
    );

    // Emit the E.164 value to the parent or fall back to raw input
    const emitValue = useCallback(
        (input, country) => {
            try {
                const phoneNumber = parsePhoneNumber(input, country);
                if (phoneNumber && phoneNumber.isValid()) {
                    isUpdatingRef.current = true;
                    onChange(phoneNumber.number);
                    return phoneNumber.number;
                }
            } catch {
                // fall through
            }
            isUpdatingRef.current = true;
            onChange(input);
            return input;
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
                try {
                    const phoneNumber = parsePhoneNumber(rawInput, newCountry);
                    if (phoneNumber && phoneNumber.isValid()) {
                        setInternalError('');
                        emitValue(rawInput, newCountry);
                    } else {
                        setInternalError('Invalid phone number');
                    }
                } catch {
                    setInternalError('');
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
