const content = `# Getting Started for Agency Focal Persons

This tutorial is for users with the **AGENCY** role. Agency users work on referrals assigned to their agency, update the referral status, add milestones, comments, and attachments, and respond to compliance requirements when the interface makes those actions available.

## 1. Sign in and complete OTP

![Login page](/assets/helpdesk/login-page.png)

1. Open the One Window Bayanihan login page.
2. Enter your registered **email address** and **password**.
3. Continue to the OTP/MFA screen.
4. Enter the one-time password for your account.

![OTP verification](/assets/helpdesk/login-otp.png)

> **Important:** OTP codes expire after **5 minutes**. If your code expires, request a new one and enter the latest code.

After verification, the system opens the agency workspace for referrals linked to your agency account.

## 2. Read the agency dashboard

![Agency dashboard](/assets/helpdesk/dashboard-agency.png)

Your dashboard focuses on referrals scoped to your agency. In the database, agency users are associated with an agency identifier, and referral lists are limited to that agency. You should not see referrals assigned to unrelated agencies.

Use dashboard cards and recent activity to answer three questions:

| Question | What to check |
|---|---|
| What needs action now? | New **PENDING** referrals and items nearing their due date. |
| What is already being handled? | **PROCESSING** referrals and milestone updates. |
| What is blocked? | **FOR_COMPLIANCE** referrals awaiting requirements or clarification. |

Open the referral record before taking action. Dashboard numbers are summaries; the referral page contains the notes, documents, comments, and history you need.

## 3. Review incoming referrals

![Referrals list](/assets/helpdesk/referrals-index.png)

1. Go to **Referrals**.
2. Use search or filters to narrow the list by status, date, or other available fields.
3. Open a referral to view the case context, request notes, uploaded attachments, comments, and timeline.

![Referral details](/assets/helpdesk/referrals-show.png)

The Case Manager sends the referral because they need your agency to perform a specific action or provide a service. Read the notes carefully. If the request is unclear, use comments to ask for clarification instead of guessing.

## 4. Know the referral statuses

The system uses these referral statuses:

| Status | Use it when |
|---|---|
| **PENDING** | The referral has been sent to your agency and is awaiting initial action. |
| **PROCESSING** | Your agency has started reviewing or providing the requested service. |
| **FOR_COMPLIANCE** | More documents, information, or client action is needed before work can continue. |
| **COMPLETED** | Your agency has finished the requested action or service. |
| **REJECTED** | Your agency cannot fulfill the referral or it was assigned incorrectly. |

When changing status, add a clear note. A useful note explains what changed, who acted, and what the Case Manager or client should expect next.

> **Do not mark a referral completed just because it was acknowledged.** Use **COMPLETED** only when your agency's required action is finished.

## 5. Start work on a referral

For a new referral:

1. Open the referral from the **Referrals** list.
2. Review the referral notes and attachments.
3. Confirm that the request belongs to your agency and service area.
4. If your agency can act on it, update the status to **PROCESSING** using the available status action.
5. Add a comment or milestone summarizing the first action taken.

Examples of useful first updates:

- Initial review completed; documents are sufficient for assessment.
- Client interview scheduled for a specific date.
- Referral received by the relevant unit for validation.
- Additional proof of identity or authorization is needed.

## 6. Request compliance requirements

Use **FOR_COMPLIANCE** when work cannot continue without missing information, documents, or client participation.

Your compliance note should include:

1. The exact requirement needed.
2. Why it is needed, if not obvious.
3. Who should provide it.
4. Any deadline or time-sensitive detail.

Example: **FOR_COMPLIANCE — Please provide a signed authorization letter and a clear copy of the OFW passport bio page before benefit eligibility can be validated.**

Once the Case Manager or client provides the requirement through the supported workflow, update the referral back to **PROCESSING** if work resumes.

## 7. Add milestones, comments, and attachments

Milestones record progress. Comments support coordination. Attachments provide evidence or output documents. Use all three where applicable.

| Tool | Best use |
|---|---|
| Milestones | Major progress events, such as assessment completed, interview conducted, documents verified, service released. |
| Comments | Questions, clarifications, delay notices, and coordination with the Case Manager. |
| Attachments | Official response letters, proof of service, forms, or other supporting files. |

Keep entries professional and short. Avoid private side conversations outside the system when the update affects the case record, because the referral timeline is part of the official coordination history.

## 8. Complete or reject a referral

Use **COMPLETED** when your agency has fully provided the requested service or issued its official response. Include a completion note that states the result and references any uploaded document.

Use **REJECTED** only when your agency cannot process the referral. Give a reason that helps the Case Manager decide the next step. Examples include wrong agency jurisdiction, service not offered, duplicate referral, or insufficient basis after review.

> **Important:** Rejection should not be used for temporary missing requirements. Use **FOR_COMPLIANCE** when the referral can continue after requirements are submitted.

## 9. Agency services and profile information

Some agency users may have screens for agency services or profile details. If available in your navigation, keep them accurate so Case Managers choose the correct service when creating referrals.

Review these items periodically:

- Agency contact information.
- Services currently offered.
- Processing time or SLA expectations, if shown.
- Inactive or unavailable services that should not receive new referrals.

If you cannot edit a needed agency setting, coordinate with a System Administrator.

## 10. Daily routine checklist

1. Check the **Dashboard** for new and overdue referrals.
2. Open **PENDING** referrals and move valid work to **PROCESSING**.
3. Add milestones after meaningful actions.
4. Use **FOR_COMPLIANCE** with exact requirements when blocked.
5. Upload official outputs before marking work **COMPLETED**.
6. Review comments so Case Managers receive timely responses.

Using the referral record consistently keeps DMW Region VII and partner agencies aligned on each client's progress.
`;
export default content;
