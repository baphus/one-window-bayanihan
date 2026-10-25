const content = `# Maintenance Mode

Maintenance mode keeps the platform operable and accountable during planned downtime. It lives under the admin **System** menu.

![Maintenance page](/assets/helpdesk/maintenance.png)

## Maintenance mode

Use maintenance mode during deployments or data fixes so users see a proper "be right back" page instead of errors.

- **Enabling** offers two options: an optional **secret** (3+ characters) — a bypass token that lets administrators who know it continue using the site — and **retry minutes** (1–1440), which tells browsers when to check back.
- **Disabling** restores normal access immediately.
- The page always shows the current status, and toggling confirms with **"Maintenance mode enabled/disabled."**

> Announce planned maintenance to agencies in advance — focal persons mid-way through a referral update lose unsaved work when maintenance starts.

## Where to look when something breaks

The in-app log viewer and the admin data-export page have been retired. For day-to-day diagnosis:

- **Audit log** — the full change trail: who did what, when, from where. Use this for accountability questions and for reconstructing what a user was doing when they hit a problem.
- **Generated documents** — queued report and per-module Excel exports land here; check the row's status when a download never arrives.
- **Active sessions** — confirm whether the affected user is still signed in, and terminate a session if an account is suspected compromised.

For day-to-day filtered exports, use the per-page **Export Excel** buttons on Cases, Referrals, and Clients.
`;
export default content;
