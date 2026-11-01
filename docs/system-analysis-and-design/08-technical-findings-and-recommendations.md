# 08 — Strengths, Risks, and Next Steps

| | |
|---|---|
| **Version** | 1.1.0 |
| **Date** | 2026-11-01 |
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
- **Document controls need deployment checks.** File types are verified and download links expire. Malware scanning is available but disabled by default, and scan failures are allowed by default. Confirm an active scanner and blocking failures where policy requires.
- **Sign-in security is strong.** Multi-factor sign-in with an authenticator app — mandatory for every role in the live environment — hashed backup recovery codes, session protections against reuse after compromise, and a configurable bot check on public forms and sign-in (currently off by default).
- **Inbound messages from outside services are authenticated.** Delivery notifications from the email provider are verified before they are accepted, rejected outright when unauthenticated, and duplicate deliveries are ignored rather than applied twice.
- **Access boundaries are enforced consistently at the top level.** Roles are checked strictly, referrals and reports are restricted to staff roles, partner agencies only see the referrals shared with them, and OFWs only see their own case.
- **The parts behind the screens are well covered by automated tests**, alongside scheduled housekeeping, a readiness check covering the database, queue backlog, failed jobs, and scheduler heartbeat (not proof of email delivery); its heartbeat shows the scheduler ran recently, not that every scheduled task succeeded.
- **Errors are handled in a user-friendly way.** Friendly pages instead of raw errors, each carrying a reference number that matches the operators' records for one incident.

## 2. Risks and gaps

Ordered most important first. These are stated in business terms; each is expanded with its technical detail elsewhere in the project's own records.

1. **Account roles have a remaining storage-level gap.** Account forms validate approved roles, public registration assigns the citizen role, and access checks reject unknown values. The database still stores unrestricted text, so direct writes can introduce invalid roles. Case and referral lists are role-restricted; agency access also depends on checks for each record. Confirm accepted exceptions for pages available before email verification.

2. **Export records differ by feature.** Reports record requests and outcomes, including blocked or failed generation. Case, client, and referral exports record the request before generation but no separate success or failure. A request record does not prove a file was produced or downloaded.

3. **Public registration is live.** Citizens can reach sign-up, review information, verify their email with a code, and submit registration. Review this real workflow as part of access and privacy testing; it is not a dead screen.

4. **Counts differ between documents.** Totals quoted across the document set — and in supporting project documents — do not agree with each other, and nothing checks them automatically. An auditor who compares two documents will find different figures, which undermines confidence in the whole set even where each figure was once correct.

5. **Heavy reports share resources with the rest of the site.** Generation happens while users wait. Row limits and a per-user export limit reduce load, but simultaneous exports can still compete with pages and background work. A background-report job exists but is not connected to the routed report flow. Load testing must establish safe capacity.

6. **Administrative screens validate less consistently than core flows.** The main public and case flows use shared, reusable validation rules; several administrative screens check their inputs inline instead. Rules then drift apart, error messages differ between similar screens, and a manual audit has to read every screen to be sure.

7. **Screen tests exist; complete browser journeys remain a gap.** There are 57 frontend test files covering pages, components, and shared behaviour, not near-empty coverage. That does not prove complete browser, accessibility, or cross-device coverage. No runnable end-to-end browser suite is configured.

8. **Delivery evidence survives deletion but loses its link.** Deleting a sent-message log leaves delivery-event records in place with no parent link. Retention reviews must account for these detached records.

9. **Retention needs confirmed governance.** Scheduled archiving, pruning, and purging exist, and repository procedures describe retention targets. Documents alone do not prove owner approval, legal acceptance, or actual execution. Confirm periods, exceptions, and operational evidence.

10. **Concentration on a handful of outside services.** Email delivery, file storage, bot protection, monitoring, and address data each carry a visible capability with them. File storage or email outages have the widest impact. The bot check's failure mode is the opposite of the others: instead of degrading, it blocks legitimate visitors.

11. **Two protections depend on configuration rather than being guaranteed.** The rule that keeps one organisation's records away from another is switched on by a setup step performed for each page view — a new part of the system that skips that step would silently lose the protection. Likewise, one-time codes and attempt-limiting depend on the installation pointing at the right shared service; a wrong setting in a new environment can make codes intermittently fail.

12. **Documentation drift is detected but not closed.** The project already tracks its own inconsistencies, which is good practice, but items stay open and nothing fails when figures drift again.

## 3. What to confirm with the owners

- **Access policy:** Is opening case and notification pages before email verification intentional for the OFW portal and notifications? Either document it as an accepted exception or close it.
- **Sign-up policy:** Confirm ownership and acceptance criteria for the live citizen registration and email-verification process.
- **Authoritative figures:** Which single document is the source of truth for structure and feature counts, and who re-confirms them each release?
- **Retention policy:** How long are deleted cases, logs, and archived activity records kept, who approved that, and what must never be pruned?
- **Audit policy for exports:** Should case, client, and referral exports record outcomes as reports already do?
- **Outage procedures:** Who receives monitoring alerts, what is the manual restart procedure, and what do staff tell users when the bot check or email service is down?
- **Hosting shape:** Is the single-server setup acceptable until traffic grows, or should background jobs be separated before the next release?
- **External hosting of images:** Is storing profile pictures and agency logos with an outside image service acceptable under the project's privacy commitments?

## 4. Recommended next steps

A short roadmap in plain order. No identifiers — each step names what to do.

| Order | Recommended step | Why it matters |
|---|---|---|
| **First — before the next release** | Produce heavy reports and documents in the background rather than while the user waits (or cap and queue them under load) | Reduces resource contention; verify capacity under load |
| **First — before the next release** | Regenerate the data documentation from a real, fresh run so all figures agree, and add an automatic check that fails when they drift | Restores audit trust in the numbers |
| **First — before the next release** | Consider a database constraint for valid roles, retaining existing form validation and strict access checks | Protects against invalid direct database writes |
| **First — before the next release** | Move administrative-screen validation onto the same shared, reusable rules used by core flows | Consistent rules and messages; one place to audit |
| **Next** | Extend existing screen tests with critical browser journeys and accessibility checks | Catches visible regressions before release |
| **Next** | Make the rule that keeps one organisation's records to itself fail-safe, rather than dependent on a step that runs for every page view | Removes a silent-loss-of-protection risk |
| **Next** | Add outcomes to case, client, and referral export requests; retain report request and outcome records | Closes the audit gap on bulk reads |
| **Next** | Write down the accepted access exceptions (unverified-email pages) and the outage fallback procedures | Turns tacit knowledge into reviewable policy |
| **Later** | Review retired structures and detached delivery events; confirm and approve retention policy | Cleaner information model and documents |
| **Later** | Separate background jobs from the pages people use, and write down what users experience when each outside service is down | Scales cleanly; predictable behaviour during an outage |
| **Later** | Keep this set refreshed each release, with the automatic figure check guarding it | Stops drift from returning |

---

*End of document 08. How information moves in and out of the system is described in [07 — Connections and Exchanges](07-api-and-integration-reference.md).*
