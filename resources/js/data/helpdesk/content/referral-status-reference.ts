const content = `# Referral Status Reference

Referral statuses show where an agency referral stands. They are separate from case statuses.

![Referrals index](/assets/helpdesk/referrals-index.png)

| Status | Meaning | Typical next action |
|---|---|---|
| PENDING | Referral has been sent and awaits agency action. | Agency adds the service, then accepts or rejects with a remark. |
| PROCESSING | Agency is working on the referral. | Monitor milestones and expected completion. |
| FOR_COMPLIANCE *(waiting on client)* | Something is needed from the client before work can continue. | Follow up with the client to supply the requirement. |
| COMPLETED | Agency action is finished. | Review outcome and update the case. |
| REJECTED | Referral cannot be processed as sent. | Review reason and create a corrected referral if needed. |

## Where referrals start

Every referral starts as **PENDING** — always. No referral begins in any other state, regardless of documents or requirements. The receiving agency then makes the first disposition.

## The agency's first decision

A **PENDING** referral shows the receiving agency two buttons:

- **Accept** — opens the *Accept Referral* modal. The agency picks **PROCESSING** or **FOR_COMPLIANCE** and must add a remark explaining the decision.
- **Reject** — opens the *Reject Referral* modal. A reason is required (choose *Other* to write your own, at least 10 characters), plus a remark.

Before or while accepting, the agency assigns the service the referral applies for. The CM who sent the referral does **not** choose the service — the agency picks it from its own list.

## Allowed transitions

The system enforces a transition matrix. Only these moves are accepted:

- **PENDING** → PROCESSING, FOR_COMPLIANCE, REJECTED
- **PROCESSING** → PROCESSING, FOR_COMPLIANCE, COMPLETED, REJECTED
- **FOR_COMPLIANCE** → FOR_COMPLIANCE, PROCESSING, COMPLETED, REJECTED
- **COMPLETED** → COMPLETED (terminal lock)
- **REJECTED** → REJECTED (terminal lock)

Three rules follow from the matrix:

1. **Idempotent self-transitions** — setting the same status again is a harmless no-op and does not re-fire notifications.
2. **PENDING cannot jump to COMPLETED** — move through PROCESSING (or FOR_COMPLIANCE where applicable) first.
3. **Terminal lock** — COMPLETED and REJECTED referrals cannot be reopened through a status change.

## Compliance loop

**FOR_COMPLIANCE** means the referral is waiting on the client. The agency records what it needs, the client supplies it (often through a secure private document-request link), and the referral returns to **PROCESSING**. Requirements are added by the agency during this loop — never at referral creation.

## Completion effects

When a referral becomes **COMPLETED**, completion handling runs: a client survey invitation is issued against the agency's active survey form, and notifications go out to relevant users. Add a closing milestone so the outcome is recorded. Once a referral is COMPLETED or REJECTED, the client can no longer see agency activity on it.

## How to use statuses

Case managers should monitor PENDING, PROCESSING, and FOR_COMPLIANCE referrals regularly. Agency users should add clear milestone notes when changing status so the case record explains what happened and why.

A referral is considered overdue when there has been no update or activity for more than the configured number of days (7 by default); see *Managing overdue referrals*.

## Relationship to case status

A case may stay OPEN while referrals are active. Closing a case is blocked while any referral is not yet COMPLETED or REJECTED — so close the case only when the overall assistance work is complete, not merely because one referral is completed.
`;
export default content;
