const content = `# Using the Dashboard for Daily Monitoring

The dashboard is a daily snapshot scoped to your role — Case Managers see their own caseload, Agency Focal Persons see their agency's referrals, and Admins see everything. It helps prioritize work, but it should not be treated as a real-time alert feed. Refresh when you need current counts.

![Case Manager dashboard](/assets/helpdesk/dashboard-cm.png)

## Start-of-day review

1. Review active **OPEN** cases assigned to you.
2. Check referrals that are **PENDING** or **FOR_COMPLIANCE**.
3. Open overdue referral lists or filtered referral pages.
4. Review recently updated cases for milestones or comments.
5. Confirm drafts are not being forgotten.

## Move from counts to records

Use the cases index to inspect actual records and statuses: **DRAFT**, **OPEN**, **CLOSED**, and **ARCHIVED**.

![Cases index](/assets/helpdesk/cases-index.png)

For each active case, ask whether the summary is complete, the category and issue are correct, consent is captured, and a referral is needed.

## Referral monitoring

Detailed work happens on referral pages. Prioritize old **PENDING** referrals, **FOR_COMPLIANCE** referrals, and active referrals near the overdue cutoff. Overdue logic excludes terminal **COMPLETED** and **REJECTED** referrals and uses the **referral_overdue_days** setting (default 7 days, adjustable by an administrator). The dedicated **Overdue Referrals** page lists exactly these items and can send reminders.

![Referrals index](/assets/helpdesk/referrals-index.png)

## Where to go next

From the sidebar, **Cases** covers the index, drafts, intake queue (self-filed cases awaiting accept/reject), and trash; **Clients** and **Stakeholders** open their registries; **Overdue Referrals** and **Survey Responses** surface follow-ups and client feedback.

## End-of-day closeout

- Publish, update, or delete drafts as appropriate.
- Add milestones for completed referral actions.
- Reply to comments needing your input.
- Check compliance items remain **PENDING** only when truly unresolved.
- Close cases only when closure requirements are met.
`;
export default content;
