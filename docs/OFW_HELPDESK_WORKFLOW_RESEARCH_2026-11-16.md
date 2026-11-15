# OFW helpdesk workflow research

Date: 2026-11-16  
Scope: current local `main`, HEAD `ed9c57c1db88f7f834d01baec5300e843b0597f1`. Source inspection and route registration checks, not a live deployment certification. Existing local changes were left untouched. No helpdesk articles changed.

## Executive findings

The helpdesk is not yet an adequate self-service guide for the current OFW portal. Its main public journey article mentions registration and filing, but there are no dedicated OFW instructions for account creation, login/recovery, intake preparation, dashboard use, intake-document compliance, or replying to agency client requests.

The corpus has **46 articles, only 9 marked public**. OFWs receive public articles, not `staff` articles. Consequently the existing security/MFA and notifications articles are unavailable to OFWs even though those are core OFW interactions. Their content is also staff-oriented; exposing them unchanged would not solve the problem. [S1, S2]

Highest-value update: build an account-first OFW guide set covering **register, sign in, prepare documents, request assistance, track requests, respond to agency requests, and recover access**. Correct outdated lifecycle and access claims before using existing articles as chatbot knowledge.

“Main activities” here means activities exposed by current navigation and executable workflows, not measured usage frequency. No analytics were inspected.

## 1. Main OFW interactions

### 1.1 Create an account and verify email

Current flow:

1. Open `/register`.
2. Supply structured identity: first/last name, optional middle name/suffix, birth date and sex, email, password and confirmation; optional profile photo.
3. Continue to `/register/review`.
4. Request an emailed six-character verification code and submit it.
5. Account is created as active OFW, email is marked verified, and user is signed in. Intended next destination is `/my-cases/intake`; an applicable MFA enrollment requirement can first redirect to Profile.

Registration password rule is at least eight characters with mixed case and numbers. Address, employment, next-of-kin, and contact details are not registration fields; they belong to later workflows. Invalid/expired codes, duplicate email, expired staged photo, and account-creation failures have distinct recovery messages. Do not tell users that completing the first registration screen already creates an account. [S3, S4, S5]

Recommended article: **Create your OFW account and verify your email**. Include correcting details before completion, using the latest code, avoiding repeat accounts, and what happens after verification.

### 1.2 Sign in, complete MFA, and recover access

OFW entry is `/login`, not `/staff/login`. Sign-in uses email and password. Enrolled users must complete an authenticator TOTP or recovery-code challenge at `/login/mfa`. Email registration/tracking codes are not login MFA codes. OFWs normally land at My Requests, subject to intended destination and security gates. [S4, S6]

MFA enrollment depends on the effective role policy and per-user override. Current executable policy includes OFWs; do not document MFA as merely a staff feature or universally optional/required without checking deployment policy. Required but unenrolled accounts are redirected to OFW Profile. Required MFA cannot be disabled. [S5, S7]

Forgotten password uses `/forgot-password` and an emailed reset link. The confirmation deliberately does not reveal whether an account exists; seeing it does not prove an email was sent to a registered account. A forgot-email page exists, but account lookup/recovery should not be promised without office-approved identity checks. [S4, S8]

Recommended articles:

- **Sign in to your OFW account**.
- **Set up an authenticator and save recovery codes**.
- **Forgot your password or lost your authenticator?**

Never ask users to send passwords, authenticator setup keys, verification codes, recovery codes, or secure access links to support.

### 1.3 Prepare an assistance request

Self-filing is account-first at `/my-cases/intake`; there is no anonymous public intake route. Filing requires a verified account and complete identity. Missing name/birth date/sex redirects to Profile. [S9, S10]

The intake captures identity/contact, civil status, deployment mode, employment status, address, employment information, narrative, vulnerability indicator, proposed assistance/assessment choices, reason for return, documents, and consent. Account identity is prefilled but the user can confirm/change facts for this request. Account email and intake date are enforced server-side. OFWs cannot file a next-of-kin case through this self-service form. [S10–S13]

