# Bayanihan One Window — UI Patterns

> **Source:** SRS v1.2 (May 19, 2026) — §3.1 User Interfaces, §6.5 Accessibility
> **Last Updated:** 2026-05-28

---

## 1. Design Philosophy

> **"One OFW, One Entry"** — Single case file per OFW, lane-column tracker for inter-agency visibility.

| Principle | Implementation |
|---|---|
| Desktop-first admin | Optimal at 1366×768+, with mobile-responsive public portal |
| Privacy-safe | Public OFW view hides internal comments, case notes, attachments |
| Append-only | Milestones shown as immutable timeline — no edit/delete |
| WCAG 2.1 AA | Color + icons + labels, keyboard nav, screen reader support |
| Consistent | Tailwind CSS design system, shared component library |

---

## 2. Layout Architecture

### 2.1 Layout Files

| Layout | File | Used For |
|---|---|---|
| AppLayout | `AppLayout.jsx` | Authenticated users — sidebar navigation |
| AuthenticatedLayout | `AuthenticatedLayout.jsx` | Alternative top-nav layout |
| GuestLayout | `GuestLayout.jsx` | Login, registration, password reset |

### 2.2 AppLayout (Sidebar) Structure

```
┌─────────────────────────────────────────────────────┐
│  [Logo]        Top Bar             [User Menu] [Notif] │
├──────────┬──────────────────────────────────────────┤
│ Sidebar  │  Page Content                             │
│          │                                           │
│ • Dashboard│  (Rendered by the current Inertia page)   │
│ • Cases   │                                           │
│ • Referrals│  [Breadcrumbs]                            │
│ • Clients │                                           │
│ • Reports │  ┌─────────────────────────────────────┐  │
│ • Admin   │  │                                     │  │
│   (ADMIN  │  │  Page-specific content               │  │
│    only)  │  │                                     │  │
│          │  └─────────────────────────────────────┘  │
│          │                                           │
│          │  [Flash Message / Toast]                   │
└──────────┴──────────────────────────────────────────┘
```

### 2.3 Navigation Sidebar Items (Role-Based)

| Item | CASE_MANAGER | AGENCY | ADMIN |
|---|---|---|---|
| Dashboard | ✅ | ✅ | ✅ |
| Cases | ✅ | ✅ | ✅ |
| Referrals | ✅ | ✅ | ✅ |
| Clients | ✅ | ✅ | ✅ |
| Stakeholders | ✅ | ✅ | ✅ |
| Reports | ✅ | ✅ | ✅ |
| Analytics | ✅ | ✅ | ✅ |
| Services | — | ✅ | ✅ |
| Agencies | — | — | ✅ |
| Users | — | — | ✅ |
| Audit Logs | ✅ | ✅ | ✅ |
| System Settings | — | — | ✅ |
| Helpdesk CMS | — | — | ✅ |
| Overdue Referrals| ✅ | — | ✅ |
| Agency Services | — | ✅ | ✅ |

---

## 3. Common UI Components

### 3.1 Flash Message / Toast (SRS §3.1)

- **Location:** Rendered in all 3 layouts via `FlashMessageWatcher`
- **Trigger:** Any `->with('success', '...')` or `->with('error', '...')` from backend
- **Behavior:** Auto-dismissing toast, appears in top-right
- **Files:** `ToastProvider.jsx`, `useToast.jsx`, `HandleInertiaRequests.php`

### 3.2 Unsaved Changes Modal

- **Hook:** `useUnsavedChanges(dirty)` in all form pages
- **Modal:** `<UnsavedChangesModal>`
- **Trigger:** Attempting navigation while `dirty === true`
- **Patterns:**
  - `useForm` pages: compare `data` against `useRef` snapshot of initial values
  - `useState` forms: compare each field individually
  - Inline modal forms: guard on `showForm`

### 3.3 Confirmation Dialogs (SRS §5.2 NFR-SAFE-001)

- Required before: case closure, referral issuance, record deactivation, admin changes
- Pattern: Modal with explicit confirm/cancel, warning text

### 3.4 Data Tables

- **Pagination:** Laravel `LengthAwarePaginator` → Inertia pagination props
- **Search:** Server-side filtering via query params (`?search=...`)
- **Filters:** Status dropdown, date range picker, agency selector
- **Sort:** Clickable column headers (server-side sort)

