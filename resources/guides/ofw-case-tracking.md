# OFW Case Tracking Guide — Bayanihan One Window System

> **Purpose:** This document is a reference for answering OFW questions about how to track their case status through the Bayanihan One Window system. It describes the general process and available support channels.

---

## What is the Bayanihan One Window System?

The **Bayanihan One Window System** is an inter-agency case management and referral platform operated by the **Department of Migrant Workers (DMW) Region VII**. It serves as a single-entry hub where Overseas Filipino Workers (OFWs) and their families can file requests for assistance, and have those requests routed to the appropriate government agency for action.

Instead of visiting multiple offices in person, an OFW or their family member can submit a single request through this system. The system then tracks the request from submission through resolution, coordinating across partner agencies behind the scenes.

Partner agencies connected through the system include:

- **DMW** — Department of Migrant Workers (lead agency)
- **OWWA** — Overseas Workers Welfare Administration
- **TESDA** — Technical Education and Skills Development Authority
- **DSWD** — Department of Social Welfare and Development
- **DOLE** — Department of Labor and Employment

---

## How to Check Case Status Using a Tracker Number

When a request is submitted through the Bayanihan One Window system, a **Tracker Number** is issued. This is a unique reference identifier assigned to that specific case. Think of it as a tracking number for a package — it allows you to look up where your case is in the process at any time.

### Step-by-Step

1. **Go to the Case Tracking Portal** — Navigate to the official Bayanihan One Window case tracking page. This is a public-facing web page, not a login-protected dashboard.

2. **Enter your Tracker Number** — Type or paste the 8-character alphanumeric tracker number (e.g., `TRK-AB12CD34`) into the tracker number field.

3. **Enter your Registered Email** — Provide the email address you used when you originally submitted the request. This confirms your identity and links you to the case.

4. **Click "Track My Case"** — The system will send a one-time passcode (OTP) to your registered email before showing you the case details.

---

## What Information You Need

To successfully track a case, you need exactly two pieces of information:

| Item | Where to Find It | Notes |
|------|------------------|-------|
| **Tracker Number** | Email confirmation received at time of submission, or printed acknowledgment receipt | Format: `TRK-` followed by 8 alphanumeric characters |
| **Registered Email** | The same email address used during the original request submission | Must match exactly — including dots, case, and domain |

> **Important:** The tracker number and email act together as a lightweight authentication mechanism. You must have both to proceed. This prevents unauthorized access to case information.

---

## How the OTP Verification Process Works

The system uses a **time-based OTP (One-Time Passcode)** sent via email to verify that you are the person who submitted the request (or an authorized representative). Here is how it works:

1. After you enter your tracker number and email, the system generates a **6-digit OTP** and sends it to your registered email.
2. The OTP is **valid for 5 minutes** from the time it is generated.
3. Check your email inbox — the OTP email typically arrives within seconds. If you do not see it, check your **Spam** or **Junk** folder.
4. Enter the 6-digit code on the verification screen.
5. Once verified, the system displays your case details and its current status.

### Important OTP Rules

- Each OTP can only be **used once**.
- If you request a new OTP (by clicking "Resend"), the previous one is immediately invalidated.
- OTPs expire after 5 minutes. If yours expires, simply request a new one.
- For testing and training environments, the OTP may be displayed on-screen (this is disabled in the live production system).

---

## What Different Case Statuses Mean

Once inside the tracking portal, you will see your case's current **status**. Here is what each status means:

| Status | Meaning | What's Happening |
|--------|---------|------------------|
| **Submitted** | The request has been received by the system. | Your case is in the queue waiting for initial review by a case manager. No action needed on your end. |
| **Under Review** | A case manager is actively evaluating the request. | The assigned agency is gathering information, verifying documents, or determining the appropriate course of action. Additional documents may be requested. |
| **Approved** | The request has been approved. | The requested assistance or intervention has been greenlit. Look for next steps in the case notes. You may be contacted by the assigned agency. |
| **Rejected** | The request could not be approved. | The case manager has determined the request does not meet the criteria for assistance. Reasons will be provided in the case notes. You may be able to submit a new request with additional information. |
| **Completed** | All actions on the case have been finished. | The case has been fully resolved. All required services or interventions have been delivered. The case is now closed. |

