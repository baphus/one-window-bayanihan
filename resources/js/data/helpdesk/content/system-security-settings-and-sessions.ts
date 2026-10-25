const content = `# System Security: Settings

Administrators control platform-wide security posture from **System → Security**.

![Security settings](/assets/helpdesk/security-settings.png)

## Security settings

The **Security** page manages this policy:

**Sign-in protection**
- **Require two-factor authentication** — makes authenticator (TOTP) MFA mandatory instead of optional (see *Securing your account: password and MFA*).

Password strength, session lifetime, and sign-in rate limits are fixed platform defaults and cannot be changed from this page. New staff passwords require a minimum of 8 characters with mixed case, numbers, and symbols (see *User management guide*).

Sign-in is password plus optional authenticator — there is no login email-OTP.

Select save to apply; you'll see **"Security settings updated."**

## Ending a user's sessions

There is no standalone session viewer. To sign a user out everywhere, use one of the credential-changing actions, each of which evicts every existing login and remembered device for that account:

- **Deactivating** the account from **Admin → Users**.
- **Resetting the account's password** from **Admin → Users** (requires re-entering your own password).
- **Resetting the user's MFA** from **Admin → Users**.

A user can also end their own other sessions by changing their password from their profile.

## Recommended review cadence

1. Review the security settings whenever office policy changes (e.g., a DPTM or ISO audit finding).
2. Deactivate accounts and reset credentials promptly when off-boarding staff and after any suspected credential leak.
3. Pair with the **audit log** (see *Understanding and using the audit log*) to investigate what an account actually did.
`;
export default content;
