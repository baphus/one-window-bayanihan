# Frontend Architecture

## Stack

| Layer | Technology |
|---|---|
| Framework | React 18 |
| Integration | Inertia.js v2 (no separate API) |
| Styling | Tailwind CSS 3 |
| State | Inertia props + local React state |
| Forms | Inertia `useForm()` |
| Validation | Zod schemas |
| Charts | Chart.js + react-chartjs-2 |
| Icons | Material Symbols |
| Search | Fuse.js (client-side) |
| Markdown | react-markdown + remark-gfm |

## Entry Point

```
resources/js/app.tsx
  └─ ErrorBoundary
      └─ QueryClientProvider (TanStack, 5-min stale time)
          └─ ToastProvider
              └─ OnboardingProvider
                  └─ createInertiaApp()
```

## Directory Structure

```
resources/js/
├── Components/           # Reusable UI components
│   ├── Admin/            # Admin-specific modals
│   ├── Dashboard/        # Dashboard widgets
│   ├── Helpdesk/         # Helpdesk components
│   ├── Reports/          # Report charts and cards
│   ├── landing/          # Public landing page
│   └── ui/               # Shared UI primitives
├── Hooks/                # Custom React hooks
├── Layouts/              # Page layout wrappers
├── Onboarding/           # Tour/onboarding system
├── Pages/                # Inertia page components
│   ├── Admin/            # Admin panel pages
│   ├── Agency/           # Agency-specific pages
│   ├── Auth/             # Authentication pages
│   ├── Case/             # Case management
│   ├── Client/           # Client profiles
│   ├── Dashboard/        # Role-based dashboards
│   ├── Feedback/         # Feedback system
│   ├── Helpdesk/         # Knowledge base
│   ├── Profile/          # User profile
│   ├── PublicAgencies/   # Public agency listing
│   ├── Referral/         # Referral management
│   ├── Reports/          # Analytics pages
│   ├── Stakeholder/      # Stakeholder views
│   ├── Tracking/         # OFW tracking portal
│   └── Errors/           # Error pages (403, 404, 500, 429)
├── Schemas/              # Zod validation schemas
├── data/                 # Static data
│   ├── helpdesk/         # Article content
│   └── *.json            # Address data, phone codes
├── lib/                  # Utility functions
├── types/                # TypeScript type definitions
├── utils/                # Export utilities (CSV, PDF)
└── vendor-stubs/         # Vite build stubs
```

## Layouts

| Layout | Used By | Sidebar |
|---|---|---|
| `AppLayout.jsx` | Authenticated app pages | Yes (collapsible sidebar) |
| `AuthenticatedLayout.jsx` | Minimal authenticated pages | No (top nav) |
| `GuestLayout.jsx` | Auth pages (login, register) | No |
| `HelpdeskLayout.jsx` | Helpdesk pages | No (helpdesk-specific nav) |

## Page Conventions

- **One file per route** in `resources/js/Pages/`
- **Default exports** for all page components
- **PascalCase** filenames matching component names
- Pages receive props from Laravel via Inertia

### Example Page Structure

```jsx
import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';
import { useForm } from '@inertiajs/react';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import UnsavedChangesModal from '@/Components/UnsavedChangesModal';

export default function CaseIndex({ cases }) {
    const { data, setData, submit, processing, isDirty } = useForm({...});
    useUnsavedChanges(isDirty);

    return (
        <AppLayout>
            <Head title="Cases" />
            {/* Page content */}
            <UnsavedChangesModal isDirty={isDirty} />
        </AppLayout>
    );
}
```

## Component Conventions

### Naming
- PascalCase for all components
- Default exports in `.jsx` files
- Use `.tsx` only if the file already exists in TypeScript

### Reusable Components

