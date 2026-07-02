---
sessionID: ses_0e2d4b5beffeY8fAiVEmUNPtrT
baseMessageCount: 275
updatedAt: 2026-07-02T07:08:17.343Z
version: 1.0
date_created: 2026-07-02
owner: agent
tags: [spec, diagnostic]
---

# I want to enhance the filters

## Current spec

# Introduction
This specification defines UI/UX enhancements to filtering on the Cases page in the One Window Bayanihan application. The goal is to make case filtering clearer, faster, and easier to manage while preserving the existing Laravel + Inertia + React architecture and URL-backed filter behavior.

## 1. Purpose & Scope
The intended audience is product stakeholders, developers, and QA contributors working on the Cases page filtering experience.

In scope:
- Enhancing the Cases page filter UI/UX.
- Adding inline desktop quick filters for Search, Status, and Client Type.
- Keeping Search visible on mobile while moving Status and Client Type into the Filters panel.
- Adding a collapsible advanced filter panel for Vulnerability, Category, Author, and Referred Agency.
- Providing Apply, Reset draft, and Clear advanced filters actions in the advanced panel.
- Displaying active filter chips directly below filter controls above the table.
- Using a 500ms debounce for Search.
- Providing Clear all behavior that removes Search, quick filters, and advanced filters while preserving sort and rows-per-page.
- Showing a filter-specific empty state message when no cases match active filters.
- Preserving URL/query-backed filtering behavior, server-side sorting, pagination, and authorization rules.

Out of scope:
- Refactoring all list/table pages.
- Saved filter presets unless later approved.
- Reporting/dashboard filters.
- New JSON API endpoints.
- New date/range filters unless later approved.

Assumptions:
- Cases filtering remains Inertia-driven.
- Search applies after typing stops for 500ms.
- Status and Client Type apply immediately on desktop.
- Advanced filters apply only after clicking Apply.
- Closing the advanced panel keeps unapplied draft changes until refresh or explicit reset/change.
- Clear all removes Search, Status, Client Type, Vulnerability, Category, Author, and Referred Agency.
- Clear all preserves current sort, sort direction, and rows-per-page.

## 2. Definitions
- **Cases page**: The authenticated page listing case records and associated table controls.
- **Filter**: A UI control or query parameter used to narrow the Cases list.
- **Quick filter**: A high-frequency filter displayed inline on desktop and applied immediately when changed.
- **Debounced search**: Search behavior where filtering runs only after the user stops typing for 500ms.
- **Advanced filter**: A lower-frequency filter displayed inside a collapsible panel and applied through an explicit Apply action.
- **Active filter chip**: A visible summary item showing an applied filter with a removal action.
- **Draft filter state**: Unsaved advanced filter values not yet applied to the URL/query state.
- **Unapplied change**: A draft advanced filter value that differs from the currently applied value.
- **Clear all**: An action that removes all user-selected filter values while preserving sort and rows-per-page.
- **Filter-specific empty state**: Empty table messaging shown when active filters produce no matching cases.

## 3. Requirements, Constraints & Guidelines
- **REQ-001**: The enhanced filter experience must target the Cases page first.
- **REQ-002**: The system must make existing Case filters easier to find, understand, apply, and clear.
- **REQ-003**: Applied filters must be summarized using active filter chips directly below filter controls above the table.
- **REQ-004**: Users must be able to clear individual filters.
- **REQ-005**: Users must be able to clear all active filters.
- **REQ-006**: Clear all must remove Search, Status, Client Type, Vulnerability, Category, Author, and Referred Agency filters.
- **REQ-007**: Clear all must preserve current sort, sort direction, and rows-per-page.
- **REQ-008**: Filter state should remain represented in the URL where practical.
- **REQ-009**: Empty/default filter values must not pollute query strings.
- **REQ-010**: Filtering must continue to work with server-side sorting and pagination.
- **REQ-011**: Filter changes should reset pagination when the current page may no longer be valid.
- **REQ-012**: Desktop quick filters must include Search, Status, and Client Type.
- **REQ-013**: Status and Client Type must apply immediately on desktop.
- **REQ-014**: Search must apply through a 500ms debounce.
- **REQ-015**: The advanced panel must include Vulnerability, Category, Author, and Referred Agency.
- **REQ-016**: Advanced filters must use an explicit Apply action.
- **REQ-017**: Closing the advanced panel must preserve unapplied draft changes until page refresh or explicit reset/change.
- **REQ-018**: Reopening the advanced panel before refresh must restore draft values.
- **REQ-019**: The Filters button must show a badge or visual marker when unapplied advanced changes exist.
- **REQ-020**: The advanced panel must show text such as “Unsaved filter changes” when unapplied changes exist.
- **REQ-021**: The advanced panel must provide Apply, Reset draft, and Clear advanced filters actions.
- **REQ-022**: On mobile, Search must remain visible outside the panel.
- **REQ-023**: On mobile, Status and Client Type should move into the Filters panel.
- **REQ-024**: When active filters produce no results, the empty state must say: “No cases match your filters. Try adjusting or clearing filters.”
- **CON-001**: The implementation must fit the existing Laravel + Inertia + React stack.
- **CON-002**: No new backend JSON API endpoints are assumed.
- **CON-003**: Existing Cases page and UnifiedTable flow should be preserved.
- **CON-004**: Backend filter props must serialize as objects, not arrays, even when empty.
- **SEC-001**: Filters must not expose cases beyond the user’s authorized scope.
- **SEC-002**: Invalid query values must not bypass authorization or visibility rules.
- **GUD-001**: Filter controls should be easy to scan and not overwhelm users.
- **GUD-002**: High-frequency filters should be more prominent than low-frequency filters.
- **GUD-003**: Unapplied advanced changes should be visible but not blocking.

