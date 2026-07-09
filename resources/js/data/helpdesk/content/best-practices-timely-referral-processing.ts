const content = `# Best Practices for Timely Referral Processing

Timely referral processing depends on correct routing, clear instructions, quick acknowledgement, and consistent updates.

![Referrals index](/helpdesk/referrals-index.png)

## Starting status

When a Case Manager creates a referral, the starting status depends on compliance requirements. Without compliance requirements, the referral is **PENDING**. With compliance requirements, it starts as **FOR_COMPLIANCE**.

![Create referral form](/helpdesk/referrals-create.png)

## Daily routine

Start by reviewing new **PENDING** referrals, **FOR_COMPLIANCE** referrals, overdue active referrals, and comments needing replies. Midday, update statuses, add milestones, and ask clear questions. End the day by confirming completed work is not still pending.

## Use the right tool

- **Milestones** record progress history.
- **Comments** handle questions and coordination.
- **Compliance requirements** track required attachments or proof.
- **Status changes** reflect the actual workflow state.

![Referral comments](/helpdesk/referrals-comments.png)

## Avoid preventable delays

Do not wait until the seventh day to review work. The default overdue cutoff is commonly seven days, but urgent cases may need same-day action. Avoid vague replies such as “noted” when a decision or document request is needed.

## Completion discipline

When work is done, update the referral to **COMPLETED** and add a milestone explaining the outcome. Completion handling and notifications depend on the status change, so leaving finished work active can delay case closure and client feedback.
`;
export default content;
