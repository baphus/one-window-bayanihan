const content = `# Email Logs and Resending Failed Emails

The platform sends operational email constantly — sign-in OTPs, tracking notifications, feedback invitations. When a client says "I never got the email," the **Email Logs** page (System → Email Logs, admin only) is where you find out what happened.

![Email logs](/assets/helpdesk/email-logs.png)

## Reading the log

Emails are listed newest first, 15 per page, each with its recipient, subject/type, timestamp, and a status of **sent** or **failed**. Use the status filter to show only failed deliveries.

## Resending a failed email

Failed emails have a **resend** action:

1. Locate the failed entry (filter by **failed**).
2. Trigger the resend. The system re-queues the original job — you'll see **"Email has been re-queued for delivery."**
3. Refresh after a minute to confirm the new attempt's status.

Notes:
- Only **failed** emails can be resent; already-sent messages are not re-deliverable from here ("Only failed emails can be resent.").
- A few legacy email types can't be reconstructed automatically — the page will say **"Cannot resend this email type automatically."** In that case, trigger the action again from its source (for example, re-sending an OTP from the login page).

## Troubleshooting checklist

1. **No log entry at all** — the action that should have sent the email didn't run; check the case/referral in question rather than the mailer.
2. **Failed repeatedly** — verify the recipient address on the client or user record (see *Managing client records* / *User management guide*); a typo there fails every future send too.
3. **Sent but not received** — ask the recipient to check spam; the log proves the platform handed it to the mail service.
4. Persistent system-wide failures — check **System → Logs** for mailer errors (see *Maintenance mode, system logs, and data export*).
`;
export default content;
