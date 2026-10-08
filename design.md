# One Window Bayanihan — Design System

Version 1.0 · 2026-10-08. Source of truth for color, type, spacing, and
components. Tailwind config (`tailwind.config.js`) is the executable token
layer; this file is the human contract. When they disagree, follow the config
and update this file.

## Principles

1. One token, one meaning. Brand blue means action/brand, green means
   success, amber means warning, red means error. Never reuse a status color
   for identity, and never use color alone — always pair with an icon + label.
2. Calm government surface. Light neutral backgrounds, white cards, one brand
   accent per view. Density lives inside tables; breathing room lives between
   sections.
3. Predictable before pretty. Same button, same input, same card everywhere.
   New variants need a reason, not a mood.
4. Plain words. Short labels, sentence case in body copy, uppercase only for
   small eyebrow labels.

## Color tokens

Brand and surface come from `tailwind.config.js` (Material-3-inspired).
Semantic `success / warning / info` were added in this pass; `error` already
existed.

| Token | Hex | Use |
|---|---|---|
| `primary` | `#005288` | Buttons, links, active states, focus rings |
| `primary-container` | `#003a63` | Primary hover / pressed |
| `primary-fixed` | `#d0e4ff` | Tinted fills (chips, icon tiles, KPI trends) |
| `on-primary` | `#ffffff` | Text on primary fills |
| `secondary` | `#006b5e` | Secondary brand moments (maps, highlights) |
| `background` | `#f7f9ff` | App backdrop |
| `surface` | `#f9fafc` | App shell (`AppLayout`) |
| `surface-bright` | `#ffffff` | Cards, modal panels, inputs |
| `surface-container-low/high` | `#f1f4fa` / `#e5e8ee` | Table control bars, footers, wells |
| `on-surface` / `on-surface-variant` | `#181c20` / `#41474f` | Body text / secondary text |
| `outline` / `outline-variant` | `#727780` / `#c1c7d1` | Strong borders / default borders |
| `error` / `error-container` | `#ba1a1a` / `#ffdad6` | Errors, destructive actions |
| `success` / `success-container` | `#2e7d46` / `#d3efdc` | New — success states |
| `warning` / `warning-container` | `#9a5b00` / `#ffe5b8` | New — warnings, pending states |
| `info` / `info-container` | `#2f6fb0` / `#d0e4ff` | New — neutral information |

Slate-200/50 fills in `cardShell` / `UnifiedTable` are grandfathered; new code uses `outline-variant` / `surface-container-low`.

Dark mode (`darkMode: 'class'`) is enabled but only the Reports area ships
`dark:` variants today. Rule: new components use the light tokens above and
add `dark:` variants only when the whole view supports dark. Do not ship
half-dark screens.

Contrast: `primary` on white passes AA (8.0:1). `on-primary` white on
`primary` passes AA. Tinted text (`text-primary` on `primary-fixed`) passes
AA. Slate status text in `StatusBadge` pairs (e.g. amber-700 on amber-50)
passes AA for bold small text; keep badges bold.

## Typography

| Role | Font | Classes | Example |
|---|---|---|---|
| Display / page title | Outfit (`font-headline`) | `text-2xl md:text-3xl font-extrabold tracking-tight text-slate-900` | Page headings |
| Section eyebrow | Public Sans | `text-[11px] font-extrabold uppercase tracking-[0.14em] text-primary` | Card/section titles |
| Body | Public Sans (`font-body`) | `text-sm text-slate-600`, base 14–16px, line-height 1.5–1.625 | Descriptions |
| Table header | Public Sans | `text-[12px] font-extrabold uppercase tracking-widest text-slate-500` | `UnifiedTable` heads |
| Metric value | Public Sans | `text-2xl font-black text-slate-900` | KPI numbers |
| Button label | Public Sans | `text-sm font-bold` (not uppercase) | All buttons |

Body font is set globally in `resources/css/app.css`. Use `font-headline`
only for page/display titles, never for body. Button labels are sentence
case, `text-sm font-bold` — the old all-caps `text-xs tracking-widest`
button style is retired.