## 4. Interfaces & Data Contracts
Expected filter prop shape:
```json
{
  "search": "string | omitted",
  "status": "string | omitted",
  "client_type": "string | omitted",
  "vulnerability_indicator": "string | omitted",
  "user_id": "string | omitted",
  "agcy_id": "string | omitted",
  "category_id": "string | omitted",
  "sort": "string | omitted",
  "direction": "asc | desc | omitted",
  "per_page": "number | string | omitted"
}
```

Desktop quick filters:
```json
["search", "status", "client_type"]
```

Advanced filters:
```json
["vulnerability_indicator", "category_id", "user_id", "agcy_id"]
```

Clear all target fields:
```json
["search", "status", "client_type", "vulnerability_indicator", "category_id", "user_id", "agcy_id"]
```

Clear all preserved fields:
```json
["sort", "direction", "per_page"]
```

UI state:
```json
{
  "appliedFilters": "URL-backed filter values currently applied to results",
  "advancedDraftFilters": "Panel-local values not applied until Apply",
  "hasUnappliedAdvancedChanges": "true when advancedDraftFilters differs from applied advanced filters",
  "searchDebounceMs": 500
}
```

## 5. Acceptance Criteria
- **AC-001**: Given a user is on the Cases page, When they apply a filter, Then the Cases table updates to show matching records only.
- **AC-002**: Given filters are active, When the page renders, Then active filter chips appear directly below filter controls above the table.
- **AC-003**: Given an active filter chip is shown, When the user removes it, Then the table reloads without that filter and pagination resets as needed.
- **AC-004**: Given multiple filters are active, When the user chooses Clear all, Then Search, quick filters, and advanced filters are removed.
- **AC-005**: Given Clear all is used, When the table reloads, Then current sort, sort direction, and rows-per-page are preserved.
- **AC-006**: Given filters are active, When the user refreshes, Then applied filter state is preserved through URL/query state.
- **AC-007**: Given no active filters, When the page loads, Then filter state is object-compatible and does not expose array prototype methods as filter values.
- **AC-008**: Given a user changes Status or Client Type on desktop, When the value changes, Then the table reloads immediately.
- **AC-009**: Given a user types in Search, When typing stops for 500ms, Then the table reloads with the search query.
- **AC-010**: Given a user continues typing before 500ms elapses, Then no intermediate table reload occurs.
- **AC-011**: Given a user clears Search, When typing stops for 500ms, Then the search query is removed and the table reloads.
- **AC-012**: Given a user changes advanced filters without clicking Apply, Then table results and URL remain unchanged.
- **AC-013**: Given a user clicks Apply in the advanced panel, Then the table reloads with draft advanced filters.
- **AC-014**: Given a user closes and reopens the advanced panel before refresh, Then draft values are preserved.
- **AC-015**: Given unapplied advanced changes exist, Then the Filters button and panel indicate unsaved filter changes.
- **AC-016**: Given a mobile viewport, Then Search remains visible and Status/Client Type are available in the Filters panel.
- **AC-017**: Given active filters produce zero matching cases, Then the empty state says “No cases match your filters. Try adjusting or clearing filters.”

## 6. Test Automation Strategy
Backend tests:
- Verify supported Case filter query parameters.
- Verify empty filter state serializes object-compatibly.
- Verify invalid values do not bypass authorization.
- Verify combined filters respect role/agency visibility.

Frontend tests:
- Verify filter normalization handles `{}`, missing filters, and accidental `[]`.
- Verify active filter chips render below filter controls.
- Verify Clear all removes Search, quick filter, and advanced filter params while preserving sort, direction, and per_page.
- Verify Search triggers only after 500ms debounce.
- Verify Status and Client Type update immediately on desktop.
- Verify advanced drafts remain local until Apply.
- Verify Reset draft, Clear advanced filters, and unapplied indicators.
- Verify mobile Search and panel layout behavior.
- Verify filter-specific empty state message.

Browser/E2E tests:
- Type in Search and confirm one update after 500ms.
- Apply quick filters and confirm immediate updates.
- Edit advanced filters without applying and confirm results do not change.
- Apply advanced filters and confirm results update.
- Clear all and confirm filter params are removed while sort/rows-per-page remain.
- Refresh a filtered URL and confirm applied state persists.
- Force a zero-result filter combination and confirm empty-state copy.

