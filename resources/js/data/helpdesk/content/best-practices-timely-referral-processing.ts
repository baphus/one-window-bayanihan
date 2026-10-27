const content = `# Best Practices for Timely Referral Processing

Timely referral processing depends on correct routing, clear instructions, quick acknowledgement, and consistent updates. Your agency dashboard shows only your agency's referrals — work that queue daily.

![Referrals index](/assets/helpdesk/referrals-index.png)

## Starting status

Every referral is created as **PENDING**. It stays PENDING until the receiving agency assigns the service it will apply for and then accepts (choosing **PROCESSING** or **FOR_COMPLIANCE** *(waiting on client)*) or rejects it. No referral starts in any other status.

![Create referral form](/assets/helpdesk/referrals-create.png)

Status moves are constrained by a transition matrix (see *Referral status reference*): self-transitions are harmless no-ops, **PENDING** cannot jump straight to **COMPLETED**, and **COMPLETED** / **REJECTED** are terminal and locked.

## Daily routine

Start by reviewing new **PENDING** referrals, **FOR_COMPLIANCE** *(waiting on client)* referrals, overdue active referrals, and comments needing replies. Midday, update statuses, add milestones, and ask clear questions. End the day by confirming completed work is not still pending.

## Use the right tool

- **Milestones** record progress history.
- **Referral comments** (INTERNAL) handle case-linked coordination questions.
- **Agency message thread** ("Other Agencies on This Case", \`/api\` messages) handles cross-agency discussion.
- **Client requests** (\`/track/request/*\` token flow) handle direct outreach to the client.
- **Compliance requirements** track required attachments or proof.
- **Status changes** reflect the actual workflow state.

![Referral comments](/assets/helpdesk/referrals-comments.png)

## Avoid preventable delays

Do not wait until the seventh day to review work. The default overdue cutoff is commonly seven days, but urgent cases may need same-day action. Avoid vague replies such as "noted" when a decision or document request is needed.

## Completion discipline

When work is done, move the referral through the allowed transitions to **COMPLETED** and add a milestone explaining the outcome. Completion triggers a client survey invitation plus notifications, so leaving finished work active delays case closure and client feedback.
`;
export default content;
