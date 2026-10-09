# Frontend Architecture

> **Version:** 1.0.0 | **Updated:** 2026-09-15 | **Status:** Verified against source code
> **Source:** `resources/js/app.tsx`, `vite.config.js`, `package.json`,
> `tailwind.config.js`, `resources/js/Components/ToastProvider.jsx`,
> `resources/js/Hooks/useUnsavedChanges.jsx`,
> `resources/js/Components/WelcomeModal.tsx`, `resources/js/Pages/`

## 1. Stack

| Concern | Technology | Version | Source |
|---------|-----------|---------|--------|
| UI library | React | `^18.2.0` (types `^18.2.79` / `^18.2.25`) | `package.json:21-22,29-30` |
| SPA bridge | Inertia.js React adapter | `^2.0.0` | `package.json:16` |
| Build | Vite | `^8.0.0` + `laravel-vite-plugin ^3.1` | `package.json:27,33` |
| Styling | Tailwind CSS | `^3.2.1` (+ `@tailwindcss/forms`) | `package.json:17,31` |
| Language | TypeScript | `^5.9.3` (check: `npm run typecheck` → `tsc --noEmit`) | `package.json:12,32` |
| Async state | TanStack Query | `^5.101.0` | `package.json:38` |
| URL generation | Ziggy (`ziggy-js`) | `^2.6.3`, global `route()` | `package.json:53` |
| Validation | Zod | `^4.4.3` | `package.json:54` |
| Charts | Chart.js + react-chartjs-2 | `^4.5.1` / `^5.3.1` | `package.json:40,48` |
| Search (helpdesk) | Fuse.js | `^7.4.2` | `package.json:43` |
| Onboarding tours | driver.js | `^1.4.0` | `package.json:42` |
| Markdown | @uiw/react-md-editor, react-markdown, rehype-sanitize, remark-gfm | `^4.1.0` / `^10.1.0` / `^6.0.0` / `^4.0.1` | `package.json:37,49-51` |
| Error tracking | Generic Sentry SDK (`@sentry/react`) | `^10.73.0` | `package.json:37` |
| Tests | Vitest (`test` watch / `test:run` one-shot) + Testing Library + jsdom | `^4.1.9` | `package.json:9-10,18-20,26` |

Scripts (`package.json:5-12`): `build` (`vite build`), `dev` (`vite`),
`addresses:sync` (`node scripts/sync-philippine-addresses.cjs`), `test`
(`vitest`), `test:run` (`vitest run`), `typecheck` (`tsc --noEmit`).

## 2. App Bootstrap (`resources/js/app.tsx`)

Provider tree, outermost first (`app.tsx:131-134,102-108`):

```
ErrorBoundary (requestId from pageRequestId(initialPage.props.request_id))
  └─ AppWithOnboarding → OnboardingProvider (onboardingRequired + onboardingState,
       synced on every Inertia 'success' navigation — app.tsx:71-82)
       └─ QueryClientProvider
            └─ ToastProvider (+ FlashMessageWatcher inside ToastProviderInner)
                 └─ Inertia page component
```

Query client defaults (`app.tsx:20-29`): `staleTime` 5 min, `gcTime` 30 min,
`retry` 1, `refetchOnWindowFocus: false`. Sentry page context (request id +
user id/role tags) is synced on boot and on every navigation
(`app.tsx:33-45,69,80`). Inertia navigation exceptions are logged to console,
never swallowed silently (`app.tsx:88-99`). Page resolution tries
`./Pages/${name}.jsx` first, then `.tsx` (`app.tsx:116-123`); document title is
`${title} - ${appName}` (`app.tsx:115`) and the progress bar is `#4B5563`
(`app.tsx:136-138`).

## 3. Pages & File Conventions

- Entry: `resources/js/app.tsx` (sole Vite input — `vite.config.js:64-68`).
- Pages resolve from `resources/js/Pages/**/*.{jsx,tsx}` (`app.tsx:117`).
  There are 100+ page modules covering Auth, Case, Referral, three role
  dashboards, Tracking, Survey (public form + agency builder + responses),
  OFW portal, Admin (users, agencies, services, case taxonomies, security,
  email logs, data export, overdue referrals), Reports, Helpdesk, Profile,
  Notifications, Clients, Stakeholders, and error pages (`Errors/NotFound`,
  `Errors/Forbidden`, `Errors/TooManyRequests`, `Errors/ServerError` — rendered
  by `bootstrap/app.php:103-188`).
