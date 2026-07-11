const content = `# Glossary of Terms

![Helpdesk index](/assets/helpdesk/helpdesk-index.png)

## Roles

**ADMIN** — System administrator role. Admin users manage users, agencies, services, security, active sessions, case categories, case issues, case statuses, data exports, maintenance, email logs, log viewing, and overdue referrals.

**CASE_MANAGER** — DMW user role responsible for creating and managing cases, referrals, notes, documents, and case follow-up.

**AGENCY** — Partner agency focal role. Agency users process referrals assigned to their agency and add updates or milestones as allowed.

## Case terms

**Case File** — The main record for an assistance request. It may include OFW information, client type, address, vulnerability details, employment details, issue, summary, receiving parties, next-of-kin information, documents, notes, and referrals.

**Case Number** — Internal case identifier used by staff.

**Tracker Number** — Public tracking identifier used by clients to check progress without logging in.

**DRAFT** — Case status for a case being prepared and not yet fully opened.

**OPEN** — Case status for active work.

**CLOSED** — Case status for completed case handling.

**ARCHIVED** — Case status for retained historical records.

## Referral terms

**Referral** — A request sent from a case to another agency or service provider for action.

**PENDING** — Referral has been sent and is waiting for agency action.

**PROCESSING** — Agency has started working on the referral.

**FOR_COMPLIANCE** — Additional requirement, document, or action is needed before the referral can proceed.

**COMPLETED** — Agency action has been completed.

**REJECTED** — Referral was not accepted or cannot be processed as sent.

## Admin terms

**Case Category** — Admin-managed grouping for case issues. Categories can be active or inactive and are soft-deleted when removed.

**Case Issue** — Admin-managed issue type linked to case handling and reporting.

**Case Status** — Admin-managed status reference. System statuses cannot be deleted.

**Data Export** — Admin function for generating or reviewing exports.

**Audit Log** — Record of significant system actions, filterable by action, module, user, date range, search, and page size.

**Soft Delete Flag** — Records are marked deleted with fields such as deletion flags and timestamps instead of being physically removed.

**Supabase/PostgreSQL** — Database platform used by the project deployment.

**Temporary URL** — Time-limited storage link used for controlled file access where supported.

## Feedback & security terms

> [!NOTE] Pending human review
> The definitions below were added with the feedback and account-security features and are awaiting terminology sign-off.

**SERVQUAL** — A standard service-quality survey model measuring five dimensions: Tangibles, Reliability, Responsiveness, Assurance, and Empathy. Client feedback forms are built on it.

**Feedback Invitation** — The personal, expiring link emailed to a client after a service is completed, used to submit feedback without logging in. The questions are snapshotted into the invitation when it is sent.

**Expectation / Perception** — The two ratings a client gives per SERVQUAL question: the minimum service level they expected, and the level they actually experienced.

**Response Rate** — Submitted feedback divided by invitations sent, shown on feedback dashboards.

**MFA (Multi-Factor Authentication)** — An extra sign-in step using a 6-digit code from an authenticator app, enabled per user on the Profile page.

**Recovery Code** — A backup code generated when MFA is enabled, used to sign in if the authenticator device is unavailable.
`;
export default content;
