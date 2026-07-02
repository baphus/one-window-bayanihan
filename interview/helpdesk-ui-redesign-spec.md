---
sessionID: ses_0de853824ffeyVrlYpgUPxyimI
baseMessageCount: 150
updatedAt: 2026-07-02T09:54:34.169Z
version: 1.0
date_created: 2026-07-02
owner: agent
tags: [spec, diagnostic]
---

# I still don't like how the helpdesk ui looks. Please research the best designs for helpdesks and adjust it to be more intuitive, more cohesive, more practical and follows best business practices

## Current spec

# Introduction
This specification defines a redesign direction for the One Window Bayanihan Help Center UI. The goal is to make the helpdesk more intuitive, cohesive with the public landing-page design, practical for real users, and aligned with established help center and government-service UX practices.

## 1. Purpose & Scope
The redesign targets the public Help Center / Helpdesk experience, including the helpdesk landing page, search page, category/subcategory browsing, article cards, sidebar navigation, article detail page, related articles, empty states, and internal helpdesk links.

Intended users include OFWs, case managers, agency focal persons, administrators, and unauthenticated public visitors seeking support information.

Scope includes frontend UI/UX, information architecture, visual hierarchy, navigation behavior, search presentation, accessibility, and maintainability of the helpdesk React/Inertia pages and components.

Out of scope: database-backed CMS migration, chatbot integration, backend authorization changes, article authoring workflow, analytics implementation, major redesign of unrelated authenticated app pages, and new automated test coverage in the initial implementation pass.

Assumptions:
- Helpdesk content remains stored in TypeScript data modules for this iteration.
- The helpdesk should visually align with the existing landing page, not the authenticated dashboard UI.
- The fixed landing header remains in use.
- Featured Articles will be replaced with task-based quick links: Track your case, Browse help topics, and Contact support.
- The desktop sidebar will remain, but only as compact category/subcategory navigation.
- Mobile navigation will rely on main-page topic sections only; no mobile sidebar or drawer is required.
- Homepage search will show lightweight inline suggestions, with full results available on the dedicated search page.
- Article pages will end with related articles plus a clear Contact support path.
- Visual density should be balanced with clear spacing, not compact-heavy and not editorially spacious.
- The next implementation pass should perform a full Help Center redesign within the current files.

## 2. Definitions
- Help Center / Helpdesk: Public support knowledge base area for guides, FAQs, and operational help articles.
- IA: Information Architecture; the structure of categories, subcategories, and article discovery paths.
- Category: Top-level helpdesk topic grouping.
- Subcategory: Child topic grouping under a category.
- Article: Individual help document with title, excerpt, content, tags, category, and published date.
- Featured Article: Existing curated article concept to be replaced by task-based quick links.
- Task-based Quick Link: A prominent link to a high-intent user action, specifically Track your case, Browse help topics, and Contact support.
- Inline Search Suggestion: Lightweight article suggestion shown below the homepage search input before navigating to full search results.
- Search Recovery State: A no-results or error state that helps users continue by suggesting alternate actions.
- Mobile Topic Sections: Main-page category/subcategory sections used as the mobile navigation pattern instead of a separate drawer or sidebar.
- Balanced Density: A layout style that provides clear separation and scanning space while keeping enough content visible for practical helpdesk use.
- Landing-page design philosophy: Existing public website style using brand blue, slate surfaces, squared/low-radius controls, restrained borders, and government-service tone.

