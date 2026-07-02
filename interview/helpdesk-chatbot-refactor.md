---
sessionID: ses_0de853824ffeyVrlYpgUPxyimI
baseMessageCount: 10
updatedAt: 2026-07-02T07:06:12.812Z
version: 1.0
date_created: 2026-07-02
owner: agent
tags: [spec, ready]
---

# Helpdesk Refactor Specification

> Refactor and fix the helpdesk pages. Chatbot knowledge base integration deferred.

## 1. Purpose & Scope

Intended for the development team implementing helpdesk improvements. Scope covers fixing Inertia SPA navigation bugs, consolidating the article data model, removing dead code, and extracting shared utilities.

**In scope:**
- Fix SPA navigation bugs (`window.location` → Inertia props / `router.visit`)
- Inline article markdown content into `articles.ts`; remove `content/*.ts` dynamic imports
- Remove dead `Category.jsx` page (no route renders it)
- Extract shared `buildCategoryTree()` utility into `categories.ts`
- Fix search bar navigation (`window.location.href` → `router.visit()`)
- Remove "Most Popular" section from Index (was a no-op duplicate of "Recently Updated")
- Simplify Show.jsx (remove dynamic import, loading state, IIFE category tree)

**Deferred (future iteration):**
- Chatbot knowledge base integration (all 22 articles as AI context)
- Chatbot keyword response improvements

## 2. Definitions

| Term | Definition |
|------|-----------|
| Helpdesk KB | 22 articles across 13 categories stored as TypeScript data modules in `resources/js/data/helpdesk/` |
| Fuse.js | Client-side fuzzy search library (threshold 0.4, distance 100) |
| Laravel AI | Laravel's first-party `agent()` helper for LLM interactions |
| SPA navigation | Inertia.js client-side navigation without full page reloads |

## 3. Requirements, Constraints & Guidelines

| ID | Description |
|----|-------------|
| **REQ-001** | Helpdesk pages must use Inertia page props for all page state — no direct `window.location` reads |
| **REQ-002** | Category filtering stays on the Index page using `?category=` query params passed as Inertia props |
| **REQ-003** | Article body content must be inlined into the `content` field of `articles.ts`; remove `content/*.ts` |
| **REQ-004** | Helpdesk Layout search bar must use `router.visit()` not `window.location.href` |
| **REQ-005** | Show.jsx must read article slug from Inertia page props, not `window.location.pathname` |
| **REQ-006** | Remove "Most Popular" section from Index.jsx (was identical to "Recently Updated") |
| **SEC-001** | Helpdesk endpoints are public — no auth gate required |
| **CON-001** | Laravel 13 + Inertia + React 18 + Tailwind CSS 3 + Vite 8 + PostgreSQL |
| **CON-002** | All helpdesk data is static TypeScript modules — no database tables |
| **CON-003** | Path alias `@/` → `resources/js/` |
| **GUD-001** | Extract shared `buildCategoryTree()` into `data/helpdesk/categories.ts` |
| **GUD-002** | Remove dead file `resources/js/Pages/Helpdesk/Category.jsx` |
| **GUD-003** | After inlining, remove `resources/js/data/helpdesk/content/` directory (22 files) |
| **GUD-004** | After inlining, Show.jsx no longer needs dynamic import or loading state render |
| **GUD-005** | Clean up HelpdeskLayout `categories` prop — currently ignored (layout rebuilds from data modules) |

## 4. Interfaces & Data Contracts

### HelpdeskArticle (after inlining)

```typescript
// resources/js/data/helpdesk/types.ts
export const helpdeskArticleSchema = z.object({
  id: z.string(),
  title: z.string(),
  slug: z.string(),
  excerpt: z.string(),
  content: z.string(),        // Full markdown body (was always "")
  categoryId: z.string(),
  tagIds: z.array(z.string()),
  featured: z.boolean(),
  publishedAt: z.string(),
});
```

### Routes (after fix)

