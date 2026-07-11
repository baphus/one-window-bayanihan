const content = `# Understanding and Using the Audit Log

The audit log is the system record of important activity in One Window Bayanihan. It helps administrators review user actions, investigate support questions, and confirm that case and referral work was handled through the proper account.

![Admin audit log](/assets/helpdesk/admin-audit-log.png)

## Who can see audit logs

Administrators can review the full audit log. Case managers see only entries for their own cases and those cases' referrals. Agency accounts do not have access to the audit log. This protects sensitive case data while still allowing operational traceability.

## Activity categories

Every audit entry belongs to one category, and the page opens showing the relevant ones by default:

| Category | Covers | Shown by default |
|---|---|---|
| Security | Sign-ins, failed sign-in attempts, MFA changes, session actions | Yes |
| Data | Case, client, referral, milestone, attachment, and feedback changes | Yes |
| Admin | User, agency, service, category/status configuration, exports | Yes |
| System | Automated and maintenance activity | No — enable it with the System chip |

Use the category chips above the timeline to widen or narrow the view. A note appears when system activity is hidden.

## Available filters

The audit log page supports these filters:

| Filter | Use |
|---|---|
| action | Narrow results to a specific action type (including LOGIN_FAILED and EXPORT). |
| module | Show activity for a system area such as cases, referrals, users, or settings. |
| category | Limit to security, data, admin, or system activity. |
| user_id | Review activity performed by one user account. |
| date_from | Start of the activity date range. |
| date_to | End of the activity date range. |
| search | Search visible audit text for relevant terms. |
| per_page | Change how many entries are shown per page. |

Module aliases are normalized by the system. For example, searches using \`case_files\` and \`case\` can resolve to the same case-related audit area, and \`referrals\` and \`referral\` are treated consistently.

## How to investigate an event

1. Start with the approximate date range.
2. Add the user filter if you know the account involved.
3. Filter by module, such as cases or referrals.
4. Open the related case or referral in another tab to compare the audit entry with the current record.
5. Document your findings in the proper operational channel. Do not copy unnecessary personal information into chat or email.

## Common uses

- Confirm who changed a case or referral status.
- Review administrative changes to users, agencies, services, settings, and maintenance items.
- Investigate reports of missing updates or unexpected record changes.
- Support privacy and accountability requirements under RA 10173 by maintaining traceability.

## Reading audit entries carefully

Audit entries show who performed an action and when the system recorded it. They should be interpreted with the related case, referral, user, or admin record. A log entry may confirm that an action occurred, but it does not always explain the business reason. Use case notes, referral milestones, and agency communications for context.

## Exporting audit logs

Administrators can export the current filter selection as a CSV file using the Export button. An explicit date range is required — the dialog pre-fills the last 30 days, and the range cannot exceed the retention window. Every export (including rejected attempts) is itself recorded in the audit log, so extraction of audit data is always attributable.

Entries older than the retention window are archived to immutable monthly bundles before being removed from the live log; contact the system administrator if you need archived history.

## Good practices

- Use the smallest date range that answers the question.
- Search by exact tracker number, case number, or user when available.
- Do not export or share audit details unless there is an operational need — exports are themselves logged.
- Escalate suspicious activity (for example repeated failed sign-in attempts) to the system administrator promptly.
`;
export default content;
