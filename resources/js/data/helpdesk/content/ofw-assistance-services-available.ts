const content = `# OFW Assistance Services Available

One Window Bayanihan helps DMW case managers coordinate assistance for OFWs and their families through cases, services, and referrals to partner agencies.

## How services are used

Services are maintained by administrators and linked to agencies. During case handling, the case manager identifies the issue, documents the need, and creates referrals to the appropriate agency or service provider.

![Admin services](/assets/helpdesk/admin-services.png)

## Common assistance areas

The full list of agencies, their services, requirements, and processing times is published on the **[Partner Agencies directory](/partners)**.

## Self-service for OFWs

Clients do not need staff help for every step. Four roles use the system — CASE_MANAGER, AGENCY, ADMIN, and OFW — and OFW users get their own portal:

- **Create an account** at **/register**, confirm your email address, then sign in.
- **File a request yourself** from your dashboard at **My Cases → File a new case** (**/my-cases/intake**). You must be signed in — there is no anonymous public form. Your request goes to a DMW case manager for review before it becomes active.
- Check progress any time in the **[public tracking portal](/track)** with your tracker number (**OWBAP-XXXXXXXXXX**) and a one-time code emailed to you.
- Signed-in OFW users can review their cases, notifications, and profile under **/my-cases**.
- Browse what help exists in the **[Partner Agencies directory](/partners)** before or after filing.

## What case managers should document

- Client type and OFW details.
- Address and contact information.
- Vulnerability and urgency factors.
- Employment information.
- Issue category and summary.
- Receiving parties and next-of-kin details when relevant.
- Documents or evidence needed by the receiving agency.

## Choosing the right agency

The case manager chooses the **agency**; the receiving agency then selects the service that best matches the issue from its own list. If unsure which agency fits, review agency service profiles or consult the designated focal person before sending. A well-targeted referral reduces delays and avoids unnecessary rejection or FOR_COMPLIANCE *(waiting on client)* returns.

## Agency responsibility

A new referral arrives as **PENDING**. Agency users assign the service, then accept it — choosing **PROCESSING** or **FOR_COMPLIANCE** and adding a remark — or reject it with a reason. After that they update progress through the remaining statuses — PROCESSING, FOR_COMPLIANCE, COMPLETED, REJECTED — plus milestone notes. Case managers should monitor referrals until the case can be closed or a next action is identified.
`;
export default content;