### 3.5 Status Badges

| Status | Color | Icon |
|---|---|---|
| OPEN / ACTIVE | Green | • |
| PENDING | Yellow/Amber | ◷ |
| PROCESSING | Blue | ⟳ |
| CLOSED / COMPLETED | Green | ✓ |
| REJECTED | Red | ✕ |
| FOR COMPLIANCE | Orange | ⚠ |
| DRAFT | Gray | ○ |

**Note:** Color is NEVER the only indicator — text labels and icons always accompany status badges (WCAG 2.1 AA compliance).

### 3.6 ChatBot Component
The chatbot is implemented as a persistent floating action button in the bottom-right corner of public and tracking pages.
- **Trigger:** Floating button with chat icon.
- **UI:** Expandable chat window with message history, scrollable container, and fixed input field at the bottom.
- **Interaction:** Real-time feedback via "Bot is typing..." indicator. Messages are styled with distinct bubbles for user (right) and bot (left).

---

## 4. Page Patterns

### 4.1 Dashboard (`Dashboard.jsx`)

Role-based KPIs displayed as stat cards:

```
┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐
│ Open     │ │ Pending  │ │ Completed│ │ Agencies │
│ Cases    │ │ Referrals│ │ This Month│ │ Involved │
│   42     │ │   15     │ │   28     │ │    6     │
└─────────┘ └─────────┘ └─────────┘ └─────────┘

┌───────────────────────────────────────────┐
│ Case Trends (Line Chart)                   │
│ ▁▃▅▇▆▅▃▁                                  │
└───────────────────────────────────────────┘

┌──────────────┐ ┌──────────────────────────┐
│ Recent Cases  │ │ Referral Status (Pie)     │
│ • Case-001    │ │ 🟢 Completed 45%          │
│ • Case-002    │ │ 🟡 Pending 25%            │
│ • Case-003    │ │ 🔴 Rejected 10%           │
└──────────────┘ └──────────────────────────┘
```

### 4.2 Case List (`Case/Index.jsx`)

```
┌─────────────────────────────────────────────────────────────┐
│ [Search...]    [Status ▼]    [Agency ▼]    [+ New Case]     │
├──────┬────────┬────────┬────────┬────────┬────────┬─────────┤
│ #    │ Client │ Type   │ Status │ Agency │ Created│ Action  │
├──────┼────────┼────────┼────────┼────────┼────────┼─────────┤
│ C001 │ Juan D │ OFW    │ OPEN   │ DMW    │ 05/20  │ [View]  │
│ C002 │ Maria S│ NOK    │ CLOSED │ OWWA   │ 05/19  │ [View]  │
└──────┴────────┴────────┴────────┴────────┴────────┴─────────┘
                  [1] [2] [3] ... [Next ›]
```

### 4.3 Case Detail (`Case/Show.jsx`)

```
┌─────────────────────────────────────────────────────────────┐
│ Case #C001  ◷ OPEN                    [Archive] [Close]     │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌─ Client Info ──────────────────────────────────┐          │
│  │ Name: Juan Dela Cruz     Type: OFW             │          │
│  │ DOB: 1990-01-15          Sex: MALE             │          │
│  │ Contact: +63 912 345 6789                      │          │
│  └─────────────────────────────────────────────────┘          │
│                                                              │
│  ┌─ Documents ─────────────────────────────────────┐          │
│  │ ┌─────────────────────────────┬────────┐          │          │
│  │ │ File Name                   │ Action │          │          │
│  │ ├─────────────────────────────┼────────┤          │          │
│  │ │ Passport_Scan.pdf           │ [v][x] │          │          │
│  │ │ Employment_Contract.docx    │ [v][x] │          │          │
│  │ └─────────────────────────────┴────────┘          │          │
│  │                                  [+ Upload Document]      │
│  └─────────────────────────────────────────────────┘          │
│                                                              │
│  ┌─ Referrals ────────────────────────────────────┐          │
│  │ ┌──────────┬──────────┬──────────┬──────────┐ │          │
│  │ │ Agency   │ Status   │ Decision │ Milestones│ │          │
│  │ ├──────────┼──────────┼──────────┼──────────┤ │          │
│  │ │ OWWA     │ PROCESSING│ ACCEPT  │ 3 entries│ │          │
│  │ │ DOLE     │ PENDING  │ —        │ 0 entries│ │          │
│  │ └──────────┴──────────┴──────────┴──────────┘ │          │
│  │                                   [+ New Referral]       │
│  └─────────────────────────────────────────────────┘          │
│                                                              │
│  ┌─ Case Timeline ────────────────────────────────┐          │
│  │ ● May 20 — Case created by Juan C.             │          │
│  │ ● May 21 — Referred to OWWA                    │          │
│  │ ● May 22 — OWWA accepted (Maria A.)           │          │
│  │   └─ Milestone: Initial assessment scheduled   │          │
│  │ ● May 25 — Milestone: Counseling provided      │          │
│  └─────────────────────────────────────────────────┘          │
└─────────────────────────────────────────────────────────────┘
```