Important validation to explain before users start:

- Age at intake must be between 15 and 100.
- Address must pass the served-region rule; exact eligibility wording should be approved against that rule and office policy.
- Narrative is required, maximum 5,000 characters.
- Consent is mandatory.
- Uploaded document types: PDF, JPG/JPEG, PNG, WEBP. Intake size ceiling is configured, with 20,480 KB fallback; publish the actual deployed limit, not a guessed universal value.
- Selecting Other assistance requires an explanation.

Required self-service documents are:

- Passport front page.
- BI arrival stamp.
- At least one employment-evidence document: employment contract abroad, OEC, or work visa.
- Reason-specific evidence: distress certificate for Distressed Worker, termination letter for Termination, or notarized sworn affidavit stating reason for return for Others.

Self-filers cannot waive missing documents using staff onsite-compliance options. Staff-assisted filing can record outstanding requirements instead. These are distinct paths, not interchangeable instructions. [S11, S12, S14]

Recommended article: **Documents and information to prepare before requesting assistance**. Cross-link from registration completion and filing guide.

### 1.4 Request assistance and understand review

Select **Request Assistance**, complete intake, upload evidence, accept consent, then **Submit Request**. On success, My Requests opens with pending-review confirmation. Review emails are queued. Submitting is not approval, a guaranteed benefit, or a promised response time. [S10, S13]

OFW filings start as `PENDING_REVIEW`; staff-assisted filings are created `OPEN`. OFW ownership is assigned server-side to the filer. Current service and regression test allow independent multiple cases; the old one-active-request rule should not be repeated. [S15, S16]

Review outcomes require separate explanations:

- Approved: active case handling can proceed.
- Documents needed: upload the requested outstanding intake documents on the existing record.
- Declined: rejected record remains visible; detail page explains why and directs user to submit a fresh intake, creating a new case rather than copying or reopening the old one.

Current rejection code uses `REJECTED`, not deletion of the case. [S17, S18]

Recommended articles:

- **Request assistance from your OFW account**.
- **After submitting: review, missing documents, and declined requests**.

### 1.5 Use My Requests and read progress

Portal navigation includes **My Requests**, **Notifications**, **Profile**, and **Help Center**. My Requests lists owned requests and counts under review, in progress, and completed; rejected cases are included in the completed bucket, so that count must not be described as assistance successfully delivered. [S19, S20]

Open a request to see its status, submitted facts, tracker number, agency progress, milestones, documents, and linked agency requests. Agency milestone pages provide more updates. Records are ownership-scoped; unrelated users cannot view another OFW’s case. Drafts are excluded from client-visible records. [S20, S21]

Keep three concepts separate:

- **Assistance request/case**: overall DMW record.
- **Referral**: one partner agency’s work on the case.
- **Client request**: an agency asks the client for documents, an answer, or an information update.

Do not promise that OFWs see everything staff see: the payload is explicitly client-safe, omitting internal material. Do not instruct users to approve referrals, change case status, assign agencies, or reopen closed cases themselves. [S9, S20, S21]

Recommended article: **Use My Requests to follow your assistance**. Rewrite the existing statuses article using current screen labels and include Declined; remove draft/publish instructions from OFW guidance.

### 1.6 Submit missing or replacement intake documents

This is separate from agency client-request attachments. The case detail renders **Documents needed from you**, the reason, requested slots, and **Submit documents**. Server authorizes the owner and outstanding requirement slots; uploads are not unrestricted replacement of every recorded document. Closed/archived records do not accept this route. Successfully uploaded requirements become received and are submitted for review. [S18, S22, S23]

Recommended article: **Submit documents requested by your DMW case manager**. Explain where the panel appears, supported files, confirmation, and how this differs from responding to an agency.

### 1.7 Respond to an agency client request

This is a major missing helpdesk topic. Agency requests support `DOCUMENT_REQUEST`, `QUESTION`, and `INFORMATION_UPDATE`. OFWs respond to requests created by agencies; they do not create these requests themselves. [S24, S9]

