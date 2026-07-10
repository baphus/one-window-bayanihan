const content = `# Maintenance Mode, System Logs, and Data Export

Three admin tools keep the platform operable and accountable: **Maintenance** for planned downtime, **Logs** for diagnosing problems, and **Data Export** for a full offline copy of operational data. All live under the admin **System** menu.

![Maintenance page](/assets/helpdesk/maintenance.png)

## Maintenance mode

Use maintenance mode during deployments or data fixes so users see a proper "be right back" page instead of errors.

- **Enabling** offers two options: an optional **secret** (3+ characters) — a bypass token that lets administrators who know it continue using the site — and **retry minutes** (1–1440), which tells browsers when to check back.
- **Disabling** restores normal access immediately.
- The page always shows the current status, and toggling confirms with **"Maintenance mode enabled/disabled."**

> Announce planned maintenance to agencies in advance — focal persons mid-way through a referral update lose unsaved work when maintenance starts.

## System logs

**System → Logs** is a viewer over the application log files:

- Pick from the available **log dates**, then filter by **level** (errors vs. everything), **search text**, and **date range**; entries load 50 per page.
- **Download** exports the current filtered view as a plain-text file (\\\`system-logs-YYYY-MM-DD.txt\\\`) — useful when escalating an issue to the development team.

Use the logs when something misbehaves without a clear UI error: failed emails, chatbot errors, queue problems. For *who did what* questions, use the **audit log** instead — system logs are technical, audit logs are accountability.

## Full data export

**System → Data Export** produces one Excel workbook (\\\`bayanihan-full-export-*.xlsx\\\`) with a sheet per table — cases, clients, referrals, users, agencies, services, milestones, next of kin, feedback, case documents, client addresses, client employments, case categories, and case statuses.

This is the heavyweight export for audits, reporting to the regional office, or migration. For day-to-day filtered exports, use the per-page **Export Excel** buttons on Cases, Referrals, Clients, and Feedback instead.

> The export contains personal data for every client in the system. Store it on approved office storage only, and delete copies when the purpose is served — see *Privacy and Data Protection in OWB*.
`;
export default content;
