const content = `# Managing Compliance Requirements

Compliance requirements track documents or proof needed before a referral can proceed. They are managed per referral service and use requirement statuses **PENDING** and **COMPLIED**, fulfilled through attachments (stored in S3-compatible object storage). Do not confuse the requirement's PENDING/COMPLIED badge with the *referral* status **PENDING** — the referral's own status while waiting on these items is **FOR_COMPLIANCE** *(waiting on client)*.

![Referral compliance requirements](/assets/helpdesk/referrals-compliance.png)

## When to create requirements

Add requirements when the agency cannot process the referral without specific documents or evidence. Requirements are added by the **receiving agency**, per referral service, once the referral has been accepted.

A referral always starts as **PENDING**. It only reaches **FOR_COMPLIANCE** *(waiting on client)* when the agency chooses that as its first disposition in the *Accept Referral* modal, or moves the referral there later with **Update Status**. Nothing attachable at creation can make a referral start in FOR_COMPLIANCE.

Examples include clear passport copies, employment contract pages, proof of repatriation, authorization documents, or agency-specific forms.

## Writing good requirements

Be specific. Instead of "documents needed," write "Clear copy of employment contract showing employer name and signature page." Include document name, quality requirement, deadline if any, and upload instructions.

## Fulfilling requirements

1. Open the referral.
2. Review the compliance section.
3. Attach the required file or proof.
4. Verify it is readable and relevant.
5. Mark **COMPLIED** only after acceptance.
6. Add a milestone or comment if the next action changes.

![Referral details](/assets/helpdesk/referrals-show.png)

## Common mistakes

Avoid marking **COMPLIED** before review, combining unrelated documents in one requirement, using comments instead of compliance records for required attachments, completing a referral while items remain **PENDING** (see *Referral status reference* — a referral with pending compliance should not be treated as complete), or uploading documents to the wrong referral.
`;
export default content;
