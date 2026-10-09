const content = `# Managing Overdue Referrals

Overdue referrals are active referrals older than the configured cutoff. The cutoff is the **referral_overdue_days** system setting (default 7 days, adjustable by an administrator). Terminal **COMPLETED** and **REJECTED** referrals are excluded. Open them from the dedicated **Overdue Referrals** page, which also offers a send-reminders action.

![Referrals index](/assets/helpdesk/referrals-index.png)

## What to review

Active referrals include work that is not finished, such as **PENDING** and **FOR_COMPLIANCE**. Open the oldest or highest-risk referral first, then check details, milestones, comments, and compliance requirements.

![Referral details](/assets/helpdesk/referrals-show.png)

## Common causes

- The agency has not acted on a **PENDING** referral.
- The referral is **FOR_COMPLIANCE** and requirements are missing.
- Work was completed but status was not updated.
- A question was left in comments without a clear owner.
- The referral was sent to the wrong agency service.

## Corrective actions

- Add a specific referral comment with the requested update and deadline.
- Add or request a milestone for work already done.
- Follow up on missing compliance attachments.
- Update status when the work is **COMPLETED** or **REJECTED**.
- Escalate through office channels if there is no response.

![Referral comments](/assets/helpdesk/referrals-comments.png)

## Prevention checklist

Create referrals with clear instructions, use compliance requirements for required documents, review **PENDING** items daily, add milestones after meaningful progress, and keep service profiles current.
`;
export default content;
