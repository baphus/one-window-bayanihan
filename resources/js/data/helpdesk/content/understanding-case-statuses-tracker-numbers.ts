const content = `# Understanding Case Statuses and Tracker Numbers

Case statuses show the internal lifecycle of a case. Tracker numbers allow clients to check progress without exposing internal record IDs.

![Cases index](/assets/helpdesk/cases-index.png)

## Case lifecycle

Cases move through this lifecycle: **DRAFT → PENDING_REVIEW → OPEN → CLOSED → ARCHIVED**.

A draft is prepared by staff or filed by an OFW and is not yet submitted; a case in **PENDING_REVIEW** has been filed — by an OFW through the portal, or by staff — and is waiting in the **Pending Reviews** queue for a case manager to add a category and approve or reject it; an **OPEN** case is active and being handled; a **CLOSED** case has completed its assistance and documentation; an **ARCHIVED** case is a retained record. Archived cases are eventually deleted to the trash with a mandatory reason, and permanently removed by the nightly housekeeping routine once the retention period passes.

## Case statuses

| Status | Plain meaning | Notes |
|---|---|---|
| DRAFT | Being prepared, not yet submitted. | Invisible to the client portal unless the client filed it themselves. |
| PENDING_REVIEW | Filed, awaiting review. | Sits in **Pending Reviews** until a case manager approves or rejects it. |
| OPEN | Active and being handled. | Required before a referral can be created. |
| CLOSED | Handling complete. | Cannot be closed while any referral is still PENDING, PROCESSING or FOR_COMPLIANCE. |
| ARCHIVED | Retained for records, no longer active. | Only a CLOSED case can be archived; can be unarchived to its previous state. |

Only two staff actions change state directly in the UI: **close** (OPEN → CLOSED) and **reopen** (CLOSED → OPEN). A case reaches OPEN only through **Approve case** in the review queue — it cannot be moved there by editing the status field.

Administrators manage case status references in the admin CaseStatus section. System statuses cannot be deleted. Categories, issues, and statuses may be active or inactive and use soft-delete behavior when removed.

## Public status mapping

The public tracking portal never shows raw system values directly. It maps them to client-safe labels:

| Public label | System value |
|---|---|
| BEING_PREPARED | DRAFT |
| BEING_REVIEWED | PENDING_REVIEW |
| IN_PROGRESS | OPEN |
| RESOLVED | CLOSED |
| ARCHIVED | ARCHIVED |

Drafts are **not** published to the public tracking portal — a case only becomes visible there once it has been filed. When staff explain progress to clients, use the public labels; when working inside the system, use the exact system values.

## Tracker numbers

A tracker number is the public reference given to a client, in the format **OWBAP-XXXXXXXXXX**. It is shown on the filing receipt and sent in the confirmation email, so the client has it from the moment their request is received. It is safer to share than internal UUIDs and helps clients ask for updates. Staff case numbers use a different format, **OWB-YYYYMM-NNNNN**, and are for internal handling. Staff should still verify identity and avoid disclosing sensitive details just because someone knows a tracker number.

## Referral statuses

Referrals carry their own exact statuses, separate from the case status:

| Status | Plain meaning | Notes |
|---|---|---|
| PENDING | Sent, awaiting agency action. | Every new referral starts here. |
| PROCESSING | The agency is actively working on it. | The agency chooses this when it accepts. |
| FOR_COMPLIANCE | Waiting on client. | The agency needs something from the client before it can proceed. |
| COMPLETED | The agency action is complete. | Final. Triggers the client survey invitation. |
| REJECTED | Not accepted, or cannot be processed as sent. | Final. A reason is required. |

## Staff guidance

- Use OPEN for active assistance work.
- Close only when the required action and documentation are complete.
- Archive according to local record-handling procedures.
- Use referral statuses separately; a case can be OPEN while one referral is COMPLETED and another is still PROCESSING.
`;
export default content;
