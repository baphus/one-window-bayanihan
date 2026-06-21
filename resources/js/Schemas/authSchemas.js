import { z } from 'zod';

export const registerSchema = z.object({
  name: z
    .string()
    .min(1, 'Name is required.')
    .max(255, 'Name must not exceed 255 characters.'),
  email: z
    .string()
    .min(1, 'Email is required.')
    .email('Please provide a valid email address.'),
  password: z
    .string()
    .min(1, 'Password is required.')
    .min(8, 'Password must be at least 8 characters.'),
  password_confirmation: z
    .string()
    .min(1, 'Please confirm your password.'),
}).refine((data) => data.password === data.password_confirmation, {
  message: 'Passwords do not match.',
  path: ['password_confirmation'],
});

export const resetPasswordSchema = z.object({
  email: z
    .string()
    .min(1, 'Email is required.')
    .email('Please provide a valid email address.'),
  password: z
    .string()
    .min(1, 'Password is required.')
    .min(8, 'Password must be at least 8 characters.'),
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

export const confirmPasswordSchema = z.object({
  password: z
    .string()
    .min(1, 'Password is required.'),
});