## 3. Requirements, Constraints & Guidelines
- **REQ-001**: The Help Center UI must support clear browsing from category to subcategory to article.
- **REQ-002**: Search must remain a primary discovery path and be visually prominent.
- **REQ-003**: Search must cover article title, excerpt, category, tags, and body content.
- **REQ-004**: Category filter views must clearly state the current topic and provide a way back to all articles.
- **REQ-005**: Article pages must include readable content, breadcrumbs, tags, and related article discovery.
- **REQ-006**: Sidebar navigation must be compact category/subcategory navigation only; it must not include article links or visible count clutter.
- **REQ-007**: Featured Articles must be replaced by task-based quick links for Track your case, Browse help topics, and Contact support.
- **REQ-008**: Empty states must provide recovery actions such as search retry, browse categories, or contact support.
- **REQ-009**: Internal helpdesk navigation should use Inertia `Link` or `router.visit` for SPA navigation where practical.
- **REQ-010**: Fixed-header overlap must be avoided across Help Center pages.
- **REQ-011**: The homepage must provide a clear task-entry section before deep browsing content.
- **REQ-012**: Homepage search must show lightweight inline suggestions before full search submission.
- **REQ-013**: Inline search suggestions must be limited and scannable, with a clear route to full results.
- **REQ-014**: Mobile helpdesk navigation must rely on main-page topic sections rather than a sidebar or drawer.
- **REQ-015**: Article pages must include a post-content support area with related articles and Contact support.
- **REQ-016**: The redesign must use balanced visual density with clear spacing.
- **REQ-017**: The implementation must stay within existing Help Center files and components unless a very small shared helper is clearly justified.
- **SEC-001**: The redesign must not expose private case, user, agency, or admin-only data.
- **SEC-002**: Search must operate only on public/static helpdesk article data unless future authorization is explicitly designed.
- **CON-001**: The app uses Laravel + Inertia + React + Tailwind CSS.
- **CON-002**: Helpdesk content remains TypeScript module data for this phase.
- **CON-003**: Do not introduce a dynamic import pattern for article content.
- **CON-004**: Do not require new backend tables or CMS infrastructure.
- **CON-005**: The UI must align with the existing landing-page style system and brand color `#005288` / `primary`.
- **CON-006**: The initial implementation pass should not add new automated tests unless the implementer finds a small, low-risk test addition already aligned with existing patterns.
- **GUD-001**: Structure help content around user tasks, not internal organization.
- **GUD-002**: Treat search as the primary entry point, with browsing as a strong secondary path.
- **GUD-003**: Use categories as topic shelves and subcategories only where they clarify intent.
- **GUD-004**: Keep sidebars short, scannable, and visually lighter than main content.
- **GUD-005**: Avoid excessive roundness, shadows, SaaS-like cards, and decorative clutter.
- **GUD-006**: Use grounded, plain support copy.
- **GUD-007**: Empty and no-results states must explain what happened and what to do next.
- **GUD-008**: Ensure keyboard access, visible focus states, sufficient contrast, and non-color-only active states.
- **GUD-009**: Task-based quick links should be fewer than article/category links and visually clear as actions, not marketing cards.
- **GUD-010**: Inline suggestions should appear only after meaningful input and should not obscure the page.
- **GUD-011**: Mobile layouts should prioritize search, task links, and topic sections in that order.
- **GUD-012**: Balanced density should avoid both cramped link lists and oversized empty hero sections.

## 4. Interfaces & Data Contracts
Current relevant data structures are TypeScript-based helpdesk modules:

```ts
HelpdeskCategory = {
  id: string;
  name: string;
  slug: string;
  description: string;
  parentId: string | null;
  icon?: string;
  sortOrder?: number;
}

HelpdeskArticle = {
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

HelpdeskTag = {
  id: string;
  name: string;
}
```

Expected frontend routes/concepts:
- `/helpdesk`: Help Center landing page.
- `/helpdesk?category={slug}`: Category or subcategory filtered browse view.
- `/helpdesk/search?q={query}`: Search results page.
- `/helpdesk/{slug}`: Article detail page.
- `/track`: Track Your Case quick link target.
- `/contact`: Contact Support quick link target.

Task-based quick link contract:
```ts
HelpdeskQuickLink = {
  label: string;
  description: string;
  href: string;
  icon: string;
  priority?: 'primary' | 'secondary';
}
```

