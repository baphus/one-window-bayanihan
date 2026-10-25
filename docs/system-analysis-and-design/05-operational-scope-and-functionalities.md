# 05 — Operational Scope and Functionalities

| | |
|---|---|
| **Version** | 1.0.0 |
| **Date** | 2026-10-11 |
| **Status** | CURRENT |
| **Audience** | DICT reviewers, capstone panelists, business stakeholders |
| **Scope** | What the system does and does not do: boundaries, business services, per-role capabilities, inputs and outputs as business documents and messages, business rules, reporting/search/exports, automatic background work, and the availability matrix |
| **Out of scope** | Data-field detail (see document 04 in this series), integration and payload detail (document 07), role and permission detail (document 06) |

---

## 1. How to read this document

- Everything is written in business language: capabilities a user can see, and the general dependencies the system needs (email delivery, secure document storage, Philippine address reference data, bot protection on public forms, scheduled automatic jobs).
- The availability column in §10 uses only four words: **Available**, **Partly available**, **Not available**, **To be confirmed**.
- Whenever a row says **Partly available**, the Notes column names exactly what is missing.
- No file names, screen addresses, or technical identifiers appear in this document by design.

## 2. System scope and boundaries

### 2.1 In scope

| Dimension | Scope |
|---|---|
| **Organizational** | DMW Region VII staff (Case Managers, Administrators), partner-agency focal points (OWWA, DOLE, TESDA, DSWD, DOH, law centres, LGUs), and the OFW citizens they serve |
| **Functional** | Single-entry intake → one unified case file per OFW → referral to partner agencies → the agency picks its service → service delivery and milestones → citizen document requests → closure, plus the supporting client registry, reporting, activity history, and administration |
| **Geographic** | Nationwide Philippine address reference data, with Region VII as the operating focus; province and city filters scope the reports |
| **Channels** | One web application used through a browser; no separate mobile app and no system-to-system link with partner agencies |
| **Actor surface** | Four user types only — Case Manager, Agency Staff, Administrator, OFW citizen — plus members of the public who have not signed in (help, contact, link-based replies and surveys only; filing requires an account) |

### 2.2 Out of scope (business-clear)

| Not covered | Note |
|---|---|
| Payments, billing, or case fees | The system never collects money |
| Native mobile applications | The service is browser-based only |
| Self-service sign-up for staff | Staff accounts are created or invited by an administrator; citizens register themselves before filing a case |
| System-to-system exchange with partner agencies | Partner agencies use the same web application as everyone else |
| Instant on-screen updates without refreshing | Not available (planned) |

### 2.3 What each role can do (in words)

| Role | Capabilities |
|---|---|
| **Case Manager** | Runs the intake queue, creates and maintains case files from draft to closure, keeps client records, creates and chases referrals with the OFW's available documents attached (the agency picks the service, not the office), uploads documents, comments on referrals, reads reports and exports, and reviews the activity history connected to their work |
| **Agency Staff** | Works only the referrals sent to their own organisation: picks the service for the referral from its own list of available services, updates status and milestones, discusses the referral with the other agencies, asks citizens for documents through temporary links, maintains its own service catalogue and survey forms, and reads its own figures and overdue list — with no screen to add or replace files |
| **Administrator** | Everything a Case Manager can do with cases and clients, plus creating and deactivating accounts, managing partner agencies and reference lists, changing platform settings, reading logs, ending sessions, and exporting the activity history |
| **OFW citizen** | Creates an account, signs in to their own portal, then files a case request; sees only their own case and notifications, answers document requests, follows the case in the portal, and completes surveys — with no access to any staff screen |

## 3. System boundary: what goes in and what comes out