| Component | Purpose |
|---|---|
| `Modal` | Generic modal wrapper |
| `Dropdown` | Dropdown menu |
| `PrimaryButton` | Main action button |
| `DangerButton` | Destructive action button |
| `SecondaryButton` | Secondary action |
| `TextInput` | Text input field |
| `InputLabel` | Form field label |
| `InputError` | Validation error display |
| `Checkbox` | Checkbox input |
| `Section` | Content section wrapper |
| `ErrorBoundary` | Error catching wrapper |
| `ToastProvider` | Flash message toasts |
| `UnsavedChangesModal` | Beforeunload warning |

### UI Primitives (`Components/ui/`)

| Component | Purpose |
|---|---|
| `StatusBadge` | Colored status indicator |
| `TypeBadge` | Entity type badge |
| `KpiCard` | Dashboard KPI card |
| `UnifiedTable` | Sortable data table |
| `RecentTable` | Recent items table |
| `CardSection` | Card wrapper |
| `NotificationPanel` | Notification dropdown |
| `UserAvatar` | User avatar display |
| `ToggleSwitch` | Toggle input |
| `ConfirmDialog` | Confirmation modal |
| `RowContextMenu` | Right-click context menu |

## Custom Hooks

| Hook | Purpose |
|---|---|
| `useUnsavedChanges(isDirty)` | Warns before leaving dirty forms |
| `useToast()` | Flash message toast notifications |
| `useAutoSave(data, callback)` | Auto-save form data on interval |
| `useLocalStorageDraft(key)` | Persist draft data to localStorage |
| `useLazyProp(prop)` | Lazily load expensive props |
| `useReportFilters()` | Report filter state management |
| `useClientValidation()` | Client-side form validation |
| `useAdminChartData()` | Admin dashboard chart data |

## Forms

All forms use Inertia's `useForm()` hook:

```jsx
const { data, setData, post, processing, errors, isDirty } = useForm({
    first_name: '',
    last_name: '',
    client_type: 'OFW',
});

const submit = (e) => {
    e.preventDefault();
    post(route('cases.store'));
};
```

### Form Requirements
1. **Always** use `useUnsavedChanges(isDirty)` for form pages
2. **Always** include `<UnsavedChangesModal isDirty={isDirty} />`
3. Use Zod schemas in `resources/js/Schemas/` for validation
4. Display errors via `<InputError message={errors.field} />`

## Styling

- **Tailwind utilities only** — no custom CSS
- Design tokens in `tailwind.config.js`
- Responsive: desktop-first for admin, mobile-first for public portal
- WCAG 2.1 Level AA compliance required

## Flash Messages

Flash messages from backend redirects auto-toast through shared `props.flash`. **Do not** add `seenRef`/dedupe state that suppresses normal navigation flash.

## Unsaved Changes

Every form page MUST use:
```jsx
useUnsavedChanges(isDirty);
// and
<UnsavedChangesModal isDirty={isDirty} />
```

The `useForm()` `isDirty` flag tracks whether the user has made changes.

## Onboarding

The onboarding system uses `driver.js` for guided tours:

- `OnboardingProvider` — Context provider wrapping the app
- `TourManager.tsx` — Tour step management
- Role-based configs: `configs/admin.ts`, `configs/agency.ts`, `configs/caseManager.ts`
- Steps tracked via API, skip/replay supported

## Error Pages

| Page | Status | Component |
|---|---|---|
| Not Found | 404 | `Errors/NotFound.jsx` |
| Forbidden | 403 | `Errors/Forbidden.jsx` |
| Server Error | 500 | `Errors/ServerError.jsx` |
| Too Many Requests | 429 | `Errors/TooManyRequests.jsx` |

## Static Data

### Helpdesk Articles

Articles are stored as TypeScript files in `resources/js/data/helpdesk/content/`:
- Each article is a `.ts` file exporting structured content
- Categories defined in `categories.ts`
- Tags in `tags.ts`
- Search index built client-side with Fuse.js

### Address Data

- `resources/js/data/philippine-addresses.ts` — PSGC address data (large, auto-generated)
- Synced via `npm run addresses:sync`
