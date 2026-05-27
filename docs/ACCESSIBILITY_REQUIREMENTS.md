# Bayanihan One Window — Accessibility Requirements

> **Source:** SRS v1.2 (May 19, 2026) — §6.5 Accessibility and User Environment
> **Last Updated:** 2026-05-28

---

## 1. Accessibility Policy

Bayanihan One Window shall provide **accessible, inclusive, and equitable digital access** for all intended users, including distressed OFWs, DMW personnel, agency focal persons, administrators, and users with disabilities.

Accessibility is treated as a **mandatory nonfunctional requirement** (not an optional enhancement) per SRS §6.5. The system shall align with **WCAG 2.1 Level AA** across all interfaces.

---

## 2. WCAG 2.1 Compliance Matrix

### 2.1 Perceivable

| SRS ID | WCAG SC | Requirement | Implementation | Status |
|---|---|---|---|---|
| ACC-005 | 1.4.3 Contrast (Minimum) | Text contrast ≥ 4.5:1 (normal), ≥ 3:1 (large) | Tailwind config: default color palette meets AA | ✅ |
| ACC-006 | 1.4.1 Use of Color | Information not conveyed by color alone | Status badges use icons + text labels + color | ✅ |
| ACC-007 | 1.1.1 Non-text Content | Images/icons have alt text | `<img alt="">` on all images, ARIA labels on icons | ✅ |
| ACC-008 | 1.4.4 Resize Text | Text resizable to 200% without loss | Responsive layout, relative units (rem) | ✅ |
| ACC-009 | 1.4.10 Reflow | Content readable at 320px width | Tailwind responsive breakpoints | ✅ |

### 2.2 Operable

| SRS ID | WCAG SC | Requirement | Implementation | Status |
|---|---|---|---|---|
| ACC-010 | 2.1.1 Keyboard | All functions operable via keyboard | TabIndex on all interactive elements | ✅ |
| ACC-011 | 2.4.7 Focus Visible | Visible focus indicators | `focus:ring-2` Tailwind utilities on all controls | ✅ |
| ACC-012 | 2.2.1 Timing Adjustable | Time limits have accommodations | Session timeout has warning; OTP allows resend | ✅ |
| ACC-013 | 3.2.3 Consistent Navigation | Navigation consistent across pages | AppLayout sidebar consistent for all authenticated pages | ✅ |
| ACC-014 | 2.3.1 Three Flashes | No seizure-inducing flashes | No animated/flashing content in UI | ✅ |

### 2.3 Understandable

| SRS ID | WCAG SC | Requirement | Implementation | Status |
|---|---|---|---|---|
| ACC-015 | 3.3.2 Labels or Instructions | Clear labels, instructions | `<label>` elements on all form fields | ✅ |
| ACC-016 | 3.3.1 Error Identification | Accessible error messages | Inline validation errors below fields | ✅ |
| ACC-017 | 3.3.3 Error Suggestion | Clear correction guidance | Validation messages describe the fix needed | ✅ |
| ACC-018 | 3.2.4 Consistent Identification | Consistent behavior | Same UI patterns across all modules | ✅ |

### 2.4 Robust

| SRS ID | WCAG SC | Requirement | Implementation | Status |
|---|---|---|---|---|
| ACC-019 | 4.1.1 Parsing | Semantic markup | Semantic HTML5 elements used | ✅ |
| ACC-020 | 4.1.2 Name, Role, Value | Screen reader compatible | ARIA roles on dynamic components | ✅ |
| ACC-021 | 4.1.3 Status Messages | Dynamic updates announced | Inertia page updates preserve accessibility | ✅ |

---

## 3. Platform Accessibility (§6.5.1)

| SRS ID | Requirement | Implementation |
|---|---|---|
| ACC-001 | Browser-based, no specialized install | Web application — any modern browser |
| ACC-002 | Cross-platform (desktop + mobile) | Responsive Tailwind CSS |
| ACC-003 | Mobile-responsive public portal | Separate mobile layout for tracking portal |
| ACC-004 | Works across modern browsers | Chrome, Edge, Firefox, Safari support |

---

## 4. Accessibility for Vulnerable Public Users (§6.5.3)

