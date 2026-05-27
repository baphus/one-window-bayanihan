# Bayanihan One Window — API Contracts

> **Source:** SRS v1.2 (May 19, 2026) — `routes/web.php`, Controllers
> **Last Updated:** 2026-05-28

All routes use Inertia.js — responses are server-rendered page visits, not JSON API responses. The exceptions are the analytics API endpoint and the chatbot endpoint which return JSON.

---

## 1. Route Conventions

| Convention | Standard |
|---|---|
| Base URL | `https://{app-domain}` |
| Auth | Session-based (Laravel) + OTP MFA |
| CSRF | Required for all `POST/PATCH/DELETE` via `_token` or `X-CSRF-TOKEN` |
| Response Format | Inertia page render (HTML) or JSON for API endpoints |
| Error Format | Inertia flash `$errors` + `$props.flash` for toast |
| Pagination | Laravel LengthAwarePaginator → Inertia pagination props |

---

## 2. Authentication Routes (`routes/auth.php`)

| Method | URI | Name | Controller | Middleware | Description |
|---|---|---|---|---|---|
| GET | `/login` | login | LoginOtpController@create | guest | Show login form |
| POST | `/login` | login.attempt | LoginOtpController@store | guest, throttle:login | Submit credentials |
| POST | `/login/otp` | login.otp | LoginOtpController@verify | guest, throttle:otp | Verify OTP code |
| POST | `/logout` | logout | LoginOtpController@destroy | auth | Logout |
| GET | `/register` | register | RegisteredUserController@create | guest | Show register form |
| POST | `/register` | register | RegisteredUserController@store | guest | Submit registration |
| GET | `/forgot-password` | password.request | PasswordResetController@create | guest | Show forgot password |
| POST | `/forgot-password` | password.email | PasswordResetController@store | guest, throttle:login | Send reset link |
| GET | `/reset-password/{token}` | password.reset | PasswordResetController@create | guest | Show reset form |
| POST | `/reset-password` | password.store | PasswordResetController@store | guest | Submit reset |

**Rate Limits:**
- Login: 6 attempts/minute (`throttle:login`)
- OTP: 3 attempts/minute (`throttle:otp`)

---

## 3. Authenticated Routes — Dashboard

| Method | URI | Name | Controller | Description |
|---|---|---|---|---|
| GET | `/dashboard` | dashboard | Closure | Role-based dashboard (CaseManager/Agency/Admin) |

**Response:** Inertia `Dashboard.jsx` with:
- `stats` — role-specific KPIs
- `recentCases` — recent case list
- `pendingReferrals` — pending referral count
- `agencyPerformance` — agency metrics (admin)
- `caseTrends` — monthly trend data
- `referralStatusDistribution` — pie chart data
- `role` — current user role

---

## 4. Case Management Routes

| Method | URI | Name | Controller | Description |
|---|---|---|---|---|
| GET | `/cases` | cases.index | CaseController@index | List cases (paginated, filterable) |
| GET | `/cases/create` | cases.create | CaseController@create | Show intake form |
| POST | `/cases` | cases.store | CaseController@store | Create new case |
| GET | `/cases/{case}` | cases.show | CaseController@show | View case detail |
| PATCH | `/cases/{case}` | cases.update | CaseController@update | Update case |
| POST | `/cases/{case}/publish` | cases.publish | CaseController@publish | Publish draft case |
| POST | `/cases/{case}/archive` | cases.archive | CaseController@archive | Soft-delete case |
| POST | `/cases/{case}/unarchive` | cases.unarchive | CaseController@unarchive | Restore archived case |
| POST | `/cases/{case}/toggle-status` | cases.toggle-status | CaseController@toggleStatus | Toggle case status |

### Case Index (`GET /cases`)

**Query Parameters:**
| Param | Type | Description |
|---|---|---|
| `search` | string | Text search (case number, client name) |
| `status` | string | Filter: OPEN, CLOSED |
| `agency` | uuid | Filter by referred agency |
| `date_from` | date | Date range start |
| `date_to` | date | Date range end |
| `per_page` | int | Pagination (default 15) |

### Case Show (`GET /cases/{case}`)

**Response Data:**
```php
[
    'case' => Case resource with: clients, referrals, documents, milestones
    'availableStatuses' => CaseStatus[] for workflow transitions
    'agencies' => Agency[] for referral creation
]
```

### Case Store (`POST /cases`)

**Validation Rules:**
| Field | Rules |
|---|---|
| `first_name` | required, string, max:255 |
| `last_name` | required, string, max:255 |
| `middle_name` | nullable, string |
| `suffix` | nullable, string |
| `date_of_birth` | nullable, date |
| `sex` | nullable, in:MALE,FEMALE |
| `client_type` | required, in:OFW,NEXT_OF_KIN |
| `summary` | nullable, string |
| `status` | nullable, in:DRAFT,OPEN |

**Note:** `case_number` and `tracker_number` are system-generated (not user-inputtable per SRS COM-DATA-003).

---

## 5. Referral Routes

