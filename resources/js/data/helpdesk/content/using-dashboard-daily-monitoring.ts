const content = `# Using the Dashboard for Daily Monitoring

The dashboard is a daily snapshot for Case Managers. It helps prioritize work, but it should not be treated as a real-time alert feed. Refresh when you need current counts.

![Case Manager dashboard](/helpdesk/dashboard-cm.png)

## Start-of-day review

1. Review active **OPEN** cases assigned to you.
2. Check referrals that are **PENDING** or **FOR_COMPLIANCE**.
3. Open overdue referral lists or filtered referral pages.
4. Review recently updated cases for milestones or comments.
5. Confirm drafts are not being forgotten.

## Move from counts to records

Use the cases index to inspect actual records and statuses: **DRAFT**, **OPEN**, **CLOSED**, and **ARCHIVED**.

![Cases index](/helpdesk/cases-index.png)

For each active case, ask whether the summary is complete, the category and issue are correct, consent is captured, and a referral is needed.

## Referral monitoring

Detailed work happens on referral pages. Prioritize old **PENDING** referrals, **FOR_COMPLIANCE** referrals, and active referrals near the overdue cutoff. Overdue logic excludes terminal **COMPLETED** and **REJECTED** referrals and commonly uses the **referral_overdue_days** setting, defaulting to 7 days.

![Referrals index](/helpdesk/referrals-index.png)

## End-of-day closeout

- Publish, update, or delete drafts as appropriate.
- Add milestones for completed referral actions.
- Reply to comments needing your input.
- Check compliance items remain **PENDING** only when truly unresolved.
- Close cases only when closure requirements are met.
`;
export default content;