Required quick links:
```ts
const quickLinks = [
  {
    label: 'Track your case',
    description: 'Check case status using your tracking number.',
    href: '/track',
    icon: 'confirmation_number',
    priority: 'primary',
  },
  {
    label: 'Browse help topics',
    description: 'View guides by category and subcategory.',
    href: '/helpdesk#topics',
    icon: 'topic',
    priority: 'secondary',
  },
  {
    label: 'Contact support',
    description: 'Reach the support team for help with your concern.',
    href: '/contact',
    icon: 'support_agent',
    priority: 'secondary',
  },
];
```

Search contract:
- Input: query string.
- Search scope: article title, excerpt, content, category name, tag names.
- Homepage inline output: limited `HelpdeskArticle[]` suggestions.
- Full search output: matching `HelpdeskArticle[]`, enriched in UI with category and tag display data.
- Suggested behavior: show inline suggestions after meaningful input, limit suggestions to a small list, and provide a “View all results” action to `/helpdesk/search?q={query}`.

Article support area contract:
```ts
ArticleSupportPanel = {
  relatedArticles: HelpdeskArticle[];
  contactHref: '/contact';
  contactLabel: 'Contact support';
}
```

Implementation boundary:
- Primary changes should be in the current Help Center layout, pages, data utilities, and Helpdesk components.
- No new backend contract is required.

## 5. Acceptance Criteria
- **AC-001**: Given a visitor opens `/helpdesk`, When the page loads, Then the search entry point is visible below the fixed header without overlap.
- **AC-002**: Given a visitor opens `/helpdesk`, When they scan the page, Then they can understand the category → subcategory → article structure without opening the sidebar.
- **AC-003**: Given a visitor selects a category, When `/helpdesk?category={slug}` loads, Then the page shows only relevant articles for that category and descendants plus a clear reset action.
- **AC-004**: Given a visitor selects a subcategory, When the filtered page loads, Then parent and subcategory context remain understandable.
- **AC-005**: Given a visitor searches for text found only in article body content, When search runs, Then matching articles appear in results.
- **AC-006**: Given a visitor searches for an unmatched phrase, When no results are found, Then the UI shows a recovery state with next actions.
- **AC-007**: Given a visitor uses keyboard navigation, When tabbing through search, sidebar, and article links, Then focus order and focus visibility are usable.
- **AC-008**: Given the Help Center page is viewed on desktop, When the sidebar is visible, Then it shows compact category/subcategory navigation only and no article links/count badges.
- **AC-009**: Given the Help Center page is viewed on mobile/tablet, When the sidebar is hidden, Then main-page topic sections provide category/subcategory/article discovery without requiring a drawer.
- **AC-010**: Given an article detail page is opened, When the user reads it, Then breadcrumbs, article metadata, tags, and related articles support navigation without clutter.
- **AC-011**: Given the Help Center homepage loads, When the user sees the first action section, Then it shows task-based quick links instead of Featured Articles.
- **AC-012**: Given a user selects a task-based quick link, When the link is clicked, Then SPA navigation takes the user to the intended route where possible.
- **AC-013**: Given a user types a meaningful query into homepage search, When matching articles exist, Then lightweight suggestions appear below the search input.
- **AC-014**: Given homepage search suggestions are visible, When the user submits the search or clicks View all results, Then they are taken to `/helpdesk/search?q={query}`.
- **AC-015**: Given homepage search suggestions are visible, When the query changes or is cleared, Then the suggestions update or disappear without stale results.
- **AC-016**: Given a user reaches the end of an article, When related articles exist, Then related articles and a Contact support action are available.
- **AC-017**: Given a user reaches the end of an article with no related articles, When the support area renders, Then Contact support is still available.
- **AC-018**: Given the redesigned Help Center is displayed, When viewed on desktop or mobile, Then spacing is balanced and content remains scannable without feeling cramped or overly sparse.
- **AC-019**: Given implementation is complete, When reviewing changed files, Then the redesign is contained to current Help Center files/components and does not require backend or unrelated page changes.