```php
// routes/web.php
Route::prefix('helpdesk')->name('helpdesk.')->group(function () {
    Route::get('/', function (Request $request) {
        return inertia('Helpdesk/Index', [
            'category' => $request->query('category'),
        ]);
    })->name('index');
    Route::get('/search', fn () => inertia('Helpdesk/Search'))->name('search');
    Route::get('/{slug}', fn ($slug) => inertia('Helpdesk/Show', [
        'slug' => $slug,
    ]))->name('show');
});
```

### Category tree utility (extracted into categories.ts)

```typescript
// resources/js/data/helpdesk/categories.ts (new exports)
export interface CategoryWithTree extends HelpdeskCategory {
  children: (HelpdeskCategory & { articleCount: number })[];
  articleCount: number;
}

/**
 * Build a hierarchical category tree with article counts.
 * Single source of truth — replaces duplicate logic in Index, Show, and Layout.
 */
export function buildCategoryTree(
  categories: HelpdeskCategory[],
  articles: HelpdeskArticle[],
): CategoryWithTree[]
```

### HelpdeskLayout props (simplified)

```typescript
interface HelpdeskLayoutProps {
  title: string;
  children: ReactNode;
  activeSlug?: string;
  query?: string;
  showSearchHero?: boolean;
}
// NOTE: `categories` prop removed — layout builds its own tree internally
```

## 5. Acceptance Criteria

| ID | Given | When | Then |
|----|-------|------|------|
| **AC-001** | A user clicks a category link in the sidebar | The link is clicked | Index page re-renders filtered articles without a full page reload |
| **AC-002** | A user navigates to `/helpdesk/some-article` | The page loads | Slug is read from Inertia page props (not `window.location`) |
| **AC-003** | A user submits a search from the helpdesk search bar | They press enter | They navigate SPA-style to `/helpdesk/search?q=...` |
| **AC-004** | An article has inline markdown content in its `content` field | Show renders | It renders via MarkdownRenderer directly (no dynamic import, no loading spinner) |
| **AC-005** | No `?category=` query param | Index loads | All 22 articles are shown |
| **AC-006** | `?category=cm-workflow` is present | Index loads | Only articles in that category tree (including children) are shown |
| **AC-007** | An invalid `?category=` slug is present | Index loads | "Category not found" empty state is shown |
| **AC-008** | `buildCategoryTree()` is called with categories and articles | It executes | It returns the correct hierarchical tree with nested children and article counts |
| **AC-009** | The Index page renders | After render | No "Most Popular" section appears |

## 6. Test Automation Strategy

### Frontend (Vitest + jsdom)

| Test | What to verify |
|------|---------------|
| `buildCategoryTree()` | Correct tree structure with nested children, article counts, empty-child handling |
| `searchArticles()` | Fuse.js index returns correct results for various queries |
| ArticleCard | Renders with title, excerpt, category badge, link |
| TagBadge | Renders tag name |
| Breadcrumbs | Renders correct trail for nested categories |

**No backend tests needed** for this iteration — routes are unchanged server-side.

## 7. Rationale & Context

### Inline article content (not dynamic imports)

- Removes fragile `import(/* @vite-ignore */ ...)` that silently fails on missing or renamed files.
- Eliminates the content/metadata split — everything in one place in `articles.ts`.
- No async boundary — content renders immediately without loading states.
- 22 small text-only files — no bundle size concern.

### Index page with query params (not separate Category page)

- Category filtering is a filtered Index view; no separate route needed.
- Query params give shareable, bookmarkable URLs per category.
- `Category.jsx` was already dead code — never reachable via any route.

### Extracted `buildCategoryTree()`

- Logic was triplicated across `Index.jsx`, `Show.jsx`, and `HelpdeskLayout.jsx`.
- Each variant computed a slightly different shape, causing sidebar inconsistencies.
- A single source of truth ensures the sidebar renders identically on every page.

### Remove "Most Popular"

- Showed all non-featured articles — identical to "Recently Updated".
- No popularity tracking exists in the system.
- Removes redundant UI without losing any information.

## 8. Dependencies & External Integrations

