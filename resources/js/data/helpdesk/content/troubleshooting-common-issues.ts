const content = `# Troubleshooting Common Issues

Use this guide before escalating a support issue. Capture the user, time, page, record identifier, and exact message shown on screen.

## Login or access problems

- Confirm the user is using the correct account.
- Check whether the role is correct: CASE_MANAGER, AGENCY, ADMIN, or OFW (OFW users work under /my-cases, not the staff console).
- For admin-only pages, confirm the user is an administrator and any required security restrictions are satisfied.
- If a session issue is suspected, administrators can review ActiveSessions.
- For MFA trouble, remember sign-in uses TOTP codes from an authenticator app; recovery codes are the fallback when the device is unavailable.

## Cannot find a case

![Cases index](/assets/helpdesk/cases-index.png)

Search by case number (**OWB-YYYYMM-NNNNN**), tracker number (**OWBAP-XXXXXXXXXX**), OFW name, or other available filters. Remember that access is role-based. Agency users normally work from referrals assigned to their agency, not from unrestricted case search.

## Tracking portal and OTP problems

- The portal needs the tracker number **including OWBAP-** plus the registered email address — both must match the case record.
- OTPs are delivered by **email only**. Ask the client to check spam/junk, wait a minute for delivery, then request a fresh code.
- A generic "unable to process" error is intentional so tracker numbers cannot be enumerated — re-check both fields for typos before retrying.
- If the registered email changed, update it on the case record first; otherwise the code keeps going to the old address.

## Intake submission blocked

- "You already have an active case" means the email has an OPEN or DRAFT case — direct the client to the tracking portal instead of filing again.
- Email verification must complete before submission; signed-in OFW users skip that step automatically.

## Survey link problems

- Survey links (**/survey/{token}**) are one-shot and expire after 30 days: "already submitted" and "expired" are expected states, not bugs.
- A missing invitation usually means the referral is not COMPLETED yet, the agency has no active form, or there is no client email on file.
- Staff must never fill in a survey on a client's behalf.

## Referral not moving forward

![Referrals index](/assets/helpdesk/referrals-index.png)

Check the referral status first. PENDING means it is waiting for agency action; PROCESSING means work has started; FOR_COMPLIANCE means more information or action is required; COMPLETED and REJECTED require review for case next steps.

## Export or report issue

If an Excel export or report does not appear correct, confirm the filters, date range, and permissions. Reports include aggregate sections, while case exports can include detailed personal data. Do not retry repeatedly if a background task may still be running.

## Email or notification issue

Administrators should review EmailLogs and LogViewer for errors around the time the user expected the message. Confirm the record action was saved before assuming a notification problem. This covers tracking OTPs, intake confirmations, and survey invitations alike.

## When to escalate

Escalate with evidence: screenshot without unnecessary personal data, timestamp, browser, page, route or admin section, and affected case/referral identifiers. Avoid sending full exports unless specifically requested for support.
`;
export default content;