| Direction | Business document or message | Notes |
|---|---|---|
| **In** | Sign-in details (email + password), plus a two-step verification code or backup code | Staff sign-in |
| **In** | Self-filed intake submission from a signed-in citizen | Account creation and sign-in required before filing |
| **In** | Case status enquiries using a tracker number, confirmed by a one-time code | Public tracking |
| **In** | Citizen replies to an agency document request (documents, messages) through a temporary private link | Link expires; can be revoked |
| **In** | Completed satisfaction survey through a private link | Sent automatically after a referral completes |
| **In** | Contact-form enquiry from the public | Protected by a bot check |
| **In** | Uploaded case and referral documents | Validated before they are stored |
| **Out** | In-app notifications and emails for case, referral, milestone, and account events | Sent in the background |
| **Out** | Spreadsheet (Excel) and PDF documents from case, client, referral, and report screens | Exported on request |
| **Out** | Activity-history spreadsheet export | Administrator only, with a date range |
| **Out** | Prepared full-system workbook for the administrator | Promised, but not yet deliverable — see §11 |
| **Out** | Health/status message for operators monitoring the service | Protected message |
| **Boundary** | Nothing is sent out to an external partner system | Partner agencies consume the system through the web application only |

## 4. Business services (module inventory)

| # | Business service | Purpose in one line |
|---|---|---|
| S01 | Sign-in and session handling | Establish and end a signed-in session safely |
| S02 | Two-step verification | Second sign-in step with an authenticator code and backup codes |
| S03 | Accounts and invitations | Create, invite, verify, deactivate, and correct accounts |
| S04 | Public intake (self-filed cases) | A signed-in citizen files a case request from the portal |
| S05 | Case handling | One unified case file per OFW, from draft to closure |
| S06 | Client registry | Master record of the OFW: addresses, employment, next of kin |
| S07 | Referrals to partner agencies | Send a case to an agency; the agency picks its service and drives it to completion |
| S08 | Inter-agency messaging and comments | Discussion between agencies on a referral, plus comments |
| S09 | Citizen document requests | Ask the OFW for specific documents through a temporary link |
| S10 | Overdue referral follow-up | Surface referrals past their target and push reminders |
| S11 | Agency service directory | Catalogue of services each agency provides |
| S12 | Feedback surveys | Satisfaction questionnaires sent to citizens after service |
| S13 | Public case tracking | Follow a case on the public tracker with tracking number + email code, and in the portal when signed in |
| S14 | Notifications | In-app notification feed plus matching emails |
| S15 | Dashboards and reports | Operational figures for managers, filtered by date, place, agency |
| S16 | Exports | Spreadsheets and PDFs of cases, clients, referrals, reports, activity |
| S17 | Activity and audit trail | Tamper-evident history of who did what |
| S18 | Administration | Agencies, accounts, reference lists, platform settings |
| S19 | System operations | Log viewing, email delivery log, maintenance, security settings |
| S20 | Help centre and chatbot | Help articles and a keyword-matched assistant |
| S21 | Document storage and delivery | Safe storage of uploaded files and short-lived download links |
| S22 | Contact form and outbound email | Public enquiries and reliable email delivery with status tracking |

## 5. Service detail

### S01 — Sign-in and session handling
- **Purpose.** Give each person a signed-in session only after their identity is confirmed.
- **Inputs.** Email and password; a password-recovery request; an email-verification link; an invitation link.
- **Outputs.** A signed-in session; confirmation that the account's email is verified; a note in the activity history of successful and failed sign-ins.
- **Business rules.** There is no public staff sign-up. Repeated failed sign-ins, password-reset requests, and invitation acceptances are temporarily slowed down to prevent abuse. Signing out ends the session immediately.
- **Availability.** Available.

### S02 — Two-step verification
- **Purpose.** Require a second proof at sign-in for accounts that hold other people's personal data.
- **Inputs.** A 6-digit code from an authenticator app; or one of the backup recovery codes issued at enrolment.
- **Outputs.** Confirmation that the account is protected; backup codes the user stores safely; an administrator's ability to reset the setup when a device is lost.
- **Business rules.** Required for staff accounts in the live system. A citizen may switch it off outside the live system depending on settings — to be confirmed (§11). Changing your password forces a fresh sign-in with the second step.
- **Availability.** Available (whether it is enforced for citizens in every environment is to be confirmed).

