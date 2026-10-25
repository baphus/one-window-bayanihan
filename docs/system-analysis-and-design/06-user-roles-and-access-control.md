# 06 — User Roles and Access Control

| | |
|---|---|
| **Version** | 1.0.0 |
| **Date** | 2026-10-11 |
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
- Creates the single case file per OFW: draft, review, publish, status changes, closure, archiving, and deletion of drafts.
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
   - Mandatory for staff accounts in the live system.
   - For citizen accounts it depends on the environment settings — **to be confirmed** (§11).
3. **Password recovery.** "Forgot password" → an email arrives with a link → a new password is chosen. The link expires and can be used once.
4. **Changing your password** forces a fresh sign-in, including the two-step step.
5. **Changing your email** is confirmed with a one-time code sent to the new address.
6. **Signing out** ends the session immediately.
7. **Abuse protection.** Repeated failed sign-ins, repeated password-reset requests, repeated wrong verification codes, and automated submissions on public forms (intake, tracking, surveys, contact form, chat) are all temporarily slowed or blocked.

## 6. Who approves what

| Decision or approval | Who can do it |
|---|---|
| Accept a self-filed intake into the case register (or reject it) | Case Manager, Administrator |
| Publish a case (move it from draft to active) | Case Manager, Administrator |
| Close or archive a case | Case Manager, Administrator |
| Create and send a referral to a partner agency, attaching the OFW's available documents | Case Manager, Administrator |
| Pick the service for a received referral, from that agency's own list | The receiving agency only — the sending office never picks the service |
| Progress, complete, or reject a referral | Agency Staff (working it); Case Manager, Administrator also may |
| Accept a staff invitation and activate an account | The invited person, then the Administrator |
| Deactivate, reactivate, or verify an account | Administrator |
| Reset someone's two-step verification | Administrator |
| Sign another person out everywhere | Administrator, by deactivating the account or resetting its password or two-step verification |
| Send a satisfaction survey | Automatically, when a referral completes (switchable in settings) |

**Lock rule.** Once a referral is completed or rejected, or a case is closed or archived, nobody can send the citizen new document requests or edits for that record.

## 7. Who sees whose records

| Role | Sees |
|---|---|
| **Case Manager** | The office's cases as a whole. A draft already assigned to another Case Manager is that person's alone; an unassigned self-filed draft may be picked up by any Case Manager. Activity history is connected to their own work (whether they should also see the office-wide history is open — §11). |
| **Agency Staff** | Only their own organisation's referrals, and the cases and clients reachable through those referrals. Their audit view covers their own referrals and their cases. Their figures in reports are limited to their agency. |
| **Administrator** | Everything: all cases, referrals, clients, accounts, agency data, settings, logs, and the complete activity history. |
| **OFW** | Only their own case, milestones, notifications, survey links, and profile — nothing about anyone else, ever. |

If someone tries to open a record that is not theirs, the system refuses and shows an access-denied page rather than the data.

## 8. Create, view, edit, and close — by area

