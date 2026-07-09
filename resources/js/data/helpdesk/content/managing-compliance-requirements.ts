const content = `# Managing Compliance Requirements

Compliance requirements track documents or proof needed before a referral can proceed. They use statuses such as **PENDING** and **COMPLIED** and are fulfilled through attachments.

![Referral compliance requirements](/assets/helpdesk/referrals-compliance.png)

## When to create requirements

Add requirements when the agency cannot process the referral without specific documents or evidence. If requirements are present at referral creation, the referral starts as **FOR_COMPLIANCE** instead of **PENDING**.

Examples include clear passport copies, employment contract pages, proof of repatriation, authorization documents, or agency-specific forms.

## Writing good requirements

Be specific. Instead of “documents needed,” write “Clear copy of employment contract showing employer name and signature page.” Include document name, quality requirement, deadline if any, and upload instructions.

## Fulfilling requirements

1. Open the referral.
2. Review the compliance section.
3. Attach the required file or proof.
4. Verify it is readable and relevant.
5. Mark **COMPLIED** only after acceptance.
6. Add a milestone or comment if the next action changes.

![Referral details](/assets/helpdesk/referrals-show.png)

## Common mistakes

Avoid marking **COMPLIED** before review, combining unrelated documents in one requirement, using comments instead of compliance records for required attachments, completing a referral while items remain **PENDING**, or uploading documents to the wrong referral.
`;
export default content;
