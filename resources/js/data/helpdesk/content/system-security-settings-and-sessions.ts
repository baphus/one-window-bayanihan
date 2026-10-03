const content = `# System Security: Settings and Active Sessions

Administrators control platform-wide security posture from **System → Security** and **System → Active Sessions**.

![Security settings](/assets/helpdesk/security-settings.png)

## Security settings

The **Security** page manages this policy:

**Sign-in protection**
- **Require two-factor authentication** — makes authenticator (TOTP) MFA mandatory instead of optional (see *Securing your account: password and MFA*).

Password strength, session lifetime, and sign-in rate limits are fixed platform defaults and cannot be changed from this page. New staff passwords require a minimum of 8 characters with mixed case, numbers, and symbols (see *User management guide*).

Sign-in is password plus optional authenticator — there is no login email-OTP.

Select save to apply; you'll see **"Security settings updated."**

## Active sessions

**System → Active Sessions** lists currently signed-in sessions. Use **Terminate** to force a session out — for example a device someone lost, or an account you've just deactivated.

- You cannot terminate your **own current** session (the page will tell you so).
- Terminating a session forces that browser to sign in again; combined with a password reset it fully evicts a compromised credential.

## Recommended review cadence

1. Review the security settings whenever office policy changes (e.g., a DPTM or ISO audit finding).
2. Check active sessions when off-boarding staff and after any suspected credential leak.
3. Pair with the **audit log** (see *Understanding and using the audit log*) to investigate what a session actually did.
`;
export default content;