### Additional Status Details

- **Pending Documents** — The case is stalled because required supporting documents are missing. Check your email for a document request and upload the required files.
- **Referred** — Your case has been forwarded to a specific partner agency (e.g., OWWA, TESDA, DSWD) for specialized handling. The referral notes will indicate which agency.

---

## Contact Information for Partner Agencies

If you need additional help outside of the tracking system, here are the primary contact channels for each partner agency:

### DMW — Department of Migrant Workers (Region VII)
- **Office:** DMW Regional Office VII, Cebu City
- **Hotline:** 1348 (nationwide)
- **Website:** dmw.gov.ph

### OWWA — Overseas Workers Welfare Administration
- **Office:** OWWA Regional Welfare Office VII
- **Hotline:** (02) 8720-1142
- **Website:** owwa.gov.ph
- **Services:** Welfare assistance, legal aid, repatriation support

### TESDA — Technical Education and Skills Development Authority
- **Office:** TESDA Region VII, Cebu City
- **Hotline:** (02) 8887-7777
- **Website:** tesda.gov.ph
- **Services:** Skills training, competency assessment, scholarship programs

### DSWD — Department of Social Welfare and Development
- **Office:** DSWD Field Office VII, Cebu City
- **Hotline:** (02) 8931-8101
- **Website:** dswd.gov.ph
- **Services:** Social welfare assistance, family counseling, emergency relief

### DOLE — Department of Labor and Employment
- **Office:** DOLE Region VII, Cebu City
- **Hotline:** 1349
- **Website:** dole.gov.ph
- **Services:** Labor law assistance, employment facilitation, dispute resolution

---

## Troubleshooting Tips

### Lost Your Tracker Number?

- Check your **email inbox** for the automated confirmation message you received when you first submitted your request. The subject line will contain "Bayanihan One Window — Case Confirmation" or similar.
- If you still have the printed **acknowledgment receipt**, your tracker number is printed there.
- If you cannot find it by any of these means, **contact the DMW Region VII office** directly. You will need to provide identifying information (full name, date of submission, email used) so they can locate your case.

### Expired OTP

- This is normal. Simply click the **"Resend OTP"** or **"Request New Code"** button on the OTP entry screen to receive a fresh 6-digit code.
- There is no limit on the number of resend attempts (within reason).
- Make sure your email inbox is open and ready before requesting a new OTP, since it expires in 5 minutes.

### Wrong Email Entered

If you enter an email that does not match the one used during submission:

- The system will still attempt to send the OTP, but it will go to the **incorrect address** if that address happens to exist. You will not receive the code.
- **Double-check** the email you entered for typos (e.g., `gmial.com` instead of `gmail.com`, missing dots, etc.).
- If you are sure the email is wrong and you no longer have access to the original email, **contact the DMW Region VII office** to verify your identity and have your registered email updated.

### OTP Not Arriving

- **Check your Spam/Junk folder** — automated emails are occasionally filtered there.
- **Wait 1–2 minutes** — delivery delays are rare but possible.
- **Add the sender domain** to your email contacts/whitelist to improve delivery.
- If it still does not arrive, request a new OTP using the resend button.

### Case Status Has Not Changed in a Long Time

- Cases that are **Under Review** can take time depending on the complexity of the request and the workload of the assigned agency.
- If it has been more than **15 working days** without a status change, consider contacting the assigned agency directly using the contact information above.
- Check your email (including Spam) for any **follow-up requests** from the case manager — sometimes a case stalls because additional documents were requested and not yet provided.

---

## Data Privacy Reminder

The Bayanihan One Window system handles sensitive personal information. Always:

- **Do not share** your tracker number or OTP with anyone you do not trust.
- **Do not post** screenshots of your case details on social media.
- The system will **never ask** for your password or bank details via email or phone.
- Report any suspicious activity to the DMW Region VII office immediately.

---

*Last updated: June 2026 — For official information, always refer to dmw.gov.ph or contact DMW Region VII directly.*
