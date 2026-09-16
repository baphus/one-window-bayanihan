const content = `# Creating and Publishing Cases

Case creation captures the official intake record for an OFW or family assistance concern. A published case receives **OWB-YYYYMM-NNNNN** and public tracker **OWBAP-XXXXXXXXXX**. Drafts remain owner-only until published.

![Case create top](/assets/helpdesk/cases-create-top.png)

## Intake sections

### Client information

Record client type, name, contact details, and vulnerability indicator when relevant. Contact details matter because public tracking uses OTP delivery.

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

Use **DRAFT** when information is incomplete. Drafts can be edited, deleted, or published later. Publish when ready for official handling; the case becomes **OPEN**.

![Draft cases](/assets/helpdesk/cases-drafts.png)
![Cases index](/assets/helpdesk/cases-index.png)

## After publishing

Open the case details page, confirm the case number and tracker number, review the summary, create referrals if needed, and explain OTP-based tracking to the client. Cases filed by clients themselves first land in the intake queue, where a Case Manager accepts or rejects them — accepted cases become **OPEN**, rejected ones are sent back with a reason.

![Case details](/assets/helpdesk/cases-show.png)
`;
export default content;
