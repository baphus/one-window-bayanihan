const content = `# Getting Started for Case Managers

This tutorial is for users with the **CASE_MANAGER** role. Case Managers clear the pending review queue, prepare case records, publish drafts, create referrals to partner agencies, and monitor progress until a case can be closed or archived. (The platform has four roles in total: **CASE_MANAGER**, **AGENCY**, **ADMIN**, and **OFW**.)

## 1. Sign in with your email and password

![Login page](/assets/helpdesk/login-page.png)

1. Open the One Window Bayanihan login page.
2. Enter your registered **email address** and **password**.
3. If MFA is enabled on your account, enter the 6-digit code from your authenticator app on the MFA challenge screen (or a recovery code if you don't have your device).
4. Staff accounts are created by invitation — an administrator invites you and you register through the invite link. There is no open self-registration.

After sign-in, the system opens your Case Manager dashboard.

## 2. Understand the Case Manager dashboard

![Case Manager dashboard](/assets/helpdesk/dashboard-cm.png)

Use the dashboard as your starting point at the beginning and end of each shift. It helps you spot new workload, overdue items, and changes made by agencies.

Typical dashboard information includes:

| Area | How to use it |
|---|---|
| Case totals and status cards | See how many cases are open, closed, archived, awaiting review, or still being prepared. |
| Referral summaries | Check pending or overdue referrals that need follow-up. |
| Recent activity | Review newly updated cases and agency responses. |
| Reports or trend widgets | Watch case volume and processing performance over time. |

The dashboard is a monitoring view, not a substitute for reviewing the actual case record. Open the case or referral before making decisions.

## 3. Navigate your workspace

The left-side navigation gives access to the main work areas available to Case Managers.

| Navigation area | Purpose |
|---|---|
| **Dashboard** | Operational overview and workload monitoring. |
| **Cases** | Search, filter, create, review, and update case records. |
| **Pending Reviews** | Requests filed by OFWs, waiting for you to approve or reject them. The sidebar badge shows how many are waiting. |
| **Referrals** | Track referrals sent to agencies and review their responses. |
| **Reports** | View reporting pages available to your role. |

Use search and filters before creating a new record. Duplicate case records make public tracking and referral coordination harder.

## 4. Create a case as a draft

![Cases list](/assets/helpdesk/cases-index.png)

1. Go to **Cases**.
2. Choose the page action for creating a new case.
3. Complete the case form using verified information from the client, OFW, family member, or official document.

![Create case form](/assets/helpdesk/cases-create-top.png)

Enter the core case information carefully. Depending on the form configuration, you may be asked for personal details, address, employment or deployment information, category/issue details, narrative summary, and supporting documents.

When you save a new case, the system creates it as **DRAFT** by default. This is intentional. Draft-first processing gives you time to check spelling, validate identity details, attach documents, and confirm that the case is ready for official handling.

> **Do not treat a saved draft as an active case.** A draft is still being prepared. Public tracking maps **DRAFT** to **BEING_PREPARED**, and unsubmitted drafts are not exposed on the public portal at all.

## 5. Clear the Pending Reviews queue

Cases do not only come from your own drafting. When an OFW files a request through their own account, it arrives already submitted and lands in **Pending Reviews** with status **PENDING_REVIEW**, unassigned. Any case manager can pick it up; the sidebar badge shows how many are waiting.

Open **Pending Reviews** and for each request:

1. Read the submitted summary, vulnerability indicators and consent.
2. Check for an existing case for the same concern before approving.
3. Attach any documents the filer referenced, if needed.
4. Set the **category** — a filed request arrives without one and is flagged **Needs category** in the list.
5. Then either:
   - **Approve case** — pick at least one category, and an issue or concern if the draft has none. Approving moves the case to **OPEN** and assigns ownership to you.
   - **Reject case** — a reason is mandatory (the *Reject case request* dialog). Rejecting removes the draft and emails the filer an explanation.

Use **All cases** / **Oldest first** to control which requests you see first.

![Pending Reviews queue with a PENDING_REVIEW case awaiting a category](/assets/helpdesk/pending-reviews.png)

## 6. Review and publish your own draft

Before publishing, open the draft and check:

1. The person and contact details are complete enough for follow-up.
2. The case category and issue match the concern.
3. The summary is factual, concise, and free of unsupported conclusions.
4. Required documents are attached or the missing documents are clearly noted.
5. No duplicate case already exists for the same concern.

When the record is ready, publish it. Publishing requires at least one active category — without one the system refuses with *"Pick at least one category before approving."* Publishing changes the status from **DRAFT** to **OPEN** and assigns ownership. Public tracking maps **OPEN** to **IN_PROGRESS**. The client is notified in-app and by email.

Case status meanings:

| Internal status | Public tracking meaning | Use when |
|---|---|---|
| **DRAFT** | **BEING_PREPARED** | Saved but not yet submitted. |
| **PENDING_REVIEW** | **BEING_REVIEWED** | Filed and waiting in **Pending Reviews** for approval or rejection. |
| **OPEN** | **IN_PROGRESS** | The case is active and may receive referrals. |
| **CLOSED** | **RESOLVED** | The case has been resolved or completed. |
| **ARCHIVED** | **ARCHIVED** | The case is retained for record purposes. |

## 7. Work from the case record

The case detail page is the official workspace for a concern. Use it to review the case narrative, uploaded documents, referral history, comments, and timeline entries.

Good case notes should be:

- **Factual** — record what was reported, submitted, or verified.
- **Specific** — include dates, agencies, document names, and action taken.
- **Professional** — avoid speculation, blame, or unnecessary personal remarks.
- **Auditable** — assume notes may be reviewed later by authorized users.

## 8. Create and monitor referrals

![Referrals list](/assets/helpdesk/referrals-index.png)

Referrals are how Case Managers coordinate with partner agencies. Create a referral only after the case is sufficiently prepared and the receiving agency/service is appropriate.

1. Open the relevant case.
2. Use the referral action available on the **OPEN** case page.
3. Select the receiving **agency**. You do not choose the service — the agency assigns the service it will apply for once it receives the referral.
4. Write clear referral notes describing what the agency is being asked to do.
5. Optionally attach supporting documents (PDF, DOC, DOCX, JPG, JPEG, PNG; 10 MB per file).
6. Submit the referral. It is created as **PENDING** and the agency is notified.

![Referral details](/assets/helpdesk/referrals-show.png)

Referral statuses used by the system are:

| Status | Meaning for Case Managers |
|---|---|
| **PENDING** | Sent to the agency and waiting for it to accept or reject. Every referral starts here. |
| **PROCESSING** | Agency has accepted and started work. |
| **FOR_COMPLIANCE** *(waiting on client)* | The client must supply something before the agency can continue. |
| **COMPLETED** | Agency has completed its action. |
| **REJECTED** | Agency declined or cannot fulfill the referral. A reason is recorded. |

Check referrals regularly. If a referral is **FOR_COMPLIANCE**, coordinate with the client and help them supply the requirement through the supported workflow. If a referral is **REJECTED**, review the reason and decide whether to revise, send to another agency, or handle internally.

Remember the closure gate: a case cannot be closed while any referral is still **PENDING**, **PROCESSING**, or **FOR_COMPLIANCE**.

## 9. Daily routine checklist

1. Open the **Dashboard** and scan for overdue or newly updated work.
2. Clear the **Pending Reviews** queue first — requests filed by OFWs wait there until you approve or reject them.
3. Review **DRAFT** cases and publish only those ready to become **OPEN**.
4. Avoid duplicate records when creating cases.
5. Check **Referrals** for pending agency action, compliance requests, and completed work.
6. Update case notes and documents after every meaningful action.
7. Use **Reports** when you need a wider workload or performance view.

## 10. Where to go deeper

- **Referrals**: *Creating a referral: choosing the agency* and *Referral status reference*.
- **Clients & partners**: *Managing client records* and *Using the stakeholder directory*.
- **Client feedback**: *Feedback dashboards for case managers and admins*.
- **Your account**: *Securing your account: password and MFA* and *Notifications: staying on top of updates*.

> **Reminder:** The system keeps an audit trail. Use your own account only, and record actions in a way another authorized user can understand later.
`;
export default content;
