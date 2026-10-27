const content = `# Case Closure Checklist and Procedures

Close a case only when assistance has been completed, the outcome is documented, and no active referral task still requires case management.

![Case details before closure](/assets/helpdesk/cases-show.png)

## Status check

Case statuses are exactly **DRAFT**, **PENDING_REVIEW**, **OPEN**, **CLOSED**, and **ARCHIVED**. A case should generally be **OPEN** before closure. Drafts should be published or deleted, not closed, and a case still in **PENDING_REVIEW** has not been approved yet — decide it in the **Pending Reviews** queue first.

## Referral checklist

Review every referral connected to the case.

- **COMPLETED** referrals should have a clear final milestone or outcome.
- **REJECTED** referrals should explain why and what alternative action was taken.
- Active statuses — **PENDING**, **PROCESSING**, or **FOR_COMPLIANCE** *(waiting on client)* — must be resolved before closure. The system actually enforces this: closing is blocked with *"Cannot close case: N referral(s) still pending or in progress."* Only COMPLETED and REJECTED referrals clear the gate.

![Referral details](/assets/helpdesk/referrals-show.png)

## Compliance checklist

Compliance requirements have statuses such as **PENDING** and **COMPLIED** and are fulfilled through attachments. Mark **COMPLIED** only after the attachment or proof is accepted.

![Referral compliance requirements](/assets/helpdesk/referrals-compliance.png)

## Documentation checklist

Confirm the record explains the original concern, client circumstances, vulnerability indicators, agencies involved, compliance requested and received, referral outcomes, final action, and any instructions given to the client.

## Status transitions are guarded

Referral statuses follow a fixed transition map with idempotent self-transitions: repeating the current status is a harmless no-op. Note there is no direct **PENDING → COMPLETED** path — the receiving agency must first accept, which moves the referral to **PROCESSING** (or **FOR_COMPLIANCE**), before any later status applies. Marking a referral **COMPLETED** fires the completion event, which issues a survey invitation so the client can give feedback.

## Closure steps

1. Open the case details page.
2. Review the summary and referrals.
3. Add missing milestones or comments before changing status.
4. Mark the case **CLOSED** using the status control — this is a toggle, and closing is refused while any referral is still open (only OPEN ↔ CLOSED can be toggled).
5. Confirm the closure date is reflected.
6. Archive the case if local records procedure requires it — only CLOSED cases can be archived.
7. Tell the client how to use tracking and feedback when appropriate — completed referrals already trigger a survey invitation automatically.
`;
export default content;
