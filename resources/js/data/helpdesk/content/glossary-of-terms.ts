const content = `# Glossary of Terms

![Helpdesk index](/assets/helpdesk/helpdesk-index.png)

## Roles

**ADMIN** — System administrator role. Admin users manage users, agencies, services, security, case categories, case issues, case statuses, maintenance, and overdue referrals.

**CASE_MANAGER** — DMW user role responsible for creating and managing cases, referrals, notes, documents, and case follow-up.

**AGENCY** — Partner agency focal role. Agency users process referrals assigned to their agency and add updates or milestones as allowed.

**OFW** — Client account role. OFW users register an account, file requests from the portal, track cases, and manage their own cases, notifications, and profile under /my-cases.

## Case terms

**Case File** — The main record for an assistance request. It may include OFW information, client type, address, vulnerability details, employment details, issue, summary, receiving parties, next-of-kin information, documents, notes, and referrals.

**Case Number** — Internal case identifier in the format OWB-YYYYMM-NNNNN, used by staff.

**Tracker Number** — Public tracking identifier in the format OWBAP-XXXXXXXXXX, used by clients to check progress without logging in.

**Intake** — The self-filing flow. An OFW creates and confirms an account, signs in, and files from the portal at /my-cases/intake. The system checks for duplicate active cases before and after submission, captures consent, and returns a filed case in PENDING_REVIEW. There is no anonymous public form; the retired /intake route no longer exists.

**Tracking OTP** — The one-time code emailed to the registered address during tracking. The portal flow is tracker number + email → send-otp → verify-otp → track.show; delivery is email-only. This is one of several places email one-time codes are used — see **One-Time Code (OTP)**.

**One-Time Code (OTP)** — A short numeric code emailed as a second factor. Used for: the OFW registration review step (/register/review), the public tracking portal, secure document-request links, and email-address changes. Not used for sign-in — staff and OFW sign-in use a password plus an authenticator MFA code.

**DRAFT** — Case status for a case being prepared and not yet submitted. Lifecycle: DRAFT → PENDING_REVIEW → OPEN → CLOSED → ARCHIVED.

**PENDING_REVIEW** — Case status for a case that has been filed and is waiting in the **Pending Reviews** queue for a Case Manager to add a category and approve or reject it. Shown to clients as **Being Reviewed**. A case can only reach OPEN by being approved from this queue.

**Pending Reviews** — The case manager queue (/cases/review-pending) holding every filed request awaiting a decision. Staff approve a case (which moves it to OPEN and assigns ownership) or reject it with a mandatory reason, which removes the draft and emails the filer.

**OPEN** — Case status for active work. Required before any referral can be created.

**CLOSED** — Case status for completed case handling. Closing is blocked while any referral is still PENDING, PROCESSING, or FOR_COMPLIANCE.

**ARCHIVED** — Case status for retained historical records. Only CLOSED cases can be archived; an archived case can be unarchived to its previous state, and is deleted to the trash with a mandatory reason before eventual permanent removal.

**my-cases** — The authenticated OFW portal (/my-cases) for viewing one's cases, notifications, agency milestones, and profile.

## Referral terms

**Referral** — A request sent from a case to another agency or service provider for action. The sending Case Manager chooses the case and the agency; the **receiving agency** chooses the service.

**PENDING** — Referral has been sent and is waiting for agency action. Every new referral starts here, with no exceptions.

**PROCESSING** — Agency has started working on the referral.

**FOR_COMPLIANCE** — Waiting on client. Additional requirement, document, or action is needed from the client before the referral can proceed.

**COMPLETED** — Agency action has been completed. Completion triggers a survey invitation for client feedback.

**REJECTED** — Referral was not accepted or cannot be processed as sent. A reason is mandatory.

**Add Service** — The control on a referral page that lets the receiving agency attach the service the referral applies for, chosen from its own list of available services. Senders do not select services.

**ReferralClientRequest** — A secure client-request channel on the tracking side (/track/request) where clients and agencies exchange messages, replacements, and attachments through expiring access links.

## Admin terms

**Case Category** — Admin-managed grouping for case issues. Categories can be active or inactive and are soft-deleted when removed.

**Case Issue** — Admin-managed issue type linked to case handling and reporting.

**Case Status** — Admin-managed status reference. System statuses cannot be deleted.

**Export Excel** — Per-page export button on Cases, Referrals, and Clients that downloads the current filtered view as a spreadsheet.

**Audit Log** — Record of significant system actions, filterable by action, module, user, date range, search, and page size. Entries are chained with SHA-256 hashes (each row stores the previous row's digest) so tampering can be detected.

**Soft Delete Flag** — Records are marked deleted with fields such as deletion flags and timestamps instead of being physically removed.

**PostgreSQL 17** — Relational database used by the deployment.

**S3-Compatible Object Storage** — File storage backend for case documents and attachments, accessed through short-lived temporary URLs issued by the StorageService.

**Temporary URL** — Time-limited storage link used for controlled file access.

## Feedback & security terms

**Survey Form** — The per-agency questionnaire clients answer after a completed referral. Each agency keeps one active form; questions use the Likert, Rating, Text, Radio, and Checkbox types.

**Survey Invitation** — The personal, expiring link (**/survey/{token}**) emailed to a client after a referral is completed, used to submit the survey without logging in. Each link can be submitted once and expires after 30 days.

**Response Rate** — Submitted surveys divided by invitations sent, shown on the Survey Responses page.

**MFA (Multi-Factor Authentication)** — An extra sign-in step using TOTP: a time-based 6-digit code from an authenticator app, enabled per user on the Profile page.

**Recovery Code** — A backup code generated when MFA is enabled, used to sign in if the authenticator device is unavailable.
`;
export default content;