| Area | Case Manager | Agency Staff | Administrator | OFW |
|---|---|---|---|---|
| **Case files** | Creates, edits, publishes, closes, archives, deletes drafts | Views referred cases only | Same as Case Manager | Views own case in own portal only |
| **Client records** | Creates through intake/case flows, edits, exports | Views those reached through own referrals | Same as Case Manager | Views own profile only |
| **Referrals** | Creates (with the OFW's documents attached), updates, deletes | Picks the service from own agency's list; updates own (status, milestones); cannot delete | Creates, updates, deletes | No part (see §11) |
| **Case documents** | Uploads, views, deletes | Views/downloads those on own referrals | Same as Case Manager | No part |
| **Citizen document requests** | No part | Creates, completes, cancels, reopens, sends and revokes links | Views the list; may revoke a link | Replies and uploads through the link |
| **Service catalogue** | No part | Own agency's catalogue | The shared global list | No part |
| **Survey forms** | Views responses | Builds forms; views own responses | Views responses | Answers the survey |
| **Reports and exports** | Full access | Own agency's figures and lists | Full access | No part (see §11) |
| **Accounts, agencies, settings, logs** | No part | No part | Full powers | No part |
| **Own profile, password, notifications** | Yes | Yes | Yes | Yes |
| **Help centre and chatbot** | Yes | Yes | Yes | Yes |

## 9. Administrator powers (summary)

**Can:**
- create/invite/edit/deactivate/reactivate staff accounts, verify them, reset their two-step verification, and change an account's email with code confirmation;
- maintain partner agencies (including reactivating a retired one), the shared service list, and all reference lists;
- change platform settings and refresh the help content used by the chatbot;
- view and download logs, review and resend outbound email, switch maintenance mode, adjust security settings;
- sign a person out everywhere by deactivating their account or resetting its password or two-step verification;
- export the activity history as a spreadsheet (with an explicit date range) and view the complete history;
- request the full-system workbook (delivery gap noted in §11);
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
| Set up two-step verification and keep backup codes | Can do | Can do | Can do | Sometimes — depends on environment settings, to be confirmed |
| Edit own profile and change own password | Can do | Can do | Can do | Can do |
| Change own email (confirmed by code) | Can do | Can do | Can do | Sometimes — no self-service entry in the citizen portal yet |
| Recover a forgotten password by email | Can do | Can do | Can do | Can do |
| Use the staff dashboard | Can do | Can do | Can do | Not relevant — citizens go to their own portal |
| Use the citizen portal (own case, notifications) | Not relevant | Not relevant | Not relevant | Can do |
| Read own notifications | Can do | Can do | Can do | Can do |
| Review the intake queue: accept or reject a self-filed request | Can do | Cannot do | Can do | Not relevant |
| Create a case file | Can do | Cannot do | Can do | Not relevant |
| Edit and publish a case file | Can do | Cannot do | Can do | Not relevant |
| Close or archive a case file | Can do | Cannot do | Can do | Not relevant |
| Delete a draft case file | Can do | Cannot do | Can do | Not relevant |
| Open a single case file | Can do | Sometimes — only cases referred to their own agency | Can do | Not relevant — own case appears in their portal |
| Upload or delete case documents | Can do | Cannot do | Can do | Not relevant |
| View or download case documents | Can do | Sometimes — only on their own referrals | Can do | Not relevant |
| Maintain client records (addresses, employment, next of kin) | Can do | Sometimes — view-only, reached through own referrals | Can do | Not relevant |
| Export case or client lists (spreadsheet) | Can do | Sometimes — client list only, own agency | Can do | Not relevant |
| Export a single case as PDF | Can do | Cannot do | Can do | Not relevant |
| Create and send a referral (with the OFW's documents attached) | Can do | Cannot do | Can do | To be confirmed |
| Pick the service for a received referral, from that agency's own list | Cannot do — the agency picks | Can do | Cannot do — the agency picks | Not relevant |
| Update a referral's status, milestones, requirements | Can do | Sometimes — own referrals only | Can do | To be confirmed |
| Delete a referral | Can do | Cannot do | Can do | Cannot do |
| View the referral list | Can do | Sometimes — own referrals only | Can do | To be confirmed — currently visible more broadly than intended |
| Export the referral list | Can do | Sometimes — own referrals only | Can do | To be confirmed |
| Upload, replace, or remove referral attachments | Can do | Cannot do — no screen for this; to be confirmed whether this should ever be offered | Can do | To be confirmed |
| Discussion thread between agencies | Cannot do — by design | Can do | Cannot do — by design | Not relevant |
| Comments and replies on a referral | Can do | Can do | Can do | To be confirmed |
| Ask a citizen for documents (create/complete/cancel a request) | Cannot do | Sometimes — own referrals only | Cannot do | Not relevant |
| View the list of document requests | Cannot do | Sometimes — own referrals only | Sometimes — alongside the agency | Not relevant |
| Issue or reissue a temporary access link | Cannot do | Sometimes — own referrals only | Cannot do | Not relevant |
| Revoke a temporary access link | Cannot do | Sometimes — own referrals only | Sometimes — may also revoke | Not relevant |
| View overdue referrals and send reminders | Can do | Sometimes — own referrals only | Can do | Not relevant |
| Maintain their own agency's service catalogue | Not relevant | Can do | Not relevant — admin keeps the global list | Not relevant |
| Maintain the shared global service list | Cannot do | Cannot do | Can do | Not relevant |
| Build and edit survey forms | Not relevant | Can do | Not relevant | Not relevant |
| View survey responses | Can do | Sometimes — own agency's responses | Can do | Not relevant |
| View reports | Can do | Sometimes — own agency's figures | Can do | To be confirmed — currently reachable by citizens |
| Export reports as PDF or Excel | Can do | Sometimes — own agency's figures | Can do | To be confirmed |
| Export reports as CSV | Cannot do | Cannot do | Cannot do | Cannot do |
| View the activity (audit) history | Sometimes — connected to their own work | Sometimes — own referrals and their cases | Can do | Cannot do |
| Export the activity history as a spreadsheet | Cannot do | Cannot do | Can do | Cannot do |
| Manage staff accounts (create, invite, deactivate, reset two-step) | Cannot do | Cannot do | Can do | Not relevant |
| Manage partner agencies | Sometimes — view-only details | Cannot do | Can do | Not relevant |
| Manage reference lists (categories, statuses, issues) | Sometimes — may add a new issue type while working a case | Cannot do | Can do | Not relevant |
| Change platform settings | Cannot do | Cannot do | Can do | Not relevant |
| View logs, email delivery log, security settings, maintenance | Cannot do | Cannot do | Can do | Not relevant |
| Sign another person out everywhere | Cannot do | Cannot do | Can do | Not relevant |
| Request the full-system workbook | Not relevant | Not relevant | To be confirmed — file is prepared but never delivered | Not relevant |
| Use the help centre and chatbot | Can do | Can do | Can do | Can do |
| Submit an intake or follow a case in the portal (account and sign-in required) | Not relevant — public pages, not part of their job | Not relevant | Not relevant | Can do |
| Answer a satisfaction survey | Not relevant | Not relevant | Not relevant | Can do |
| Contact the office through the public contact form | Not relevant | Not relevant | Not relevant | Can do |

## 11. Gaps in plain language

These are open items, not settled rules. None of them names a screen location or a technical mechanism — they are stated as business gaps for the owner to resolve.

1. **Some lists are currently visible more broadly than intended — owner to confirm.** The referral list and the reports area can be reached by signed-in users outside the three staff roles, even though individual records remain protected and the navigation does not offer those entries to citizens. The project's own source material disagrees about whether this is intended (one statement calls it "available to all roles", another calls it a priority bug), so a single decision is needed.
2. **Some client lookups are reachable by any signed-in account — owner to confirm.** The client picker used when creating a case is meant for staff, but the underlying lookup is not restricted to them.
3. **Some referral message and activity reads are not restricted either — owner to confirm**, unlike their case-level counterparts, which are staff-only.
4. **The full-system workbook is promised but never delivered — to be confirmed.** The administrator is told they will be notified when the file is ready, but no notification and no download ever appear.
5. **It is not settled whether Case Managers see the office-wide activity history or only their own cases — owner to confirm.** Two parts of the design point in different directions.
6. **Whether citizens must enrol in two-step verification outside the live environment depends on settings — to be confirmed.**
7. **Whether uploaded documents are actively scanned for malware depends on deployment settings — to be confirmed.**
8. **A leftover public sign-up screen exists but is not connected — owner to confirm removal.** Citizens register before filing, so this screen can never be reached; it would not work even if it were.
9. **Duplicate case detection compares email addresses only; a name-plus-date-of-birth check is not built yet.**
10. **Citizens changing their own email address has no entry point in the citizen portal — owner to confirm** whether that is deliberate.
11. **Two administrator actions are impossible by design and should be understood as such:** an administrator cannot remove their own account, and cannot remove the last remaining administrator. There is currently no recovery path documented if the last administrator loses access — **owner to confirm** the intended recovery process.

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
