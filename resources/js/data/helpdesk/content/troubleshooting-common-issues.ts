const content = `# Troubleshooting Common Issues

Use this guide before escalating a support issue. Capture the user, time, page, record identifier, and exact message shown on screen.

## Login or access problems

- Confirm the user is using the correct account.
- Check whether the role is correct: CASE_MANAGER, AGENCY, or ADMIN.
- For admin-only pages, confirm the user is an administrator and any required security restrictions are satisfied.
- If a session issue is suspected, administrators can review ActiveSessions.

## Cannot find a case

![Cases index](/assets/helpdesk/cases-index.png)

Search by case number, tracker number, OFW name, or other available filters. Remember that access is role-based. Agency users normally work from referrals assigned to their agency, not from unrestricted case search.

## Referral not moving forward

![Referrals index](/assets/helpdesk/referrals-index.png)

Check the referral status first. PENDING means it is waiting for agency action; PROCESSING means work has started; FOR_COMPLIANCE means more information or action is required; COMPLETED and REJECTED require review for case next steps.

## Export or report issue

If an Excel export or report does not appear correct, confirm the filters, date range, and permissions. Reports include aggregate sections, while case exports can include detailed personal data. Do not retry repeatedly if a background task may still be running.

## Email or notification issue

Administrators should review EmailLogs and LogViewer for errors around the time the user expected the message. Confirm the record action was saved before assuming a notification problem.

## When to escalate

Escalate with evidence: screenshot without unnecessary personal data, timestamp, browser, page, route or admin section, and affected case/referral identifiers. Avoid sending full exports unless specifically requested for support.
`;
export default content;
