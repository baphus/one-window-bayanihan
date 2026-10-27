const content = `# Troubleshooting Common Issues

Use this guide before escalating a support issue. Capture the user, time, page, record identifier, and exact message shown on screen.

## Login or access problems

- Confirm the user is using the correct account.
- Check whether the role is correct: CASE_MANAGER, AGENCY, ADMIN, or OFW (OFW users work under /my-cases, not the staff console).
- For admin-only pages, confirm the user is an administrator and any required security restrictions are satisfied.
- If a session issue is suspected, administrators can reset the account's password or MFA from Admin → Users, which signs the user out everywhere.
- For MFA trouble, remember sign-in uses TOTP codes from an authenticator app; recovery codes are the fallback when the device is unavailable.

## Cannot find a case

![Cases index](/assets/helpdesk/cases-index.png)

Search by case number (**OWB-YYYYMM-NNNNN**), tracker number (**OWBAP-XXXXXXXXXX**), OFW name, or other available filters. Remember that access is role-based. Agency users normally work from referrals assigned to their agency, not from unrestricted case search.

## Tracking portal and OTP problems

- The portal needs the tracker number **including OWBAP-** plus the registered email address — both must match the case record.
- OTPs are delivered by **email only**. Ask the client to check spam/junk, wait a minute for delivery, then request a fresh code.
- A generic "unable to process" error is intentional so tracker numbers cannot be enumerated — re-check both fields for typos before retrying.
- If the registered email changed, update it on the case record first; otherwise the code keeps going to the old address.

## Client cannot file a request

- OFWs file from their own account at **/my-cases/intake**, so they must first register at **/register**, confirm their email, and sign in. There is no anonymous public intake form any more.
- "No linked client found. Please complete your profile first." means the OFW account is not yet linked to a client record — fix the profile link, not the form.
- "You already have an active case" means a case for this person is already filed or in progress. There is a limit of one active case per person, checked at three points in the filing flow — direct the client to the tracking portal or their dashboard instead of filing again.
- A filed request is not active immediately: it waits in **Pending Reviews** as **PENDING_REVIEW** until a case manager approves it.

## Case stuck awaiting review

- A request in **PENDING_REVIEW** only moves when someone acts on it in **Pending Reviews**. Nothing expires or auto-approves.
- **Approve case** needs at least one category, and an issue or concern if the case has none — the modal will not submit otherwise.
- **Reject case** needs a reason; the filer is emailed that reason and the draft is removed.

## Survey link problems

- Survey links (**/survey/{token}**) are one-shot and expire after 30 days: "already submitted" and "expired" are expected states, not bugs.
- A missing invitation usually means the referral is not COMPLETED yet, the agency has no active form, or there is no client email on file.
- Staff must never fill in a survey on a client's behalf.

## Referral not moving forward

![Referrals index](/assets/helpdesk/referrals-index.png)

Check the referral status first. **PENDING** means it is still waiting for the receiving agency to accept or reject it — the agency may also need to assign its service first. **PROCESSING** means work has started. **FOR_COMPLIANCE** *(waiting on client)* means something is owed by the client before work can continue. **COMPLETED** and **REJECTED** are final and require review for case next steps.

If a case manager says the agency "never picked up" a referral, confirm the agency has assigned a service — the *Required Services* section stays empty until it does.

## Export or report issue

If an Excel export or report does not appear correct, confirm the filters, date range, and permissions. Reports include aggregate sections, while case exports can include detailed personal data. Do not retry repeatedly if a background task may still be running.

## Email or notification issue

Administrators should review the audit log and the recipient's in-app notifications around the time the message was expected. Confirm the record action was saved before assuming a notification problem. This covers tracking OTPs, filing confirmations, case review decisions, and survey invitations alike.

## When to escalate

Escalate with evidence: screenshot without unnecessary personal data, timestamp, browser, page, route or admin section, and affected case/referral identifiers. Avoid sending full exports unless specifically requested for support.
`;
export default content;
