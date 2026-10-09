import { z } from 'zod';

export const referralSchema = z.object({
  case_id: z
    .string()
    .min(1, 'Case ID is required.')
    .uuid('Invalid Case ID format.'),
  agcy_id: z
    .string()
    .min(1, 'Agency ID is required.')
    .uuid('Invalid Agency ID format.'),
  notes: z
    .string()
    .max(5000, 'Remarks must not exceed 5000 characters.')
    .optional()
    .nullable(),
  documents: z
    .array(z.any())
    .optional()
    .nullable(),
});

