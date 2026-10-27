const content = `# Managing Draft Cases

Draft cases let Case Managers save incomplete case work before submitting it for review. Drafts use the exact status **DRAFT**, and they are owner-only: only the person who created the draft can edit, delete or publish it.

![Draft cases list](/assets/helpdesk/cases-drafts.png)

## What drafts are for

Use a draft when client details, address, employment, next-of-kin, consent, category, issue, or summary still need review. Drafts can be edited, deleted, or published by the owner.

Do not treat a draft as an active case. Public tracking, formal referrals, and closure work all happen after the case reaches **OPEN**.

## How a draft becomes an active case

There is no "submit" button that publishes a draft directly. Publishing happens through the **Pending Reviews** queue:

- **Drafts you create yourself** stay in DRAFT until you publish them.
- **Drafts an OFW files themselves** arrive already submitted — they land as **PENDING_REVIEW**, are unassigned, and wait in **Pending Reviews** for any case manager to approve or reject.
- **Ownership** is assigned at publish time, not at creation.

## Editing a draft

1. Open the drafts page (**Cases → Drafts**).
2. Select your draft to open the edit-draft view.
3. Review each intake section.
4. Complete the missing information and save. The case stays in **DRAFT**.
5. When everything on the publishing checklist below is satisfied, publish it — see *Creating and Publishing Cases*.

![Client information section](/assets/helpdesk/cases-create-client.png)
![Address section](/assets/helpdesk/cases-create-address.png)
![Next-of-kin section](/assets/helpdesk/cases-create-nok.png)

## Publishing checklist

- Consent is recorded.
- Summary is clear and factual.
- Category and issue are correct.
- Contact details are accurate for follow-up and notifications.
- Vulnerability indicators are recorded when relevant.
- Next-of-kin entries are complete enough for contact needs.

Publishing requires at least one active category. After publishing, the case becomes **OPEN** and appears in the regular case workflow. The client is notified in-app and by email.

![Cases index after publishing](/assets/helpdesk/cases-index.png)

## Deleting drafts

Delete a draft only when it was created by mistake, duplicated, or no longer represents a valid case. Review old drafts weekly so unfinished assistance is not hidden.

![Pending Reviews queue with a PENDING_REVIEW case awaiting a category](/assets/helpdesk/pending-reviews.png)
`;
export default content;