Signed-in path:

1. Open the assistance record and its **Requests** tab or follow the related update.
2. Open the agency request, including its title, instructions, due date where supplied, and checklist.
3. Write a reply and/or attach documents; associate checklist documents with the correct requested item.
4. Send; the response is stored against the request. Submission does not itself mean accepted, approved, or completed.
5. Return for agency replies and revision instructions.

Owner access does not require the emailed access token or another tracking OTP. Reply validation allows up to 5 attachments, each up to 20,480 KB, in PDF/JPG/JPEG/PNG/WEBP; message maximum is 5,000 characters. An empty reply without files is rejected. Closed/unavailable requests cannot accept a new reply. Agency completion/cancellation are staff actions. [S25, S26]

Separate emailed secure-link path:

- `/track/request` exchanges a usable secret token for a request-scoped browser session.
- This is not the tracker-number-plus-email OTP flow.
- Invalid/expired/revoked access may require replacement access or contacting the agency. Signed-in owners can instead use their own portal where the request remains available.

Do not call this secure-link exchange an emailed OTP challenge; current controller validates a token capability. Treat the link as private. [S27]

Recommended articles:

- **Reply to an agency request and upload checklist documents**.
- **Open a secure agency request link from email**.

### 1.8 Notifications

OFWs have their own notifications inbox at `/my-cases/notifications` and a portal bell. Backend paginates at 15 and supports marking an individual notification read. Destinations are rewritten to OFW-safe locations; public tracking links fall back to the owned case. [S9, S19, S28]

Do not copy staff instructions for notification preferences, staff referral pages, 20-per-page pagination, or Mark All Read into an OFW guide without verifying equivalent controls. OFW Profile currently has no notification-preference section. [S29]

Recommended article: **Find updates and action requests in Notifications**.

### 1.9 Profile and account settings

OFW Profile supports identity, contact number, profile photo, email change, password update, MFA, and account deletion/deactivation. Updating profile identity does not rewrite historical case facts. The UI explicitly explains that official case, document, message, and audit records are retained when account access is deactivated. [S29, S30]

Email change has a current-password confirmation step, new-email OTP, verification, and notice to the old address. Do not promise that changing the account email automatically changes email stored on earlier cases. [S31]

Recommended article: **Update your OFW profile and account details**. Include separate security warnings for changing credentials and deactivating access.

### 1.10 Track without signing in

Fallback `/track` requires tracker number and email matching the case, then emailed OTP. Verified browser session is bound to that tracker. The result provides client-safe tracking and agency milestones; it is not a replacement for account-first filing or an unrestricted messaging portal. Generic mismatch errors are intentional privacy protection. [S32, S9]

Recommended action: retain **Using the Public Request Tracking Portal**, but position it after My Requests rather than as the main journey. Verify actual tracker format/email copy before publishing recovery instructions; do not promise a printed receipt or a particular email subject solely because the old article says so.

### 1.11 Surveys, services, and support

- **Feedback survey:** personal `/survey/{token}` link; no account login required. Required questions must be answered; submitted/expired/invalid links show errors. Expiry is governed by `survey_invitation_expiry_days`, so “always 30 days” is not a safe permanent promise. Availability depends on the agency’s survey setup. [S33, S34]
- **Partner directory:** `/partners` lists active agencies; detail page loads services and requirements. Useful before filing, but browsing a service is not a direct application or guarantee of eligibility. [S35]
- **Help Center:** public articles searchable by title/body/category/tags; feedback can be submitted. OFW content must actually be authorized for OFW readers. [S1, S2, S36]
- **Contact:** `/contact` takes name, email, message up to 2,000 characters and sends an email. This is not the assistance intake or an agency request thread. Do not promise emergency response or ticket tracking. [S37]
- **Chatbot:** consumes audience-filtered helpdesk articles. Updating article accuracy improves its evidence; stale workflow claims can contaminate answers. Chatbot help must not be described as submitting or approving assistance. [S38]

