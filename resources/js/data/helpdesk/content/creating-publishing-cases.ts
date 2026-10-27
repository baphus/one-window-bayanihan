const content = `# Creating and Publishing Cases

Case creation captures the official record for an OFW or family assistance concern. A published case receives **OWB-YYYYMM-NNNNN** and public tracker **OWBAP-XXXXXXXXXX**. Drafts remain owner-only until published.

![Case create top](/assets/helpdesk/cases-create-top.png)

## Intake sections

### Client information

Record client type, name, contact details, and vulnerability indicator when relevant. Contact details matter because the filing confirmation and tracking code go to the registered email address.

![Client information](/assets/helpdesk/cases-create-client.png)

### Address and employment

Enter address details consistently using the region → province → city → barangay cascades (backed by the address lookup reference data). Employment information helps determine category, issue, and referral service. If unknown, save a draft rather than guessing.

![Address section](/assets/helpdesk/cases-create-address.png)

### Next of kin

Next of kin is optional. When one is available, the form supports multiple entries; complete the required name, relationship, contact, and address fields for every contact you add.

![Next-of-kin section](/assets/helpdesk/cases-create-nok.png)

### Consent, category, issue, and summary

Capture consent before processing. Select the best categories (**category_ids** is canonical; a legacy single category field is still accepted — never send both) and case issue. Write a factual summary explaining the problem, prior actions, and needed assistance.

## Save draft or publish

Use **DRAFT** when information is incomplete. Drafts can be edited, deleted, or published later by their owner.

Publishing requires at least one active category — **Approve case** in the review queue will otherwise prompt *"Pick at least one category before approving."* and, if the case has no issue recorded, *"Pick an issue or concern before approving."* Once published the case becomes **OPEN** and ownership is assigned.

![Draft cases](/assets/helpdesk/cases-drafts.png)
![Cases index](/assets/helpdesk/cases-index.png)

## After publishing

Open the case details page, confirm the case number and tracker number, review the summary, and create referrals if needed. Explain to the client that progress is visible at **/track** using their tracker number plus a one-time code emailed to them, or at **/my-cases** if they have an OFW account.

Cases filed by clients themselves do not arrive as an ordinary draft awaiting publication. An OFW files from their own account, and the case lands in the **Pending Reviews** queue as **PENDING_REVIEW**, unassigned. A Case Manager then either:

- **Approves** it — choosing at least one category (and an issue if none exists) — which moves it to **OPEN** and assigns ownership, or
- **Rejects** it with a mandatory reason, which removes the draft and emails the filer an explanation.

![Case details](/assets/helpdesk/cases-show.png)

![Pending Reviews queue with a PENDING_REVIEW case awaiting a category](/assets/helpdesk/pending-reviews.png)
`;
export default content;
