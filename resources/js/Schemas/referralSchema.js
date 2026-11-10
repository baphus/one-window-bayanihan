import { z } from 'zod';

export const referralSchema = z.object({
  case_id: z.string().min(1, 'Case is required.').uuid('Invalid case ID format.'),
  category_id: z.string().min(1, 'Category is required.').uuid('Invalid category ID format.'),
  agcy_id: z.string().min(1, 'Agency is required.').uuid('Invalid agency ID format.'),
  concern: z.string().trim().min(1, 'Describe the concern for this referral.').max(5000, 'Concern must not exceed 5000 characters.'),
  override_reason: z.string().max(5000, 'Override reason must not exceed 5000 characters.').optional().nullable(),
  notes: z.string().max(5000, 'Remarks must not exceed 5000 characters.').optional().nullable(),
  documents: z.array(z.any()).optional().nullable(),
});
