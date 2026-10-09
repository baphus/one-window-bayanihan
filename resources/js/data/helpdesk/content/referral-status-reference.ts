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

## Allowed transitions

The system enforces a transition matrix. Only these moves are accepted:

- **PENDING** → PENDING, PROCESSING, FOR_COMPLIANCE, REJECTED
- **PROCESSING** → PROCESSING, FOR_COMPLIANCE, COMPLETED, REJECTED
- **FOR_COMPLIANCE** → FOR_COMPLIANCE, PROCESSING, COMPLETED, REJECTED
- **COMPLETED** → COMPLETED (terminal lock)
- **REJECTED** → REJECTED (terminal lock)

Three rules follow from the matrix:

1. **Idempotent self-transitions** — setting the same status again is a harmless no-op and does not re-fire notifications.
2. **PENDING cannot jump to COMPLETED** — move through PROCESSING (or FOR_COMPLIANCE where applicable) first.
3. **Terminal lock** — COMPLETED and REJECTED referrals cannot be reopened through a status change.

New referrals start as **PENDING**, or as **FOR_COMPLIANCE** when compliance requirements are attached at creation.

## Completion effects

When a referral becomes **COMPLETED**, completion handling runs: a client survey invitation is issued against the agency's active survey form, and notifications go out to relevant users. Add a closing milestone so the outcome is recorded.

## How to use statuses

Case managers should monitor PENDING, PROCESSING, and FOR_COMPLIANCE referrals regularly. Agency users should add clear milestone notes when changing status so the case record explains what happened and why.

## Relationship to case status

A case may stay OPEN while referrals are active. Close the case only when the overall assistance work is complete, not merely because one referral is completed.
`;
export default content;