### 4.4 Helpdesk Patterns

#### 4.4.1 Article List (`Helpdesk/Index.jsx`)
- **Search Header:** Prominent search bar with background illustration.
- **Main Grid:** Cards for each category showing title, icon, and article count.
- **Sidebar:** Featured articles list and recent updates.
- **Article Cards:** Show title, excerpt, category badge, and "Read More" link.

#### 4.4.2 Article Detail (`Helpdesk/Show.jsx`)
- **Breadcrumbs:** Path from home to current category.
- **Content Area:** Article title, metadata (date, author), and rich-text/markdown content.
- **Feedback Section:** Thumbs up/down icons at the bottom of the article.
- **Navigation:** Previous/Next article links.

#### 4.4.3 Helpdesk CMS (`Admin/Helpdesk/ArticleForm.jsx`)
- **Editor:** Markdown editor with live preview side-by-side.
- **Metadata Sidebar:** Category selection, tags (multi-select), featured toggle, visibility settings.
- **Publish Controls:** Draft/Published status toggle with publish date picker.

### 4.5 System Settings Chatbot Config
Located at `/admin/system-settings`, includes a dedicated AI/Chatbot section:
- **Provider Selection:** Dropdown for OpenAI, Anthropic, or Custom.
- **API Key Field:** Password-type field for secure key input (masked by default).
- **Instruction Prompt:** Large textarea for defining the chatbot's system message/persona.
- **Model Parameters:** Sliders or number inputs for temperature and token limits.

### 4.6 Overdue Referral Page
A specialized dashboard for monitoring delayed inter-agency responses.
- **Table:** Lists referrals where the target agency has not responded within the SLA.
- **Action:** Bulk selection with "Send Reminder Notifications" button at the top.
- **Highlighting:** Rows are color-coded based on days past SLA.

### 4.7 Agency Services Management
Located at `/services` for authenticated agency users.
- **CRUD Table:** List of services offered by the agency with toggle for active/inactive status.
- **Form:** Modal-based form to add or edit service descriptions and processing time targets.

### 4.8 OFW Tracking Portal (`Tracking/`)

```
┌────────────────────────────────────┐
│     Bayanihan One Window            │
│     Track Your Case                 │
│                                     │
│  [Enter Tracker Number: _________]  │
│                                     │
│  [Send OTP Code]                    │
│                                     │
│  [OTP Code: ____]                   │
│                                     │
│  [Verify & Track]                   │
└────────────────────────────────────┘

After verification:
┌────────────────────────────────────┐
│ Case Status: OPEN                   │
│ Responsible: DMW Region VII         │
│                                     │
│ Timeline (Public View):             │
│ ✓ May 20 — Case filed               │
│ ✓ May 21 — Referred to OWWA        │
│ ◷ May 22 — Awaiting agency update  │
│                                     │
│ Need help? [Chat with AI Assistant]│
└────────────────────────────────────┘
```

### 4.5 Admin: Agency/User Management

Standard CRUD pattern for all admin resources:

