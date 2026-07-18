const content = `# Case Closure Checklist and Procedures

Close a case only when assistance has been completed, the outcome is documented, and no active referral task still requires case management.

![Case details before closure](/assets/helpdesk/cases-show.png)

## Status check

Case statuses are exactly **OPEN**, **CLOSED**, and **ARCHIVED**. A case should generally be **OPEN** before closure. Drafts are saved separately and should be published or deleted, not closed.

## Referral checklist

Review every referral connected to the case.

- **COMPLETED** referrals should have a clear final milestone or outcome.
- **REJECTED** referrals should explain why and what alternative action was taken.
- Active statuses such as **PENDING** or **FOR_COMPLIANCE** should be resolved before closure unless a documented exception applies.

![Referral details](/assets/helpdesk/referrals-show.png)

## Compliance checklist

Compliance requirements have statuses such as **PENDING** and **COMPLIED** and are fulfilled through attachments. Mark **COMPLIED** only after the attachment or proof is accepted.

![Referral compliance requirements](/assets/helpdesk/referrals-compliance.png)

## Documentation checklist

Confirm the record explains the original concern, client circumstances, vulnerability indicators, agencies involved, compliance requested and received, referral outcomes, final action, and any instructions given to the client.

## Closure steps

1. Open the case details page.
2. Review the summary and referrals.
3. Add missing milestones or comments before changing status.
4. Mark the case **CLOSED**.
5. Confirm the closure date is reflected.
6. Tell the client how to use tracking and feedback when appropriate.
`;
export default content;
