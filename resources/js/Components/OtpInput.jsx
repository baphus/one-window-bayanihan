import { useId, useState } from 'react';

export default function OtpInput({ id, label, value, onChange, error, disabled = false, autoFocus = false, inputRef, className = '', 'data-dialog-initial-focus': dialogInitialFocus }) {
    const generatedId = useId();
    const inputId = id || generatedId;
    const errorId = `${inputId}-error`;
    const [focused, setFocused] = useState(false);
    const [caret, setCaret] = useState(String(value ?? '').length);
    const digits = String(value ?? '').slice(0, 6).split('');
    const activeSlot = Math.min(caret, 5);

    return (
        <div className={className}>
            <label htmlFor={inputId} className="mb-2 block text-sm font-semibold text-slate-700">{label}</label>
            <div className="relative mx-auto w-full max-w-[22rem]">
                <div aria-hidden="true" className="grid grid-cols-6 gap-2">
                    {Array.from({ length: 6 }, (_, index) => (
                        <span key={index} className={`flex h-12 min-w-0 items-center justify-center rounded-lg border bg-white font-mono text-xl font-semibold text-slate-900 transition-colors sm:h-14 ${error ? `border-red-400 ${focused && index === activeSlot ? 'ring-2 ring-red-300' : ''}` : focused && index === activeSlot ? 'border-primary ring-2 ring-primary/20' : 'border-slate-300'} ${disabled ? 'bg-slate-100 text-slate-400' : ''}`}>
                            {digits[index] || ''}
                        </span>
                    ))}
                </div>
                <input
                    id={inputId}
                    ref={inputRef}
                    type="text"
                    inputMode="numeric"
                    autoComplete="one-time-code"
                    autoCorrect="off"
                    spellCheck={false}
                    value={value}
                    onChange={(event) => {
                        const next = event.target.value.replace(/\D/g, '').slice(0, 6);
                        setCaret(Math.min(event.target.selectionStart ?? next.length, next.length));
                        onChange(next);
                    }}
                    onPaste={(event) => {
                        const pasted = event.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6);
                        if (pasted.length) {
                            event.preventDefault();
                            setCaret(pasted.length);
                            onChange(pasted);
                        }
                    }}
                    onSelect={(event) => setCaret(event.currentTarget.selectionStart ?? 0)}
                    onFocus={(event) => { setFocused(true); setCaret(event.currentTarget.selectionStart ?? digits.length); }}
                    onBlur={() => setFocused(false)}
                    disabled={disabled}
                    autoFocus={autoFocus}
                    data-dialog-initial-focus={dialogInitialFocus ? '' : undefined}
                    aria-label={`${label}, 6 digits`}
                    aria-invalid={Boolean(error)}
                    aria-describedby={error ? errorId : undefined}
                    required
                    className="absolute inset-0 h-full w-full cursor-text opacity-[0.015] disabled:cursor-not-allowed"
                />
            </div>
            {error && <p id={errorId} role="alert" className="mt-2 text-sm text-red-600">{error}</p>}
        </div>
    );
}
