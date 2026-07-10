const content = `# Monitoring Queue Jobs and System Health

Administrators use the maintenance and log pages to monitor background work and system health. This includes queue-related issues, email delivery checks, application logs, and operational maintenance tasks.

![Admin maintenance](/assets/helpdesk/admin-maintenance.png)

## Where to look

The relevant admin pages live under the **System** menu: **Maintenance**, **Email Logs**, **Logs** (the log viewer), and **Active Sessions**, plus the **Overdue Referrals** page. The dashboard also shows operational summaries for cases, referrals, and workload. Deep-dives: *Maintenance mode, system logs, and data export*, *Email logs and resending failed emails*, and *System security: settings, IP whitelist, and active sessions*.

![Admin dashboard](/assets/helpdesk/dashboard-admin.png)

## Common health checks

- Confirm that new cases and referrals can be created.
- Confirm that referral status updates save correctly.
- Review **Email Logs** if users report missing messages (failed emails can be re-queued from there).
- Review **System → Logs** for repeated application errors.
- Check **Overdue Referrals** for referrals that require follow-up.
- Review **Active Sessions** when investigating account access concerns.

## Queue and job symptoms

A queue or background processing problem may appear as delayed emails, reports taking longer than expected, or records not updating after an expected background task. First confirm whether the user-facing action actually saved. Then check logs and maintenance information before retrying the task.

## Safe operational response

1. Capture the time, user, and action attempted.
2. Check whether the related case, referral, or export record exists.
3. Review EmailLogs or LogViewer for matching errors.
4. Avoid repeatedly retrying actions that may create duplicate notifications.
5. Escalate to technical support with the timestamp and affected record identifiers.

## Overdue referrals

Overdue referral monitoring helps administrators and case managers identify agency responses that need follow-up. Use it with the referral status reference: PENDING and PROCESSING usually require active monitoring; FOR_COMPLIANCE may require information from the case manager or client; COMPLETED and REJECTED should be reviewed for closure or next action.
`;
export default content;
