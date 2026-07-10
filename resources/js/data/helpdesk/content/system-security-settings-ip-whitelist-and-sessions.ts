const content = `# System Security: Settings, IP Whitelist, and Active Sessions

Administrators control platform-wide security posture from **System → Security** and **System → Active Sessions**. Like all admin pages, these sit behind the admin IP whitelist once it is enabled.

![Security settings](/assets/helpdesk/security-settings.png)

## Security settings

The **Security** page manages these policies:

**Password policy**
- **Minimum length** (6–64 characters)
- **Require special characters** and **require numbers**
- **Password expiry** in days (0 disables expiry, up to 365)

**Sign-in protection**
- **Session lifetime** in minutes (15–1440) — how long an idle session stays valid.
- **Max login attempts** (1–50) and **lockout duration** in minutes — brute-force protection.
- **Require two-factor authentication** — makes MFA mandatory instead of optional (see *Securing your account: password and MFA*).

**Admin IP whitelist**
- **Enable IP whitelist** plus the list of allowed IPs. When enabled, admin-only pages are reachable only from those addresses.

> **Lock-out warning:** before enabling the IP whitelist, confirm your own current IP is on the list — the setting takes effect immediately for admin pages, including this one. Keep at least one known-good address (e.g., the office network) whitelisted.

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
