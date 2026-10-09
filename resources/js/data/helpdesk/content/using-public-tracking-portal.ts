const content = `# Using the Public Tracking Portal

The public tracking portal lets a client check progress without signing in. It is a limited, client-facing view protected by tracker number plus a one-time code (OTP) sent by email.

![Tracking portal form](/assets/helpdesk/tracking-portal.png)

## What you need

- Tracker number: **OWBAP-XXXXXXXXXX**.
- Access to the email address given when the case was filed.
- A browser session to request and enter the OTP.

The tracker number is different from the staff case number. Case numbers use **OWB-YYYYMM-NNNNN**. Give clients the tracker number for public updates. The OTP is delivered by email only — there is no SMS delivery.

## Lost Your Tracker Number?

If you've lost or forgotten your tracker number, here's how to find it:

1. **Check your email** — search your inbox for "One Window Bayanihan — Case Confirmation" or "OWBAP-". The tracker number was sent in the confirmation email when your case was submitted.
2. **Check your acknowledgment receipt** — if you received a printed or digital acknowledgment receipt, the tracker number is printed there.
3. **Ask the Case Manager** — contact the DMW Region VII office and provide your full name, date of submission, and the email address you used. They can look up your case and provide the tracker number.

## Steps

1. Open the tracking page at **/track**.
2. Enter the tracker number exactly, including **OWBAP-**, plus the registered email address.
3. Submit to **send-otp** — the code is emailed to the registered address.
4. Enter the OTP on the verification step (**verify-otp**).
5. Once verified, the browser session is bound to that tracker and the case result opens (**track.show**).

![Tracking result page](/assets/helpdesk/track-result.png)

If the details do not match, the portal shows a generic error on purpose so tracker numbers cannot be enumerated. Double-check the tracker number and email spelling, then try again.

## What clients can see

The result shows client-safe progress, including case status, public milestones, and agency progress cards built from referral activity. Internal notes and private coordination comments are not shown. Updates are attributed to the agency, never to individual staff names.

The portal shows public status labels mapped from the system values:

| Public label | System value | Meaning |
| --- | --- | --- |
| **BEING_PREPARED** | DRAFT | Owner-only staff draft; not normally public. |
| **IN_PROGRESS** | OPEN | The case has been submitted and is active. |
| **RESOLVED** | CLOSED | The case has been completed or resolved. |
| **ARCHIVED** | ARCHIVED | The case is retained as an inactive record. |

Referral progress may mention **PENDING**, **PROCESSING**, **FOR_COMPLIANCE**, **COMPLETED**, or **REJECTED** when relevant. If requirements are requested, the client should follow the Case Manager or agency instructions.

## Staff checklist

- Confirm the client has the tracker number, not just the case number.
- Confirm the client can access the registered email address — OTPs are email-only.
- Keep milestone wording factual and client-safe.
- Use referral comments for internal coordination.
- Do not ask clients to share OTPs except under an approved support process.
- Update contact details through the case record if OTP delivery fails because the registered email changed.
`;
export default content;
