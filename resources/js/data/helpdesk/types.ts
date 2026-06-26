import * as z from "zod";

// ---------------------------------------------------------------------------
// HelpdeskTag
// ---------------------------------------------------------------------------
export const helpdeskTagSchema = z.object({
  id: z.string(),
  name: z.string(),
});

export type HelpdeskTag = z.infer<typeof helpdeskTagSchema>;

// ---------------------------------------------------------------------------
// HelpdeskCategory
// ---------------------------------------------------------------------------
export const helpdeskCategorySchema = z.object({
  id: z.string(),
  name: z.string(),
  slug: z.string(),
  description: z.string(),
  parentId: z.string().nullable(),
  icon: z.string().optional(),
  sortOrder: z.number().optional(),
});

export type HelpdeskCategory = z.infer<typeof helpdeskCategorySchema>;

// ---------------------------------------------------------------------------
// HelpdeskArticle
// ---------------------------------------------------------------------------
export const helpdeskArticleSchema = z.object({
  id: z.string(),
  title: z.string(),
  slug: z.string(),
  excerpt: z.string(),
  content: z.string(),
  categoryId: z.string(),
  tagIds: z.array(z.string()),
  featured: z.boolean(),
  publishedAt: z.string(),
});

export type HelpdeskArticle = z.infer<typeof helpdeskArticleSchema>;
