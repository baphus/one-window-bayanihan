const content = `# Configuring System Settings and the AI Chatbot

Administrators manage runtime settings on the **System Settings** page. The AI chatbot's model and provider are **deployment configuration** (environment settings), not something changed in the UI — this article covers both, and what each change affects.

![Admin settings](/assets/helpdesk/admin-settings.png)

## The System Settings page

**System Settings** (admin only) contains:

- **Application Information** — read-only: application name, version, and region.
- **Referral Overdue Threshold** — *Overdue after (days)*, 1–365 (default 7). Referrals exceeding this age without being completed or rejected are flagged overdue on referral pages and the Overdue Referrals view. Changing it immediately changes what counts as overdue everywhere.
- **Email OTP Debug Mode** (\`debug_otp_enabled\`) — returns the email-change verification code in responses for testing profile and admin email changes. Testing only. The page itself warns that debug output must stay off in production.
- **Tracking OTP Debug Mode** (\`debug_tracking_otp_enabled\`) — same as above for the public tracking portal and intake email verification. **Both debug toggles must stay off in production** — they bypass a security control for real users.

Neither toggle affects sign-in: login uses password plus an optional authenticator-app MFA challenge, never an email OTP.

Changes confirm with **"Settings updated successfully."** and are recorded in the audit log.

> Feedback questionnaires are managed by each agency under **Feedback → Survey Forms** — see *Building client survey forms*.

## AI chatbot configuration (deployment-level)

The public help chatbot's language model is set by the deployment's environment configuration, not the admin UI:

- Deployments use a configured language model that supports the chatbot's article-search and reading functions.
- The technical team configures the model and credentials in the server environment and tests changes before release.
- The chatbot reads relevant helpdesk sections and displays the supporting article titles as links under **Sources**. Improving the articles improves its answers.
- **Update Knowledge** calls **POST /admin/system-settings/reindex-chatbot**, which runs \`php artisan chatbot:index\` to refresh the cached helpdesk content. Public agency information is read directly from the directory.

Practical guidance for administrators:

1. Provider or key changes are infrastructure changes — coordinate with the technical team; they take effect on deployment, not per-request.
2. After any provider change, test the common public questions (tracking, lost tracker number, OTP problems) before announcing.
3. Treat the chatbot as a guide, not a decision maker — official case actions belong to authorized users in the case and referral workflows.
4. Never place credentials or personal case details in prompts, articles, or tickets.

## Related security pages

Password policy, lockouts, mandatory MFA, the admin IP whitelist, and session termination live on separate pages — see *System security: settings, IP whitelist, and active sessions*.
`;
export default content;
