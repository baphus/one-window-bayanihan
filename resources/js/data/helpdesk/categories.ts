import type { HelpdeskArticle, HelpdeskCategory } from "./types";

/**
 * All helpdesk categories extracted from HelpdeskSeeder.
 * Parent categories have parentId: null; child categories reference the parent's id.
 */
export const categories: HelpdeskCategory[] = [
  // ── Parent categories (top-level) ────────────────────────────────────────
  {
    id: "cat-1",
    name: "OFW Assistance",
    slug: "ofw-assistance",
    description:
      "Guides and resources for Overseas Filipino Workers on case submission, document requirements, and rights.",
    parentId: null,
    icon: "badge",
    sortOrder: 0,
  },
  {
    id: "cat-2",
    name: "Case Management",
    slug: "case-management",
    description:
      "Standard operating procedures, workflows, and guidelines for Case Managers.",
    parentId: null,
    icon: "folder",
    sortOrder: 1,
  },
  {
    id: "cat-3",
    name: "Agency Partnership",
    slug: "agency-partnership",
    description:
      "Referral processing, status updates, and coordination guides for partner agencies.",
    parentId: null,
    icon: "handshake",
    sortOrder: 2,
  },
  {
    id: "cat-4",
    name: "System Administration",
    slug: "system-administration",
    description:
      "Configuration, account management, and troubleshooting for system administrators.",
    parentId: null,
    icon: "settings",
    sortOrder: 3,
  },
  {
    id: "cat-5",
    name: "Frequently Asked Questions",
    slug: "faq",
    description:
      "Common questions and answers about the One Window Bayanihan system.",
    parentId: null,
    icon: "help",
    sortOrder: 4,
  },

  // ── Child categories (under OFW Assistance) ──────────────────────────────
  {
    id: "cat-6",
    name: "Case Submission",
    slug: "case-submission",
    description: "Guides on submitting cases and required documents.",
    parentId: "cat-1",
    icon: "add_circle",
    sortOrder: 0,
  },
  {
    id: "cat-7",
    name: "OFW Rights & Protection",
    slug: "ofw-rights",
    description:
      "Information on OFW rights, repatriation, and legal assistance.",
    parentId: "cat-1",
    icon: "gavel",
    sortOrder: 1,
  },

  // ── Child categories (under Case Management) ─────────────────────────────
  {
    id: "cat-8",
    name: "Case Manager Workflow",
    slug: "cm-workflow",
    description: "Standard procedures for case intake and management.",
    parentId: "cat-2",
    icon: "assignment",
    sortOrder: 0,
  },
  {
    id: "cat-9",
    name: "Referrals & Escalations",
    slug: "referrals-escalations",
    description:
      "Guides on creating, processing, and escalating referrals.",
    parentId: "cat-2",
    icon: "swap_horiz",
    sortOrder: 1,
  },

  // ── Child categories (under Agency Partnership) ──────────────────────────
  {
    id: "cat-10",
    name: "Referral Processing",
    slug: "referral-processing",
    description: "Step-by-step guides for agency focal persons.",
    parentId: "cat-3",
    icon: "handshake",
    sortOrder: 0,
  },
  {
    id: "cat-11",
    name: "Coordination & Communication",
    slug: "coordination-communication",
    description: "Guidelines for inter-agency coordination.",
    parentId: "cat-3",
    icon: "forum",
    sortOrder: 1,
  },

  // ── Child categories (under System Administration) ───────────────────────
  {
    id: "cat-12",
    name: "User & Account Management",
    slug: "user-account-management",
    description: "Managing user accounts, roles, and permissions.",
    parentId: "cat-4",
    icon: "manage_accounts",
    sortOrder: 0,
  },
  {
    id: "cat-13",
    name: "System Configuration",
    slug: "system-config",
    description:
      "System settings, agencies, services, and troubleshooting.",
    parentId: "cat-4",
    icon: "tune",
    sortOrder: 1,
  },
];

export interface CategoryWithTree extends HelpdeskCategory {
  children: (HelpdeskCategory & { articleCount: number })[];
  articleCount: number;
}

/**
 * Build a hierarchical category tree with article counts.
 * Single source of truth for the helpdesk sidebar.
 * Replaces duplicate logic previously in Index.jsx, Show.jsx, and HelpdeskLayout.jsx.
 */
export function buildCategoryTree(
  catList: HelpdeskCategory[],
  articles: HelpdeskArticle[],
): CategoryWithTree[] {
  const counts: Record<string, number> = {};
  articles.forEach((a) => {
    counts[a.categoryId] = (counts[a.categoryId] || 0) + 1;
  });

  const children = catList.filter((c) => c.parentId !== null);
  const topLevel = [...catList.filter((c) => c.parentId === null)].sort(sortCategories);

  return topLevel.map((parent) => ({
    ...parent,
    children: children
      .filter((c) => c.parentId === parent.id)
      .sort(sortCategories)
      .map((child) => ({
        ...child,
        articleCount: counts[child.id] || 0,
      })),
    articleCount:
      (counts[parent.id] || 0) +
      children
        .filter((c) => c.parentId === parent.id)
        .reduce((sum, child) => sum + (counts[child.id] || 0), 0),
  }));
}

function sortCategories(a: HelpdeskCategory, b: HelpdeskCategory) {
  const orderA = a.sortOrder ?? 0;
  const orderB = b.sortOrder ?? 0;

  if (orderA !== orderB) {
    return orderA - orderB;
  }

  return a.name.localeCompare(b.name);
}

export function getDescendantCategoryIds(
  catList: HelpdeskCategory[],
  categoryId: string,
): string[] {
  const children = catList.filter((c) => c.parentId === categoryId);

  return children.flatMap((child) => [child.id, ...getDescendantCategoryIds(catList, child.id)]);
}
