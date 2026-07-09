// ---------------------------------------------------------------------------
// HelpdeskTag
// ---------------------------------------------------------------------------
export interface HelpdeskTag {
  id: string;
  name: string;
}

// ---------------------------------------------------------------------------
// HelpdeskCategory
// ---------------------------------------------------------------------------
export interface HelpdeskCategory {
  id: string;
  name: string;
  slug: string;
  description: string;
  parentId: string | null;
  icon?: string;
  sortOrder?: number;
}

// ---------------------------------------------------------------------------
// HelpdeskArticle
// ---------------------------------------------------------------------------
export interface HelpdeskArticle {
  id: string;
  title: string;
  slug: string;
  excerpt: string;
  content: string;
  categoryId: string;
  tagIds: string[];
  featured: boolean;
  publishedAt: string;
}