```
┌──────────────────────────────────────────────┐
│  Agencies                  [+ Add Agency]    │
├──────────────────────────────────────────────┤
│ ┌───────────┬──────────┬──────────┬────────┐ │
│ │ Name      │ Services │ Status   │ Actions│ │
│ ├───────────┼──────────┼──────────┼────────┤ │
│ │ OWWA      │ 3        │ Active   │ [E][D] │ │
│ │ DOLE      │ 5        │ Active   │ [E][D] │ │
│ └───────────┴──────────┴──────────┴────────┘ │
└──────────────────────────────────────────────┘
```

### 4.6 Analytics Dashboard (`AnonymizedAnalytics/Index.jsx`)

```
┌─────────────────────────────────────────────────────────────┐
│ Analytics Dashboard                                          │
├─────────────────────────────────────────────────────────────┤
│ ┌────────────┐ ┌────────────┐ ┌────────────┐ ┌────────────┐ │
│ │ Total      │ │ Open       │ │ Active     │ │ Avg.        │ │
│ │ Cases      │ │ Cases      │ │ Referrals  │ │ Processing  │ │
│ │    150     │ │    42      │ │    28      │ │  12 days    │ │
│ └────────────┘ └────────────┘ └────────────┘ └────────────┘ │
│                                                               │
│ ┌─ Cases by Status ────┐ ┌─ Referral Stats ────┐            │
│ │ OPEN     42  ████████│ │ PENDING    15 ████  │            │
│ │ CLOSED  108  ██████████████████████│ │ PROCESSING 8 ██   │            │
│ └───────────────────────┘ │ COMPLETED 20 █████ │            │
│ ┌─ Export ───────────────┐│ REJECTED   5 █     │            │
│ │ [PDF] [CSV]            │└────────────────────┘            │
│ └────────────────────────┘                                   │
└─────────────────────────────────────────────────────────────┘
```

---

## 5. Form Patterns

### 5.1 Case Intake Form

Multi-section form with the following sections:
1. **OFW Information** — name, DOB, sex, contact
2. **Address Details** — house/street, barangay, city, province
3. **Employment History** — employer, position, country, dates
4. **Next of Kin** — emergency contact
5. **Case Summary** — narrative, service needs
6. **Supporting Documents** — file uploads

Saved as draft by default; explicit "Publish" action to activate.

### 5.2 Referral Form

- Select target agency (dropdown)
- Specify required services (multi-select)
- Add notes/instructions (textarea)
- Attach supporting documents (file upload)
- Submit creates referral in PENDING status

### 5.3 Milestone Form (Agency Side)

- Title (required)
- Description (optional)
- Submit → append-only entry (no edit/delete)

---

## 6. State Patterns

| State | Implementation |
|---|---|
| Loading | Inertia progress bar (`<progress>` element) |
| Empty | "No records found" with illustration + CTA |
| Error | Flash message toast + inline validation errors |
| Success | Flash message toast with green styling |
| Offline | Not handled in v1.0 (requires service worker) |

### Empty State Pattern
Used on all list views when no records match:
```
┌────────────────────────────────┐
│         📋 No cases yet         │
│   Create your first case to    │
│   start tracking OFW assistance │
│                                │
│    [+ Create New Case]          │
└────────────────────────────────┘
```

---

## 7. Error Handling

### Validation Errors (Inertia `$errors`)

Inline errors displayed below each form field:
```
┌─────────────────────────────────────────┐
│ First Name: [________________________]  │
│            ⚠ First name is required      │
└─────────────────────────────────────────┘
```

### Authorization Errors
- 403: Rendered by Inertia error page or redirect to dashboard with flash
- 404: Custom "Not Found" page within layout
- 429: Rate limit notice with retry timing

---

## 8. Accessibility Implementation (SRS §6.5)

| Requirement | Implementation |
|---|---|
| Keyboard navigation | All interactive elements tab-indexable, focusable |
| Focus indicators | Visible `:focus-visible` rings on all controls |
| Color contrast | Text/bg contrast ≥ 4.5:1 (AA normal) |
| Alt text | All images/icons have accessible descriptions |
| ARIA labels | Semantic HTML + ARIA where needed |
| Screen reader | Compatible with NVDA, VoiceOver, TalkBack |
| Form labels | Every input has associated `<label>` |
| Error messages | Descriptive, clear, non-technical |
| Mobile responsive | Breakpoints at 640px, 768px, 1024px |
| Text resizing | No loss of function at 200% zoom |