### S03 — Accounts and invitations
- **Purpose.** Let an administrator create or invite staff accounts, and let citizens create their own.
- **Inputs.** Administrator's account form; an invitation request; the citizen's own registration during intake or tracking, confirmed by an email code.
- **Outputs.** A staff account and an invitation email with a link valid for one week; citizen accounts marked as verified at creation.
- **Business rules.** An administrator can create only staff accounts, never citizen accounts. An administrator cannot delete their own account, and cannot remove the last remaining administrator. Citizens appear in the citizen register, not in the staff list.
- **Availability.** Available.

### S04 — Public intake (self-filed cases)
- **Purpose.** Let a signed-in OFW file a case request from the portal, landing in the Case Manager's review queue.
- **Inputs.** The intake questionnaire; the signed-in account; a duplicate check on the email address.
- **Outputs.** A pending case request awaiting review; a client record; a "we received your request" email; an in-app alert to Case Managers.
- **Business rules.** Filing requires creating an account and signing in first — there is no filing path without an account. A pending request is invisible in the client picker until a Case Manager accepts it. Duplicate detection currently compares email addresses only — a name-plus-date-of-birth check is not yet built (§11).
- **Availability.** Available.

### S05 — Case handling
- **Purpose.** Own the single case file per OFW from draft through closure and archiving.
- **Inputs.** Case create/edit forms, intake review decisions, document uploads, status changes, closure decisions.
- **Outputs.** The case file with its history, stakeholders, and documents; emails and notifications when a case is created, published, rejected, or its status changes; exports.
- **Business rules.** A self-filed draft may be picked up by any Case Manager; a draft already assigned to a named Case Manager belongs to that person alone. Cases move through draft → active → closed → archived, and archiving is reversible for a period before permanent removal (performed automatically).
- **Availability.** Available.

### S06 — Client registry
- **Purpose.** Keep one master person record for each OFW: addresses, employment history, next of kin, photo.
- **Inputs.** Information captured through intake and case handling; photo upload.
- **Outputs.** The client record and its address, employment, and next-of-kin details; a client list export.
- **Business rules.** Partner-agency staff only reach clients through their own organisation's referrals. There is no direct "delete client" action available to users — whether one is intended is to be confirmed (§11).
- **Availability.** Available.

### S07 — Referrals to partner agencies
- **Purpose.** Send a case to a partner agency and drive it through pending → in progress / awaiting compliance → completed or rejected, with the receiving agency choosing the service it will deliver.
- **Inputs.** Referral create form with the OFW's available documents attached; the receiving agency's choice of service; status changes; requirements; milestones; attachments added by the sending office.
- **Outputs.** The referral record with its attached documents, the service the agency picked, requirements, milestones, and attachments; notifications to the receiving agency and updates to the case.
- **Business rules.** The office attaches the OFW's documents and sends the referral; the service is picked by the receiving agency from its own list of available services, never by the Case Manager or Administrator. The same agency cannot receive a duplicate referral while an earlier one is still open. Status changes follow the allowed sequence only. An agency sees only its own referrals. Whether the referral list itself should be staff-only is currently open — see §11.
- **Availability.** Partly available (records are protected, but the list is currently visible more broadly than intended — owner to confirm).

### S08 — Inter-agency messaging and comments
- **Purpose.** Let agencies discuss a referral with each other, and let staff leave comments with replies on a referral.
- **Inputs.** Messages on a referral; read confirmations; comments and replies.
- **Outputs.** The message thread and the comment thread.
- **Business rules.** The peer-agency thread is deliberately for agency staff only — Case Managers and Administrators do not read it. Messages cannot be deleted by users.
- **Availability.** Available.