## Spacing, radius, shadow, breakpoints

- Spacing: Tailwind default scale. Page gutter `p-8` in `AppLayout > main`;
  card padding `p-4`–`p-5`; control-bar height 40px inputs/buttons; gaps
  `gap-2`–`gap-4`.
- Radius: `rounded-md` (6px) buttons/inputs · `rounded-xl` (8px) cards ·
  `rounded-xl` modal panels · `rounded-full` pills/avatars only. Fixed in
  this pass: `rounded-full` used to resolve to 12px because the config
  overrode `full: 0.75rem` — now `9999px` again. Sharp `rounded-[2px]`
  badges stay for the `sharp` badge variant only; do not spread them.
- Shadow: `shadow-sm` cards/buttons · `shadow-xl` modals · `shadow-lg`
  floating popovers. No colored shadows.
- Breakpoints: Tailwind defaults (`sm/md/lg/xl`). Tables scroll
  horizontally (`overflow-x-auto`) below `lg`; control bars wrap
  (`flex-wrap`) on small screens. Grids: 1 col → `md:2` → `lg:3` → `xl:4`.

## Icons

Material Symbols (`material-symbols-outlined`) is the primary icon style —
used in nav, tables, KPI tiles, empty states. Exception: `StatusBadge` uses
`lucide-react` icons keyed by status; keep that mapping, do not mix both
icon sets in one component.

## Components

### Button — `PrimaryButton`, `SecondaryButton`

- Primary: `bg-primary text-on-primary`, hover `bg-primary-container`,
  `rounded-md px-4 py-2 text-sm font-bold shadow-sm`.
- Secondary: `bg-surface-bright border-outline-variant text-on-surface`,
  hover `bg-surface-container-low`.
- Focus: `focus-visible:ring-2 ring-primary ring-offset-2` on both.
- Disabled: `opacity-50 cursor-not-allowed`. Do: one primary action per
  view. Don't: uppercase labels, gray-on-gray secondary, `rounded-[3px]`
  one-off buttons (use the shared components).

### Input / Select / Textarea — `TextInput`

- `w-full rounded-md border-outline-variant bg-surface-bright text-sm
  text-on-surface shadow-sm`, focus `border-primary ring-2 ring-primary/40`.
- Errors via `InputError` (red `text-sm`) below the field; never color the
  label alone. Do: visible labels, placeholders as hints only. Don't:
  borderless inputs, `focus:ring-blue-*` one-offs.

### Card — `CardSection`, `KpiCard`, `cardShell`

- Shell: `rounded-xl border border-slate-200 bg-white shadow-sm` (shared as
  `cardShell` in `Components/Reports/pageHeadingStyles.js`).
- Title inside: 11px eyebrow style (see type table). KPI: icon tile
  `bg-primary-fixed text-primary`, value `text-2xl font-black`, trend chip
  `bg-primary-fixed text-primary text-[11px] font-bold`. Do: one metric per
  KPI card. Don't: `rounded-[3px]` cards, blue-900 one-off fills.

### Table — `ui/UnifiedTable`

- Header row `bg-slate-50`, 12px uppercase slate-500; rows `hover:bg-slate-100`,
  `divide-slate-300`; control bar `bg-slate-50` with 40px search input and
  action buttons; footer `Showing X–Y of Z` + rows-per-page + numbered
  pagination with current page `bg-primary text-white`.
- Empty states: `inbox` icon + "No records yet"; filtered-empty: `search_off`
  + "No results found". Loading: skeleton rows with `animate-pulse`.
- Do: server- or client-sort through the built-in sort; keep the sort-reset
  affordance. Don't: reintroduce `blue-900` fills — the table now uses the
  `primary` token throughout.

### Badge / Chip — `ui/StatusBadge`, `ui/TypeBadge`

- `sharp` (default): `rounded-[2px] font-extrabold uppercase` + status tint;
  `pill`: `rounded-full font-semibold` + title-case label. Sizes `sm/md`.
- Status colors are identity-free tints (blue/slate/amber/emerald/rose…);
  always render icon + label. Don't: new status colors without adding the
  icon mapping and the Reports `STATUS_COLORS` fallback together.

