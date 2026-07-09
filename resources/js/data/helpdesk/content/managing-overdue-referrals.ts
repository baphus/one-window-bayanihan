const content = `# Managing Overdue Referrals

Overdue referrals are active referrals older than the configured cutoff. The default setting is commonly **referral_overdue_days = 7**. Terminal **COMPLETED** and **REJECTED** referrals are excluded.

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
