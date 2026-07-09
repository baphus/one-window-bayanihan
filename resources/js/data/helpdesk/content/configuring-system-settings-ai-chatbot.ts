const content = `# Configuring System Settings and AI Chatbot

Administrators manage application settings from the admin settings area. Settings can affect login behavior, notifications, integrations, and optional AI chatbot behavior, so changes should be planned and recorded.

![Admin settings](/helpdesk/admin-settings.png)

## Before changing settings

Confirm the purpose of the change, who requested it, and when it should take effect. Some sensitive settings are stored encrypted by the application. Do not paste secrets into tickets, screenshots, chat, or helpdesk articles.

## Recommended change process

1. Review the current value.
2. Confirm the expected operational effect.
3. Make the smallest necessary change.
4. Save the setting.
5. Test the affected workflow, such as login, case creation, referral notification, or chatbot response.
6. Review the audit log for the settings change if confirmation is needed.

## AI chatbot guidance

If chatbot settings are enabled in your deployment, treat the chatbot as a support assistant, not a decision maker. It should help users find guidance, but official case action still belongs to authorized users in the case or referral workflow.

Good chatbot configuration practices:

- Keep prompts factual and aligned with current helpdesk articles.
- Do not include credentials, private keys, or personal case details in prompts.
- Test with common user questions before announcing a change.
- Disable or revise responses that could be read as legal, medical, or final adjudication advice.

## Security-related admin pages

Depending on your role and deployment, administrators may also use Security and ActiveSessions pages to review security configuration and active user sessions. Use these pages when investigating account access issues or enforcing operational security practices.

## When to escalate

Escalate before changing settings that affect authentication, data retention, storage, email delivery, or external integrations. These settings can interrupt work across DMW and partner agencies if misconfigured.
`;
export default content;
