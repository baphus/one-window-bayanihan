import { useState } from 'react';
import InputError from '@/Components/InputError';
import PasswordStrengthMeter from '@/Components/PasswordStrengthMeter';

export default function PasswordInput({
  id,
  label,
  value,
  onChange,
  error,
  autoComplete,
  autoFocus,
  showStrengthMeter = false,
  rules,
  confirmation,
  placeholder,
  readOnly,
  required,
  className = '',
}) {
  const [show, setShow] = useState(false);

  return (
    <div className={className}>
      {label && (
        <label htmlFor={id} className="mb-2 block text-xs font-bold uppercase tracking-widest text-on-surface-variant">
          {label}
        </label>
      )}

      <div className="relative">
        <span className="absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant/40 text-[20px] material-symbols-outlined">
          lock
        </span>

        <input
          id={id}
          type={show ? 'text' : 'password'}
          value={value}
          onChange={(e) => onChange(e.target.value)}
          className="w-full border border-outline-variant bg-surface-container px-4 py-3 pl-12 pr-12 text-sm focus:border-primary focus:outline-none rounded-none"
          autoComplete={autoComplete}
          autoFocus={autoFocus}
          placeholder={placeholder}
          readOnly={readOnly}
          required={required}
        />

        <button
          type="button"
          onClick={() => setShow(!show)}
          className="absolute right-4 top-1/2 -translate-y-1/2 text-on-surface-variant/40 hover:text-primary"
          tabIndex={-1}
          aria-label={show ? 'Hide password' : 'Show password'}
        >
          <span className="material-symbols-outlined text-[20px]">
            {show ? 'visibility_off' : 'visibility'}
          </span>
        </button>
      </div>

      <InputError message={error} className="mt-2" />

      {showStrengthMeter && (
        <PasswordStrengthMeter
          value={value}
          rules={rules}
          confirmation={confirmation}
          showAll
        />
      )}
    </div>
  );
}