## 2. Existing helpdesk defects and gaps

Corpus inspected: `resources/helpdesk/articles.json`, entries identified below by stable slug. Counts obtained by parsing JSON, not inferred from category names.

1. **Missing OFW account and intake walkthroughs.** No dedicated public registration, login, recovery, document preparation, or step-by-step filing articles.
2. **Missing agency client-request guidance.** No public article covering request types, checklist uploads, replies, revisions, or owner versus secure-link access.
3. **Security article inaccessible to OFWs.** `securing-your-account-password-and-mfa` is `staff`; it describes staff profile sections and overgeneralizes MFA optionality.
4. **Notifications article inaccessible and contradictory.** `notifications-staying-on-top-of-updates` is `staff` and says clients do not use the feed, despite the OFW notifications route and UI.
5. **Old single-active-case restriction.** `your-case-journey-from-intake-to-resolution` claims only one active request and repeated duplicate checks. Current creation logic and explicit independent-cases test do not support that rule. [S15, S16]
6. **Rejected case described as removed.** Same journey article says rejection removes the request; current code keeps `REJECTED` and shows it to owners. [S17, S18, S20]
7. **Obsolete draft workflow.** `understanding-case-statuses-tracker-numbers` describes OFW drafts and draft visibility. Client-visible scope excludes drafts, and current intake posts a full request. [S13, S21]
8. **Wrong primary entry point.** Journey article emphasizes public tracking instead of account portal, despite My Requests being the main signed-in surface.
9. **Overbroad visibility claim.** Journey article says users see “the same updates staff see”; client-safe filtering means this should be narrowed. [S20, S21]
10. **Hard-coded survey expiry.** Feedback/journey articles promise 30 days although runtime expiry is a setting. [S34]
11. **OTP concepts mixed.** Security article includes secure document links among email OTP uses; current secure client-request exchange is token-based. [S27]
12. **Staff troubleshooting not suitable for OFWs.** Existing troubleshooting article includes obsolete linked-client errors and active-case restriction plus admin-only actions. Create public task-focused troubleshooting instead of exposing it wholesale.

These findings concern current corpus accuracy, not a request to remove useful staff documentation. Staff article corrections can be a later, separate scope.

## 3. Proposed article backlog

### First release: unblock the main journey

Create these public articles, in this order:

1. **Create your OFW account and verify your email** — `/register`, review/code, errors, first destination.
2. **Sign in to your OFW account** — `/login`, authenticator challenge, recovery code, logout.
3. **Documents and information to prepare before requesting assistance** — conditional evidence checklist and file limits.
4. **Request assistance from your OFW account** — intake walkthrough, consent, validation, confirmation.
5. **Use My Requests to follow your assistance** — list, record detail, agencies, timeline, tracker number, documents.
6. **Reply to an agency request and upload checklist documents** — agency request versus assistance request, instructions, due date, reply, replacement/revision.
7. **Submit documents requested by your DMW case manager** — intake requirements, not agency message attachments.
8. **Forgot your password or lost your authenticator?** — reset link, recovery code, escalation.

Rewrite existing public journey and statuses articles in the same release to remove conflicting directions. Do not publish the new guides while leaving the old single-case, deleted-rejection, and draft claims as authoritative cross-links.

### Second release: account upkeep and recovery paths

9. **Set up an authenticator and save recovery codes**.
10. **Find updates and action requests in Notifications**.
11. **Update your OFW profile and account details**.
12. **Open a secure agency request link from email**.
13. **After submitting: review, missing documents, and declined requests**.
14. **Fix common OFW access, upload, and tracking problems**.

Refresh existing public tracking, feedback, partner-directory, privacy, glossary, and chatbot articles; preserve working slugs wherever possible. Keep surveys and public tracking separate from account login instructions.

## 4. Editorial and publication requirements

