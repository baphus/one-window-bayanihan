const content = `# Feedback Views for Case Managers and Admins

Client feedback is visible to every staff role, scoped to what that role needs. Everyone uses the same **Survey Responses** page (**Survey Responses** in the sidebar) — there is no separate dashboard per role. Agency Focal Persons see their own agency's results; Case Managers and Admins see across agencies. See *Reading your agency feedback* for the agency view.

## How invitations are created

When a referral is marked **COMPLETED**, the completion event queues a survey invitation for the client automatically (one invitation per referral; duplicates are skipped). The client submits through a token-based link with an expiry — no login required. A stale referral that sits uncompleted therefore delays feedback collection, which is one more reason to keep referral statuses current.

## What the page shows

Three summary cards — **Total Sent**, **Total Submitted**, **Response Rate** — followed by the submitted-responses table (**Client**, **Service**, **Agency**, **Survey Form**, **Submitted**). Open any row to read the full submission: every question with the client's answer, plus written comments.

Use this view to connect feedback to casework: if a client reports an unresolved problem in an answer, treat it as a signal to review the case and its referrals through the normal case process — do not edit or answer feedback on the client's behalf.

### Typical workflow

1. Scan the summary cards for low response rates.
2. Find the service or agency behind the weakest results.
3. Open individual submissions to read comments before raising findings with the agency.

> Response rates matter as much as scores: an agency with a 90% response rate and a middling average is telling you more than one with a 10% rate and a perfect average. Encourage agencies to close referrals promptly — invitations are generated from completed work, and stale referrals delay feedback collection.
`;
export default content;
