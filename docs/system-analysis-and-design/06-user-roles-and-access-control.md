# 06 — User Roles and Access Control

| | |
|---|---|
| **Version** | 1.1.0 |
| **Date** | 2026-11-01 |
| **Status** | CURRENT |
| **Audience** | DICT reviewers, capstone panelists, administrators, business stakeholders |
| **Scope** | The four user roles explained as job functions, how accounts are created and invited, signing in and out (including two-step verification and password recovery), who approves what, who sees whose records, create/view/edit/close abilities per area, administrator powers, session and security behaviour, the permission matrix, and plain-language gaps |
| **Out of scope** | Module-by-module functionality and availability (see document 05 in this series); technical integration detail (document 07) |

---

## 1. How to read this document

- Everything is described as a job function and a business behaviour. No technical implementation detail is included by design.
- The permission matrix in §10 uses only these values:
  - **Can do** — the role may perform the activity.
  - **Cannot do** — the role is denied.
  - **Sometimes (with note)** — allowed only in the situation described in the note; every "Sometimes" cell carries one.
  - **Not relevant** — the activity is not part of that role's job (it belongs to a different screen or a different portal).
  - **To be confirmed** — the intended rule is not settled; see §11.

## 2. The four roles

| Role name used here | Job function | Who the person is |
|---|---|---|
| **Case Manager** | DMW case manager | DMW Region VII staff member who owns the case file for each OFW |
| **Agency Staff** | Partner-agency staff | Focal point at OWWA, DOLE, TESDA, DSWD, DOH, a law centre, or an LGU |
| **Administrator** | System administrator | DMW-appointed person who runs the platform itself |
| **OFW** | OFW citizen | The worker (or their next of kin) receiving assistance |

Each person holds **exactly one** role. There are no combined roles, no sub-roles, and no team structures — a person's job title decides what they can do.

## 3. What each role does

### 3.1 Case Manager (DMW case manager)
- Reviews the intake queue of self-filed requests and **accepts or rejects** each one.
- Creates cases linked to the OFW: review of pending filings, approval or rejection, status changes, closure, archiving, reopening, and the trash-and-restore step.
- Keeps the client record up to date (addresses, employment, next of kin) and uploads case documents.
- Creates referrals to partner agencies, attaching the OFW's available documents (the receiving agency picks the service, not the office), monitors their progress, chases overdue referrals, and can send reminders.
- Leaves comments and replies on referrals, views reports and exports, and reviews the activity history connected to their work.
- Does **not** read the discussion threads agencies hold with each other — those are deliberately for agencies only.

### 3.2 Agency Staff (partner-agency focal point)
- Works **only** the referrals sent to their own organisation; nothing else in the system is their lane.
- Picks the service for a referral from their own agency's list of available services — the sending office attaches the documents but does not choose the service.
- Updates referral status, milestones, and requirements; messages the other agencies on that referral.
- Asks the OFW for specific documents through a temporary private link, then completes, cancels, or reopens that request.
- Views the case and client details reachable through their own referrals (read-only for the case itself).
- Maintains their agency's own service catalogue and builds the feedback survey forms their agency sends out.
- Views their own overdue referrals, survey responses, and figures in reports, and comments on referrals.
- Cannot create, edit, close, or delete case files; cannot create or delete referrals; has no screen to add or replace files or to view earlier copies of files.

### 3.3 Administrator (system administrator)
- Can do everything a Case Manager can do with cases and referrals, **plus** run the platform:
  - creates, invites, edits, deactivates, and reactivates staff accounts; verifies accounts; resets a person's two-step verification when they lose their device;
  - manages partner agencies, the shared list of services, and the reference lists (case categories, statuses, issues);
  - changes platform settings and refreshes the help content so updated articles appear in the chatbot;
  - switches maintenance mode and adjusts security settings;
  - signs a person out everywhere by deactivating their account or resetting its password or two-step verification;
  - exports the activity history as a spreadsheet, and requests the full-system workbook;
  - views agency details (Case Managers may view these read-only as well).