### S09 — Citizen document requests and temporary links
- **Purpose.** Let an agency ask an OFW for specific documents without requiring the OFW to have an account.
- **Inputs.** The request and its document list; lifecycle actions (complete, cancel, reopen); issuing, reissuing, and revoking the temporary link; the citizen's replies and replacement documents.
- **Outputs.** The request record, the time-limited access link, an email to the citizen, and an activity note on the referral.
- **Business rules.** Only the agency that owns the referral may manage the request. Nothing client-facing can be changed once the referral is completed or rejected, or once the case is closed or archived. Links can be revoked at any time.
- **Availability.** Available.

### S10 — Overdue referral follow-up
- **Purpose.** Show referrals that have passed their target time and push reminders.
- **Inputs.** The overdue list; a "send reminders" action.
- **Outputs.** The overdue list scoped to the user's role; reminder notifications and emails.
- **Business rules.** Available to Case Managers, Administrators, and Agency staff (each scoped appropriately); citizens have no part in it.
- **Availability.** Available.

### S11 — Agency service directory
- **Purpose.** Maintain the catalogue of services each agency provides; administrators maintain the shared global list of services.
- **Inputs.** Create, edit, and remove entries for the agency's own catalogue, and for the global list by administrators.
- **Outputs.** The agency catalogue and the global service list used elsewhere in the system.
- **Business rules.** Each agency maintains only its own catalogue; the shared list is administrator-maintained. The catalogue is what an agency picks from when a referral arrives — the Case Manager and Administrator do not choose a service on the agency's behalf.
- **Availability.** Available.

### S12 — Feedback surveys
- **Purpose.** Let agencies build questionnaires and send them to citizens when a referral completes.
- **Inputs.** Form builder (agency staff); the citizen's responses through a private link.
- **Outputs.** The survey form, the invitation (one per case, agency, and referral), and the response dashboard.
- **Business rules.** Invitations are sent automatically when a referral completes, unless feedback is switched off in settings; the same referral never generates a second invitation.
- **Availability.** Available.

### S13 — Public case tracking
- **Purpose.** Let a citizen follow their case on the public tracker (tracking number plus a one-time email code) and in the portal when signed in.
- **Inputs.** The tracker number and email address (public path); or the signed-in session (portal path).
- **Outputs.** The case, referral, and milestone summary for that tracker number.
- **Business rules.** Tracking is read-only on both paths and is protected against automated abuse.
- **Availability.** Available.

### S14 — Notifications
- **Purpose.** One durable in-app feed plus a matching email for each event.
- **Inputs.** Business events: case created/updated/status changed, referral created/changed/overdue, milestone added, intake received, compliance request activity, operational alerts.
- **Outputs.** The notification feed (with mark-as-read) and emails sent in the background; citizens have their own parallel feed in their portal.
- **Business rules.** A citizen who has not yet confirmed their email can still read their own notification feed.
- **Availability.** Available.

### S15 — Dashboards and reports
- **Purpose.** Role-shaped key figures for operational monitoring.
- **Inputs.** The dashboard (staff) or the citizen portal (OFW); report filters by date, geography, and agency.
- **Outputs.** Headline counts and trends, refreshed periodically; each user's figures are scoped to what they may see.
- **Business rules.** Reporting is meant for the three staff roles; a citizen who signs in can currently still reach the reports area — owner to confirm (§11). Figures are cached briefly, so a filter change may take a few seconds to reflect.
- **Availability.** Partly available (core reporting works; citizen access restriction and spreadsheet export of reports are open items).

### S16 — Exports
- **Purpose.** Let users take data out as spreadsheets and PDFs.
- **Inputs.** Export requests from referral, case, client, report, and activity screens.
- **Outputs.** Excel files for referrals, cases, and clients; PDF for a case and for reports; Excel for reports; a spreadsheet of the activity history (administrator only, with an explicit date range); the full-system workbook (see §11).
- **Business rules.** Very large report exports are politely refused with an explanatory message rather than being generated. Activity-history export is administrator-only. Every export attempt is recorded in the activity history.
- **Availability.** Partly available (report spreadsheet export and the full-system workbook are missing — see §11).

