const content = `# Your Case Journey: From Intake to Resolution

This is what happens to your request for assistance, from the moment you approach DMW Region VII until your case is resolved — and where you can check progress along the way.

## 1. Intake

There are two ways a case starts:

- **File it yourself online** through the **[intake form](/intake)**. You verify your email with a code (**verify-email**), the system checks for duplicates (**check-duplicate**), and then you submit your details (**submit**). If you already have an active case, submission is blocked and you are pointed to the tracking portal instead. After filing you may optionally create an OFW account (**/intake/register**) to manage your cases online.
- **File with staff help.** A DMW case manager records your case: your details, the problem, and the assistance you need. Bring valid identification and any documents about your situation (contracts, correspondence, receipts).

When your case is accepted you receive a **tracker number** in the format **OWBAP-XXXXXXXXXX** by email. Keep it — it is your key to checking progress. (Staff also use an internal case number that looks like OWB-YYYYMM-NNNNN; the tracker number is the one meant for you.)

## 2. Referral to the right agency

Most assistance is delivered by partner agencies (for example OWWA or legal aid providers). Your case manager creates **referrals** — formal requests to the agency whose service matches your need. You can see which agencies offer which services in the public [Partner Agencies directory](/partners).

## 3. Processing and milestones

The receiving agency works the referral and records **milestones** — dated notes of concrete progress. Referrals move through the exact statuses **PENDING**, **PROCESSING**, **FOR_COMPLIANCE** (the agency needs something more, sometimes from you), **COMPLETED**, or **REJECTED**. Your case manager coordinates across agencies and keeps your case moving.

## 4. Checking progress yourself

Use the **[public tracking portal](/track)** any time:

1. Enter your tracker number (**OWBAP-XXXXXXXXXX**) and your registered email address.
2. Submit to receive a one-time code (OTP) by email.
3. Enter the OTP to verify and see your case status, referrals, and milestone updates.

The portal shows public labels mapped from system values — BEING_PREPARED, IN_PROGRESS, RESOLVED, ARCHIVED — instead of the internal DRAFT, OPEN, CLOSED, ARCHIVED values staff see.

![Tracking result](/assets/helpdesk/track-result.png)

See *Using the Public Tracking Portal* for details, including what to do if you lose your tracker number. If you have an OFW account, you can also review your cases under **/my-cases**.

## 5. Resolution and closure

When the assistance is delivered, referrals are completed and your case is closed with a summary of the outcome. Cases follow the lifecycle DRAFT → Published → OPEN → CLOSED → Archived. If anything remains unresolved, tell your case manager before closure.

## 6. Your feedback

After a referral is marked COMPLETED, the system sends you a personal **survey link** (**/survey/{token}**) by email. Each link works once and expires after 30 days. Your honest ratings — they take a few minutes — directly shape how DMW and partner agencies improve. Staff must never fill in a survey on your behalf. See *Providing Feedback on Your Case*.

> [!NOTE] Pending human review
> Processing times vary by agency and by the type of assistance; each service in the Partner Agencies directory shows its target processing days where available. This section intentionally avoids promising specific response times until reviewed and approved by the office.

## If you need help along the way

- Questions about your case status: check the tracking portal first — it shows the client-safe version of the updates staff see.
- Lost tracker number: search your email for "OWBAP-", or see the tracking portal guide.
- Anything else: use the [contact page](/contact) or the help chatbot at the bottom of this page.
`;
export default content;
