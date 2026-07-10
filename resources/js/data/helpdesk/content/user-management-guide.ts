const content = `# User Management Guide

Administrators manage staff accounts from **Admin → Users**. User management controls who can access cases, referrals, reports, and administrative functions. The page shows totals for active users, case managers, agency focals, and admins, and can be filtered by search, role, active status, and agency.

![Admin users](/assets/helpdesk/admin-users.png)

## Roles

Assign the least-privileged role that supports the user's work:

- **ADMIN** — system administration.
- **CASE_MANAGER** — DMW case handling.
- **AGENCY** — partner agency referral processing. An agency focal account **must be linked to an agency** — the form requires it.

## Creating an account

New accounts need a name, email, role, and a strong password (minimum 8 characters with mixed case, numbers, and symbols). Accounts are created active with a verified email — the user can sign in immediately and should change their password and set up MFA (see *Securing your account: password and MFA*).

## Verifying and unverifying

The **verify** action toggles a user's email-verified state. Unverifying blocks access to routes requiring a verified account; use it when an email address is in doubt. Deleted or deactivated users cannot be toggled.

## Changing a user's email (OTP flow)

Email changes are deliberately high-friction:

1. Start the email change from the user's record. You must confirm **your own admin password**.
2. A **6-digit one-time code** is sent for confirmation.
3. Enter the code to complete the change. The user is notified their email was changed.

This prevents silent account takeover through a compromised admin session.

## Deactivating and deleting

- Setting a user **inactive** (or deleting them) blocks sign-in immediately; active sessions can be evicted from **System → Active Sessions**.
- The system refuses to delete the **last remaining admin** — there must always be at least one.
- Deletions are soft: records are flagged, not erased, preserving the audit trail.

## Good practices

- Never share accounts; every action is attributed in the audit log.
- Don't assign ADMIN for routine case work.
- Deactivate promptly when staff leave — then terminate their sessions and review the audit log for their recent activity.
- Confirm agency assignment before giving an agency focal person access; their data visibility is scoped by that agency.
`;
export default content;
