const content = `# Using the Public Tracking Portal

The public tracking portal lets a client check progress without signing in. It is a limited, client-facing view protected by tracker number and OTP.

![Tracking portal form](/helpdesk/tracking-portal.png)

## What you need

- Tracker number: **OWBAP-XXXXXXX**.
- Access to the registered mobile number or email address.
- A browser session to request and enter the OTP.

The tracker number is different from the staff case number. Case numbers use **CASE-YYYYMMDD-XXXX**. Give clients the tracker number for public updates.

## Steps

1. Open the tracking page.
2. Enter the tracker number exactly, including **OWBAP-**.
3. Request an OTP.
4. Check the registered phone or email.
5. Enter the OTP to view the result.

![Tracking result page](/helpdesk/track-result.png)

## What clients can see

The result shows client-safe progress, including case status, public milestones, and agency progress cards built from referral activity. Internal notes and private coordination comments are not shown.

Case statuses are exact system values:

| Status | Meaning |
| --- | --- |
| **DRAFT** | Owner-only staff draft; not normally public. |
| **OPEN** | The case has been submitted and is active. |
| **CLOSED** | The case has been completed or resolved. |
| **ARCHIVED** | The case is retained as an inactive record. |

Referral progress may mention **PENDING**, **FOR_COMPLIANCE**, **COMPLETED**, or **REJECTED** when relevant. If requirements are requested, the client should follow the Case Manager or agency instructions.

## Staff checklist

- Confirm the client has the tracker number, not just the case number.
- Keep milestone wording factual and client-safe.
- Use referral comments for internal coordination.
- Do not ask clients to share OTPs except under an approved support process.
- Update contact details through the case record if OTP delivery fails because the registered phone or email changed.
`;
export default content;