### Alert / Toast — `ui/AppToast` + `ToastProvider`

- Stack top-right, `z-[100]`, auto-dismiss; tones map to semantic tokens
  (success green, error red, warning amber, info blue). Flash messages from
  backend redirects auto-toast via `FlashMessageWatcher` — do not add
  seen/dedupe state that swallows normal navigation flashes.

### Modal — `Modal`, `ui/ConfirmDialog`

- Panel: `rounded-xl bg-surface-bright shadow-xl`, `max-h-[90vh]`, sizes
  `sm–2xl`; overlay `bg-gray-500/75`; enter animation `owb-modal-animate`.
  Forms inside modals still use `useUnsavedChanges` + `UnsavedChangesModal`.
  Don't: `rounded-lg`/`rounded-[3px]` panels, unclosable modals without an
  explicit reason.

### Destructive — `DangerButton`, `ui/ConfirmDialog`

- `DangerButton`: `bg-error text-on-primary`, `rounded-md text-sm font-bold`
  (sentence case — the old all-caps style is retired).
- `ConfirmDialog` danger tone: `bg-error` confirm button, `error-container`
  icon tile; panel `rounded-xl`.

### Nav / Sidebar — `AppSidebar`, `NavLink`

- Shell `bg-surface`, content scrolls in `AppLayout > main` (`owb-scroll`).
  Active nav item uses the primary tint + pop animation (`owb-icon-active-pop`).
  `AuthenticatedLayout` (legacy top-nav) uses `bg-surface`. Don't: add new
  top-level nav without a sidebar entry and a tour anchor.

### Pagination

- Owned by `UnifiedTable`: first/prev/numbered/next/last, 5 visible numbers
  with ellipsis, rows-per-page `[10, 25, 50]`. Reuse it — do not build
  page-level paginators.

### Empty state / Loading skeleton

- Empty: centered Material Symbol (`inbox` / `search_off`), bold title +
  one-line hint (`TrackingNotFoundState` for tracking). Loading: `animate-pulse`
  blocks or `TableLoadingOverlay` / `ChartSkeleton`; keep layout size stable
  while loading to avoid jumps.

## Accessibility + responsive

- Focus visible everywhere: 2px `ring-primary` with offset on all
  interactives. Minimum touch target 40px for buttons/inputs in control bars.
- Status is never color-alone (icon + text in badges, toasts, charts).
- Tables: horizontal scroll with sticky semantics preserved; no clipped
  action columns on 360px widths. Test `sm` and `lg` before shipping.

## What was changed to conform (this pass)

1. `tailwind.config.js` — added `success / warning / info` (+ containers);
   fixed `borderRadius.full` (`0.75rem` → `9999px`, which had silently broken
   every `rounded-full` pill/avatar) and added the missing `md` step.
2. `PrimaryButton` — retired all-caps `text-xs tracking-widest`; now
   `text-sm font-bold`, `text-on-primary`, `focus-visible` ring, `opacity-50`
   disabled.
3. `SecondaryButton` — gray-300/700 one-offs → `outline-variant` /
   `surface` tokens with matching focus ring.
4. `TextInput` — full-width system input: `border-outline-variant`,
   `focus:border-primary focus:ring-2`, disabled state.
5. `Modal` — panel `rounded-lg` → `rounded-xl` to match `cardShell`.
6. `UnifiedTable` — all `blue-900/800/50/200` accents → `primary` family
   (`bg-primary`, `text-primary`, `bg-primary-fixed`, focus rings).
7. `KpiCard` — default icon tile + trend chip `blue-50/900` → `primary-fixed`
   / `primary`.
8. `AuthenticatedLayout` — page backdrop `bg-gray-100` → `bg-surface`.
9. Added `design.md` (this file) + `public/design-system.html` showcase.

Known non-conformances left alone (do not "fix" without a reason): Reports
chart palettes intentionally diverge for colorblind-safe dataviz
(`pageHeadingStyles.js` documents why); `StatusBadge` uses lucide icons;
driver.js tour styles use indigo to match the tour library default.
