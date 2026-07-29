import { useMemo } from 'react';

function checkRules(value, rules) {
  const checks = [];

  if (rules?.min_length) {
    checks.push({
      label: `At least ${rules.min_length} characters`,
      met: value.length >= rules.min_length,
    });
  }
  if (rules?.require_mixed_case) {
    checks.push({
      label: 'Uppercase letter',
      hint: '(A-Z)',
      met: /[A-Z]/.test(value),
    });
    checks.push({
      label: 'Lowercase letter',
      hint: '(a-z)',
      met: /[a-z]/.test(value),
    });
  }
  if (rules?.require_numbers) {
    checks.push({
      label: 'Number',
      hint: '(0-9)',
      met: /[0-9]/.test(value),
    });
  }
  if (rules?.require_symbols) {
    checks.push({
      label: 'Symbol',
      hint: '(!@#)',
      met: /[^a-zA-Z0-9]/.test(value),
    });
  }

  return checks;
}

function getStrength(checks, value, minLength) {
  if (!value) return { label: '', percent: 0, color: 'bg-gray-200' };

  // Cap at weak if below minimum length
  if (minLength && value.length < minLength) {
    return { label: 'Weak', percent: 25, color: 'bg-red-500' };
  }

  const met = checks.filter((c) => c.met).length;
  const total = checks.length;

  if (total === 0) return { label: '', percent: 0, color: 'bg-gray-200' };

  const ratio = met / total;

  if (ratio <= 0.25) return { label: 'Weak', percent: 25, color: 'bg-red-500' };
  if (ratio <= 0.5) return { label: 'Fair', percent: 50, color: 'bg-orange-400' };
  if (ratio <= 0.75) return { label: 'Strong', percent: 75, color: 'bg-lime-500' };
  return { label: 'Very Strong', percent: 100, color: 'bg-green-500' };
}

export default function PasswordStrengthMeter({ value = '', rules, confirmation, showAll = false }) {
  const checks = useMemo(() => checkRules(value, rules), [value, rules]);

  const strength = useMemo(
    () => getStrength(checks, value, rules?.min_length),
    [checks, value, rules?.min_length],
  );

  const showChecks = showAll || value.length > 0;

  return (
    <div className="mt-2 space-y-1.5">
      {/* Progress bar */}
      <div className="flex items-center gap-2">
        <div className="h-1.5 flex-1 overflow-hidden rounded-full bg-gray-200">
          <div
            className={`h-full rounded-full transition-all duration-300 ${strength.color}`}
            style={{ width: `${strength.percent}%` }}
          />
        </div>
        {strength.label && (
          <span className="text-xs font-semibold text-gray-500 min-w-[72px] text-right">
            {strength.label}
          </span>
        )}
      </div>

      {/* Rule checklist */}
      {showChecks && (
        <ul className="space-y-0.5">
          {checks.map((check, i) => (
            <li key={i} className="flex items-center gap-1.5 text-xs">
              <span
                className={`material-symbols-outlined text-[14px] ${
                  check.met ? 'text-green-600' : 'text-gray-400'
                }`}
              >
                {check.met ? 'check_circle' : 'radio_button_unchecked'}
              </span>
              <span className={check.met ? 'text-gray-700' : 'text-gray-400'}>
                {check.label}
                {check.hint && (
                  <span className="ml-1 text-gray-400">{check.hint}</span>
                )}
              </span>
            </li>
          ))}
        </ul>
      )}

      {/* Password confirmation match indicator */}
      {confirmation !== undefined && value && (
        <div className="flex items-center gap-1.5 text-xs">
          <span
            className={`material-symbols-outlined text-[14px] ${
              value === confirmation ? 'text-green-600' : 'text-red-500'
            }`}
          >
            {value === confirmation ? 'check_circle' : 'error_outline'}
          </span>
          <span className={value === confirmation ? 'text-gray-700' : 'text-red-600'}>
            {value === confirmation ? 'Passwords match' : 'Passwords do not match'}
          </span>
        </div>
      )}
    </div>
  );
}
