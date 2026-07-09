const content = `# Referral Status Reference

Referral statuses show where an agency referral stands. They are separate from case statuses.

![Referrals index](/assets/helpdesk/referrals-index.png)

| Status | Meaning | Typical next action |
|---|---|---|
| PENDING | Referral has been sent and awaits agency action. | Agency reviews and accepts work. |
| PROCESSING | Agency is working on the referral. | Monitor milestones and expected completion. |
| FOR_COMPLIANCE | More information, document, or action is required. | Case manager or client provides the requirement. |
| COMPLETED | Agency action is finished. | Review outcome and update the case. |
| REJECTED | Referral cannot be processed as sent. | Review reason and create a corrected referral if needed. |

## How to use statuses

Case managers should monitor PENDING, PROCESSING, and FOR_COMPLIANCE referrals regularly. Agency users should add clear milestone notes when changing status so the case record explains what happened and why.

## Relationship to case status

A case may stay OPEN while referrals are active. Close the case only when the overall assistance work is complete, not merely because one referral is completed.
`;
export default content;