## 6. Test Automation Strategy
- Run `npm run build` as the primary frontend regression check.
- Add or update Vitest tests for pure helpdesk data utilities only if a small, aligned test can be added without broad test infrastructure changes.
- Test search with representative fixtures where practical: title match, tag match, category match, body-content match, and no-results match.
- Test inline suggestion logic with short query, valid query, no-result query, and cleared query cases if component tests already exist or can be added minimally.
- Use component-level tests for critical empty states and category filtering only if existing test setup supports React component rendering without large setup changes.
- Manual QA should cover desktop and mobile breakpoints for `/helpdesk`, `/helpdesk?category=...`, `/helpdesk/search?q=...`, and `/helpdesk/{slug}`.
- Manual QA should verify task quick links route to Track Your Case, Browse help topics, and Contact Support.
- Manual QA should verify article pages show related articles plus Contact support at the bottom.

## 7. Rationale & Context
Research-backed help center patterns emphasize search as the primary entry point, category browsing as secondary support, and task-based IA over organizational structure. Government-service design systems also favor plain language, strong hierarchy, restrained visuals, accessibility, and clear recovery states.

The current helpdesk has improved functionality but the user does not like the style. The next design pass should prioritize visual cohesion with the public landing page rather than adding more functionality. This means reducing bulky card treatment, avoiding excessive rounded corners and shadows, making the sidebar less visually dominant, and using the brand blue/slate design language consistently.

Featured Articles are being replaced because the user wants a more practical and intuitive first section. Task-based quick links better match high-intent help center behavior: users usually want to track something, browse help topics, or contact support.

Homepage search will include lightweight inline suggestions because this can reduce friction without turning the homepage into a full results page. Full result scanning remains delegated to the dedicated search page.

The sidebar remains because it supports desktop browsing, but it should be compact category/subcategory navigation only. Article discovery should happen in the main content and search results, not in a crowded sidebar.

Mobile should avoid a separate sidebar/drawer because the main page topic sections can carry the hierarchy more simply and with less interaction overhead.

Article pages should end with related articles plus Contact support because users who finish an article either need adjacent information or direct escalation.

Balanced density is chosen because this is a practical help center, not a dense admin table and not a marketing landing page. Users should see enough options to proceed quickly while still having clear visual grouping.

The initial implementation should stay within current files to avoid expanding scope before the redesigned direction is validated visually.

Trade-offs:
- Keeping TypeScript data modules avoids backend complexity now but limits authoring/admin features.
- A sidebar is useful on desktop but should not be the only browsing mechanism.
- Task-based quick links improve practical navigation but require careful choice of routes to avoid promoting unavailable actions.
- Inline search suggestions improve discovery but must be limited to avoid clutter and accessibility problems.
- Avoiding a mobile drawer reduces complexity but means the main page hierarchy must be strong enough on small screens.
- Avoiding new tests in the first implementation pass keeps scope focused on UI correction, but search/category utility tests remain valuable follow-up work.

## 8. Dependencies & External Integrations
- **EXT-001**: Inertia React for SPA navigation and page props.
- **EXT-002**: Tailwind CSS for visual styling.
- **EXT-003**: Fuse.js or equivalent local search index for static helpdesk search.
- **EXT-004**: Material Symbols icon font for category/search/navigation icons.
- **EXT-005**: Existing landing components/design references: `AppHeader`, `AppFooter`, `AppButton`, and landing page sections.
- **EXT-006**: Existing public routes for task quick links, including Track Your Case, Help Center topics, and Contact Support.

## 9. Examples & Edge Cases
Example category-filter behavior:
```txt
/helpdesk?category=case-management
Shows Case Management plus its subcategories and descendant articles.
```

Example subcategory-filter behavior:
```txt
/helpdesk?category=referrals-escalations
Shows only Referrals & Escalations context and its articles, with a link back to all Help Center topics.
```

