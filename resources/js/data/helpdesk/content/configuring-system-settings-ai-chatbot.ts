const content = `# Configuring System Settings and the AI Chatbot

Administrators manage runtime settings on the **System Settings** page. The AI chatbot's model and provider are **deployment configuration** (environment settings), not something changed in the UI — this article covers both, and what each change affects.

![Admin settings](/assets/helpdesk/admin-settings.png)

## The System Settings page

**System Settings** (admin only) contains:

- **Application Information** — read-only: application name, version, and region.
- **Referral Overdue Threshold** — *Overdue after (days)*, 1–365 (default 7). Referrals exceeding this age without being completed or rejected are flagged overdue on referral pages and the Overdue Referrals view. Changing it immediately changes what counts as overdue everywhere.
- **Login OTP Debug Mode** — auto-fills login OTP values on the verification screen, for testing only. The page itself warns: *"Exposes OTP values in login page responses. Disable in production."*
- **Tracking OTP Debug Mode** — same as above for the public tracking portal. **Both debug toggles must stay off in production** — they bypass a security control for real users.

Changes confirm with **"Settings updated successfully."** and are recorded in the audit log.

> The SERVQUAL section on this page is informational. Feedback questionnaires are managed by each agency under **Feedback → SERVQUAL Configurations** — see *Building SERVQUAL feedback questionnaires*.

## AI chatbot configuration (deployment-level)

The public help chatbot's language model is set by the deployment's environment configuration, not the admin UI:

- The **default provider is a hosted model** (OpenRouter free tier, requiring an API key in the server environment).
- Deployments can switch to any configured provider, **including a local Ollama / llama.cpp model**, by changing the chatbot provider environment setting.
- Answers are grounded in the helpdesk articles through retrieval — improving the articles improves the chatbot.

Practical guidance for administrators:

1. Provider or key changes are infrastructure changes — coordinate with the technical team; they take effect on deployment, not per-request.
2. After any provider change, test the common public questions (tracking, lost tracker number, OTP problems) before announcing.
3. Treat the chatbot as a guide, not a decision maker — official case actions belong to authorized users in the case and referral workflows.
4. Never place credentials or personal case details in prompts, articles, or tickets.

## Related security pages

Password policy, lockouts, mandatory MFA, the admin IP whitelist, and session termination live on separate pages — see *System security: settings, IP whitelist, and active sessions*.
`;
export default content;
