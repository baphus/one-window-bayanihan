const content = `# Glossary of Terms

![Helpdesk index](/assets/helpdesk/helpdesk-index.png)

## Roles

**ADMIN** — System administrator role. Admin users manage users, agencies, services, security, active sessions, case categories, case issues, case statuses, data exports, maintenance, email logs, log viewing, and overdue referrals.

**CASE_MANAGER** — DMW user role responsible for creating and managing cases, referrals, notes, documents, and case follow-up.

**AGENCY** — Partner agency focal role. Agency users process referrals assigned to their agency and add updates or milestones as allowed.

**OFW** — Client account role. OFW users file requests through intake, track cases, and manage their own cases, notifications, and profile under /my-cases.

## Case terms

**Case File** — The main record for an assistance request. It may include OFW information, client type, address, vulnerability details, employment details, issue, summary, receiving parties, next-of-kin information, documents, notes, and referrals.

**Case Number** — Internal case identifier in the format OWB-YYYYMM-NNNNN, used by staff.

**Tracker Number** — Public tracking identifier in the format OWBAP-XXXXXXXXXX, used by clients to check progress without logging in.

**Intake** — The self-filing flow at /intake: email verification (verify-email), duplicate check (check-duplicate), submission (submit), with an optional account step (/intake/register). Filing is blocked when the email already has an active case.

**Tracking OTP** — The one-time code emailed to the registered address during tracking. The portal flow is tracker number + email → send-otp → verify-otp → track.show; delivery is email-only.

**DRAFT** — Case status for a case being prepared and not yet fully opened. Lifecycle: DRAFT → Published → OPEN → CLOSED → Archived.

**OPEN** — Case status for active work.

**CLOSED** — Case status for completed case handling.

**ARCHIVED** — Case status for retained historical records.

**my-cases** — The authenticated OFW portal (/my-cases) for viewing one's cases, notifications, agency milestones, and profile.

## Referral terms

**Referral** — A request sent from a case to another agency or service provider for action.

**PENDING** — Referral has been sent and is waiting for agency action.

**PROCESSING** — Agency has started working on the referral.

**FOR_COMPLIANCE** — Additional requirement, document, or action is needed before the referral can proceed.

**COMPLETED** — Agency action has been completed. Completion triggers a survey invitation for client feedback.

**REJECTED** — Referral was not accepted or cannot be processed as sent.

**ReferralClientRequest** — A secure client-request channel on the tracking side (/track/request) where clients and agencies exchange messages, replacements, and attachments through expiring access links.

## Admin terms

**Case Category** — Admin-managed grouping for case issues. Categories can be active or inactive and are soft-deleted when removed.

**Case Issue** — Admin-managed issue type linked to case handling and reporting.

**Case Status** — Admin-managed status reference. System statuses cannot be deleted.

**Data Export** — Admin function for generating or reviewing exports.

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
