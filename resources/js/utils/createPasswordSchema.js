import { z } from 'zod';

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

  if (rules?.min_length) {
    field = field.min(rules.min_length, `At least ${rules.min_length} characters.`);
  }
  if (rules?.require_mixed_case) {
    field = field
      .regex(/[a-z]/, 'Must include a lowercase letter.')
      .regex(/[A-Z]/, 'Must include an uppercase letter.');
  }
  if (rules?.require_numbers) {
    field = field.regex(/[0-9]/, 'Must include a number.');
  }
  if (rules?.require_symbols) {
    field = field.regex(/[^a-zA-Z0-9]/, 'Must include a symbol.');
  }

  return field;
}