## 7. Rationale & Context
The selected enhancement focuses on the Cases page only to reduce risk while improving a high-value workflow. Search, Status, and Client Type are quick filters because they support frequent case triage. A 500ms search debounce balances responsiveness with reducing unnecessary Inertia visits. Vulnerability, Category, Author, and Referred Agency are advanced filters because they are useful but less frequently adjusted and require more space.

Clear all removes all user-selected filters because users generally expect “all” to include search and every active narrowing criterion. Sort and rows-per-page are preserved because they represent table preference rather than filter criteria.

The filter-specific empty message helps users understand that records may exist, but none match the current criteria.

The implementation must avoid the known empty-filter serialization pitfall where PHP empty arrays become JavaScript arrays and `filters.sort` resolves to `Array.prototype.sort`.

## 8. Dependencies & External Integrations
- **EXT-001**: Inertia.js page navigation and partial reload behavior.
- **EXT-002**: Laravel request/query handling for filter parameters.
- **EXT-003**: Existing React Cases page and table UI.
- **EXT-004**: Existing backend Case query/service logic.
- **EXT-005**: Existing authentication and role/agency authorization model.
- **EXT-006**: Responsive CSS/Tailwind behavior used by the existing frontend.

## 9. Examples & Edge Cases
Example URL:
```text
/cases?search=Juan&status=OPEN&client_type=OFW&category_id=abc-uuid&sort=created_at&direction=desc
```

Example active chips:
```json
[
  { "key": "search", "label": "Search", "value": "Juan" },
  { "key": "status", "label": "Status", "value": "Open" },
  { "key": "client_type", "label": "Client Type", "value": "OFW" },
  { "key": "category_id", "label": "Category", "value": "Welfare Assistance" }
]
```

Empty state copy:
```text
No cases match your filters. Try adjusting or clearing filters.
```

Edge cases:
- User types quickly in Search.
- User clears Search before debounce fires.
- Clear all clicked while Search debounce is pending.
- Clear all used while custom sort or rows-per-page is active.
- Advanced panel closed with unapplied changes.
- Clear advanced filters clicked while applied advanced filters still exist.
- Filter combination returns zero records.
- Backend receives unsupported values.
- Filter prop arrives as `[]` instead of `{}`.
- Sorting and filtering are both active.

## 10. Validation Criteria
- Query params match active chip UI.
- Active filter chips appear directly below filter controls.
- Empty/default values are omitted from query params.
- Backend returns object-compatible filter state.
- Frontend normalizes malformed filter state safely.
- Search updates after 500ms and does not reload on intermediate keystrokes.
- Clear all removes Search, Status, Client Type, Vulnerability, Category, Author, and Referred Agency.
- Clear all preserves sort, direction, and rows-per-page.
- Advanced filters do not update results until Apply.
- Mobile layout keeps Search visible and moves selects into the panel.
- Zero-result filtered state shows the specified empty-state copy.
- Authorization remains enforced under all filter combinations.

## 11. Related Specifications / Further Reading
- Existing Cases table Inertia partial reload implementation.
- Existing UnifiedTable sort and loading behavior.
- Project architecture documentation for Laravel, Inertia, React, and authorization patterns.

## Q&A history

Q: Which area should the enhanced filters target first?
A: Cases page only

Q: What kind of enhancement matters most?
A: Better filter UI/UX

Q: What UI layout should the enhanced Cases filters use?
A: Inline quick filters plus collapsible advanced panel

Q: When should filter changes apply?
A: Immediate for quick filters, Apply button for advanced filters

Q: Which fields should be shown as inline quick filters?
A: Search + Status + Client Type

Q: What should happen if the advanced panel has unapplied changes and the user closes it?
A: Keep draft changes until page refresh

Q: Which fields should go in the advanced filter panel?
A: Vulnerability + Category + Author + Referred Agency

Q: How should unapplied advanced changes be shown?
A: Badge plus 'Unsaved filter changes' text inside panel

Q: Which actions should the advanced filter panel provide?
A: Apply + Reset draft + Clear advanced filters

Q: How should quick filters behave on mobile?
A: Keep Search visible and move selects into Filters panel

Q: How should Search apply while the user types?
A: Debounced after typing stops

Q: Where should active filter chips appear?
A: Directly below the filter controls above the table

Q: What debounce delay should Search use?
A: 500ms

Q: What should Clear all active filters remove?
A: All filters including Search, quick filters, and advanced filters

Q: What debounce delay should Search use?
A: 500ms

Q: What should Clear all active filters remove?
A: All filters including Search, quick filters, and advanced filters

Q: When filters are cleared, should current sort and rows-per-page be preserved?
A: Preserve sort and rows-per-page

Q: What should the empty results state say when filters match no cases?
A: No cases match your filters. Try adjusting or clearing filters.