### S17 — Activity and audit trail
- **Purpose.** A tamper-evident record of who did what, per area.
- **Inputs.** Actions recorded automatically as users work.
- **Outputs.** The activity history with user, action, record, old and new values; role-scoped viewing; the administrator's spreadsheet export; an automatic periodic integrity check.
- **Business rules.** History is append-only — nobody can edit or delete entries from the screen. Administrators see everything; Case Managers and Agency staff see the parts connected to their own work. Whether Case Managers should see the office-wide history or only their own is to be confirmed (§11).
- **Availability.** Available.

### S18 — Administration
- **Purpose.** Maintain agencies, the shared service list, accounts, reference lists, and platform settings.
- **Inputs.** Create/edit/remove forms for agencies, services, accounts, case categories, case statuses, case issues; platform settings forms.
- **Outputs.** Updated reference data and settings, with everything recorded in the activity history.
- **Business rules.** Administrator-only, except that a Case Manager may view agency details read-only and may add a new case-issue type while working a case. Reference entries are archived rather than hard-deleted, and can be reactivated.
- **Availability.** Available.

### S19 — System operations
- **Purpose.** Operator visibility and control.
- **Inputs.** Requests to view logs and the email delivery log, resend a message, toggle maintenance mode, and adjust security settings.
- **Outputs.** Log viewing and download; resent emails; maintenance mode on or off.
- **Business rules.** Ending a user's sessions is not a standalone screen: it happens as a side effect of deactivating the account or resetting its password or MFA.
- **Availability.** Available.

### S20 — Help centre and chatbot
- **Purpose.** Static help articles plus a keyword-matched assistant drawn from the same content.
- **Inputs.** Search terms in the help centre; questions typed into the chat window (protected by a bot check).
- **Outputs.** Help articles, search results, and a chat reply. Only the most recent few turns are kept in the conversation window.
- **Business rules.** The assistant matches keywords and intent against a prepared copy of the help content — it is not a general chatbot. Keeping full chat transcripts for supervisor review is not yet built (§11).
- **Availability.** Partly available (help and chat work; chat history for supervisors is not available).

### S21 — Document storage and delivery
- **Purpose.** Keep case and referral documents safe and hand back short-lived download links.
- **Inputs.** Document uploads from case and referral screens.
- **Outputs.** Stored files with generated names; download links that expire after a day; automatic daily removal of orphaned files.
- **Business rules.** Every upload is checked for its real file type, and unknown types are refused. Malware scanning of uploads depends on the deployment setting and is not guaranteed active by default — to be confirmed (§11).
- **Availability.** Partly available (storage and validation work; scanning strength depends on the environment).

### S22 — Contact form and outbound email
- **Purpose.** Receive public enquiries and deliver email reliably with status tracking.
- **Inputs.** Contact-form submissions (protected by a bot check); delivery-status confirmations from the email provider.
- **Outputs.** A notification email to the office; an email delivery log administrators can review and resend from.
- **Business rules.** Duplicate delivery confirmations are ignored so no message is counted twice.
- **Availability.** Available.

## 6. Business rules

