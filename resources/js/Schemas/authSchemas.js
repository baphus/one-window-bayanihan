import { z } from 'zod';
import createPasswordSchema from '@/utils/createPasswordSchema';

/**
 * Registration schema — password uses server-defined rules.
 */
export function makeRegisterSchema(rules) {
  return z.object({
    name: z
      .string()
      .min(1, 'Name is required.')
      .max(255, 'Name must not exceed 255 characters.'),
    email: z
      .string()
      .min(1, 'Email is required.')
      .email('Please provide a valid email address.'),
    password: createPasswordSchema(rules),
    password_confirmation: z
      .string()
      .min(1, 'Please confirm your password.'),
  }).refine((data) => data.password === data.password_confirmation, {
    message: 'Passwords do not match.',
    path: ['password_confirmation'],
  });
}

/**
 * Password reset schema — password uses server-defined rules.
 */
export function makeResetPasswordSchema(rules) {
  return z.object({
    email: z
      .string()
      .min(1, 'Email is required.')
      .email('Please provide a valid email address.'),
    password: createPasswordSchema(rules),
    password_confirmation: z
      .string()
      .min(1, 'Please confirm your password.'),
    token: z
      .string()
      .min(1, 'Reset token is required.'),
  }).refine((data) => data.password === data.password_confirmation, {
    message: 'Passwords do not match.',
    path: ['password_confirmation'],
  });
}

/**
 * Confirm password schema — just checks the field is filled (current password re-auth).
 */
export const confirmPasswordSchema = z.object({
  password: z
    .string()
    .min(1, 'Password is required.'),
});

/**
 * Login schema — email format + password not-empty (no complexity check on login).
 */
export function makeLoginSchema() {
  return z.object({
    email: z
      .string()
      .min(1, 'Email is required.')
      .email('Please provide a valid email address.'),
    password: z
      .string()
      .min(1, 'Password is required.'),
  });
}

/**
 * Forgot password schema — email format only.
 */
export function makeForgotPasswordSchema() {
  return z.object({
    email: z
      .string()
      .min(1, 'Email is required.')
      .email('Please provide a valid email address.'),
  });
}