- Use current screen names: **My Requests**, **Request Assistance**, **Notifications**, **Profile**, **Help Center**, **Requests**, **Documents**. Explain “case” only as staff terminology where necessary.
- Write task instructions first: what you need, numbered actions, success confirmation, what happens next, common problems, related articles.
- Keep assistance status, referral status, client-request status, and document-review result separate. “Response received” is not “assistance approved.”
- Include privacy boundaries: never share passwords, OTPs, recovery codes, setup keys, secure links, or unnecessary personal documents with support/chatbot.
- Do not invent benefit eligibility, service guarantees, emergency handling, processing times, or office contact details. Require office approval for those claims.
- Do not invent autosave/drafts, SMS codes, one-active-case restrictions, client-selected referral agencies, or self-service reopen/approval controls.
- Verify screenshots against current UI before reuse. Several old articles embed staff screens, unsuitable for OFW walkthroughs.
- New OFW guides must use an audience supported by the existing authorization rule. Today that means `public`; simply adding `audience: OFW` would make them unavailable to OFWs under the current service. Any new audience rule is a separate implementation decision. [S1]
- Authoritative content lives in JSON, not a database article editor: `HelpdeskArticleService` reads `resources/helpdesk/articles.json`; runtime pages receive server-filtered articles. Review categories, tags, featured/popular links, internal links, and chatbot retrieval along with article text. [S1, S2, S36, S38]

## 5. Verification and limits

Executed successfully (shell tool exit code 0):

- `php artisan route:list --except-vendor --path=my-cases` — 16 registered routes, including intake, owner client-request replies/downloads, notifications, profile, and documents.
- `php artisan route:list --except-vendor --path=register` — registration/review/OTP, email availability, and tracking registration routes present.
- PowerShell JSON parsing — 46 entries: public 9, staff 6, CASE_MANAGER 13, AGENCY 7, ADMIN 11.

Inspected regression-test source for independent cases and owner client-request access/uploads. **Tests were not run**; their assertions are supporting design evidence, not fresh passing results. Browser flows, email delivery, deployed MFA/settings, actual file-upload infrastructure, accessibility, screenshots, and production data were not tested.

Two research agents could not start due to model unavailability. Research was completed directly through source inspection. No external library documentation was needed: report describes this application’s business workflows, not framework APIs.

Potential implementation inconsistency to verify before screenshot-led download instructions: `ownerCasePayload()` currently builds intake-document URLs with staff-named `cases.documents.download` although a separate OFW download route exists. Do not imply end-to-end download success from registered routes alone. [S9, S20]

## Source register

Paths and line ranges refer to inspected checkout. JSON references use slugs because article bodies are stored as escaped strings.

