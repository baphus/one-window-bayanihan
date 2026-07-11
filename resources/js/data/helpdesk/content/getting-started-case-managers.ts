const content = `# Getting Started for Case Managers

This tutorial is for users with the **CASE_MANAGER** role. Case Managers handle intake, prepare case records, publish drafts, create referrals to partner agencies, and monitor progress until a case can be closed or archived.

## 1. Sign in with OTP verification

![Login page](/assets/helpdesk/login-page.png)

1. Open the One Window Bayanihan login page.
2. Enter your registered **email address** and **password**.
3. Submit the login form and check the OTP/MFA screen.
4. Enter the one-time password sent for your account.

![OTP verification](/assets/helpdesk/login-otp.png)

> **Important:** OTP codes expire after **5 minutes**. If the code is rejected because it expired, request a new code and use the most recent one.

After verification, the system opens your Case Manager dashboard.

## 2. Understand the Case Manager dashboard

![Case Manager dashboard](/assets/helpdesk/dashboard-cm.png)

Use the dashboard as your starting point at the beginning and end of each shift. It helps you spot new workload, overdue items, and changes made by agencies.

Typical dashboard information includes:

| Area | How to use it |
|---|---|
| Case totals and status cards | See how many cases are open, closed, archived, or still being prepared. |
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
| **Referrals** | Track referrals sent to agencies and review their responses. |
| **Reports** | View reporting pages available to your role. |

Use search and filters before creating a new record. Duplicate case records make public tracking and referral coordination harder.

## 4. Create a case as a draft

![Cases list](/assets/helpdesk/cases-index.png)

1. Go to **Cases**.
2. Choose the page action for creating a new case.
3. Complete the intake form using verified information from the client, OFW, family member, or official document.

![Create case form](/assets/helpdesk/cases-create-top.png)

Enter the core case information carefully. Depending on the form configuration, you may be asked for personal details, address, employment or deployment information, category/issue details, narrative summary, and supporting documents.

When you save a new case, the system creates it as **DRAFT** by default. This is intentional. Draft-first processing gives you time to check spelling, validate identity details, attach documents, and confirm that the case is ready for official handling.

> **Do not treat a saved draft as an active public case.** A draft is still being prepared. Public tracking maps **DRAFT** to **BEING_PREPARED**.

## 5. Review and publish the draft

Before publishing, open the draft and check:

1. The person and contact details are complete enough for follow-up.
2. The case category and issue match the concern.
3. The summary is factual, concise, and free of unsupported conclusions.
4. Required documents are attached or the missing documents are clearly noted.
5. No duplicate case already exists for the same concern.

When the record is ready, use the available publish action on the draft. Publishing changes the status from **DRAFT** to **OPEN**. Public tracking maps **OPEN** to **IN_PROGRESS**.

Case status meanings:

| Internal status | Public tracking meaning | Use when |
|---|---|---|
| **DRAFT** | **BEING_PREPARED** | Intake is saved but not yet published. |
| **OPEN** | **IN_PROGRESS** | The case is active and may receive referrals. |
| **CLOSED** | **RESOLVED** | The case has been resolved or completed. |
| **ARCHIVED** | **ARCHIVED** | The case is retained for record purposes. |

## 6. Work from the case record

The case detail page is the official workspace for a concern. Use it to review the case narrative, uploaded documents, referral history, comments, and timeline entries.

Good case notes should be:

- **Factual** — record what was reported, submitted, or verified.
- **Specific** — include dates, agencies, document names, and action taken.
- **Professional** — avoid speculation, blame, or unnecessary personal remarks.
- **Auditable** — assume notes may be reviewed later by authorized users.

## 7. Create and monitor referrals

![Referrals list](/assets/helpdesk/referrals-index.png)

Referrals are how Case Managers coordinate with partner agencies. Create a referral only after the case is sufficiently prepared and the receiving agency/service is appropriate.

1. Open the relevant case.
2. Use the referral action available on the case page.
3. Select the partner agency and service, if the form provides service selection.
4. Write clear referral notes describing what the agency is being asked to do.
5. Attach or reference supporting documents needed by the agency.
6. Submit the referral.

![Referral details](/assets/helpdesk/referrals-show.png)

Referral statuses used by the system are:

| Status | Meaning for Case Managers |
|---|---|
| **PENDING** | Sent to the agency and waiting for action. |
| **PROCESSING** | Agency has started work. |
| **FOR_COMPLIANCE** | Agency needs additional requirements or information. |
| **COMPLETED** | Agency has completed its action. |
| **REJECTED** | Agency declined or cannot fulfill the referral. |

Check referrals regularly. If a referral is **FOR_COMPLIANCE**, coordinate with the client or source office and provide the needed requirement through the supported workflow. If a referral is **REJECTED**, review the reason and decide whether to revise, send to another agency, or handle internally.

## 8. Daily routine checklist

1. Open the **Dashboard** and scan for overdue or newly updated work.
2. Review **DRAFT** cases and publish only those ready to become **OPEN**.
3. Process new intake and avoid duplicate records.
4. Check **Referrals** for pending agency action, compliance requests, and completed work.
5. Update case notes and documents after every meaningful action.
6. Use **Reports** when you need a wider workload or performance view.

## 9. Where to go deeper

- **Referrals**: *Creating a referral: choosing agency and service* and *Referral status reference*.
- **Clients & partners**: *Managing client records* and *Using the stakeholder directory*.
- **Client feedback**: *Feedback dashboards for case managers and admins*.
- **Your account**: *Securing your account: password and MFA* and *Notifications: staying on top of updates*.

> **Reminder:** The system keeps an audit trail. Use your own account only, and record actions in a way another authorized user can understand later.
`;
export default content;