| ID | Dependency | Purpose |
|----|-----------|---------|
| **EXT-001** | Fuse.js | Client-side fuzzy search |
| **EXT-002** | `@uiw/react-md-editor` + rehype-sanitize | Markdown rendering in article Show page |
| **EXT-003** | `@inertiajs/react` | SPA framework (`router.visit`, `Link`, `usePage`) |

## 9. Examples & Edge Cases

### Category filtering on Index

```
/helpdesk?category=cm-workflow
```

Index filters articles to the `cm-workflow` category tree (including child categories). Shows category name in the heading. If no articles match, shows empty state.

### Unknown category slug

```
/helpdesk?category=nonexistent
```

`buildCategoryTree()` returns no match. Index shows "Category not found" with a link to browse all articles.

### Article slug collision with category

The `/{slug}` route matches articles first via `articles.find(a => a.slug === slug)`. Category links always use `?category=`, so no collision in practice.

### Dynamic import removed — Show.jsx simplification

**Before:**
```jsx
const [content, setContent] = useState(null);
const [loading, setLoading] = useState(true);

useEffect(() => {
  import(`@/data/helpdesk/content/${slug}.ts`)
    .then(mod => { setContent(mod.default); setLoading(false); })
    .catch(() => { setContent(null); setLoading(false); });
}, [slug]);
// Loading spinner branch in render...
```

**After:**
```jsx
// article.content already populated — no async needed
<MarkdownRenderer content={article.content} />
```

### Search bar navigation fix

**Before:**
```js
const handleSearch = (q) => {
  window.location.href = '/helpdesk/search?q=' + encodeURIComponent(q);
};
```

**After:**
```js
import { router } from '@inertiajs/react';

const handleSearch = (q) => {
  router.visit('/helpdesk/search?q=' + encodeURIComponent(q));
};
```

## 10. Validation Criteria

1. Search all files — verify zero `window.location` reads in helpdesk pages and layout.
2. Confirm `Category.jsx` is deleted and no route references it.
3. Confirm `resources/js/data/helpdesk/content/` directory is removed (22 files).
4. Confirm `Show.jsx` has no dynamic `import()` or `useEffect` for content loading.
5. Confirm `buildCategoryTree()` is the only category tree computation — no duplicate logic in files.
6. Confirm `HelpdeskLayout` search handler uses `router.visit()`.
7. Confirm Index route closure passes `category` query param as an Inertia prop.
8. Confirm "Most Popular" section and its associated code are removed from Index.

## 11. Related Specifications & Further Reading

| File | Action |
|------|--------|
| `resources/js/data/helpdesk/articles.ts` | Inline content into each article's `content` field |
| `resources/js/data/helpdesk/categories.ts` | Add `buildCategoryTree()` export |
| `resources/js/data/helpdesk/types.ts` | No changes needed |
| `resources/js/data/helpdesk/tags.ts` | No changes needed |
| `resources/js/data/helpdesk/search.ts` | No changes needed |
| `resources/js/data/helpdesk/index.ts` | No changes needed |
| `resources/js/data/helpdesk/content/` | **Delete entire directory** (22 files) |
| `resources/js/Pages/Helpdesk/Index.jsx` | Add category filtering via Inertia prop; remove "Most Popular" |
| `resources/js/Pages/Helpdesk/Show.jsx` | Simplify: remove dynamic import, loading state, IIFE category tree |
| `resources/js/Pages/Helpdesk/Search.jsx` | Verify — likely no changes needed |
| `resources/js/Pages/Helpdesk/Category.jsx` | **Delete file** (dead code — no route renders it) |
| `resources/js/Layouts/HelpdeskLayout.jsx` | Fix search nav to use `router.visit()`; drop unused `categories` prop |
| `resources/js/Components/Helpdesk/MarkdownRenderer.jsx` | No changes needed |
| `resources/js/Components/Helpdesk/` (other 5 components) | No changes expected |
| `routes/web.php` (lines 319–323) | Update Index route closure to pass `?category=` as Inertia prop |
| `resources/views/app.blade.php` | No changes needed |
