const content = `# Understanding Case Statuses and Tracker Numbers

Case statuses show the internal lifecycle of a case. Tracker numbers allow clients to check progress without exposing internal record IDs.

![Cases index](/assets/helpdesk/cases-index.png)

## Case lifecycle

Cases move through this lifecycle: **DRAFT → Published → OPEN → CLOSED → Archived**. A draft is prepared by staff, published when it is ready for handling, worked while open, closed when the assistance and documentation are complete, and archived as a retained record.

## Case statuses

| Status | Meaning |
|---|---|
| DRAFT | The case is being prepared and may still need required details. |
| OPEN | The case is active and being handled. |
| CLOSED | The case handling is complete. |
| ARCHIVED | The case is retained for records and no longer active. |

Administrators manage case status references in the admin CaseStatus section. System statuses cannot be deleted. Categories, issues, and statuses may be active or inactive and use soft-delete behavior when removed.

## Public status mapping

The public tracking portal never shows raw system values directly. It maps them to client-safe labels:

| Public label | System value |
|---|---|
| BEING_PREPARED | DRAFT |
| IN_PROGRESS | OPEN |
| RESOLVED | CLOSED |
| ARCHIVED | ARCHIVED |

When staff explain progress to clients, use the public labels; when working inside the system, use the exact system values.

## Tracker numbers

A tracker number is the public reference given to a client, in the format **OWBAP-XXXXXXXXXX**. It is safer to share than internal UUIDs and helps clients ask for updates. Staff case numbers use a different format, **OWB-YYYYMM-NNNNN**, and are for internal handling. Staff should still verify identity and avoid disclosing sensitive details just because someone knows a tracker number.

## Referral statuses

Referrals carry their own exact statuses, separate from the case status:

| Status | Meaning |
|---|---|
| PENDING | The referral has been sent and is waiting for agency action. |
| PROCESSING | The agency has started working on the referral. |
| FOR_COMPLIANCE | More information or action is required before the referral can proceed. |
| COMPLETED | The agency action is complete. |
| REJECTED | The referral was not accepted or cannot be processed as sent. |

## Staff guidance

- Use OPEN for active assistance work.
- Close only when the required action and documentation are complete.
- Archive according to local record-handling procedures.
- Use referral statuses separately; a case can be OPEN while one referral is COMPLETED and another is still PROCESSING.
`;
export default content;