- **Administrator is not above the rules:** they must still sign in with two-step verification, cannot switch off their own two-step verification in the live system, cannot delete their own account, and cannot remove the last remaining administrator.

### 3.4 OFW (citizen)
- Creates an account and signs in (with verification and the two-step check where required) before filing a case request from the portal.
- Signs in to their own portal to see **only their own** case, its milestones, their notifications, and their profile.
- Answers agency document requests through the temporary link or their portal, and completes satisfaction surveys.
- Follows their case on the public tracker (tracking number plus a one-time email code) and in the portal when signed in.
- Cannot see other people's cases, cannot create referrals, cannot see reports, and has no access to any staff screen.

## 4. Accounts: creation and invitation

| Path | Who uses it | What happens |
|---|---|---|
| Administrator creates an account | Administrator | Account exists immediately; only staff roles (Case Manager, Agency Staff, Administrator) can be created |
| Administrator sends an invitation | Administrator | The invitee receives an email with a link valid for **one week**, sets a password, and finishes setup |
| Citizen self-registration | The public | Happens before filing a case; the email address is confirmed with a one-time code |
| Public sign-up for staff | Nobody | There is no open staff registration |

- Administrators can also edit details, deactivate, reactivate, mark an account as verified, reset someone's two-step verification, and change an account's email (confirmed by a one-time code).
- An administrator can never create a citizen account — citizens register themselves.
- **Deactivation takes effect at once:** a deactivated person is signed out the next time they use the system, even if they were already signed in.

## 5. Signing in, two-step verification, and password recovery

1. **Ordinary sign-in.** Email address + password.
2. **Two-step verification.** After the password, staff enter a 6-digit code from an authenticator app on their phone or computer. At enrolment they receive a set of **backup recovery codes** to keep somewhere safe, for when the device is lost. If the device is lost, an administrator can reset the setup.
   - Mandatory for every account, citizen included, in the live environment; it cannot be switched off there.
   - Outside the live environment, enrolment enforcement is configurable and defaults to staff accounts.
3. **Password recovery.** "Forgot password" → an email arrives with a link → a new password is chosen. The link expires and can be used once.
4. **Changing your password** forces a fresh sign-in, including the two-step step.
5. **Changing your email** is confirmed with a one-time code sent to the new address.
6. **Signing out** ends the session immediately.
7. **Abuse protection.** Repeated failed sign-ins, repeated password-reset requests, repeated wrong verification codes, and automated submissions on public forms (intake, tracking, surveys, contact form, chat) are all temporarily slowed or blocked.

## 6. Who approves what

| Decision or approval | Who can do it |
|---|---|
| Review a pending filing: approve it into the case register, or reject it with a reason | Case Manager, Administrator |
| Close or archive a case | Case Manager, Administrator |
| Create and send a referral to a partner agency, attaching the OFW's available documents | Case Manager, Administrator |
| Pick the service for a received referral, from that agency's own list | The receiving agency only — the sending office never picks the service |
| Accept, gate, reject, progress, or complete a referral | The receiving agency that is working it; the Case Manager or Administrator can also progress and complete a referral once the agency has engaged |
| Accept a staff invitation and activate an account | The invited person, then the Administrator |
| Deactivate, reactivate, or verify an account | Administrator |
| Reset someone's two-step verification | Administrator |
| Sign another person out everywhere | Administrator, by deactivating the account or resetting its password or two-step verification |
| Send a satisfaction survey | Automatically, when a referral completes (switchable in settings) |

**Lock rule.** Once a referral is completed or rejected, or a case is closed or archived, nobody can send the citizen new document requests or edits for that record.

## 7. Who sees whose records

| Role | Sees |
|---|---|
| **Case Manager** | The office's cases as a whole, including pending filings awaiting review. Only the trash list and restoration are limited to the person who deleted the case. Their activity history aggregates the cases they handled themselves. |
| **Agency Staff** | Only their own organisation's referrals, and the cases and clients reachable through those referrals. Their audit view covers their own referrals and their cases. Their figures in reports are limited to their agency. |
| **Administrator** | Everything: all cases, referrals, clients, accounts, agency data, settings, and the complete activity history with its export. |
| **OFW** | Only their own case, milestones, notifications, survey links, and profile — nothing about anyone else, ever. |