- **S1:** `app/Services/HelpdeskArticleService.php:9–34` — corpus source and audience authorization.
- **S2:** `resources/helpdesk/articles.json` — complete article inventory, audience values, and cited existing article bodies; `resources/js/data/helpdesk/articles.ts:1–5` — static export is test fixture, not runtime authorization.
- **S3:** `app/Http/Controllers/Auth/OfwRegistrationController.php:34–58,83–155,160–225,228–233` — registration fields, review, verification, completion.
- **S4:** `routes/auth.php:21–93` — OFW/staff login, registration, MFA challenge, password reset, forgot email.
- **S5:** `app/Services/MfaPolicyService.php:25–44,59–79`; `app/Http/Middleware/CheckMfaEnrolled.php:16–35` — effective policy/enrollment redirect.
- **S6:** `app/Http/Controllers/Auth/AuthenticatedSessionController.php:47–71` — login challenge/destination.
- **S7:** `app/Http/Controllers/MfaController.php:154–168,204–212` — required-policy disable guard and sensitive operations.
- **S8:** `app/Http/Controllers/Auth/PasswordResetLinkController.php:29–48` — reset request and generic confirmation.
- **S9:** `routes/web.php:179–184,399–441,489–508` — filing, tracking, OFW routes and gates.
- **S10:** `app/Http/Controllers/CaseController.php:117–138,177–217` — intake setup, identity gate, submission/destination.
- **S11:** `app/Http/Requests/StoreCaseRequest.php:23–112` — intake fields, validation and required evidence.
- **S12:** `app/Http/Requests/StoreIntakeRequest.php:5–16` — OFW authorization and account email.
- **S13:** `resources/js/Pages/OFW/Intake.jsx:31–68,72–94` — prefill, submit, copy and unsaved-change guard.
- **S14:** `app/Services/IntakeDocumentService.php:11–57,67–81` — slots, required evidence, requirement states.
- **S15:** `app/Services/CaseService.php:68–160` — ownership, case creation, self/staff status and requirements.
- **S16:** `tests/Feature/CaseIntakeWorkflowTest.php:40–67` — independent-case and required-document assertions; inspected, not executed.
- **S17:** `app/Services/CaseService.php:208–284` — approval/rejection operations, including stored REJECTED status.
- **S18:** `resources/js/Pages/OFW/CaseDetail.jsx:34–78,295–399,475–485,674–714` — intake uploads, action states, declined-record next step and tabs.
- **S19:** `resources/js/Layouts/OfwPortalLayout.jsx:18–23,94–104` — main navigation and own-record boundary.
- **S20:** `app/Http/Controllers/OfwDashboardController.php:27–85,91–164,175–195` — list, buckets, owner detail, download URL generation and milestones.
- **S21:** `app/Models/CaseFile.php:72–84`; `app/Http/Controllers/OfwDashboardController.php:124–147` — no client drafts and explicit payload exclusions.
- **S22:** `app/Http/Requests/ReplaceIntakeDocumentsRequest.php:11–26` — owner/outstanding/nonclosed authorization and validation.
- **S23:** `app/Http/Controllers/CaseDocumentController.php:24–64` — intake-document replacement and requirement synchronization.
- **S24:** `app/Models/ReferralClientRequest.php:27–41,84–86` — request types/statuses.
- **S25:** `app/Http/Controllers/ReferralClientRequestController.php:98–217` — owner request screen, routes, checklist and reply validation.
- **S26:** `app/Services/ReferralClientRequestService.php:270,311–334`; `tests/Feature/OfwClientRequestAccessTest.php:31–115` — response status, submit guard, staff transitions, owner assertions; tests not executed.
- **S27:** `app/Http/Controllers/ReferralClientRequestController.php:298–363` — capability-token exchange/session and replacement action.
- **S28:** `app/Http/Controllers/OfwDashboardController.php:204–265`; `routes/web.php:492–493` — OFW notifications and read action.
- **S29:** `resources/js/Pages/OFW/Profile.jsx:16–56` — actual OFW settings and retained-record warning.
- **S30:** `app/Http/Controllers/OfwProfileController.php:28–52,100–138` — account fields and password update behavior.
- **S31:** `app/Http/Controllers/EmailChangeController.php:30–64,74–145` — password-confirmed initiation, new-email code, commit, old-email notification.
- **S32:** `app/Http/Controllers/TrackController.php:27–101,104–145` — matching, OTP, binding, tracking and milestones.
- **S33:** `app/Http/Controllers/PublicSurveyController.php:16–56` — public form and submission outcomes.
- **S34:** `app/Services/SurveyInvitationService.php:43–47,53–62,88–103` — active form, configured expiry, invalid/expired/submitted states.
- **S35:** `routes/web.php:349–381` — active partner list and service/requirement detail.
- **S36:** `routes/web.php:443–466`; `resources/js/data/helpdesk/search.ts:14–35,45–76` — authorized help pages, feedback, search.
- **S37:** `app/Http/Controllers/ContactController.php:14–29,45–48`; `routes/web.php:383–389` — contact submission and security middleware.
- **S38:** `app/Services/Chatbot/ChatbotKnowledge.php:9–12,50–77` — audience-filtered article knowledge and cited article reading.
