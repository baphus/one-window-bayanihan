const content = `# Managing Your Agency Services Profile

Your agency's service list is what Case Managers see when they route referrals to you. It lives on the **Services** page (AGENCY role only: \`GET /services\`), separate from the admin master service catalog. Keep it accurate so referrals arrive with the right expectations and attachments.

![Create referral form](/assets/helpdesk/referrals-create.png)

## What to maintain

- Specific service name (recognizable to non-agency staff).
- Clear description of what the service covers and its limits.
- Eligibility notes and exclusions.
- Required documents or attachments (stored in S3-compatible object storage).
- Expected processing days if used for overdue monitoring.
- Current focal or contact information shown to Case Managers.

Your dashboard is scoped to your agency's referrals only — you never see other agencies' queues.

## Write clear descriptions

Use practical language. Instead of "assistance," write "financial assistance assessment for repatriated OFWs" or "legal referral coordination for contract-related complaints." Include limits so cases are not misrouted.

## Review routine

Check the profile monthly, update it after program changes, and watch for repeated mismatched referrals. Repeated mismatches often mean the service description is unclear. Services you no longer offer should be removed so Case Managers stop routing to them.

## Status impact

If a referral has no compliance requirements, it is created as **PENDING**. If compliance requirements are included, it starts as **FOR_COMPLIANCE**. Accurate service instructions help Case Managers choose the right starting point. See *Referral status reference* for the full transition matrix.

## Checklist before changes

- Is the service within your mandate?
- Is the name understandable to non-agency staff?
- Are required documents listed clearly?
- Does the processing estimate reflect actual practice?
- Will the change reduce back-and-forth comments?
`;
export default content;