Example task quick links:
```ts
const quickLinks = [
  {
    label: 'Track your case',
    description: 'Check case status using your tracking number.',
    href: '/track',
    icon: 'confirmation_number',
    priority: 'primary',
  },
  {
    label: 'Browse help topics',
    description: 'View guides by category and subcategory.',
    href: '/helpdesk#topics',
    icon: 'topic',
    priority: 'secondary',
  },
  {
    label: 'Contact support',
    description: 'Reach the support team for help with your concern.',
    href: '/contact',
    icon: 'support_agent',
    priority: 'secondary',
  },
];
```

Example inline suggestions:
```txt
User types: referral
Suggestions:
- Adding Milestones & Referrals: Complete Guide
- Best Practices for Timely Referral Processing
- Managing Overdue Referrals
View all results
```

Example article support area:
```txt
Related articles
- Understanding case statuses and tracker numbers
- Managing overdue referrals

Still need help?
Contact support
```

Example no-results recovery:
```txt
No articles found for “medical reimbursement”.
Try another keyword, browse help topics, or contact support.
```

Edge cases:
- Invalid category slug should show a helpful not-found category state.
- Category with no articles should show an empty topic state, not a broken layout.
- Article without tags should still render cleanly.
- Article with long title should wrap without breaking cards/sidebar.
- Search query with whitespace only should not show misleading results.
- Fixed header should not cover search or hero content.
- A quick link must not point to a route that does not exist.
- Inline suggestions must disappear when the input is cleared.
- Inline suggestions must not trap keyboard focus or obscure the submit path.
- Article support area must still show Contact support even when no related articles exist.
- Balanced density should still work with both short and long category names.

## 10. Validation Criteria
- `npm run build` passes.
- Helpdesk pages have no fixed-header overlap.
- Sidebar contains compact category/subcategory navigation only.
- Sidebar contains no visible count clutter and no article list.
- Mobile does not require a sidebar or drawer to browse topics.
- Internal helpdesk links use Inertia navigation where practical.
- Search returns results from article body content.
- Featured Articles are removed from the homepage and replaced with Track your case, Browse help topics, and Contact support quick links.
- Homepage search shows lightweight inline suggestions and still routes to the full search page.
- Article pages end with related articles plus Contact support.
- Helpdesk visual style matches the landing page: brand blue, slate surfaces, restrained borders, squared/low-radius controls, and `font-headline` / `font-label` usage.
- Desktop and mobile layouts remain usable with balanced spacing.
- Changes remain scoped to current Help Center files/components unless a small helper is clearly justified.

## 11. Related Specifications / Further Reading
- GOV.UK Service Manual / Design System: service navigation, breadcrumbs, search, problem pages.
- UK Home Office Design System: search patterns and accessibility guidance.
- Help Scout knowledge base design guidance.
- Intercom help center search and article ordering guidance.
- Atlassian design navigation guidance.
- Microsoft style guide guidance on scannable content and sidebars.
- Internal project files: `resources/js/Pages/Welcome.jsx`, `resources/js/Components/landing/AppHeader.jsx`, `resources/js/Layouts/HelpdeskLayout.jsx`, `resources/js/Pages/Helpdesk/*`, `resources/js/data/helpdesk/*`.

## Q&A history

Q: What should Featured Articles do in the redesigned Help Center?
A: Replace them with task-based quick links such as Track a case, Submit a case, Contact support

Q: How should the desktop sidebar behave?
A: Compact category/subcategory navigation only

Q: Which task quick links should replace Featured Articles?
A: Track your case, Browse help topics, Contact support

Q: Should the homepage search show results inline or navigate to the dedicated search page?
A: Show lightweight inline suggestions, then full results on search page

Q: How should category/subcategory navigation work on mobile?
A: Use the main page topic sections only; no mobile sidebar/drawer

Q: What should the article page emphasize after the article content?
A: Related articles plus Contact support

Q: What density should the redesigned Help Center use?
A: Balanced with clear spacing

Q: How far should the next implementation pass go?
A: Full Help Center redesign within current files
