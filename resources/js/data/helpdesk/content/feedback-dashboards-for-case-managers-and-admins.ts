const content = `# Feedback Dashboards for Case Managers and Admins

Client feedback is visible to every staff role, scoped to what that role needs. This guide covers the **Case Manager** view and the **Administrator** cross-agency view. Agency Focal Persons have their own scoped dashboard — see *Reading your agency feedback dashboard*.

## Case Manager view

Case Managers open **Feedback** from the sidebar and see the same dashboard layout as agencies, but **across all agencies**: summary cards (Total Sent, Responses, Response Rate, Avg Rating, Avg SERVQUAL), rating distribution, SERVQUAL dimension averages, a service breakdown, and recent feedback. The **Time window** filter (All time, Last 7/30/90 days, This quarter, This year) recalculates everything.

Open any entry in **Recent feedback** to see the full submission: per-question expectation and experience scores, dimension averages, the overall rating, and written comments.

Use this view to connect feedback to casework: if a client reports an unresolved problem in comments, treat it as a signal to review the case and its referrals through the normal case process — do not edit or answer feedback on the client's behalf.

## Administrator view: Feedback Overview

Administrators who open **Feedback** are taken to the cross-agency **Feedback Overview** (this page is protected by the admin IP whitelist, like other admin pages).

![Admin feedback overview](/assets/helpdesk/feedback-dashboard-admin.png)

It has two parts:

1. **Agency summary** — one row per agency with invitations sent, responses submitted, response rate, and average rating for the selected window. This is the fastest way to compare service quality across partner agencies.
2. **Detailed feedback table** — individual submissions, filterable by **Agency**, **Service**, **Date from**, **Date to**, **Minimum rating**, and **Window**, paginated 15 per page. Each row shows the client (or **Anonymous**), agency, service, overall rating, and the SERVQUAL average.

### Typical admin workflow

1. Pick a reporting window (for example, **This quarter**).
2. Scan the agency summary for low response rates or low averages.
3. Filter the detailed table to that agency, optionally with **Minimum rating** left empty to include the lowest scores.
4. Open individual submissions to read comments before raising findings with the agency.
5. Export to Excel when you need the underlying records for a report.

> Response rates matter as much as scores: an agency with a 90% response rate and a 4.0 average is telling you more than one with a 10% rate and a 4.8 average. Encourage agencies to close referrals promptly — invitations are generated from completed work, and stale referrals delay feedback collection.
`;
export default content;