| Method | URI | Name | Controller | Description |
|---|---|---|---|---|
| GET | `/referrals` | referrals.index | ReferralController@index | List referrals (paginated) |
| GET | `/referrals/create` | referrals.create | ReferralController@create | Show referral form |
| POST | `/referrals` | referrals.store | ReferralController@store | Create referral |
| GET | `/referrals/{referral}` | referrals.show | ReferralController@show | View referral detail |
| PATCH | `/referrals/{referral}/status` | referrals.update-status | ReferralController@updateStatus | Update referral status |
| POST | `/referrals/{referral}/milestones` | referrals.milestones.store | ReferralController@addMilestone | Add milestone |
| POST | `/referrals/{referral}/comments` | referrals.comments.store | ReferralController@addComment | Add comment |
| POST | `/referrals/{referral}/comments/{comment}/reply` | referrals.comments.reply | ReferralController@replyToComment | Reply to comment |
| POST | `/referrals/{referral}/attachments` | referrals.attachments.store | ReferralController@addAttachment | Upload attachment |
| POST | `/referrals/{referral}/attachments/{attachment}/replace` | referrals.attachments.replace | ReferralController@replaceAttachment | Replace attachment (versioning) |
| GET | `/referrals/{referral}/attachments/{versionGroupId}/versions` | referrals.attachments.versions | ReferralController@getAttachmentVersions | List attachment versions |

### Referral Status Update (`PATCH /referrals/{referral}/status`)

**Validation:**
| Field | Rules |
|---|---|
| `status` | required, in:PROCESSING,COMPLETED,REJECTED,FOR_COMPLIANCE |
| `decision` | required_if:status,COMPLETED,REJECTED, in:ACCEPT,REJECT |
| `decision_reason` | required, string (mandatory comment per BR-006) |

### Add Milestone (`POST /referrals/{referral}/milestones`)

**Validation:**
| Field | Rules |
|---|---|
| `title` | required, string, max:255 |
| `description` | nullable, string |

**Note:** Milestones are append-only. No update/delete endpoints exist.

---

## 6. Analytics & Reports Routes

| Method | URI | Name | Controller | Description |
|---|---|---|---|---|
| GET | `/analytics` | analytics.index | AnonymizedAnalyticsController@index | Show analytics dashboard |
| GET | `/reports` | reports.index | ReportsController@index | Show reports page |
| GET | `/reports/export-pdf` | reports.export-pdf | ReportsController@exportPdf | Export PDF report |
| GET | `/api/analytics` | api.analytics | AnonymizedAnalyticsController@api | JSON analytics data |

### Analytics Endpoints

**GET `/analytics`** (Inertia)
```php
[
    'casesByStatus' => [['status' => 'OPEN', 'count' => 42], ...],
    'casesByService' => [['client_type' => 'OFW', 'count' => 30], ...],
    'totalClients' => 150,
    'referralStats' => [['status' => 'PENDING', 'count' => 15], ...],
]
```

**GET `/api/analytics`** (JSON)
Same data as above, returned as JSON for external consumption.

**GET `/reports/export-pdf`**
| Param | Type | Description |
|---|---|---|
| `date_from` | date, nullable | Report start date |
| `date_to` | date, nullable | Report end date |

Returns: PDF file download (application/pdf).

---

## 7. Client & Stakeholder Routes

| Method | URI | Name | Controller | Description |
|---|---|---|---|---|
| GET | `/clients` | clients.index | ClientController@index | List clients |
| GET | `/clients/{client}` | clients.show | ClientController@show | Client detail with case history |
| GET | `/stakeholders` | stakeholders.index | StakeholderController@index | List stakeholders |
| GET | `/stakeholders/{stakeholder}` | stakeholders.show | StakeholderController@show | Stakeholder detail |

---

## 8. Administrative Routes (Admin-only + IP Whitelist)

All routes in this section require `role:ADMIN` + `ip.whitelist` middleware.

### 8.1 Agency Management (`/admin/agencies`)

| Method | URI | Name | Description |
|---|---|---|---|
| GET | `/admin/agencies` | admin.agencies.index | List agencies |
| POST | `/admin/agencies` | admin.agencies.store | Create agency |
| PATCH | `/admin/agencies/{agency}` | admin.agencies.update | Update agency |
| DELETE | `/admin/agencies/{agency}` | admin.agencies.destroy | Delete agency |

### 8.2 Service Management (`/admin/services`)

| Method | URI | Name | Description |
|---|---|---|---|
| GET | `/admin/services` | admin.services.index | List services |
| POST | `/admin/services` | admin.services.store | Create service |
| PATCH | `/admin/services/{service}` | admin.services.update | Update service |
| DELETE | `/admin/services/{service}` | admin.services.destroy | Delete service |

### 8.3 User Management (`/admin/users`)

| Method | URI | Name | Description |
|---|---|---|---|
| GET | `/admin/users` | admin.users.index | List users |
| POST | `/admin/users` | admin.users.store | Create user |
| PATCH | `/admin/users/{user}` | admin.users.update | Update user |
| DELETE | `/admin/users/{user}` | admin.users.destroy | Deactivate user |

### 8.4 System Settings (`/admin/system-settings`)

