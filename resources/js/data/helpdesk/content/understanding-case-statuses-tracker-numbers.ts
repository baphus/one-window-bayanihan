const content = `# Understanding Case Statuses and Tracker Numbers

Case statuses show the internal lifecycle of a case. Tracker numbers allow clients to check progress without exposing internal record IDs.

![Cases index](/assets/helpdesk/cases-index.png)

## Case statuses

| Status | Meaning |
|---|---|
| OPEN | The case is active and being handled. |
| CLOSED | The case handling is complete. |
| ARCHIVED | The case is retained for records and no longer active. |

> **Note:** Drafts are saved separately from cases and are not a case status. A draft becomes a case only when published.

Administrators manage case status references in the admin CaseStatus section. System statuses cannot be deleted. Categories, issues, and statuses may be active or inactive and use soft-delete behavior when removed.

## Tracker numbers

A tracker number is the public reference given to a client. It is safer to share than internal UUIDs and helps clients ask for updates. Staff should still verify identity and avoid disclosing sensitive details just because someone knows a tracker number.

## Staff guidance

- Use OPEN for active assistance work.
- Close only when the required action and documentation are complete.
- Archive according to local record-handling procedures.
- Use referral statuses separately; a case can be OPEN while one referral is COMPLETED and another is still PROCESSING.
`;
export default content;