| # | Rule (plain language) |
|---|---|
| BR-01 | One OFW, one entry: a single unified case file per OFW; referrals attach agencies to that one case |
| BR-02 | No duplicate referral to the same agency while an earlier referral for that agency is still open |
| BR-03 | A referral's status moves only through the allowed sequence: pending → in progress / awaiting compliance → completed or rejected |
| BR-04 | Once a referral is completed or rejected, or a case is closed or archived, no new document requests can be sent to the citizen for that record |
| BR-05 | An agency sees only referrals and clients reachable through its own organisation |
| BR-06 | Discussion threads between agencies are for agencies only; Case Managers and Administrators do not read them |
| BR-07 | A self-filed intake must be accepted by a Case Manager before it becomes part of the client register |
| BR-08 | An unassigned self-filed draft may be picked up by any Case Manager; a draft assigned to a named Case Manager is theirs alone |
| BR-09 | Duplicate detection on intake currently uses the email address only; a name-plus-date-of-birth rule is not yet built |
| BR-10 | Two-step verification is mandatory for staff accounts; the requirement for citizen accounts depends on the environment |
| BR-11 | Administrators create staff accounts only; citizen accounts are self-registered |
| BR-12 | At most one survey invitation per case, agency, and referral |
| BR-13 | Survey invitations are sent automatically when a referral completes, unless feedback is switched off in settings |
| BR-14 | Report figures are cached briefly per user, so identical questions cost less to answer |
| BR-15 | Every export attempt — allowed or blocked — is recorded in the activity history |
| BR-16 | Very large report exports are refused with an explanatory message instead of being produced |
| BR-17 | Activity-history spreadsheet export is administrator-only and requires an explicit date range within the retention window |
| BR-18 | Unscanned uploads are accepted by default; operators must opt in to blocking them — to be confirmed per environment |
| BR-19 | Citizens are taken to their own portal, never to the staff dashboard |
| BR-20 | Referral and report lists are currently scoped only for partner agencies, so other signed-in users may see broader lists than intended — owner to confirm |

## 7. Reporting, search, and exports

### 7.1 Reporting
- Dashboards give each role its own headline figures; the staff dashboard and the citizen portal are separate views.
- The reports screen offers date, geography (province/city), and agency filters, with figures for intake, cases, referrals, and outcomes. Geography filtering is intended for Case Managers; partners see their own agency's figures only.
- Figures refresh automatically on a short cycle, so a change of filter may take a few seconds.

### 7.2 Search and lookup
- Case list and client list can be searched and filtered by the people allowed to see them.
- Citizens locate their case by tracker number; they never browse other people's records.
- The help centre is searchable, and the chatbot answers questions by matching them to the help content.
- Address selection uses the standard Philippine address reference data shipped with the application.

### 7.3 Exports

| Export | Who | Format | Availability |
|---|---|---|---|
| Referral list | Case Manager, Administrator, Agency staff (own agency) | Excel | Available |
| Case list | Case Manager, Administrator | Excel | Available |
| Client list | Case Manager, Administrator, Agency staff (own agency) | Excel | Available |
| Single case | Case Manager, Administrator | PDF | Available |
| Reports | Case Manager, Administrator, Agency staff (own agency) | PDF and Excel | Available |
| Reports | Anyone | CSV spreadsheet | Not available |
| Activity history | Administrator, explicit date range | CSV spreadsheet | Available |
| Full-system workbook | Administrator | Excel (multiple sheets) | Not available |

## 8. Automatic background work

All of the following happen on their own schedule; no user has to trigger them, and no technical knowledge is required to rely on them:

| Automatic activity | Cadence (plain) |
|---|---|
| Overdue-referral reminders pushed to the responsible people | On schedule, when a referral passes its target |
| Emails sent in the background after the relevant event | Continuous |
| Survey invitations sent when a referral completes | On completion (can be switched off in settings) |
| Clean-up of old system logs | Daily |
| Removal of orphaned uploaded files | Daily |
| Removal of cases that have been in the trash long enough | Daily |
| Archive and prune of activity-history records past the retention window | Monthly |
| Integrity check of the activity history | Weekly |
| Heartbeat that tells operators the scheduler is alive | Every minute |

A written data-retention and disposal policy document has not been produced yet, even though the clean-ups themselves run automatically (§11).

## 9. Notifications and general dependencies

### 9.1 What triggers a notification

| Business event | Who is informed |
|---|---|
| A case is created or published | The citizen and the Case Manager |
| A case's status changes | The citizen and staff on the case |
| A referral is created or its status changes | The receiving agency and the Case Manager |
| A referral passes its target time | The responsible agency and Case Manager |
| A milestone is recorded | The Case Manager |
| A document request activity occurs | The citizen and the agency |
| A new self-filed intake arrives | The Case Managers (in-app) |
| An invitation, email change, or verification code is issued | The recipient, by email |
| A public enquiry is submitted | The office, by email |
| An operational alert occurs | Administrators (in-app) |