| Method | URI | Name | Description |
|---|---|---|---|
| GET | `/admin/system-settings` | admin.system-settings.index | List settings |
| POST | `/admin/system-settings` | admin.system-settings.update | Update settings |

### 8.5 Helpdesk CMS (`/admin/helpdesk/*`)

| Method | URI | Name | Description |
|---|---|---|---|
| GET/POST | `/admin/helpdesk/articles` | CRUD | Article management |
| GET/POST | `/admin/helpdesk/categories` | CRUD | Category management |
| GET/POST | `/admin/helpdesk/tags` | CRUD | Tag management |

### 8.6 Case Statuses (`/admin/case-statuses`)

| Method | URI | Name | Description |
|---|---|---|---|
| GET | `/admin/case-statuses` | admin.case-statuses.index | List statuses |
| POST | `/admin/case-statuses` | admin.case-statuses.store | Create status |
| PATCH | `/admin/case-statuses/{caseStatus}` | admin.case-statuses.update | Update status |
| DELETE | `/admin/case-statuses/{caseStatus}` | admin.case-statuses.destroy | Delete status |

### 8.7 Overdue Referrals

| Method | URI | Name | Middleware | Description |
|---|---|---|---|---|
| GET | `/overdue-referrals` | overdue-referrals.index | auth, verified | List overdue referrals |
| POST | `/overdue-referrals/send-reminders` | overdue-referrals.send-reminders | auth, verified | Send reminder notifications |

---

## 9. Public Routes

| Method | URI | Name | Middleware | Description |
|---|---|---|---|---|
| GET | `/` | — | guest | Welcome page |
| GET | `/partners` | partners | — | Public agency listing |
| GET | `/contact` | contact | — | Contact page |
| GET | `/track` | track.index | — | OFW tracking form |
| POST | `/track/send-otp` | track.send-otp | throttle:tracking | Send OTP for tracking |
| POST | `/track/verify-otp` | track.verify-otp | throttle:tracking | Verify tracking OTP |
| GET | `/track/case` | track.show | — | Show case tracking progress |

### Tracking OTP Rate Limit: 5 attempts/minute (`throttle:tracking`)

---

## 10. Helpdesk Public Routes

| Method | URI | Name | Description |
|---|---|---|---|
| GET | `/helpdesk` | helpdesk.index | Public helpdesk home |
| GET | `/helpdesk/search` | helpdesk.search | Search articles |
| GET | `/helpdesk/{slug}` | helpdesk.show | Article detail |
| POST | `/helpdesk/feedback` | helpdesk.feedback | Article feedback |

---

## 11. AI Chatbot Route

| Method | URI | Name | Description |
|---|---|---|---|
| POST | `/chatbot/message` | chatbot.message | Send/receive chat message |

**Request:** `{ "message": "string" }`  
**Response:** `{ "reply": "string" }` (JSON)

---

## 12. Profile Routes

| Method | URI | Name | Description |
|---|---|---|---|
| GET | `/profile` | profile.edit | Show profile form |
| PATCH | `/profile` | profile.update | Update profile |
| DELETE | `/profile` | profile.destroy | Delete account |

---

## 13. Audit Log Routes

| Method | URI | Name | Description |
|---|---|---|---|
| GET | `/audit-logs` | audit-logs.index | List/Filter audit logs |

**Query Parameters:** `search`, `action`, `module`, `date_from`, `date_to`, `user_id`

---

## 14. Feedback Routes

| Method | URI | Name | Description |
|---|---|---|---|
| GET | `/feedbacks` | feedbacks.index | List feedback submissions |

---

## 15. Agency Self-Service Routes (`role:AGENCY`)

| Method | URI | Name | Description |
|---|---|---|---|
| GET | `/services` | agency.services.index | List agency services |
| POST | `/services` | agency.services.store | Add service |
| PATCH | `/services/{service}` | agency.services.update | Update service |
| DELETE | `/services/{service}` | agency.services.destroy | Remove service |

---

## 16. Response Format Convention

### Inertia Page Response (default)
```php
return Inertia::render('PageName', [
    'data' => $data,
    'filters' => $request->only(['search', 'status', ...]),
]);
```

### Flash Messages (auto-toast)
```php
return redirect()->route('route.name')
    ->with('success', 'Operation completed successfully.')
    ->with('error', 'Operation failed.');
```

Accessible in React via `usePage().props.flash`.

### Error Responses
- **Validation Errors:** Inertia `$errors` prop (keyed by field name)
- **Authorization Errors:** `abort(403)` — caught by `HandleInertiaRequests.php`
- **Not Found:** `abort(404)` — caught by `HandleInertiaRequests.php`
- **Rate Limit:** `abort(429)` with Retry-After header

---

## 17. Middleware Stack by Route Group

| Route Group | Middleware |
|---|---|
| Public | `web` |
| Authenticated | `web`, `auth`, `verified` |
| Admin | `web`, `auth`, `verified`, `role:ADMIN`, `ip.whitelist` |
| Agency | `web`, `auth`, `verified`, `role:AGENCY` |
| Tracking | `web`, `throttle:tracking` |
