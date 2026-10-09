import { z } from 'zod';

/**
 * Single rule list for server-defined password policy. Both the Zod field
 * builder below and UI meters derive from this list so the policy lives
 * in exactly one place.
 *
 * Each entry carries the display message plus an `apply` that layers the
 * rule onto a Zod string field and a `met` predicate for UI checklists.
 */
export function getPasswordRuleChecks(rules) {
  const checks = [];

  if (rules?.min_length) {
    const min = rules.min_length;
    checks.push({
      key: 'min_length',
      message: `At least ${min} characters.`,
      apply: (field) => field.min(min, `At least ${min} characters.`),
      met: (value) => value.length >= min,
    });
  }
  if (rules?.require_mixed_case) {
    checks.push({
      key: 'lowercase',
      message: 'Must include a lowercase letter.',
      apply: (field) => field.regex(/[a-z]/, 'Must include a lowercase letter.'),
      met: (value) => /[a-z]/.test(value),
    });
    checks.push({
      key: 'uppercase',
      message: 'Must include an uppercase letter.',
      apply: (field) => field.regex(/[A-Z]/, 'Must include an uppercase letter.'),
      met: (value) => /[A-Z]/.test(value),
    });
  }
  if (rules?.require_numbers) {
    checks.push({
      key: 'number',
      message: 'Must include a number.',
      apply: (field) => field.regex(/[0-9]/, 'Must include a number.'),
      met: (value) => /[0-9]/.test(value),
    });
  }
  if (rules?.require_symbols) {
    checks.push({
      key: 'symbol',
      message: 'Must include a symbol.',
      apply: (field) => field.regex(/[^a-zA-Z0-9]/, 'Must include a symbol.'),
      met: (value) => /[^a-zA-Z0-9]/.test(value),
    });
  }

  return checks;
}

/**
 * Meter-shaped evaluation of the rule list: [{ key, label, met }].
 * Same shape as the password-strength checklist so meters can adopt it.
 */
export function checkPasswordRules(value, rules) {
  const text = value ?? '';
  return getPasswordRuleChecks(rules).map(({ key, message, met }) => ({
    key,
    label: message.replace(/\.$/, ''),
    met: met(text),
  }));
}

/**
 * Build a Zod string field with password validators based on server-defined rules.
 *
 * @param {Object} rules - Password rules from Inertia shared props (pageProps.passwordRules)
 * @param {number} rules.min_length
 * @param {boolean} rules.require_mixed_case
 * @param {boolean} rules.require_numbers
 * @param {boolean} rules.require_symbols
 * @returns {import('zod').ZodString}
 */
export default function createPasswordSchema(rules) {
  let field = z.string().min(1, 'Password is required.');

  for (const check of getPasswordRuleChecks(rules)) {
    field = check.apply(field);
  }

  return field;
}

/**
 * Shared password-confirmation refinement for object schemas with
 * `password` + `password_confirmation` fields.
 */
export function withPasswordConfirmation(schema) {
  return schema.refine((data) => data.password === data.password_confirmation, {
    message: 'Passwords do not match.',
    path: ['password_confirmation'],
  });
}