- **`.jsx` default exports are dominant.** The only production `.tsx` modules
  are `resources/js/app.tsx`, `resources/js/Components/WelcomeModal.tsx`,
  and the `resources/js/Onboarding/` pair (`OnboardingProvider.tsx`,
  `TourManager.tsx`); remaining `.tsx` files are tests (`**/__tests__/`,
  `Pages/**/​__tests__/`). New pages follow the surrounding `.jsx` convention.
- Alias `@` → `resources/js` must stay absolute for the Vite 8 bundler
  (`vite.config.js:9-15`, mirrored in `tsconfig.json` and `vitest.config.ts`).
- Dev server binds `127.0.0.1:5173` with explicit HMR host/protocol
  (`vite.config.js:17-29`).
- The pre-resolve `util`/`node:util` stub (`vite.config.js:41-49` →
  `resources/js/vendor-stubs/util-stub.js`) exists because the bundler detects
  Node built-ins before alias resolution; removing it as "unused" breaks
  production builds that transitively import `util` (e.g. via `object-inspect`).

## 4. Design Tokens & Icons

- Design tokens live in `tailwind.config.js:40-93`: `primary #005288`
  (`tailwind.config.js:41`), `secondary #006b5e` (`tailwind.config.js:50`),
  plus container/fixed/on-* variants, error, outline, and surface scales.
  Fonts: Public Sans (sans/body/label), Outfit (headline/serif —
  `tailwind.config.js:95-101`). `darkMode: 'class'` (`tailwind.config.js:12`).
  Content scanning covers Blade pagination views, Blade views, and
  `resources/js/**/*.{jsx,tsx}` (`tailwind.config.js:6-10`).
- **Icons are Material Symbols** (`material-symbols-outlined` spans —
  e.g. `resources/js/Components/AppSidebar.jsx:263`,
  `resources/js/Pages/Auth/Login.jsx:99-130`). Do not introduce a second icon
  system for new UI; `lucide-react` (`package.json:47`) is available only where
  already used.
- Styling is Tailwind utilities only. Entrance/exit motion uses the custom
  `slide-in`/`slide-out`/`pop-in`/`pop-out` keyframes
  (`tailwind.config.js:16-39`).

## 5. Data Flow & Mutations

- Server state arrives as Inertia page props (controllers render
  `Inertia::render('Page', $props)`); shared props (`auth`, `flash`,
  `onboarding*`, `request_id`) come from `HandleInertiaRequests`
  (`bootstrap/app.php:69`).
- Mutations use Inertia's `useForm()` in ~27 files, navigation uses
  `Link`/`router` (`router.get` with `preserveState` for report filters and
  table pagination; `router.visit/post/patch/delete` elsewhere), and URLs are
  built with Ziggy's `route()` throughout.
- Client-persisted state: `localStorage` draft backup for case creation
  (`useLocalStorageDraft`, wired into `Pages/Case/Create.jsx:580` via the
  hook's `onDiscard`/`onSaveDraft` callbacks).

## 6. Flash → Toast Contract (`resources/js/Components/ToastProvider.jsx`)

`FlashMessageWatcher` (`ToastProvider.jsx:27-58`) reads `props.flash` on every
page visit and toasts each present key — `success`, `error`, `warning`, `info`,
`status` (`ToastProvider.jsx:35-39`) — plus `mfa_recovery_codes_remaining`
(`ToastProvider.jsx:41-54`: warning tone at ≤ 3 remaining, 8 s; info tone
otherwise, 6 s). There is intentionally **no dedupe/`seenRef` state**: normal
Inertia navigation replaces `props.flash`, so each redirect's message toasts
exactly once. Do not add suppression state — it breaks standard
redirect-with-flash messaging. Toasts render top-right (`ToastProvider.jsx:12`)
via `AppToast`.

## 7. Unsaved-Changes Guard (`resources/js/Hooks/useUnsavedChanges.jsx`)

Form pages call `useUnsavedChanges(dirty)` (39 usages) and render the returned
portal `UnsavedModal`. Mechanics (`useUnsavedChanges.jsx:14-48`):

- Intercepts Inertia's `router.on('before')` CustomEvent and reads
  `event.detail.visit`; **GET navigations only** (`useUnsavedChanges.jsx:18`) —
  form submissions (POST/PATCH/PUT/DELETE) are never blocked.
- Intercepts browser tab close / hard reload via `beforeunload`
  (`useUnsavedChanges.jsx:40-48`); skipped while a submission/visit is already
  in progress (`bypassRef`).
- Returns `{ showModal, confirmNavigation, cancelNavigation, bypassNext,
  UnsavedModal }` (`useUnsavedChanges.jsx:86`); call `bypassNext()` before
  programmatic navigation after a successful save, and pass
  `{ onDiscard, onSaveDraft }` where drafts apply (`useUnsavedChanges.jsx:6,50-62`).