### 9.2 General dependencies

| Dependency | What the system needs it for |
|---|---|
| Email delivery provider | All outbound mail: notifications, invitations, verification codes, survey links |
| Secure document storage | Case and referral documents, generated export files |
| Philippine address reference data | Address selection in intake and client records, geographic report filters |
| Bot protection on public forms | Intake, tracking, contact form, chat, surveys |
| Scheduled jobs service | Everything in §8 |
| Monitoring | Operator visibility that the service is healthy |

## 10. Functionality matrix

| Service | What it does | Who uses it | Availability | Notes |
|---|---|---|---|---|
| Sign-in and sign-out | Email + password sign-in, session ends on sign-out | All roles | Available | Repeated failed attempts are temporarily blocked |
| Password recovery | Email link to set a new password | All roles | Available | — |
| Two-step verification | Second sign-in step with authenticator code and backup codes | All roles (mandatory for staff) | Partly available | Whether citizens must enrol outside the live environment is to be confirmed |
| Account invitation | Administrator invites staff; link valid one week | Administrator | Available | No public staff sign-up |
| Account lifecycle | Create, edit, deactivate, reactivate, verify, reset two-step setup | Administrator | Available | Administrator cannot remove their own account or the last administrator |
| Citizen self-registration | Citizen creates an account before filing a case | Public / OFW | Available | Confirmed by an email code |
| Public intake | Signed-in citizen files a case request; goes to the review queue | OFW, Case Manager | Available | Account and sign-in required before filing |
| Duplicate check on intake | Flags a repeat submission by email address | System | Partly available | Name + date-of-birth check not built |
| Case handling | Draft, review, publish, status change, archive, close, delete drafts | Case Manager, Administrator | Available | Agency staff may view referred cases only |
| Intake review | Accept or reject a self-filed request | Case Manager, Administrator | Available | — |
| Client registry | Master record: addresses, employment, next of kin | Case Manager, Administrator; Agency staff (own referrals) | Available | Direct deletion of a client record is not offered — to be confirmed |
| Referrals | Create and send with the OFW's documents; the receiving agency picks the service, then progresses, completes, or rejects it; milestones | Case Manager, Administrator; Agency staff (own referrals — picks the service and works it) | Partly available | Record access is restricted correctly; the list itself is currently visible more broadly — owner to confirm |
| Referral deletion | Remove a referral | Case Manager, Administrator | Available | — |
| Inter-agency message thread | Discussion between agencies on a referral | Agency staff | Available | Case Managers and Administrators deliberately excluded |
| Referral comments | Comments and replies visible to staff | Case Manager, Administrator, Agency staff | Available | — |
| Citizen document requests | Ask the citizen for documents via temporary link | Agency staff | Available | Links can be revoked; locked once the referral or case is finished |
| Overdue referral follow-up | List past-target referrals; send reminders | Case Manager, Administrator, Agency staff (own) | Available | Citizens have no access |
| Agency service catalogue | Each agency maintains its own list of services and picks from it when a referral arrives | Agency staff | Available | — |
| Global service list | Shared list of services | Administrator | Available | — |
| Survey form builder | Create and edit questionnaires | Agency staff | Available | — |
| Survey responses | View citizen feedback | Case Manager, Administrator, Agency staff (own) | Available | — |
| Survey invitations | Sent automatically when a referral completes | System | Available | One per case, agency, and referral |
| Public case tracking | Follow own case on the public tracker (tracking number + email code) and in the portal when signed in | OFW | Available | Read-only |
| Notifications | In-app feed plus matching emails | All roles | Available | Citizens have their own feed |
| Staff dashboard | Headline figures for operations | Case Manager, Administrator, Agency staff | Available | Citizens are taken to their own portal |
| Reports | Filtered figures by date, geography, agency | Case Manager, Administrator, Agency staff (own) | Partly available | Citizens can currently still reach this area — owner to confirm |
| Report export (PDF/Excel) | Download report figures | Case Manager, Administrator, Agency staff (own) | Available | Very large exports are refused with a message |
| Report export (CSV) | Download report figures as CSV | All roles | Not available | Planned, not built |
| Case / client / referral exports | Excel lists and case PDF | Case Manager, Administrator; Agency staff (own, where allowed) | Available | — |
| Activity history | Who did what, append-only | Case Manager, Administrator, Agency staff (own) | Available | Whether Case Managers see only their own or the office-wide history is to be confirmed |
| Activity history export | CSV with an explicit date range | Administrator | Available | — |
| Full-system workbook | One workbook with all data | Administrator | Not available | File is prepared but never delivered to the administrator |
| Administration: agencies, accounts, reference lists, settings | Maintain master data and platform settings | Administrator | Available | — |
| System operations | Logs, email delivery log, maintenance, security settings | Administrator | Available | Session eviction via deactivation or credential reset |
| Help centre | Searchable help articles | All roles (public too) | Available | — |
| Chatbot | Answers by matching the help content | All roles (public too) | Partly available | Chat transcripts kept for supervisor review are not built |
| Document storage | Safe upload, type validation, short-lived download links | Case Manager and Administrator (uploading); Agency staff (viewing and downloading files on their own referrals) | Partly available | Malware scanning depends on deployment settings — to be confirmed |
| Automatic clean-ups and checks | Logs, orphaned files, trashed cases, archive/prune, integrity check | System | Available | — |
| Contact form | Public enquiry reaching the office | Public | Available | Bot-protected |
| Email delivery tracking | Delivery status log with resend | Administrator | Available | — |
| Real-time on-screen updates | Live refresh without reloading | All roles | Not available | Planned |
| Data-retention policy document | Written governance for how long data is kept | Owner | Not available | Automatic clean-ups exist; the policy document does not |
| Written export-access policy | Documented rule on who may export what | Owner | Not available | Mechanical controls (roles, audit, limits) exist |

