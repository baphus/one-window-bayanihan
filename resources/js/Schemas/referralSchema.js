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
  services: z
    .array(z.string())
    .min(1, 'At least one service must be selected.'),
  notes: z
    .string()
    .optional(),
});

