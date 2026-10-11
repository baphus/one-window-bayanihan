# 08 — Strengths, Risks, and Next Steps

| | |
|---|---|
| **Version** | 1.0.0 |
| **Date** | 2026-10-11 |
| **Scope** | A business-level review of the system: what works well, where the risks and gaps are, what to confirm with the owners, and a short roadmap |
| **Audience** | Decision makers, DICT reviewers, capstone panelists, business stakeholders |
| **Out of scope** | Programming detail, code references, and technical severity scales |

> This document deliberately avoids technical terms. Items are ordered by plain priority — most important first — rather than by technical severity codes. See the [reading guide](README.md).

---

## Table of Contents

1. [What works well](#1-what-works-well)
2. [Risks and gaps](#2-risks-and-gaps)
3. [What to confirm with the owners](#3-what-to-confirm-with-the-owners)
4. [Recommended next steps](#4-recommended-next-steps)

---

## 1. What works well

- **The design separates concerns cleanly.** Handling of input, business rules, and stored records are kept apart, so changes to one area are less likely to break another, and the rules are easier to test and audit.
- **Core flows check their input thoroughly.** Self-filing, case work, referrals, surveys, reports, and sign-in all validate information against shared, reusable rules before accepting it — including domain checks such as age and employment dates, address within the served region, and required consent.
- **Concurrent updates are protected.** When two people change the same referral or client request at the same time, the system takes turns rather than silently overwriting one another's work.
- **Emails follow the record, not the other way round.** Messages are only dispatched after the related record has been saved, so a rolled-back action never produces a phantom email.
- **The activity trail is tamper-evident.** It is append-only, chained so that later edits to earlier entries can be detected, checked on a schedule, and restricted so one organisation's records cannot read another's.
- **Documents are screened before they are kept.** Every upload is scanned for malware before storage, file types are verified, download links are short-lived, and unused files are cleaned up automatically.
- **Sign-in security is strong.** Multi-factor sign-in with an authenticator app, hashed backup recovery codes, session protections against reuse after compromise, and a bot check on public forms and sign-in.
- **Inbound messages from outside services are authenticated.** Delivery notifications from the email provider are verified before they are accepted, rejected outright when unauthenticated, and duplicate deliveries are ignored rather than applied twice.
- **Access boundaries are enforced consistently at the top level.** Roles are checked strictly, partner agencies only see the referrals shared with them, and OFWs only see their own case.
- **The parts behind the screens are well covered by automated tests**, alongside scheduled housekeeping, a health check that covers the database, email, and background jobs, and a heartbeat that proves the scheduled jobs are still running.
- **Errors are handled in a user-friendly way.** Friendly pages instead of raw errors, each carrying a reference number that matches the operators' records for one incident.

## 2. Risks and gaps

Ordered most important first. These are stated in business terms; each is expanded with its technical detail elsewhere in the project's own records.

1. **Access lists are broader and looser than they look.** The role attached to a user account is free text rather than a fixed list, so a mistyped or injected value behaves unpredictably instead of being rejected outright. Some case and notification pages can be opened before the user has confirmed their email address — possibly deliberate, but not written down as an accepted exception. And the rule that limits a partner agency to its own referrals depends on developers remembering it in each place, rather than on one guard that cannot be forgotten.

2. **An export is only recorded after it succeeds.** Spreadsheet and printable exports are written to the activity trail only *after* the file has been produced. If generation fails or is stopped partway, nothing shows who asked for what — a hole in an otherwise strong audit trail, over exactly the most sensitive bulk reads.

3. **An orphan registration page.** A public sign-up page exists in the interface but is not connected to anything: it cannot be reached through any real link, and if it were reached it would not work. Dead screens like this confuse reviewers and should be removed or properly wired up.

4. **Counts differ between documents.** Totals quoted across the document set — and in supporting project documents — do not agree with each other, and nothing checks them automatically. An auditor who compares two documents will find different figures, which undermines confidence in the whole set even where each figure was once correct.

5. **Heavy reports can take the whole site down.** Report and document generation happens while the user is waiting, sharing the same server with everything else. A burst of simultaneous exports can exhaust it and make the whole portal unavailable — the failure mode the health check was added to detect.

6. **Administrative screens validate less consistently than core flows.** The main public and case flows use shared, reusable validation rules; several administrative screens check their inputs inline instead. Rules then drift apart, error messages differ between similar screens, and a manual audit has to read every screen to be sure.

7. **Testing of the visible screens is nearly empty next to the behind-the-scenes tests.** The parts behind the screens are covered broadly, but what users actually see has almost no automated coverage — regressions in the visible layer (confirmation messages, unsaved-change warnings, form wiring) can ship unnoticed because the other tests cannot catch them.

8. **Deleting a message record can erase its delivery evidence.** When a record of a sent message is deleted, the delivery-status records belonging to it are cleared too — so the deletion also removes proof of what was sent and what bounced.

9. **Retention and clean-up have no written governance.** The system automatically archives, prunes, and purges on a schedule, but no policy document says how long things are kept, who approved it, or what must be preserved for audit. Retired records also linger in the stored data, which muddies the business information model.

10. **Concentration on a handful of outside services.** Email delivery, file storage, bot protection, monitoring, and address data each carry a visible capability with them. File storage or email outages have the widest impact. The bot check's failure mode is the opposite of the others: instead of degrading, it blocks legitimate visitors.

11. **Two protections depend on configuration rather than being guaranteed.** The rule that keeps one organisation's records away from another is switched on by a setup step performed for each page view — a new part of the system that skips that step would silently lose the protection. Likewise, one-time codes and attempt-limiting depend on the installation pointing at the right shared service; a wrong setting in a new environment can make codes intermittently fail.

12. **Documentation drift is detected but not closed.** The project already tracks its own inconsistencies, which is good practice, but items stay open and nothing fails when figures drift again.

## 3. What to confirm with the owners

- **Access policy:** Is opening case and notification pages before email verification intentional for the OFW portal and notifications? Either document it as an accepted exception or close it.
- **Sign-up policy:** Should the public sign-up page exist at all? Remove it, or connect it to a real registration process.
- **Authoritative figures:** Which single document is the source of truth for structure and feature counts, and who re-confirms them each release?
- **Retention policy:** How long are deleted cases, logs, and archived activity records kept, who approved that, and what must never be pruned?
- **Audit policy for exports:** Should the request be recorded before an export is generated, not only after?
- **Outage procedures:** Who receives monitoring alerts, what is the manual restart procedure, and what do staff tell users when the bot check or email service is down?
- **Hosting shape:** Is the single-server setup acceptable until traffic grows, or should background jobs be separated before the next release?
- **External hosting of images:** Is storing profile pictures and agency logos with an outside image service acceptable under the project's privacy commitments?

## 4. Recommended next steps

A short roadmap in plain order. No identifiers — each step names what to do.

| Order | Recommended step | Why it matters |
|---|---|---|
| **First — before the next release** | Produce heavy reports and documents in the background rather than while the user waits (or cap and queue them under load) | Removes the one weakness that can make the whole portal unavailable |
| **First — before the next release** | Regenerate the data documentation from a real, fresh run so all figures agree, and add an automatic check that fails when they drift | Restores audit trust in the numbers |
| **First — before the next release** | Fix the role attached to an account so only the four valid roles can ever be saved, both where it is entered and where it is kept | Closes the loosest access control gap |
| **First — before the next release** | Move administrative-screen validation onto the same shared, reusable rules used by core flows | Consistent rules and messages; one place to audit |
| **Next** | Start automated coverage of the user interface, beginning with shared behaviour and the highest-traffic forms | Catches visible regressions before release |
| **Next** | Make the rule that keeps one organisation's records to itself fail-safe, rather than dependent on a step that runs for every page view | Removes a silent-loss-of-protection risk |
| **Next** | Record exports in the activity trail before they are generated as well as after | Closes the audit gap on bulk reads |
| **Next** | Write down the accepted access exceptions (unverified-email pages) and the outage fallback procedures | Turns tacit knowledge into reviewable policy |
| **Later** | Remove retired structures and the orphan sign-up page; agree and publish the retention policy | Cleaner information model and documents |
| **Later** | Separate background jobs from the pages people use, and write down what users experience when each outside service is down | Scales cleanly; predictable behaviour during an outage |
| **Later** | Keep this set refreshed each release, with the automatic figure check guarding it | Stops drift from returning |

---

*End of document 08. How information moves in and out of the system is described in [07 — Connections and Exchanges](07-api-and-integration-reference.md).*