## 11. Limitations and clarifications (plain gaps)

1. **Some lists are currently visible more broadly than intended — owner to confirm.** The referral list, the reports area, and certain client lookups can be reached by signed-in users outside the three staff roles. Individual records remain protected; the *lists* do not. The project's own source material contains two conflicting statements about this (one calls it "available to all roles", another calls it a priority bug), so the intended rule needs an owner decision.
2. **The full-system workbook is promised but never delivered — to be confirmed.** The administrator is told "you will be notified when the file is ready", the file is prepared in the background, but no notification arrives and there is no way to download it. Either the delivery step is missing or the message is wrong.
3. **Report figures cannot be downloaded as CSV — not available.** PDF and Excel downloads exist; the spreadsheet (CSV) option does not.
4. **Duplicate case detection by name and date of birth — not available.** Only the email-address check exists today.
5. **Chat transcripts are not kept for supervisor review — not available.**
6. **Malware scanning of uploaded documents depends on deployment settings — to be confirmed.** Out of the box, uploads are accepted without an active scanner unless operators opt in to blocking them.
7. **Two-step verification for citizen accounts depends on the environment — to be confirmed.** It is mandatory for staff in the live system; the citizen rule varies with settings.
8. **A written data-retention and disposal policy has not been produced — not available**, although the clean-ups themselves run automatically.
9. **A written policy on who may export data has not been produced — not available**, although roles, limits, and audit records on exports already work.
10. **Direct deletion of a client record is not offered — to be confirmed** whether that is deliberate (records are archived rather than removed).
11. **Instant on-screen updates without refreshing — not available.** Screens must be refreshed to show new activity.
12. **A leftover public sign-up screen exists but is not connected — owner to confirm removal.** Citizens register before filing, so this screen can never be reached; it would not work even if it were.
