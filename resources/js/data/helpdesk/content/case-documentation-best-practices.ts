const content = `# Case Documentation Best Practices

Good documentation makes a case easier to act on, review, and close. A case record includes the case number, tracker number, client details, vulnerability indicators, consent timestamp, category, issue, summary, status, and related referrals.

![Case details page](/assets/helpdesk/cases-show.png)

## Keep key fields accurate

- **case_number**: internal reference, **CASE-YYYYMMDD-XXXX**.
- **tracker_number**: public reference, **OWBAP-XXXXXXX**.
- **client_type** and client profile: identify who is assisted.
- **vulnerability_indicator** and **nok_vulnerability_indicator**: record relevant risks factually.
- **category_id** and **case_issue_id**: select the best match.
- **summary**: explain concern, prior actions, and next steps.
- **status**: use **DRAFT**, **OPEN**, **CLOSED**, or **ARCHIVED**.

## Intake sections

The create form captures client data, address, employment, multiple next-of-kin entries, consent, category, issue, and summary. Review all sections before publishing.

![Case create top section](/assets/helpdesk/cases-create-top.png)
![Client information section](/assets/helpdesk/cases-create-client.png)
![Address section](/assets/helpdesk/cases-create-address.png)
![Next-of-kin section](/assets/helpdesk/cases-create-nok.png)

## Write useful summaries

Use plain language and chronological order. State who needs assistance, what happened, what has already been done, and what action is needed next. Separate verified facts from client statements.

## Do and do not

Do use dates, agency names, document references, milestones, and clear next steps. Do not add personal opinions, unofficial case statuses, unnecessary sensitive numbers, or vague notes such as “for action” with no owner.

## Before closure

Before marking a case **CLOSED**, confirm that the final summary, referral outcomes, milestones, and compliance items explain why closure is appropriate.
`;
export default content;
