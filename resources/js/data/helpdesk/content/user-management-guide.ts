const content = `# User Management Guide

Administrators manage staff accounts from **Admin → Users**. User management controls who can access cases, referrals, reports, and administrative functions. The page shows totals for active users, case managers, agency focals, and admins, and can be filtered by search, role, active status, and agency. Client (OFW) accounts are excluded from this staff list.

![Admin users](/assets/helpdesk/admin-users.png)

## Roles

Assign the least-privileged role that supports the user's work:

- **ADMIN** — system administration.
- **CASE_MANAGER** — DMW case handling.
- **AGENCY** — partner agency referral processing. An agency focal account **must be linked to an agency** — the form requires it.

## Creating an account

Staff accounts are invite-only:

1. Create the invite from **Admin → Users** with name, email, role (and agency for AGENCY accounts), and a strong password (minimum 8 characters with mixed case, numbers, and symbols).
2. The invited user completes registration through the invite token link (\`invite/{token}\`).
3. The user signs in with password plus optional authenticator (TOTP). There is no login email-OTP — sign-in codes are never emailed.

New users should change their password and set up MFA promptly (see *Securing your account: password and MFA*).

## Verifying and unverifying

The **verify** action toggles a user's email-verified state. Unverifying blocks access to routes requiring a verified account; use it when an email address is in doubt. Deleted or deactivated users cannot be toggled.

## Changing a user's email

Email changes are deliberately high-friction and verified with a one-time code sent for confirmation. This prevents silent account takeover through a compromised admin session. The user is notified their email was changed.

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