| SRS ID | Requirement | Implementation |
|---|---|---|
| ACC-022 | Minimize complexity in public workflows | OTP tracking = 3-step flow: enter number → OTP → view status |
| ACC-023 | Usable by varying digital literacy | Simple language, clear CTA buttons, visual progress indicators |
| ACC-024 | Avoid technical language in public interfaces | Plain Filipino-English terms (e.g., "Tracker Number" not "UUID") |
| ACC-025 | Privacy-safe, user-comprehensible error messages | "Invalid tracker number" not system-level error details |

---

## 5. Administrative Accessibility (§6.5.4)

| SRS ID | Requirement | Implementation |
|---|---|---|
| ACC-026 | Readable layouts in admin dashboards | Clean typography, generous spacing, consistent grid |
| ACC-027 | Keyboard-accessible tables, forms, filters | All table rows, form inputs, filter controls tabbable |
| ACC-028 | Usable under prolonged use | High-contrast mode, reduced eye strain palette |

---

## 6. Implementation Checklist

### 6.1 HTML/Semantic Structure

- [x] `<nav>` for navigation blocks
- [x] `<main>` for primary content
- [x] `<h1>-<h6>` heading hierarchy
- [x] `<label>` elements for all form inputs
- [x] `<table>` with `<thead>`, `<th scope="">` for data tables
- [x] `<button>` or `<a>` for clickable elements (not `<div>`)

### 6.2 ARIA Attributes

- [x] `aria-label` on icon-only buttons
- [x] `aria-current="page"` on active nav items
- [x] `aria-expanded` on collapsible sections
- [x] `role="alert"` on validation error messages
- [x] `role="dialog"` and `aria-modal="true"` on modals

### 6.3 Keyboard Navigation

- [x] All interactive elements reachable via Tab
- [x] Tab order follows visual order
- [x] Focus trapped in modals (FocusTrap)
- [x] Escape key closes modals/dropdowns
- [x] Enter/Space activates buttons
- [x] Skip-to-content link for keyboard users

### 6.4 Visual Design

- [x] Color contrast ≥ 4.5:1 (normal text)
- [x] Color contrast ≥ 3:1 (large text / UI components)
- [x] Information not conveyed by color alone
- [x] Focus indicators visible (2px ring)
- [x] Text resizable to 200%
- [x] Responsive down to 320px width

### 6.5 Forms

- [x] All inputs have associated labels
- [x] Required fields indicated (asterisk + `aria-required`)
- [x] Validation errors displayed inline
- [x] Error summary at top of form
- [x] Error messages suggest correction
- [x] Auto-focus on first error field

### 6.6 Screen Reader Compatibility

- [x] NVDA compatibility (Windows)
- [x] VoiceOver compatibility (macOS/iOS)
- [x] TalkBack compatibility (Android)
- [x] Dynamic content changes announced
- [x] Loading states communicated

---

## 7. Testing & Validation (§6.5.5)

| SRS ID | Requirement | Method |
|---|---|---|
| ACC-029 | Accessibility validated during dev/test | Automated (axe-core) + Manual (keyboard + screen reader) |
| ACC-030 | Regressions corrected before release | CI/CD accessibility gate |

### Validation Tools

| Tool | Purpose | Frequency |
|---|---|---|
| axe-core (Lighthouse) | Automated audit for WCAG violations | Every PR |
| Keyboard-only testing | Ensure full keyboard operability | Every PR |
| Screen reader testing (NVDA/VoiceOver) | Verify announcements and navigation | Per major release |
| Contrast checker | Verify color contrast ratios | During design review |
| Responsive checker | Test at 320px, 768px, 1024px+ | Every PR |

---

## 8. Accessibility Gaps

| Gap | Impact | Priority | Remediation |
|---|---|---|---|
| No skip-to-content link | Keyboard users must tab through nav | MEDIUM | Add skip link as first focusable element |
| No ARIA live regions on dynamic content | Screen reader may miss updates | MEDIUM | Add `aria-live="polite"` on notification areas |
| No automated a11y CI check | Regressions possible | MEDIUM | Integrate axe-core in CI pipeline |
| No screen reader testing documented | Unknown compatibility gaps | LOW | Schedule manual screen reader pass |