If someone tries to open a record that is not theirs, the system refuses and shows an access-denied page rather than the data.

## 8. Create, view, edit, and close — by area

| Area | Case Manager | Agency Staff | Administrator | OFW |
|---|---|---|---|---|
| **Case files** | Creates, reviews and approves or rejects, edits, closes, archives, reopens, moves to trash and restores | Views referred cases only | Same as Case Manager | Views own case in own portal only |
| **Client records** | Creates through intake/case flows, edits, exports | Views those reached through own referrals | Same as Case Manager | Views own profile only |
| **Referrals** | Creates (with the OFW's documents attached), updates, deletes | Picks the service from own agency's list; updates own (status, milestones); cannot delete | Creates, updates, deletes | No part — the referral list is restricted to staff roles |
| **Case documents** | Uploads, views, deletes | Views/downloads those on own referrals | Same as Case Manager | No part |
| **Citizen document requests** | No part | Creates, completes, cancels, reopens, sends and revokes links | Views the list; may revoke a link | Replies and uploads through the link |
| **Service catalogue** | No part | Own agency's catalogue | The shared global list | No part |
| **Survey forms** | Views responses | Builds forms; views own responses | Views responses | Answers the survey |
| **Reports and exports** | Full access | Own agency's figures and lists | Full access | No part — the reports area is restricted to staff roles |
| **Accounts, agencies, settings, activity history** | No part | No part | Full powers | No part |
| **Own profile, password, notifications** | Yes | Yes | Yes | Yes |
| **Help centre and chatbot** | Yes | Yes | Yes | Yes |

## 9. Administrator powers (summary)

**Can:**
- create/invite/edit/deactivate/reactivate staff accounts, verify them, reset their two-step verification, and change an account's email with code confirmation;
- maintain partner agencies (including reactivating a retired one), the shared service list, and all reference lists;
- change platform settings and refresh the help content used by the chatbot;
- monitor the system: system health, the email delivery log with per-message detail, failed jobs with retry, and the security log;
- switch maintenance mode and adjust security settings (application log files remain operator-side; there is no resend action);
- sign a person out everywhere by deactivating their account or resetting its password or two-step verification;
- export the activity history as a spreadsheet (with an explicit date range) and view the complete history;
- do everything a Case Manager can do with cases, referrals, clients, and reports.

**Cannot:**
- create a citizen account (citizens self-register);
- delete their own account, or delete the last remaining administrator;
- switch off their own two-step verification in the live system, or sign in without the second step;
- deactivate or reset the credentials of their own account from the Users screen;
- edit or delete entries in the activity history (it is append-only for everyone).

## 10. Permission matrix

Reading: **Can do** / **Cannot do** / **Sometimes (with note)** / **Not relevant** / **To be confirmed**.

| Activity | Case Manager | Agency Staff | Administrator | OFW |
|---|---|---|---|---|
| Sign in and complete two-step verification | Can do | Can do | Can do | Can do |
| Set up two-step verification and keep backup codes | Can do | Can do | Can do | Can do |
| Edit own profile and change own password | Can do | Can do | Can do | Can do |
| Change own email (confirmed by code) | Can do | Can do | Can do | Sometimes — email is read-only in the citizen portal; a change goes through the office |
| Recover a forgotten password by email | Can do | Can do | Can do | Can do |
| Use the staff dashboard | Can do | Can do | Can do | Not relevant — citizens go to their own portal |
| Use the citizen portal (own case, notifications) | Not relevant | Not relevant | Not relevant | Can do |
| Read own notifications | Can do | Can do | Can do | Can do |
| Review the intake queue: accept or reject a self-filed request | Can do | Cannot do | Can do | Not relevant |
| Create a case file | Can do | Cannot do | Can do | Not relevant |
| Edit a case file, or approve a pending filing into the case register | Can do | Cannot do | Can do | Not relevant |
| Close or archive a case file | Can do | Cannot do | Can do | Not relevant |
| Move an archived case to trash, and restore it | Can do — own deletions only in the trash list | Cannot do | Can do | Not relevant |
| Open a single case file | Can do | Sometimes — only cases referred to their own agency | Can do | Not relevant — own case appears in their portal |
| Upload or delete case documents | Can do | Cannot do | Can do | Not relevant |
| View or download case documents | Can do | Sometimes — only on their own referrals | Can do | Not relevant |
| Maintain client records (addresses, employment, next of kin) | Can do | Sometimes — view-only, reached through own referrals | Can do | Not relevant |
| Export case or client lists (spreadsheet) | Can do | Sometimes — client list only, own agency | Can do | Not relevant |
| Export a single case as PDF | Can do | Cannot do | Can do | Not relevant |
| Create and send a referral (with the OFW's documents attached) | Can do | Cannot do | Can do | Cannot do |
| Pick the service for a received referral, from that agency's own list | Cannot do — the agency picks | Can do | Cannot do — the agency picks | Not relevant |
| Accept, gate, or reject a received referral | Cannot do — the receiving agency decides | Sometimes — own referrals only | Cannot do — the receiving agency decides | Not relevant |
| Update a referral's status, milestones, requirements | Can do | Sometimes — own referrals only | Can do | Cannot do |
| Delete a referral | Can do | Cannot do | Can do | Cannot do |
| View the referral list | Can do | Sometimes — own referrals only | Can do | Cannot do — the list is restricted to staff roles |
| Export the referral list | Can do | Sometimes — own referrals only | Can do | Cannot do |
| Upload, replace, or remove referral attachments | Can do | Cannot do — no screen for this; to be confirmed whether this should ever be offered | Can do | Cannot do |
| Discussion thread between agencies | Cannot do — by design | Can do | Cannot do — by design | Not relevant |
| Comments and replies on a referral | Can do | Can do | Can do | Cannot do |
| Ask a citizen for documents (create/complete/cancel a request) | Cannot do | Sometimes — own referrals only | Cannot do | Not relevant |
| View the list of document requests | Cannot do | Sometimes — own referrals only | Sometimes — alongside the agency | Not relevant |
| Issue or reissue a temporary access link | Cannot do | Sometimes — own referrals only | Cannot do | Not relevant |
| Revoke a temporary access link | Cannot do | Sometimes — own referrals only | Sometimes — may also revoke | Not relevant |
| View overdue referrals, and send reminders | Can do, including reminders | Sometimes — own referrals only, view only | Can do, including reminders | Not relevant |
| Maintain their own agency's service catalogue | Not relevant | Can do | Not relevant — admin keeps the global list | Not relevant |
| Maintain the shared global service list | Cannot do | Cannot do | Can do | Not relevant |
| Build and edit survey forms | Not relevant | Can do | Not relevant | Not relevant |
| View survey responses | Can do | Sometimes — own agency's responses | Can do | Not relevant |
| View reports | Can do | Sometimes — own agency's figures | Can do | Cannot do — the reports area is restricted to staff roles |
| Export reports as PDF or Excel | Can do | Sometimes — own agency's figures | Can do | Cannot do |
| Export reports as CSV | Cannot do | Cannot do | Cannot do | Cannot do |
| View the activity (audit) history | Sometimes — connected to their own work | Sometimes — own referrals and their cases | Can do | Cannot do |
| Export the activity history as a spreadsheet | Cannot do | Cannot do | Can do | Cannot do |
| Manage staff accounts (create, invite, deactivate, reset two-step) | Cannot do | Cannot do | Can do | Not relevant |
| Manage partner agencies | Sometimes — view-only details | Cannot do | Can do | Not relevant |
| Manage reference lists (categories, statuses, issues) | Sometimes — may add a new issue type while working a case | Cannot do | Can do | Not relevant |
| Change platform settings | Cannot do | Cannot do | Can do | Not relevant |
| Review system health, the email delivery log, failed jobs, and the security log | Cannot do | Cannot do | Can do | Not relevant |
| View the activity history export, security settings, and maintenance mode | Cannot do | Cannot do | Can do | Not relevant |
| Sign another person out everywhere | Cannot do | Cannot do | Can do | Not relevant |
| Request the full-system workbook | Not relevant | Not relevant | Cannot do — the workbook workflow is retired | Not relevant |
| Use the help centre and chatbot | Can do | Can do | Can do | Can do |
| Submit an intake or follow a case in the portal (account and sign-in required) | Not relevant — public pages, not part of their job | Not relevant | Not relevant | Can do |
| Answer a satisfaction survey | Not relevant | Not relevant | Not relevant | Can do |
| Contact the office through the public contact form | Not relevant | Not relevant | Not relevant | Can do |

## 11. Gaps in plain language

These are open items, not settled rules. None of them names a screen location or a technical mechanism — they are stated as business gaps for the owner to resolve.

1. **Settled: the referral list, the reports area, and the staff client picker are restricted to staff roles.** Signed-in citizens cannot reach them. Individual referral records remain protected by a second check inside each record, so a future route added without the staff gate still cannot expose another agency's referral.
2. **Settled: referral discussions and the activity history are access-checked.** Agency discussions require membership of the participating agency; activity reads are role-gated and scoped per role (Case Managers see their own handled cases; Agency Staff see their own referrals).
3. **Settled: Case Manager activity history covers the cases they handled.** Whether they should also see the office-wide history is a product decision, not an open defect.
4. **Settled: citizens enrol in two-step verification in the live environment** — it applies to every role there and cannot be switched off; outside it, enforcement is configurable and defaults to staff.
5. **Whether uploaded documents are actively scanned for malware depends on deployment settings — to be confirmed.** Scanning is available but off by default, and an unavailable scanner lets uploads through unless the deployment changes that.
6. **Citizen registration is live.** Citizens create an account, review their details, and confirm their email with a code before filing. There is no separate, disconnected sign-up screen.
7. **Duplicate case detection compares email addresses only, and only on the staff client picker.** No name-plus-date-of-birth check and no active-case guard on citizen intake exist yet — **owner to confirm** whether that is acceptable.
8. **Citizens changing their own email address has no entry point in the citizen portal — owner to confirm** whether that is deliberate; for now only staff-side email changes carry the code confirmation.
9. **Partial hardening to review — owner to confirm.** Staff screens are the only way to add, replace, or remove referral attachments, but the underlying actions accept agency staff requests too, and a few referral discussion and download actions rely on the in-record check rather than a role gate. No exposure has been found; the ask is whether to close them defensively.
10. **Two administrator actions are impossible by design and should be understood as such:** an administrator cannot remove their own account, and cannot remove the last remaining administrator. There is currently no recovery path documented if the last administrator loses access — **owner to confirm** the intended recovery process.

## 12. Session and security behaviour (in words)

| Concern | How it behaves |
|---|---|
| Signing out | Ends the session immediately; the browser cannot continue as that user |
| Session expiry | Sign-in sessions end automatically after a period of time (currently about two hours), so an unattended screen does not stay signed in forever |
| Deactivated or removed accounts | Signed out at the next action, even if they were already signed in |
| Two-step verification loss | The person's authenticator setup can be reset by an administrator; backup codes are the self-service fallback |
| Password change | Forces a fresh sign-in, including the two-step step |
| Failed attempts | Sign-ins, password resets, verification codes, and public-form submissions are temporarily blocked after too many tries |
| Administrator oversight | The administrator can sign a person out everywhere by deactivating the account or resetting its password or two-step verification; there is no separate list of who is currently signed in |
| Page authenticity | The system checks that submissions come from its own pages before accepting them |
| Personal data exposure | Someone who opens a record that is not theirs is shown an access-denied message, not the data |
| Citizen privacy | Citizens only ever see their own case: every lookup in their portal is limited to their own record |
