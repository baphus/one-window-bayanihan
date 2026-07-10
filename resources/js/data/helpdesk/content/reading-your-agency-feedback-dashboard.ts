const content = `# Reading Your Agency Feedback Dashboard

The **Feedback Dashboard** shows how clients rate your agency's completed services. Agency Focal Persons see their own agency's results only. Open it from **Feedback** in the sidebar.

![Agency feedback dashboard](/assets/helpdesk/feedback-dashboard-agency.png)

## Choosing a time window

The **Time window** filter at the top recalculates every number on the page. Options: **All time**, **Last 7 days**, **Last 30 days**, **Last 90 days**, **This quarter**, and **This year**.

## The five summary cards

- **Total Sent** — feedback invitations issued to clients for your agency's referrals.
- **Responses** — feedback forms actually submitted.
- **Response Rate** — responses ÷ sent. A low rate means clients receive links but don't finish the form.
- **Avg Rating** — the average of clients' overall ratings (1–5 stars).
- **Avg SERVQUAL** — the average of all per-question experience scores (1–5).

## Charts and breakdowns

- **Rating distribution** — how many clients gave each overall rating from 1 to 5. Watch for clusters at the low end even when the average looks acceptable.
- **SERVQUAL dimensions** — average score per dimension (Tangibles, Reliability, Responsiveness, Assurance, Empathy). A weak dimension tells you *what kind* of problem clients experience — for example, low Responsiveness with high Assurance suggests clients trust your staff but wait too long.
- **Service breakdown** — one row per service showing **Invitations Sent**, **Responses**, **Response Rate**, and **Avg Rating**. Use this to spot which specific service drives low scores.
- **Recent feedback** — the latest submissions with **Date**, **Client**, **Agency**, **Service**, **Rating**, and **SERVQUAL Avg**. Open an entry to read the full per-question responses and any written comments.

Sections show an empty state ("No ratings yet", "No service data yet") until clients begin responding.

## Exporting

Use the export option to download feedback records as an Excel file for offline analysis or reporting.

## Acting on what you see

1. Check **Response Rate** first — scores from a handful of responses are not representative.
2. Compare dimensions to find the weakest area, then read the recent comments for that period.
3. If one service stands out in the service breakdown, review its referral handling with the staff involved, and consider whether that service needs its own questionnaire (see *Building SERVQUAL feedback questionnaires*).

> Feedback is anonymous-friendly: submissions without an identifiable client are listed as **Anonymous**. Never pressure clients for positive ratings — the value of the dashboard depends on honest answers.
`;
export default content;
