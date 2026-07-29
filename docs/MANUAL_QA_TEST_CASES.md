# Manual QA Test Cases — Case Manager & Agency Focal

> **Version:** 1.0.0
> **Date:** 2026-07-29
> **Scope:** One Window Bayanihan case management system — manual QA for Case Manager and Agency Focal roles

---

## Table of Contents

1. [Authentication & Login](#1-authentication--login)
2. [Dashboard](#2-dashboard)
3. [Case Management — Case Manager](#3-case-management--case-manager)
4. [Intake Queue — Self-Filed Cases](#4-intake-queue--self-filed-cases)
5. [Referral Management](#5-referral-management)
6. [Agency Focal — Referral Actions](#6-agency-focal--referral-actions)
7. [Client Requests (Agency-Only)](#7-client-requests-agency-only)
8. [Services Management (Agency-Only)](#8-services-management-agency-only)
9. [Case Documents](#9-case-documents)
10. [Client Directory](#10-client-directory)
11. [Survey Forms & Responses](#11-survey-forms--responses)
12. [Reports & Analytics](#12-reports--analytics)
13. [Audit Logs](#13-audit-logs)
14. [Notifications](#14-notifications)
15. [Overdue Referrals](#15-overdue-referrals)
16. [Profile & Account](#16-profile--account)
17. [RBAC & Permission Enforcement](#17-rbac--permission-enforcement)
18. [Edge Cases & Negative Testing](#18-edge-cases--negative-testing)

---

## 1. Authentication & Login

### 1.1 Login Flow

| ID | Test Case | Steps | Expected Result | Role |
|---|---|---|---|---|
| AUTH-001 | Successful login with email + password | 1. Navigate to `/login` 2. Enter valid email and password 3. Click "Log in" | Redirected to OTP verification step | Both |
| AUTH-002 | Failed login with wrong password | 1. Enter valid email + wrong password 2. Click "Log in" | Error message displayed; no OTP step shown | Both |
| AUTH-003 | OTP verification — valid code | 1. Complete login step 2. Enter 6-digit OTP from email | Redirected to dashboard | Both |
| AUTH-004 | OTP verification — invalid code | 1. Enter wrong OTP code | Error message; retry allowed | Both |
| AUTH-005 | OTP resend | 1. Request OTP resend 2. Wait for new email | New OTP sent; old code invalidated | Both |
| AUTH-006 | TOTP verification (if enrolled) | 1. Complete OTP step 2. Enter TOTP code from authenticator app | Redirected to dashboard | Both |
| AUTH-007 | TOTP verification — invalid code | 1. Enter wrong TOTP code | Error message; retry allowed | Both |
| AUTH-008 | Recovery code usage | 1. Enter recovery code instead of TOTP | Login succeeds; recovery code marked used | Both |
| AUTH-009 | Recovery code — already used | 1. Enter a recovery code that was already used | Error message; code rejected | Both |
| AUTH-010 | CAPTCHA enforcement | 1. Attempt login without completing CAPTCHA | Request blocked by VerifyTurnstile middleware | Both |
| AUTH-011 | Rate limiting — login attempts | 1. Submit 5+ failed login attempts within 1 minute | Rate limit triggered; temporary lockout | Both |
| AUTH-012 | Rate limiting — OTP attempts | 1. Submit 3+ wrong OTP codes within a session | Rate limit triggered; temp lockout | Both |
| AUTH-013 | Session timeout | 1. Log in 2. Idle for 120+ minutes 3. Attempt action | Redirected to login; session expired | Both |
| AUTH-014 | Logout | 1. Click logout | Session destroyed; audit log entry created; redirected to login | Both |
| AUTH-015 | Password reset flow | 1. Click "Forgot password" 2. Enter email 3. Click reset link 4. Enter new password | Password updated; can login with new password | Both |

---

## 2. Dashboard

### 2.1 Case Manager Dashboard

| ID | Test Case | Steps | Expected Result |
|---|---|---|---|
| DASH-CM-001 | Dashboard loads with stats | 1. Log in as CASE_MANAGER 2. Navigate to `/dashboard` | Dashboard displays: active cases, clients served (OFW/NOK split), pending referrals, completed referrals |
| DASH-CM-002 | Work queue triage strip | 1. View dashboard | Triage strip shows: aging open cases, pending referrals, returned referrals, draft cases, cases without referrals |
| DASH-CM-003 | Quick action links work | 1. Click "New case" → navigates to `/cases/create` 2. Click "Cases" → navigates to `/cases` 3. Click "Referrals" → navigates to `/referrals` | Each link navigates to correct page |
| DASH-CM-004 | Recent case activity section | 1. View dashboard | Recent case activity feed displays with timestamps |
| DASH-CM-005 | Referral status donut chart | 1. View dashboard | Donut chart renders with correct status distribution |
| DASH-CM-006 | Cases per month bar chart | 1. View dashboard | Bar chart displays case counts by month |
| DASH-CM-007 | Agency load bars | 1. View dashboard | Horizontal bars show agency workload distribution |
| DASH-CM-008 | Intake queue count badge | 1. Log in as CASE_MANAGER 2. Check sidebar/nav | `intake_queue_count` badge visible with correct count |

### 2.2 Agency Focal Dashboard

| ID | Test Case | Steps | Expected Result |
|---|---|---|---|
| DASH-AG-001 | Dashboard loads with stats | 1. Log in as AGENCY 2. Navigate to `/dashboard` | Dashboard displays: pending, processing, for compliance, completed counts |
| DASH-AG-002 | Work queue triage strip | 1. View dashboard | Triage strip shows: new referrals (<2 days), pending, for compliance, processing, overdue (>5 days), returned |
| DASH-AG-003 | Priority referrals list | 1. View dashboard | Top 8 priority referrals shown, sorted by severity score |
| DASH-AG-004 | Recent activity feed | 1. View dashboard | Last 10 audit logs scoped to agency's referrals |
| DASH-AG-005 | Referral aging bands | 1. View dashboard | Aging bands displayed: 0-2d, 3-5d, 6-10d, 11+ |
| DASH-AG-006 | Service demand section | 1. View dashboard | Top 6 services by active count with completion rate |
| DASH-AG-007 | Client feedback pulse | 1. View dashboard | Response rate, rating, SERVQUAL metrics shown |
| DASH-AG-008 | Data scoping — no other agency data | 1. Log in as Agency A 2. View dashboard | Only Agency A referrals/data shown; no Agency B data visible | 

---

## 3. Case Management — Case Manager

### 3.1 Case Creation (3-Step Wizard)

| ID | Test Case | Steps | Expected Result |
|---|---|---|---|
| CASE-001 | Navigate to case creation | 1. Log in as CASE_MANAGER 2. Click "New case" or go to `/cases/create` | 3-step wizard loads with Step 1 (Client Profile) |
| CASE-002 | Step 1 — Search existing client | 1. In Step 1, search by client name 2. Select matching client | Client fields auto-populated with existing client data |
| CASE-003 | Step 1 — Enter new client details | 1. Fill in: first name, last name, DOB, sex 2. Fill contact info 3. Select address via PSGC dropdowns | All fields accepted; address cascade works (region → province → city → barangay) |
| CASE-004 | Step 1 — Client type selection | 1. Select OFW or NEXT_OF_KIN | Selection recorded; relevant sections shown |
| CASE-005 | Step 1 — Employment section (OFW) | 1. Select client type OFW 2. Fill employer, position, country, dates | Employment fields accepted |
| CASE-006 | Step 1 — Next-of-kin entry | 1. Add NOK: name, relationship, contact, address | NOK added to list; can add multiple |
| CASE-007 | Step 1 — Vulnerability indicators | 1. Select PWD, Senior Citizen, Solo Parent, Indigenous Person (multi-select) | Indicators saved as comma-separated values |
| CASE-008 | Step 2 — Case setup | 1. Select client type (OFW/NOK) 2. Select categories (multi-select) 3. Select case issue | All selections recorded |
| CASE-009 | Step 2 — Category validation | 1. Try to proceed without selecting any category | Validation error; at least 1 category required on publish |
| CASE-010 | Step 3 — Case narrative | 1. Enter summary/description (max 5000 chars) 2. Check data privacy consent checkbox | Narrative accepted; consent recorded |
| CASE-011 | Step 3 — Submit as draft | 1. Check "Save as draft" 2. Click submit | Case saved as DRAFT; redirect to draft list or case detail |
| CASE-012 | Step 3 — Publish immediately | 1. Uncheck "Save as draft" 2. Ensure all required fields filled 3. Click submit | Case published as OPEN; client record created; OFW notified |
| CASE-013 | Auto-save draft | 1. Start creating case 2. Fill partial data 3. Navigate away 4. Return to drafts | Draft preserved with partial data in `draft_client_data` |

### 3.2 Case Listing & Filtering

| ID | Test Case | Steps | Expected Result |
|---|---|---|---|
| CASE-014 | Case list loads | 1. Navigate to `/cases` | Paginated table of all cases displayed |
| CASE-015 | Filter by status | 1. Select status filter (OPEN, CLOSED, DRAFT, ARCHIVED) | List filters to matching cases |
| CASE-016 | Filter by category | 1. Select category filter | List filters to cases with matching category |
| CASE-017 | Filter by case manager | 1. Select case manager filter | List filters to cases assigned to that manager |
| CASE-018 | Search by case number | 1. Enter case number in search | Matching case displayed |
| CASE-019 | Search by client name | 1. Enter client name in search | Cases with matching client displayed |
| CASE-020 | Search by tracker number | 1. Enter tracker number | Matching case displayed |
| CASE-021 | Sort by columns | 1. Click column headers (case number, client, status, date) | List sorts by selected column |
| CASE-022 | Pagination | 1. Navigate through pages | Correct page displayed; total count accurate |
| CASE-023 | Export to Excel | 1. Click "Export Excel" | Background job triggered; download link provided |

### 3.3 Case Detail (Show)

| ID | Test Case | Steps | Expected Result |
|---|---|---|---|
| CASE-024 | Case detail page loads | 1. Click on a case from the list | Case detail page shows: client info, case metadata, timeline, referrals |
| CASE-025 | Case timeline | 1. View case detail | Timeline shows all events: creation, updates, referrals, milestones, status changes |
| CASE-026 | Edit case details | 1. Click edit 2. Modify fields 3. Save | Changes saved; audit log entry created |
| CASE-027 | Toggle status — close case | 1. On OPEN case with no active referrals 2. Click "Close" | Status changes to CLOSED; `closed_at` timestamp set |
| CASE-028 | Toggle status — close blocked | 1. On OPEN case WITH active referrals 2. Attempt to close | Error: cannot close case with active referrals |
| CASE-029 | Toggle status — reopen case | 1. On CLOSED case 2. Click "Reopen" | Status changes back to OPEN |
| CASE-030 | Archive case | 1. On CLOSED case 2. Click "Archive" | Status changes to ARCHIVED |
| CASE-031 | Archive — must be CLOSED first | 1. On OPEN case 2. Attempt to archive | Error: must close case before archiving |
| CASE-032 | Unarchive case | 1. On ARCHIVED case 2. Click "Unarchive" | Status restored to previous state |
| CASE-033 | Delete archived case | 1. On ARCHIVED case 2. Click "Delete" 3. Enter reason (min 10 chars) 4. Confirm | Case moved to trash; soft-deleted |
| CASE-034 | Restore from trash | 1. Navigate to `/cases/trash` 2. Click "Restore" on a case | Case restored to ARCHIVED state |
| CASE-035 | Case audit log | 1. View case detail 2. Open audit log | Full audit trail displayed for the case |

### 3.4 Draft Management

| ID | Test Case | Steps | Expected Result |
|---|---|---|---|
| CASE-036 | List drafts | 1. Navigate to `/cases/drafts` | List of own draft cases displayed |
| CASE-037 | Edit draft | 1. Click "Edit" on a draft 2. Modify fields 3. Save | Draft updated with new data |
| CASE-038 | Publish draft | 1. On draft with categories assigned 2. Click "Publish" | Draft published as OPEN; client record created |
| CASE-039 | Delete draft | 1. Click "Delete" on a draft 2. Confirm | Draft removed from list |
| CASE-040 | Publish draft without categories | 1. Draft with no categories 2. Attempt publish | Error: at least one category required |

### 3.5 Trash Management

| ID | Test Case | Steps | Expected Result |
|---|---|---|---|
| CASE-041 | View trash | 1. Navigate to `/cases/trash` | Soft-deleted cases listed |
| CASE-042 | Restore from trash | 1. Click "Restore" on a case | Case restored to ARCHIVED state |
| CASE-043 | Empty trash | 1. Select multiple cases 2. Confirm permanent deletion | Cases permanently removed (if feature exists) |

---

## 4. Intake Queue — Self-Filed Cases

| ID | Test Case | Steps | Expected Result |
|---|---|---|---|
| INTAKE-001 | View intake queue | 1. Log in as CASE_MANAGER 2. Navigate to `/cases/intake-queue` | List of self-filed intakes awaiting review |
| INTAKE-002 | Review intake — accept | 1. Click "Review" on an intake 2. Review client data 3. Make corrections if needed 4. Click "Accept" | Case published as OPEN; self-filed client record created/updated; OFW notified; assigned to current case manager |
| INTAKE-003 | Review intake — reject | 1. Click "Review" on an intake 2. Click "Reject" 3. Enter deletion reason (min 10 chars) 4. Confirm | Case soft-deleted; `IntakeRejectedMail` sent to OFW |
| INTAKE-004 | Review intake — edit client data | 1. Open intake review page 2. Modify client fields 3. Accept | Changes saved; client record reflects corrections |
| INTAKE-005 | Intake queue count badge | 1. Check sidebar/nav | Intake queue count matches actual queue size |
| INTAKE-006 | Rejection reason validation | 1. Enter rejection reason < 10 characters | Validation error; minimum 10 characters required |
| INTAKE-007 | Intake queue — only self-filed cases | 1. View intake queue | Only cases with `source=SELF_FILED` and `user_id=null` displayed |

---

## 5. Referral Management

### 5.1 Referral Creation

| ID | Test Case | Steps | Expected Result |
|---|---|---|---|
| REF-001 | Navigate to referral creation | 1. Log in as CASE_MANAGER 2. Go to `/referrals/create` | Referral creation form loads |
| REF-002 | Select case | 1. Search by case number, tracker number, or client name 2. Select matching case | Case selected; case details shown |
| REF-003 | Select receiving agency | 1. Open agency dropdown 2. Select agency | Agency selected; services listed |
| REF-004 | Select services | 1. Select one or more services from agency's catalog | Services selected |
| REF-005 | Add notes | 1. Enter optional notes | Notes saved with referral |
| REF-006 | Upload documents | 1. Attach documents to referral | Documents validated and uploaded |
| REF-007 | Submit referral | 1. Fill all required fields 2. Click "Submit" | Referral created as PENDING; notifications sent to agency users + OFW |
| REF-008 | Validation — no case selected | 1. Submit without selecting case | Validation error |
| REF-009 | Validation — no agency selected | 1. Submit without selecting agency | Validation error |
| REF-010 | Validation — no services selected | 1. Submit without selecting services | Validation error (if services required) |

### 5.2 Referral Listing

| ID | Test Case | Steps | Expected Result |
|---|---|---|---|
| REF-011 | Referral list loads | 1. Navigate to `/referrals` | Paginated referral list displayed |
| REF-012 | Filter by status | 1. Select status filter (PENDING, PROCESSING, etc.) | List filters accordingly |
| REF-013 | Filter by agency | 1. Select agency filter | List filters to matching agency |
| REF-014 | Filter by category | 1. Select category filter | List filters to matching category |
| REF-015 | Search referrals | 1. Enter search term (case#, client name, agency) | Matching referrals displayed |
| REF-016 | Sort by columns | 1. Click column headers | List sorts correctly |
| REF-017 | Export referrals to Excel | 1. Click "Export Excel" | Background job triggered |

### 5.3 Referral Detail

| ID | Test Case | Steps | Expected Result |
|---|---|---|---|
| REF-018 | Referral detail loads | 1. Click on a referral | Full referral detail displayed: info card, case info, client details, status, timeline, comments, attachments |
| REF-019 | Referral info card | 1. View referral detail | Shows: agency, status, case#, tracking ID, dates, services, notes |
| REF-020 | Case information card | 1. View referral detail | Shows: case status, tracker#, category, issue |
| REF-021 | Client details card | 1. View referral detail | Shows: avatar, name, type, DOB, age, vulnerability, email, contact, address, employment, NOK |
| REF-022 | Add milestone (CASE_MANAGER) | 1. Click "Add Milestone" 2. Enter title, description, requirements 3. Submit | Milestone added to timeline; audit log created |
| REF-023 | Add comment | 1. Enter comment text (max 5000 chars) 2. Select visibility (INTERNAL or AGY_ONLY) 3. Submit | Comment posted; visible to appropriate roles |
| REF-024 | Reply to comment | 1. Click "Reply" on a comment 2. Enter reply 3. Submit | Threaded reply posted under parent comment |
| REF-025 | Upload attachment | 1. Click "Upload" 2. Select file 3. Add document label 4. Submit | Attachment uploaded; version 1 created |
| REF-026 | Replace attachment | 1. Click "Replace" on an attachment 2. Upload new file | New version created; version history preserved |
| REF-027 | Download attachment | 1. Click "Download" on attachment | Temporary download URL generated (24h expiry) |
| REF-028 | View attachment versions | 1. Click "Version History" on attachment | Version list displayed with timestamps |
| REF-029 | Export referral to Excel | 1. Click export option | Background job triggered |

---

## 6. Agency Focal — Referral Actions

### 6.1 Accept / Reject

| ID | Test Case | Steps | Expected Result |
|---|---|---|---|
| AG-REF-001 | Accept referral | 1. Log in as AGENCY 2. Open PENDING referral 3. Click "Accept" | Status changes to PROCESSING; `decision=ACCEPT`; `first_action_at` set; audit log created |
| AG-REF-002 | Reject referral | 1. Open PENDING referral 2. Click "Reject" 3. Enter decision comment 4. Confirm | Status changes to REJECTED; `decision=REJECT`; comment recorded |
| AG-REF-003 | Reject — comment required | 1. Attempt to reject without entering comment | Validation error; comment required |
| AG-REF-004 | Cannot accept own referral (duplicate) | 1. Attempt to accept a referral already in PROCESSING | Error; cannot accept non-PENDING referral |

### 6.2 Status Updates

| ID | Test Case | Steps | Expected Result |
|---|---|---|---|
| AG-REF-005 | Update status — PROCESSING → FOR_COMPLIANCE | 1. Open PROCESSING referral 2. Update status to FOR_COMPLIANCE | Status updated; milestone-like event recorded |
| AG-REF-006 | Update status — FOR_COMPLIANCE → COMPLETED | 1. Open FOR_COMPLIANCE referral 2. Update status to COMPLETED | Status changes to COMPLETED; `ReferralCompleted` event triggers; feedback invitation sent to client |
| AG-REF-007 | Status flow validation | 1. Attempt to skip from PENDING directly to COMPLETED | Error; must go through PROCESSING first |

### 6.3 Services on Referral (Agency-Only)

| ID | Test Case | Steps | Expected Result |
|---|---|---|---|
| AG-REF-008 | Add service to referral | 1. Open referral 2. Click "Add Service" 3. Select from agency's catalog 4. Submit | Service added; shown as badge |
| AG-REF-009 | Remove service from referral | 1. Click X on a service badge 2. Confirm | Service removed from referral |
| AG-REF-010 | Cannot add service to COMPLETED referral | 1. Open COMPLETED referral 2. Attempt to add service | Error; cannot modify services on completed referral |

### 6.4 Milestones (Agency)

| ID | Test Case | Steps | Expected Result |
|---|---|---|---|
| AG-REF-011 | Add milestone — PROCESSING status | 1. Open PROCESSING referral 2. Add milestone with title + description | Milestone added; audit log + notifications sent |
| AG-REF-012 | Add milestone — PENDING status blocked | 1. Open PENDING referral 2. Attempt to add milestone | Error; must accept referral first |
| AG-REF-013 | Add milestone — COMPLETED status blocked | 1. Open COMPLETED referral 2. Attempt to add milestone | Error; cannot add to completed referral |

### 6.5 Comments & Attachments (Agency)

| ID | Test Case | Steps | Expected Result |
|---|---|---|---|
| AG-REF-014 | Add comment | 1. Enter comment 2. Select visibility 3. Submit | Comment posted |
| AG-REF-015 | Reply to comment | 1. Reply to existing comment | Threaded reply posted |
| AG-REF-016 | Upload attachment | 1. Upload file with label | Attachment uploaded |
| AG-REF-017 | Replace attachment | 1. Replace existing attachment | New version created |
| AG-REF-018 | Delete own attachment | 1. Delete an attachment uploaded by own user | Attachment removed (soft-delete) |
| AG-REF-019 | Cannot delete other's attachment | 1. Attempt to delete attachment uploaded by another user | Error; only uploader can delete |

---

## 7. Client Requests (Agency-Only)

### 7.1 Create Client Request

| ID | Test Case | Steps | Expected Result |
|---|---|---|---|
| CR-001 | Create document request | 1. Open referral 2. Create client request 3. Type: DOCUMENT_REQUEST 4. Add title, instructions, checklist items 5. Submit | Request created; client notified |
| CR-002 | Create question request | 1. Type: QUESTION 2. Add title and instructions 3. Submit | Request created |
| CR-003 | Create information update request | 1. Type: INFORMATION_UPDATE 2. Add title and instructions 3. Submit | Request created |
| CR-004 | Validation — missing required fields | 1. Submit without type/title/instructions | Validation error |
| CR-005 | Set due date | 1. Set optional due_at date | Due date recorded |
| CR-006 | Document request — checklist items required | 1. Create DOCUMENT_REQUEST without checklist items | Validation error; checklist required |

### 7.2 Manage Client Request

| ID | Test Case | Steps | Expected Result |
|---|---|---|---|
| CR-007 | Send message to client | 1. Open client request 2. Send message | Message sent via access link |
| CR-008 | Complete request | 1. Click "Complete" on active request | Status changes to COMPLETED |
| CR-009 | Cancel request | 1. Click "Cancel" on active request | Status changes to CANCELLED |
| CR-010 | Reopen request | 1. Click "Reopen" on completed/cancelled request | Status reverts to active |
| CR-011 | Issue access link | 1. Click "Issue Access Link" | Magic link generated; email sent to client |
| CR-012 | Reissue access link | 1. Previous link expired/revoked 2. Click "Reissue" | New link generated; old one invalidated |
| CR-013 | Revoke access link | 1. Click "Revoke" on active link | Link invalidated; client can no longer access |

### 7.3 Client Request Authorization

| ID | Test Case | Steps | Expected Result |
|---|---|---|---|
| CR-014 | Only owning agency can manage | 1. Log in as Agency B 2. Attempt to manage Agency A's client request | 403 Forbidden |
| CR-015 | Case manager can view (not manage) | 1. Log in as CASE_MANAGER 2. View client request history | Can view requests; cannot create/complete/cancel |

---

## 8. Services Management (Agency-Only)

| ID | Test Case | Steps | Expected Result |
|---|---|---|---|
| SVC-001 | View service list | 1. Log in as AGENCY 2. Navigate to `/services` | List of own agency's services displayed |
| SVC-002 | Create service | 1. Click "Add Service" 2. Enter name, description, requirements 3. Submit | Service created; visible in list |
| SVC-003 | Create service — validation | 1. Submit without name or description | Validation error |
| SVC-004 | Edit service | 1. Click "Edit" on a service 2. Modify fields 3. Save | Service updated |
| SVC-005 | Delete service | 1. Click "Delete" on a service 2. Confirm | Service removed |
| SVC-006 | Service search | 1. Enter search term in service list | Matching services filtered |
| SVC-007 | Service KPIs | 1. View service list | Stats show: Total Services, Active Services, Total Requirements |
| SVC-008 | Cannot manage other agency's services | 1. Log in as Agency A 2. Attempt to access Agency B's service | 403 Forbidden |

---

## 9. Case Documents

| ID | Test Case | Steps | Expected Result |
|---|---|---|---|
| DOC-001 | View case documents | 1. Open case detail 2. Navigate to documents section | List of case documents displayed |
| DOC-002 | Upload document (CASE_MANAGER only) | 1. Click "Upload" 2. Select file 3. Submit | Document uploaded; listed in documents |
| DOC-003 | Download document | 1. Click "Download" on a document | Temporary download URL generated (24h) |
| DOC-004 | Delete document (CASE_MANAGER only) | 1. Click "Delete" on a document 2. Confirm | Document soft-deleted |
| DOC-005 | Agency — view only | 1. Log in as AGENCY 2. Open case with active referral 3. View documents | Can view/download documents |
| DOC-006 | Agency — cannot upload | 1. Log in as AGENCY 2. Attempt to upload document | Upload button not visible or 403 error |
| DOC-007 | Agency — cannot delete | 1. Log in as AGENCY 2. Attempt to delete document | Delete button not visible or 403 error |

---

## 10. Client Directory

| ID | Test Case | Steps | Expected Result |
|---|---|---|---|
| CLI-001 | View client list (CASE_MANAGER) | 1. Navigate to `/clients` | All clients listed |
| CLI-002 | View client list (AGENCY) | 1. Log in as AGENCY 2. Navigate to `/clients` | Only clients linked through own referrals listed |
| CLI-003 | Client profile | 1. Click on a client | Full profile: name, DOB, sex, contact, address, employment, NOK, case history |
| CLI-004 | Upload client avatar | 1. Click "Upload Avatar" on client profile 2. Select image | Avatar uploaded and displayed |
| CLI-005 | Delete client avatar | 1. Click "Remove Avatar" | Avatar removed; default shown |
| CLI-006 | Client audit trail | 1. View client profile 2. Open audit/timeline | Timeline of all client-related events displayed |
| CLI-007 | Export clients to Excel | 1. Click "Export" on client list | Background job triggered |

---

## 11. Survey Forms & Responses

### 11.1 Survey Forms (Agency-Only)

| ID | Test Case | Steps | Expected Result |
|---|---|---|---|
| SURV-001 | View survey form list | 1. Log in as AGENCY 2. Navigate to `/survey-forms` | List of own agency's survey forms |
| SURV-002 | Create survey form | 1. Click "Create" 2. Enter title, description 3. Add questions 4. Save | Form created; listed as Inactive |
| SURV-003 | Add question — likert | 1. Add question type: likert 2. Enter label | Likert question added |
| SURV-004 | Add question — text | 1. Add question type: text | Text question added |
| SURV-005 | Add question — radio | 1. Add question type: radio 2. Enter options | Radio question with options added |
| SURV-006 | Add question — checkbox | 1. Add question type: checkbox 2. Enter options | Checkbox question with options added |
| SURV-007 | Add question — rating | 1. Add question type: rating | Rating question added |
| SURV-008 | Reorder questions | 1. Use move up/down controls | Questions reordered correctly |
| SURV-009 | Remove question | 1. Click remove on a question | Question removed from form |
| SURV-010 | Activate form | 1. Click "Activate" on a form | Form set as active; only one active form at a time |
| SURV-011 | Deactivate form | 1. Deactivate current active form | Form set as inactive |
| SURV-012 | Edit form | 1. Click "Edit" 2. Modify questions 3. Save | Form updated |
| SURV-013 | Delete form | 1. Click "Delete" 2. Confirm | Form removed |

### 11.2 Survey Responses

| ID | Test Case | Steps | Expected Result |
|---|---|---|---|
| SURV-014 | View response list (AGENCY) | 1. Navigate to `/surveys` | Only own agency's responses listed |
| SURV-015 | View response list (CASE_MANAGER) | 1. Navigate to `/surveys` | All responses listed |
| SURV-016 | View response detail | 1. Click on a response | Full response: client info, service, answers per question |
| SURV-017 | Stats display | 1. View response list | Shows: Total Sent, Total Submitted, Response Rate |

---

## 12. Reports & Analytics

| ID | Test Case | Steps | Expected Result |
|---|---|---|---|
| RPT-001 | Reports page loads | 1. Navigate to `/reports` | Reports dashboard with charts displayed |
| RPT-002 | KPIs section | 1. View reports | KPIs shown: total referrals, active, completed, etc. |
| RPT-003 | Referral status distribution chart | 1. View reports | Pie/donut chart with status breakdown |
| RPT-004 | Cases over time chart | 1. View reports | Line/bar chart showing case trends |
| RPT-005 | Gender distribution chart | 1. View reports | Gender breakdown displayed |
| RPT-006 | Age group distribution chart | 1. View reports | Age group breakdown displayed |
| RPT-007 | Agency scorecard | 1. View reports | Agency performance metrics shown |
| RPT-008 | Geographic distribution | 1. View reports | Map or table with geographic data |
| RPT-009 | Export report — PDF | 1. Click "Export PDF" | Background job triggered |
| RPT-010 | Export report — Excel | 1. Click "Export Excel" | Background job triggered |
| RPT-011 | Agency scoping | 1. Log in as AGENCY 2. View reports | All charts scoped to own agency data only |

---

## 13. Audit Logs

| ID | Test Case | Steps | Expected Result |
|---|---|---|---|
| AUD-001 | View audit logs (CASE_MANAGER) | 1. Navigate to `/audit-logs` | Logs for accessible cases displayed |
| AUD-002 | View audit logs (AGENCY) | 1. Navigate to `/audit-logs` | Logs scoped to own referrals + parent cases only |
| AUD-003 | Filter audit logs | 1. Apply filters (date range, action type, user) | Logs filtered accordingly |
| AUD-004 | Case-specific audit log | 1. Open case detail 2. View audit log | Only events for that case displayed |
| AUD-005 | Referral-specific audit log | 1. Open referral detail 2. View audit log | Only events for that referral displayed |
| AUD-006 | Export audit logs (ADMIN only) | 1. Log in as ADMIN 2. Click "Export" | Audit logs exported |
| AUD-007 | Agency — cannot export | 1. Log in as AGENCY 2. Attempt export | Export button hidden or 403 |
| AUD-008 | Audit log integrity | 1. View audit logs | Each entry has `prev_hash` linking to predecessor; chain intact |

---

## 14. Notifications

| ID | Test Case | Steps | Expected Result |
|---|---|---|---|
| NOTIF-001 | View notifications | 1. Navigate to `/notifications` | List of notifications displayed |
| NOTIF-002 | Unread count badge | 1. Check navigation | Unread count shown as badge |
| NOTIF-003 | Mark notification as read | 1. Click on a notification or "Mark read" | Notification marked as read; badge count decreases |
| NOTIF-004 | Mark all as read | 1. Click "Mark all read" | All notifications marked as read |
| NOTIF-005 | Notification on case creation | 1. Create a new case | OFW receives notification |
| NOTIF-006 | Notification on referral creation | 1. Create a referral | Agency users + OFW receive notifications |
| NOTIF-007 | Notification on referral status change | 1. Agency accepts/rejects/referrals | Case manager + OFW notified |

---

## 15. Overdue Referrals

| ID | Test Case | Steps | Expected Result |
|---|---|---|---|
| OVER-001 | View overdue referrals (CASE_MANAGER) | 1. Navigate to `/overdue-referrals` | Dashboard with overdue referrals displayed |
| OVER-002 | View overdue referrals (AGENCY) | 1. Navigate to `/overdue-referrals` | Only own agency's overdue referrals shown |
| OVER-003 | Overdue threshold | 1. View overdue list | Only referrals older than `referral_overdue_days` (default 7) shown |
| OVER-004 | Priority scoring | 1. View overdue list | Referrals sorted by severity: mild/moderate/severe |
| OVER-005 | Filter by status | 1. Filter by PENDING/PROCESSING/FOR_COMPLIANCE | List filters accordingly |
| OVER-006 | Send reminders (CASE_MANAGER only) | 1. Click "Send Reminders" | Reminder emails sent to overdue agencies |
| OVER-007 | Agency — cannot send reminders | 1. Log in as AGENCY 2. View overdue referrals | "Send Reminders" button hidden or 403 |

---

## 16. Profile & Account

| ID | Test Case | Steps | Expected Result |
|---|---|---|---|
| PROF-001 | View profile | 1. Navigate to `/profile` | Profile page with user info displayed |
| PROF-002 | Update profile | 1. Edit name/email 2. Save | Profile updated; audit log created |
| PROF-003 | Change password | 1. Enter current password 2. Enter new password 3. Confirm 4. Save | Password updated; must re-login |
| PROF-004 | MFA enrollment | 1. Navigate to MFA setup 2. Scan QR code 3. Enter TOTP code 4. Save recovery codes | MFA enrolled; TOTP required on next login |
| PROF-005 | MFA disable | 1. Navigate to MFA settings 2. Disable MFA | MFA removed; no TOTP required on login |
| PROF-006 | Recovery codes — view | 1. Navigate to MFA recovery codes | 8 recovery codes displayed (only shown once on enrollment) |
| PROF-007 | Email change flow | 1. Request email change 2. Verify OTP to old email 3. Verify OTP to new email | Email updated after both verifications |
| PROF-008 | Delete account | 1. Click "Delete Account" 2. Enter password 3. Confirm | Account soft-deleted; audit log created |

---

## 17. RBAC & Permission Enforcement

### 17.1 Case Manager Permissions

| ID | Test Case | Steps | Expected Result |
|---|---|---|---|
| RBAC-CM-001 | Can create cases | 1. Log in as CASE_MANAGER 2. Navigate to `/cases/create` | Form loads; can create case |
| RBAC-CM-002 | Can create referrals | 1. Navigate to `/referrals/create` | Form loads; can create referral |
| RBAC-CM-003 | Can view all cases | 1. Navigate to `/cases` | All cases in system displayed |
| RBAC-CM-004 | Can view all referrals | 1. Navigate to `/referrals` | All referrals displayed |
| RBAC-CM-005 | Cannot access admin areas | 1. Try to navigate to `/admin/*` routes | 403 Forbidden |
| RBAC-CM-006 | Cannot manage services | 1. Try to navigate to `/services` | 403 Forbidden |
| RBAC-CM-007 | Cannot create survey forms | 1. Try to navigate to `/survey-forms/create` | 403 Forbidden |
| RBAC-CM-008 | Cannot accept referrals | 1. Open a PENDING referral 2. Look for "Accept" button | Accept button not visible (only agency can accept) |

### 17.2 Agency Focal Permissions

| ID | Test Case | Steps | Expected Result |
|---|---|---|---|
| RBAC-AG-001 | Cannot create cases | 1. Log in as AGENCY 2. Try to navigate to `/cases/create` | 403 Forbidden |
| RBAC-AG-002 | Cannot view all cases | 1. Navigate to `/cases` | Only cases with own active referrals shown (or 403) |
| RBAC-AG-003 | Can view own referrals only | 1. Navigate to `/referrals` | Only own agency's referrals displayed |
| RBAC-AG-004 | Can accept/reject own referrals | 1. Open PENDING referral from own agency | Accept/Reject buttons visible |
| RBAC-AG-005 | Cannot accept other agency's referral | 1. Attempt to access referral from different agency | 403 Forbidden |
| RBAC-AG-006 | Can manage own services | 1. Navigate to `/services` | Own agency's services listed |
| RBAC-AG-007 | Cannot access admin areas | 1. Try to navigate to `/admin/*` routes | 403 Forbidden |
| RBAC-AG-008 | Cannot view all audit logs | 1. Navigate to `/audit-logs` | Only own referral/case activity shown |
| RBAC-AG-009 | Cannot export audit logs | 1. Look for export button | Button hidden or 403 |
| RBAC-AG-010 | Cannot send overdue reminders | 1. Navigate to `/overdue-referrals` | "Send Reminders" button hidden or 403 |

### 17.3 Lane Isolation

| ID | Test Case | Steps | Expected Result |
|---|---|---|---|
| RBAC-ISO-001 | Agency A cannot see Agency B referrals | 1. Log in as Agency A 2. Navigate to `/referrals` | Only Agency A referrals shown |
| RBAC-ISO-002 | Agency A cannot access Agency B's referral detail | 1. Manually construct URL for Agency B's referral | 403 Forbidden |
| RBAC-ISO-003 | Agency A cannot manage Agency B's client request | 1. Attempt to complete/cancel Agency B's client request | 403 Forbidden |
| RBAC-ISO-004 | Agency A cannot see Agency B's survey forms | 1. Navigate to `/survey-forms` | Only Agency A forms shown |
| RBAC-ISO-005 | RLS enforcement at DB level | 1. Attempt direct DB query bypassing app | PostgreSQL RLS policies block cross-agency access |

---

## 18. Edge Cases & Negative Testing

### 18.1 Case Edge Cases

| ID | Test Case | Steps | Expected Result |
|---|---|---|---|
| EDGE-001 | Create case with all optional fields null | 1. Create case leaving optional fields empty | Case created with nulls where allowed |
| EDGE-002 | Create case with max-length narrative | 1. Enter 5000 characters in summary | Accepted (at limit) |
| EDGE-003 | Create case with narrative > 5000 chars | 1. Enter 5001 characters in summary | Validation error; max 5000 |
| EDGE-004 | Concurrent edits on same case | 1. Open same case in two tabs 2. Edit in both 3. Save both | Last save wins; no data corruption |
| EDGE-005 | Create case while offline | 1. Disconnect network 2. Attempt to submit | Error message; data preserved locally (if auto-save) |
| EDGE-006 | PSGC address cascade — incomplete | 1. Select region but skip province | Validation error; address incomplete |

### 18.2 Referral Edge Cases

| ID | Test Case | Steps | Expected Result |
|---|---|---|---|
| EDGE-007 | Create referral for CLOSED case | 1. Attempt to create referral for CLOSED case | Error; case must be OPEN |
| EDGE-008 | Create duplicate referral | 1. Create referral to same agency for same case | Allowed (multiple referrals per case) or error (depending on business rule) |
| EDGE-009 | Referral with large file upload | 1. Upload file near size limit | File accepted if within limits |
| EDGE-010 | Referral with oversized file | 1. Upload file exceeding size limit | Validation error; file too large |
| EDGE-011 | Referral with invalid file type | 1. Upload executable or disallowed file type | Validation error; invalid MIME type |

### 18.3 Timing & Concurrency

| ID | Test Case | Steps | Expected Result |
|---|---|---|---|
| EDGE-012 | Simultaneous status update | 1. Two agency users accept same referral simultaneously | Only one succeeds; other gets error |
| EDGE-013 | Session expiry during form submission | 1. Fill form 2. Wait for session to expire 3. Submit | Redirected to login; form data may be lost |
| EDGE-014 | Browser back button after submission | 1. Submit form 2. Click browser back | No duplicate submission; correct page shown |

### 18.4 Data Validation

| ID | Test Case | Steps | Expected Result |
|---|---|---|---|
| EDGE-015 | SQL injection in search fields | 1. Enter `' OR 1=1 --` in search | Input sanitized; no data leak |
| EDGE-016 | XSS in comment field | 1. Enter `<script>alert('xss')</script>` in comment | Script not executed; displayed as text |
| EDGE-017 | File upload with double extension | 1. Upload `file.php.jpg` | MIME validation catches it; rejected if not allowed |
| EDGE-018 | Unicode in client name | 1. Enter client name with accented characters (e.g., "José") | Name accepted and stored correctly |
| EDGE-019 | Very long input in text fields | 1. Enter 10,000 chars in a field with no max | Either truncated or validation error |

### 18.5 State Transition Guards

| ID | Test Case | Steps | Expected Result |
|---|---|---|---|
| EDGE-020 | Archive OPEN case | 1. Attempt to archive case that is OPEN | Error; must be CLOSED first |
| EDGE-021 | Close case with active referral | 1. Attempt to close case with PROCESSING referral | Error; cannot close with active referrals |
| EDGE-022 | Publish draft without categories | 1. Attempt to publish draft with zero categories | Error; at least one category required |
| EDGE-023 | Accept already-accepted referral | 1. Attempt to accept referral already in PROCESSING | Error; cannot accept non-PENDING referral |
| EDGE-024 | Add milestone to PENDING referral | 1. Attempt to add milestone before accepting | Error; must accept first |
| EDGE-025 | Complete referral directly from PENDING | 1. Attempt to set COMPLETED from PENDING | Error; must go through PROCESSING |

---

## Appendix A: Test Environment Requirements

| Requirement | Details |
|---|---|
| Browser | Chrome 120+, Firefox 120+, Safari 17+, Edge 120+ |
| Screen resolution | 1280x720 minimum; test responsive at 768px and 375px |
| Test data | At least 2 agencies with services, 3+ cases (various statuses), 5+ referrals (various statuses) |
| Test accounts | 1 CASE_MANAGER, 2 AGENCY (different agencies), 1 ADMIN |
| Email | Access to test email accounts for OTP/notification verification |
| Timezone | Test with dates near midnight to catch date-boundary bugs |

## Appendix B: Test Execution Checklist

- [ ] All AUTH tests pass
- [ ] Both dashboards render correctly with correct data scoping
- [ ] Case creation wizard completes end-to-end (draft → publish)
- [ ] Intake queue review workflow works (accept + reject)
- [ ] Referral lifecycle completes (create → pending → accept → processing → for_compliance → completed)
- [ ] Agency can accept/reject; cannot access other agency's data
- [ ] Client requests work end-to-end (create → issue link → message → complete)
- [ ] Services CRUD works for agency; inaccessible to other roles
- [ ] Survey forms and responses work correctly
- [ ] Reports display with correct scoping
- [ ] Audit logs show correct data per role
- [ ] Notifications fire on key events
- [ ] Overdue referrals display correctly; reminders only from CM/Admin
- [ ] RBAC blocks all unauthorized access attempts
- [ ] Edge cases and negative tests pass
