import { z } from 'zod';

export const profileSchema = z.object({
  name: z
    .string()
    .min(1, 'Name is required.')
    .max(255, 'Name must not exceed 255 characters.'),
  email: z
    .string()
    .min(1, 'Email is required.')
    .email('Please provide a valid email address.'),
  contact_number: z.string().optional(),
  address: z.string().optional(),
  notifications_enabled: z.boolean().optional(),
});

export const updatePasswordSchema = z.object({
  current_password: z
    .string()
    .min(1, 'Current password is required.'),
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

