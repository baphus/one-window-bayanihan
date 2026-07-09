import { z } from 'zod';

export const userFormSchema = z.object({
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
    .min(8, 'Password must be at least 8 characters.')
    .optional()
    .or(z.literal('')),
  role: z
    .string()
    .min(1, 'Role is required.')
    .refine((val) => ['CASE_MANAGER', 'AGENCY', 'ADMIN'].includes(val), {
      message: 'Please select a valid role.',
    }),
  agcy_id: z.string().uuid().nullable().optional(),
  contact_number: z.string().optional(),
});

/**
 * Stricter schema for new user creation — password is required.
 */
export const createUserFormSchema = z.object({
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
    .min(8, 'Password must be at least 8 characters.'),
  role: z
    .string()
    .min(1, 'Role is required.')
    .refine((val) => ['CASE_MANAGER', 'AGENCY', 'ADMIN'].includes(val), {
      message: 'Please select a valid role.',
    }),
  agcy_id: z.string().uuid().nullable().optional(),
  contact_number: z.string().optional(),
});

export const serviceFormSchema = z.object({
  name: z
    .string()
    .min(1, 'Name is required.')
    .max(255, 'Name must not exceed 255 characters.'),
  description: z.string().optional(),
  agcy_id: z
    .string()
    .min(1, 'Agency is required.')
    .uuid('Please select a valid agency.'),
  processing_days: z
    .number()
    .min(0, 'Processing days must be a non-negative number.')
    .optional(),
  requirements: z
    .array(
      z.object({
        name: z
          .string()
          .min(1, 'Requirement name is required.'),
        description: z.string().optional(),
        is_required: z.boolean(),
        sort_order: z.number().optional(),
      })
    )
    .optional(),
});

export const agencyFormSchema = z.object({
  name: z
    .string()
    .min(1, 'Name is required.')
    .max(255, 'Name must not exceed 255 characters.'),
  short: z
    .string()
    .min(1, 'Short name is required.')
    .max(50, 'Short name must not exceed 50 characters.'),
  description: z.string().optional(),
  contact_info: z.string().optional(),
  logo_url: z.any().nullable().optional(),
  location_query: z.string().nullable().optional(),
  latitude: z.number().nullable().optional(),
  longitude: z.number().nullable().optional(),
  is_active: z.boolean().optional(),
  map_link: z.string().url('Must be a valid URL').optional().or(z.literal('')),
});

export const caseStatusSchema = z.object({
  name: z
    .string()
    .min(1, 'Name is required.')
    .max(255, 'Name must not exceed 255 characters.'),
  type: z
    .string()
    .min(1, 'Type is required.')
    .refine((val) => ['case', 'referral'].includes(val), {
      message: 'Type must be either "case" or "referral".',
    }),
  color: z.string().optional(),
  sort_order: z.number().optional(),
  is_default: z.boolean().optional(),
});

export const caseCategorySchema = z.object({
  name: z
    .string()
    .min(1, 'Name is required.')
    .max(255, 'Name must not exceed 255 characters.'),
  description: z.string().optional(),
  color: z.string().optional(),
  sort_order: z.number().optional(),
});

export const caseIssueSchema = z.object({
  name: z
    .string()
    .min(1, 'Name is required.')
    .max(255, 'Name must not exceed 255 characters.'),
  sort_order: z.number().optional(),
});
