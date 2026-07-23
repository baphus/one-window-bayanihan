import { PageGuide } from './types';

/**
 * Guide registry: every page reachable from a role's sidebar has a guide,
 * keyed by Ziggy route name. Coverage is enforced by
 * resources/js/Onboarding/__tests__/registry-coverage.test.ts — adding a
 * sidebar route without a guide here fails CI.
 *
 * Steps target `data-tour` anchors on the page. Steps whose anchor is
 * absent at runtime (empty states, role-trimmed sections) are skipped by
 * TourManager, so shared guides can safely include role-specific steps.
 */
export const pageGuides: Record<string, PageGuide> = {
    // ── Shared ───────────────────────────────────────────────────────
    'dashboard': {
        title: 'Dashboard',
        helpdeskSlug: 'using-dashboard-daily-monitoring',
        steps: [
            { element: '[data-tour="dashboard-header"]', title: 'Your Daily Start', description: 'Start here every day. The dashboard shows the work that needs your attention — pending referrals, overdue items, and anything requiring action — all scoped to your role.', side: 'bottom' },
            { element: '[data-tour="dashboard-stats"]', title: 'Key Numbers at a Glance', description: 'These tiles summarize your caseload, open referrals, and compliance items. Click any tile to jump straight to its filtered list.', side: 'bottom' },
            { element: '[data-tour="dashboard-work-queue"]', title: 'Triage First', description: 'The dispatch board surfaces cases and referrals that need action right now, ordered by urgency. Start here to decide what to tackle today.', side: 'bottom' },
            { element: '[data-tour="dashboard-work-queues"]', title: 'Monitor All Queues', description: 'Work queues let you scan open cases, pending and processing referrals, compliance items, and overdue referrals across the system at a glance.', side: 'bottom' },
            { element: '[data-tour="page-guide-button"]', title: 'Help Is Always Available', description: 'The ? button on every page opens this page guide. Use it anytime you want a refresher on a workspace you haven\'t visited recently.', side: 'left' },
        ],
    },
    'notifications.page': {
        title: 'Notifications',
        helpdeskSlug: 'notifications-staying-on-top-of-updates',
        steps: [
            { element: '[data-tour="notifications-header"]', title: 'Your Notification Feed', description: 'Every update that needs your attention lands here — referral decisions from agencies, case status changes, reminders, and system alerts. Check this page whenever the bell icon shows a count.', side: 'bottom' },
            { element: '[data-tour="notifications-list"]', title: 'Read and Act', description: 'Each card shows severity, message, and timestamp. Unread notifications are highlighted in blue. Click a card or its View link to jump straight to the related case or referral and take action.', side: 'top' },
            { element: '[data-tour="notifications-mark-all"]', title: 'Keep the Feed Clean', description: 'Mark individual items as read from each card, or use Mark All Read to clear everything at once. Regular triage here keeps you from missing important updates.', side: 'bottom', align: 'end' },
        ],
    },
    'reports.index': {
        title: 'Reports & Analytics',
        helpdeskSlug: 'using-reports-analytics',
        steps: [
            { element: '[data-tour="reports-header"]', title: 'Reports Overview', description: 'Your analytics hub. All figures here are scoped to your role, so you only see the cases, referrals, and agencies relevant to you.', side: 'bottom' },
            { element: '[data-tour="reports-filters"]', title: 'Set the Period', description: 'Pick a date range or a quick preset to reframe every metric on the page. Use Export to download the current view as a report.', side: 'bottom' },
            { element: '[data-tour="reports-kpis"]', title: 'Headline Metrics', description: 'Track active caseload, completions, completion rate, and average resolution time. Trend arrows compare against the previous period.', side: 'bottom' },
            { element: '[data-tour="reports-charts"]', title: 'Visual Breakdowns', description: 'The referral funnel and status distribution show where work is flowing — and where it stalls. Hover any chart for exact counts.', side: 'top' },
            { element: '[data-tour="reports-tabs"]', title: 'Go Deeper', description: 'Switch tabs to explore performance trends, agency and service breakdowns, and client demographics in detail.', side: 'bottom' },
        ],
    },
    'audit-logs.index': {
        title: 'Audit Logs',
        helpdeskSlug: 'understanding-using-audit-log',
        steps: [
            { element: '[data-tour="audit-header"]', title: 'System Audit Trail', description: 'A chronological record of every action in the system — who did what, when, and what changed. Use it to answer "what happened here?" with certainty.', side: 'bottom' },
            { element: '[data-tour="audit-timeline"]', title: 'Filter the Timeline', description: 'The filter bar at the top narrows the trail by action type, module, user, date range, or keyword. Combine filters to zero in on a specific event.', side: 'top' },
            { element: '[data-tour="audit-timeline"]', title: 'Read the Entries', description: 'Entries are grouped by day, newest first. Expand an entry to see field-level before-and-after values. Start here whenever you need to trace an unexpected change.', side: 'top' },
        ],
    },
    'helpdesk.index': {
        title: 'Help Center',
        steps: [
            { element: '[data-tour="helpdesk-header"]', title: 'Welcome to Help', description: 'The Help Center holds guides for everything in the system — from creating cases to reading reports. This landing page is the fastest way in.', side: 'bottom' },
            { element: '[data-tour="helpdesk-search"]', title: 'Search First', description: 'Type a few words about what you are trying to do — "publish a draft", "referral overdue" — and press Enter to search every article at once.', side: 'bottom' },
            { element: '[data-tour="helpdesk-audiences"]', title: 'Help for Your Role', description: 'These shortcuts collect the articles most relevant to your role, so you skip content meant for other teams.', side: 'bottom' },
            { element: '[data-tour="helpdesk-categories"]', title: 'Browse by Topic', description: 'Prefer to explore? Each topic card opens a category of related articles, with the article count shown up front.', side: 'top' },
            { element: '[data-tour="helpdesk-popular"]', title: 'Start with the Basics', description: 'The most-read articles live here — a good first stop if you are new. Cannot find an answer? Use the contact link at the bottom of any help page.', side: 'top' },
        ],
    },

    // ── Case operations ──────────────────────────────────────────────
    'cases.create': {
        title: 'Create a Case',
        helpdeskSlug: 'creating-publishing-cases',
        steps: [
            { element: '[data-tour="case-create-steps"]', title: 'Follow the Stages', description: 'The left panel shows the three stages: Client Profile, Case Setup, and Case Narrative. The guide explains only the stage you are on — advance through each stage at your own pace.', side: 'right' },
            { element: '[data-tour="case-create-client-selection"]', title: 'Select or Add Client', description: 'Start by choosing an existing client to pre-fill their details, or enter a new client from scratch. The visible fields belong to this stage only.', side: 'bottom' },
            { element: '[data-tour="case-create-form"]', title: 'Current Stage Fields', description: 'Fill in the fields shown on screen. Required fields are marked with a red asterisk. The Next button advances only when the current stage is complete.', side: 'left' },
            { element: '[data-tour="case-create-actions"]', title: 'Save or Submit at Any Time', description: 'Use Save as Draft to pause and return later. When all stages are complete, click Create Case to finalize and publish.', side: 'top' },
        ],
    },
    'cases.index': {
        title: 'Cases',
        helpdeskSlug: 'creating-publishing-cases',
        steps: [
            { element: '[data-tour="cases-header"]', title: 'Case Management', description: 'This is your case workspace — every case you can access, with its number, client, status, and assignment.', side: 'bottom' },
            { element: '[data-tour="cases-filter"]', title: 'Narrow the List', description: 'Search by case number, client, or keyword, and open the advanced filters to slice by status, category, or date range. Active filters show as removable chips.', side: 'bottom' },
            { element: '[data-tour="cases-columns"]', title: 'Customize Columns', description: 'Toggle which columns appear in the list — add or remove fields without losing your filters.', side: 'top', align: 'end' },
            { element: '[data-tour="cases-view-mode"]', title: 'List or Card View', description: 'Switch between a dense list view and a card layout. Choose whichever helps you scan cases faster.', side: 'top', align: 'end' },
            { element: '[data-tour="cases-table"]', title: 'Open a Case', description: 'Click any row to open the full case record with its timeline, referrals, and documents.', side: 'top' },
            { element: '[data-tour="cases-export"]', title: 'Export Your Results', description: 'Download the current filtered case list as an Excel file. Filters you applied carry over to the export. Use New Case to start a new one.', side: 'top', align: 'end' },
        ],
    },
    'cases.drafts': {
        title: 'My Drafts',
        helpdeskSlug: 'managing-draft-cases',
        steps: [
            { element: '[data-tour="drafts-header"]', title: 'Your Draft Cases', description: 'Cases you saved but have not submitted yet. Drafts are private — only you can see and edit them.', side: 'bottom' },
            { element: '[data-tour="drafts-filters"]', title: 'Find a Draft', description: 'Search by client name or case number, or narrow the list with a creation date range. Active filters appear as removable chips below.', side: 'bottom' },
            { element: '[data-tour="drafts-table"]', title: 'Review and Publish', description: 'The Draft Age badge shows how long each draft has been sitting — red means over a week old. Use Edit to finish a draft, Publish to submit it as a live case, or the delete button to discard it.', side: 'top' },
            { element: '[data-tour="drafts-new-case"]', title: 'Start a New Case', description: 'Ready to begin? Click New Case to open the case creation form. You can save it as a draft at any point and return here later.', side: 'bottom', align: 'end' },
        ],
    },
    'clients.index': {
        title: 'Clients',
        helpdeskSlug: 'getting-started-case-managers',
        steps: [
            { element: '[data-tour="clients-header"]', title: 'Client Directory', description: 'All registered clients and their associated cases in one place. Use Export Excel to download the current list for reporting.', side: 'bottom' },
            { element: '[data-tour="clients-stats"]', title: 'At-a-Glance Stats', description: 'These cards summarize your client base: totals, OFW versus Next of Kin split, vulnerability flags, and referral volume across all client cases.', side: 'bottom' },
            { element: '[data-tour="clients-table"]', title: 'Search and Open', description: 'Search by name, email, contact number, case number, or tracker ID, and use the filter and column controls to shape the view. Click a row to open the client profile and their case history.', side: 'top' },
        ],
    },
    'referrals.index': {
        title: 'Referrals',
        helpdeskSlug: 'referral-status-reference',
        steps: [
            { element: '[data-tour="referrals-header"]', title: 'Referral Tracking', description: 'Every referral you can act on or monitor lives here, with its current status — Pending, Processing, For Compliance, Completed, or Rejected.', side: 'bottom' },
            { element: '[data-tour="referrals-table"]', title: 'Work the List', description: 'Search by referral ID, client, agency, or service, and open the advanced filters to narrow by status or date. Click a row to see the referral details and take action.', side: 'top' },
            { element: '[data-tour="referrals-export"]', title: 'Export When Needed', description: 'Download the current referral list as an Excel file for reporting or offline review. Filters you applied carry over to the export.', side: 'bottom', align: 'end' },
        ],
    },
    'overdue-referrals.index': {
        title: 'Overdue Referrals',
        helpdeskSlug: 'managing-overdue-referrals',
        steps: [
            { element: '[data-tour="overdue-header"]', title: 'Overdue Referrals', description: 'Referrals that have gone too long without progress, sorted with the most stale first so the biggest risks surface at the top.', side: 'bottom' },
            { element: '[data-tour="overdue-kpis"]', title: 'Severity at a Glance', description: 'The cards break the total into Mild, Moderate, and Severe bands by days overdue. Watch the Severe count — those referrals need action first.', side: 'bottom' },
            { element: '[data-tour="overdue-breakdown"]', title: 'Spot the Bottleneck', description: 'This chart shows where overdue referrals are stuck by status, and calls out the biggest bottleneck so you know where follow-up will help most.', side: 'bottom' },
            { element: '[data-tour="overdue-filters"]', title: 'Sort and Filter', description: 'Re-sort by staleness, status, or client name, and filter to a single status when you want to work through one queue at a time.', side: 'bottom' },
            { element: '[data-tour="overdue-list"]', title: 'Take Action', description: 'Open any card to see the full referral. Admins and case managers can select referrals here and send email reminders to the responsible agency — individually, in batches, or all at once.', side: 'top' },
        ],
    },
    'stakeholders.index': {
        title: 'Stakeholders',
        helpdeskSlug: 'finding-partner-agencies-and-their-services',
        steps: [
            { element: '[data-tour="stakeholders-header"]', title: 'Partner Agencies', description: 'A directory of every agency and organization in the One Window Bayanihan network — useful when deciding where to refer a client.', side: 'bottom' },
            { element: '[data-tour="stakeholders-grid"]', title: 'Browse the Network', description: 'Each card is one partner agency, showing how many services it offers and how many referrals it is actively handling right now.', side: 'top' },
            { element: '[data-tour="stakeholders-card"]', title: 'Dig Into an Agency', description: 'Scan the service tags to see what an agency handles, then click View Details for its full profile before creating a referral to it.', side: 'bottom' },
        ],
    },

    // ── Case detail ──────────────────────────────────────────────────
    'cases.show': {
        title: 'Case Detail',
        helpdeskSlug: 'creating-publishing-cases',
        steps: [
            { element: '[data-tour="case-header"]', title: 'Case Overview', description: 'The case summary card shows the case number, status, client info, assigned team, and key dates at a glance.', side: 'bottom' },
            { element: '[data-tour="case-client-info"]', title: 'Client Profile', description: 'Client details including contact information, demographics, and next-of-kin. Use the profile tab to make updates.', side: 'right' },
            { element: '[data-tour="case-timeline"]', title: 'Activity Timeline', description: 'Every event in chronological order — case creation, status changes, referral activity, and milestone updates. Use it to answer "what happened and when."', side: 'left' },
            { element: '[data-tour="case-referrals"]', title: 'Referrals', description: 'All referrals from this case, their current status, and receiving agencies. Click any referral to open its detail page and track agency progress.', side: 'top' },
            { element: '[data-tour="case-documents"]', title: 'Documents', description: 'Upload case-related documents here: intake forms, affidavits, contracts, or any supporting file. Documents are accessible to authorized stakeholders.', side: 'top' },
            { element: '[data-tour="case-actions"]', title: 'Case Actions', description: 'Update status, archive closed cases, export a case summary PDF, or navigate to create a new referral from this case.', side: 'bottom' },
        ],
    },
    'referrals.create': {
        title: 'Create a Referral',
        helpdeskSlug: 'creating-managing-referrals',
        steps: [
            { element: '[data-tour="referral-create-steps"]', title: 'Follow the Stages', description: 'The left panel shows the three stages: Select Case, Select Agency, and Select Service. The guide explains only the stage you are on — advance at your own pace.', side: 'right' },
            { element: '[data-tour="referral-create-form"]', title: 'Current Stage Fields', description: 'Each stage shows the relevant controls on the right. Required fields must be filled before you can proceed. Use Back to revisit previous stages.', side: 'left' },
            { element: '[data-tour="referral-create-actions"]', title: 'Navigate and Submit', description: 'Move forward with Next or backward with Back. On the final step, click Submit Referral to send it to the agency.', side: 'top' },
        ],
    },

    // ── Agency operations ────────────────────────────────────────────
    'referrals.show': {
        title: 'Referral Detail',
        helpdeskSlug: 'referral-status-reference',
        steps: [
            { element: '[data-tour="referral-info"]', title: 'Referral Overview', description: 'Key details at a glance: the receiving agency, current status, associated case number, and dates. Overdue warnings appear here when action is needed.', side: 'bottom' },
            { element: '[data-tour="referral-actions"]', title: 'Take Action', description: 'Accept or reject pending referrals, update the status as work progresses, and view the full audit log for this referral.', side: 'bottom' },
            { element: '[data-tour="referral-timeline"]', title: 'Track Progress', description: 'The timeline shows every event — when the referral was sent, status changes, and milestones. Add milestones to record progress toward completion.', side: 'left' },
            { element: '[data-tour="referral-documents"]', title: 'Compliance Documents', description: 'Required documents are listed per service. Upload files, mark items as complied, or replace outdated uploads to keep the referral moving.', side: 'top' },
            { element: '[data-tour="referral-comments"]', title: 'Collaborate', description: 'Use comments to coordinate with the case manager or other teams. Reply threads keep conversations organized.', side: 'left' },
        ],
    },
    'agency.services.index': {
        title: 'Agency Services',
        helpdeskSlug: 'managing-your-agency-services-profile',
        steps: [
            { element: '[data-tour="services-header"]', title: 'Your Service Catalog', description: 'Maintain the services your agency offers. Case managers see this catalog when referring clients, so keep it accurate and current.', side: 'bottom' },
            { element: '[data-tour="services-actions"]', title: 'Catalog at a Glance', description: 'A quick count of your total services, how many are active, and the document requirements attached across them.', side: 'bottom' },
            { element: '[data-tour="services-list"]', title: 'Manage Services', description: 'Search or filter the list, edit a service and its required documents, or click + New Service to add an offering to your catalog.', side: 'top' },
        ],
    },
    'feedbacks.index': {
        title: 'Feedback Dashboard',
        helpdeskSlug: 'feedback-dashboards-for-case-managers-and-admins',
        steps: [
            { element: '[data-tour="feedbacks-header"]', title: 'Client Feedback Hub', description: 'Monitor how clients rate the services they received — response volume, satisfaction scores, and individual submissions in one place.', side: 'bottom' },
            { element: '[data-tour="feedbacks-filters"]', title: 'Filter the Data', description: 'Narrow results to a specific time window — or by agency, service, and rating where available. Every section below updates to match.', side: 'bottom' },
            { element: '[data-tour="feedbacks-kpis"]', title: 'Response Snapshot', description: 'Compare invitations sent against responses received, and watch the response rate and average ratings for early warning signs.', side: 'bottom' },
            { element: '[data-tour="feedbacks-breakdown"]', title: 'Score Breakdowns', description: 'See how ratings distribute from 1 to 5 stars and how each SERVQUAL dimension scores. Low dimensions point to what needs improving.', side: 'top' },
            { element: '[data-tour="feedbacks-list"]', title: 'Read Submissions', description: 'Browse the latest feedback records and click View to open a full response, including per-question SERVQUAL answers.', side: 'top' },
        ],
    },
    'servqual-configs.index': {
        title: 'SERVQUAL Questionnaires',
        helpdeskSlug: 'building-servqual-feedback-questionnaires',
        steps: [
            { element: '[data-tour="servqual-header"]', title: 'Questionnaire Builder', description: 'This is where you manage the SERVQUAL forms clients answer after a service. One default form applies everywhere; overrides target specific services.', side: 'bottom' },
            { element: '[data-tour="servqual-list"]', title: 'Your Configurations', description: 'Each row shows a form, its assignment, and question count. Use Make Active to set the default, or Assign to attach a form to one service.', side: 'top' },
            { element: '[data-tour="servqual-create"]', title: 'Build a New Form', description: 'Click Add New to create a questionnaire — choose questions per SERVQUAL dimension, then activate or assign it when ready.', side: 'left' },
        ],
    },
    'survey.forms.index': {
        title: 'Survey Forms',
        helpdeskSlug: 'building-servqual-feedback-questionnaires',
        steps: [
            { element: '[data-tour="survey-forms-header"]', title: 'Survey Forms', description: 'Create and manage the questionnaires your clients receive after a service is completed.', side: 'bottom' },
            { element: '[data-tour="survey-forms-list"]', title: 'Manage Your Forms', description: 'Review existing forms, edit their questions, and identify which form is currently active for new survey invitations.', side: 'top' },
            { element: '[data-tour="survey-forms-create"]', title: 'Create a Form', description: 'Use New Form to build a questionnaire with a title, description, and the questions clients should answer.', side: 'left' },
        ],
    },
    'survey.responses.index': {
        title: 'Survey Responses',
        helpdeskSlug: 'feedback-dashboards-for-case-managers-and-admins',
        steps: [
            { element: '[data-tour="survey-responses-header"]', title: 'Feedback Responses', description: 'Review the client feedback collected from completed service surveys in one place.', side: 'bottom' },
            { element: '[data-tour="survey-responses-stats"]', title: 'Response Snapshot', description: 'Compare invitations sent, responses received, and the overall response rate at a glance.', side: 'bottom' },
            { element: '[data-tour="survey-responses-list"]', title: 'Open a Response', description: 'Select a response to read the complete answers and understand the client experience in detail.', side: 'top' },
        ],
    },

    // ── Admin management ─────────────────────────────────────────────
    'admin.agencies.index': {
        title: 'Agency Management',
        helpdeskSlug: 'agency-service-management-guide',
        steps: [
            { element: '[data-tour="agencies-header"]', title: 'Manage Agencies', description: 'This is the master list of every partner agency that can receive referrals. Keep names, short codes, and contact details accurate — they appear on referrals and reports.', side: 'bottom' },
            { element: '[data-tour="agencies-stats"]', title: 'Agency Overview', description: 'Track totals at a glance: active vs. inactive agencies and how many are marked Default. A rising inactive count may mean agencies need re-onboarding.', side: 'bottom' },
            { element: '[data-tour="agencies-table"]', title: 'Agency Directory', description: 'Search, filter by status or type, and toggle columns. Use New Agency to onboard a partner, or Edit / Deactivate from the Actions column. Default agencies cannot be deactivated.', side: 'top' },
        ],
    },
    'admin.services.index': {
        title: 'Service Catalog',
        helpdeskSlug: 'agency-service-management-guide',
        steps: [
            { element: '[data-tour="admin-services-header"]', title: 'Manage Services', description: 'Every service offered through the system lives here. Each service belongs to an agency and drives what clients can be referred for.', side: 'bottom' },
            { element: '[data-tour="admin-services-new"]', title: 'Add a Service', description: 'Create a new service and assign it to an agency. Set processing days realistically — they feed the referral overdue calculations.', side: 'left' },
            { element: '[data-tour="admin-services-table"]', title: 'Service List', description: 'Review each service\'s owning agency and processing days. Edit to update details, or Delete a service that is no longer offered. Next, check Agencies to keep ownership accurate.', side: 'top' },
        ],
    },
    'admin.users.index': {
        title: 'User Management',
        helpdeskSlug: 'user-management-guide',
        steps: [
            { element: '[data-tour="users-header"]', title: 'Manage Users', description: 'Administer every account in the system — case managers, agency focals, and admins. This is also where you control account activation and email verification.', side: 'bottom' },
            { element: '[data-tour="users-stats"]', title: 'User Breakdown', description: 'See totals by role and how many accounts are active. Watch the Admins count — keep privileged accounts to a minimum.', side: 'bottom' },
            { element: '[data-tour="users-table"]', title: 'User Directory', description: 'Search, filter by role, agency, status, or MFA, and toggle the Email Verified switch inline. Use New User to create accounts; deactivate before deleting anyone permanently.', side: 'top' },
        ],
    },

    // ── Admin system health ──────────────────────────────────────────
    'admin.system.logs': {
        title: 'System Logs',
        helpdeskSlug: 'monitoring-queue-jobs-system-health',
        steps: [
            { element: '[data-tour="logs-header"]', title: 'Application Logs', description: 'Browse the raw application log stream. Check here first when users report errors or something behaves unexpectedly.', side: 'bottom' },
            { element: '[data-tour="logs-filters"]', title: 'Filter the Stream', description: 'Narrow by date range, severity level, or a message search. Start with "error" and above to surface real problems quickly.', side: 'bottom' },
            { element: '[data-tour="logs-table"]', title: 'Log Entries', description: 'Each row shows timestamp, level, and message. Hover a truncated message to read it in full.', side: 'top' },
            { element: '[data-tour="logs-download"]', title: 'Export Logs', description: 'Download exactly what you have filtered as a file — useful for sharing with developers or attaching to an incident report.', side: 'left' },
        ],
    },
    'admin.system.email-logs.index': {
        title: 'Email Logs',
        helpdeskSlug: 'monitoring-queue-jobs-system-health',
        steps: [
            { element: '[data-tour="email-logs-header"]', title: 'Outbound Email', description: 'Every email the system sends is logged here. Use it to confirm notifications actually reached users.', side: 'bottom' },
            { element: '[data-tour="email-logs-tabs"]', title: 'Sent vs. Failed', description: 'Switch to the Failed tab regularly — repeated failures usually point to a mail configuration or recipient address problem.', side: 'bottom' },
            { element: '[data-tour="email-logs-table"]', title: 'Delivery Details', description: 'Review the recipient, subject, timestamp, and any error message. Failed emails have a Resend button — fix the underlying issue first, then resend.', side: 'top' },
        ],
    },

    // ── Admin taxonomies & tools ─────────────────────────────────────
    'admin.case-statuses.index': {
        title: 'Case Statuses',
        helpdeskSlug: 'admin-case-categories-issues-statuses',
        steps: [
            { element: '[data-tour="case-statuses-header"]', title: 'Status Configuration', description: 'Define the workflow states that cases and referrals move through. These labels and colors appear everywhere statuses are shown.', side: 'bottom' },
            { element: '[data-tour="case-statuses-new"]', title: 'Add a Status', description: 'Create a status with a name, type (case or referral), color, and sort order. Sort order controls how statuses are listed in dropdowns.', side: 'left' },
            { element: '[data-tour="case-statuses-list"]', title: 'Case & Referral Statuses', description: 'Statuses are grouped by type. System statuses are protected and cannot be deleted; deactivate custom ones instead of deleting if they are still on old records.', side: 'top' },
        ],
    },
    'admin.case-categories.index': {
        title: 'Case Categories',
        helpdeskSlug: 'admin-case-categories-issues-statuses',
        steps: [
            { element: '[data-tour="case-categories-header"]', title: 'Category Setup', description: 'Categories classify client cases for reporting and routing. A clean, non-overlapping category list keeps case data meaningful.', side: 'bottom' },
            { element: '[data-tour="case-categories-new"]', title: 'Add a Category', description: 'Create a category with a name, color, and sort order. The color is used as a visual tag on case lists.', side: 'left' },
            { element: '[data-tour="case-categories-table"]', title: 'Category List', description: 'The Cases column shows how many cases use each category — check it before deactivating. Deactivated categories stay on existing cases but disappear from new-case forms.', side: 'top' },
        ],
    },
    'admin.case-issues.index': {
        title: 'Case Issues',
        helpdeskSlug: 'admin-case-categories-issues-statuses',
        steps: [
            { element: '[data-tour="case-issues-header"]', title: 'Issue Setup', description: 'Issues are the specific problem tags applied to client cases, complementing the broader categories.', side: 'bottom' },
            { element: '[data-tour="case-issues-new"]', title: 'Add an Issue', description: 'Create a new issue type with a name and sort order. Keep wording consistent with your intake forms so staff pick the right one.', side: 'left' },
            { element: '[data-tour="case-issues-table"]', title: 'Issue List', description: 'The Cases column shows usage per issue. Deactivate obsolete issues rather than deleting them so historical cases keep their classification.', side: 'top' },
        ],
    },
    'admin.data-export.index': {
        title: 'Data Export',
        helpdeskSlug: 'exporting-case-data-excel',
        steps: [
            { element: '[data-tour="data-export-header"]', title: 'Export Center', description: 'Export the full business dataset as a single formatted Excel workbook — useful for backups, audits, and offline analysis.', side: 'bottom' },
            { element: '[data-tour="data-export-sheets"]', title: 'What Gets Exported', description: 'Each table listed here becomes one sheet in the workbook — cases, clients, referrals, users, agencies, and more. Review the list so you know exactly what data leaves the system.', side: 'top' },
            { element: '[data-tour="data-export-button"]', title: 'Run the Export', description: 'Click to generate and download the workbook. The file contains personal data — store and share it according to your data-protection policy.', side: 'left' },
        ],
    },
    'admin.system.maintenance': {
        title: 'Maintenance Mode',
        helpdeskSlug: 'troubleshooting-common-issues',
        steps: [
            { element: '[data-tour="maintenance-header"]', title: 'Maintenance Mode', description: 'Take the site offline for planned maintenance. While active, most users see a maintenance page instead of the app.', side: 'bottom' },
            { element: '[data-tour="maintenance-status"]', title: 'Current Status', description: 'Shows whether maintenance mode is on and since when. Always confirm this reads Inactive after finishing maintenance work.', side: 'bottom' },
            { element: '[data-tour="maintenance-toggle"]', title: 'Enable or Disable', description: 'When enabling, set an optional bypass secret so your team can still log in, and a retry time to tell browsers when to check back. Announce downtime to users before toggling.', side: 'top' },
        ],
    },
    'admin.system-settings.index': {
        title: 'System Settings',
        helpdeskSlug: 'configuring-system-settings-ai-chatbot',
        steps: [
            { element: '[data-tour="settings-header"]', title: 'System Settings', description: 'Configure system-wide behavior: application info, referral timing, and OTP debug options all live on this page.', side: 'bottom' },
            { element: '[data-tour="settings-form"]', title: 'Settings Panels', description: 'Each card is an independent setting group. Changes to toggles apply immediately; numeric settings need an explicit Save.', side: 'top' },
            { element: '[data-tour="settings-overdue-threshold"]', title: 'Overdue Threshold', description: 'Referrals older than this many days without completion are flagged overdue across dashboards and reports. Align it with your agencies\' agreed processing times.', side: 'top' },
            { element: '[data-tour="settings-otp-debug"]', title: 'OTP Debug Modes', description: 'These toggles auto-fill OTP codes for testing and expose them in responses. Keep both switched off in production — check them after every deployment.', side: 'top' },
        ],
    },
    'admin.system.active-sessions': {
        title: 'Active Sessions',
        helpdeskSlug: 'monitoring-queue-jobs-system-health',
        steps: [
            { element: '[data-tour="active-sessions-header"]', title: 'Active Sessions', description: 'Monitor every signed-in user across the system. Use this page to spot unusual activity and terminate compromised or stale sessions.', side: 'bottom' },
            { element: '[data-tour="active-sessions-stats"]', title: 'Session Count', description: 'The counter shows how many sessions are currently active. A sudden spike may indicate unauthorized access.', side: 'bottom' },
            { element: '[data-tour="active-sessions-table"]', title: 'Session Details', description: 'Each row shows the user, IP address, browser, OS, and last activity. The Current badge marks your own session. Click Terminate on any other session to sign that user out immediately.', side: 'top' },
        ],
    },
    'admin.system.security': {
        title: 'Security Settings',
        helpdeskSlug: 'securing-your-account-password-and-mfa',
        steps: [
            { element: '[data-tour="security-header"]', title: 'Security Policies', description: 'Set the password, session, and access-control rules that apply to every account in the system.', side: 'bottom' },
            { element: '[data-tour="security-password-policy"]', title: 'Password Policy', description: 'Define minimum length, required character types, and expiry. Stricter rules apply to new passwords only — existing ones rotate at their next expiry.', side: 'top' },
            { element: '[data-tour="security-session"]', title: 'Sessions & Lockout', description: 'Control how long sessions last and when repeated failed logins lock an account. Shorter lifetimes are safer for shared workstations.', side: 'top' },
            { element: '[data-tour="security-access-control"]', title: 'Access Control', description: 'Optionally restrict access to whitelisted IPs or CIDRs and require two-factor authentication for all users. Verify your own IP is listed before enabling the whitelist.', side: 'top' },
            { element: '[data-tour="security-save"]', title: 'Apply Changes', description: 'Nothing takes effect until you click Save Changes. Review each section, save, then test a login to confirm you have not locked yourself out.', side: 'left' },
        ],
    },
};

// Admins reach the feedback dashboard via a dedicated route
// (`/feedbacks` redirects ADMIN there); alias the same guide so the [?]
// launcher works on both.
pageGuides['admin.feedbacks.dashboard'] = pageGuides['feedbacks.index'];

/**
 * Role‑specific guide overrides keyed by `<ROLE>:<route-name>`.
 * When a guide is looked up with a role and a matching key exists here,
 * it takes precedence over the shared guide in `pageGuides`.
 */
export const rolePageGuides: Record<string, PageGuide> = {
    'CASE_MANAGER:referrals.show': {
        title: 'Referral Detail',
        helpdeskSlug: 'referral-status-reference',
        steps: [
            { element: '[data-tour="referral-info"]', title: 'Referral Overview', description: 'Key details at a glance: the receiving agency, current status, associated case number, and dates. Overdue warnings appear here when action is needed.', side: 'bottom' },
            { element: '[data-tour="referral-timeline"]', title: 'Track Agency Progress', description: 'The timeline shows every event — when the referral was sent, status changes, and milestones. Use it to monitor how the agency is progressing.', side: 'left' },
            { element: '[data-tour="referral-documents"]', title: 'Review Compliance Documents', description: 'Required documents are listed per service. Check what has been uploaded and what is still outstanding. Contact the agency if documents are overdue.', side: 'top' },
            { element: '[data-tour="referral-comments"]', title: 'Communicate', description: 'Use comments to coordinate with the agency or other case managers. Reply threads keep conversations organized.', side: 'left' },
            { element: '[data-tour="referral-actions"]', title: 'Case-Manager Actions', description: 'View the full audit log for this referral. Return to the related case to continue working.', side: 'bottom' },
        ],
    },
    'AGENCY:referrals.show': {
        title: 'Referral Detail',
        helpdeskSlug: 'referral-status-reference',
        steps: [
            { element: '[data-tour="referral-info"]', title: 'Referral Overview', description: 'Key details at a glance: the referral status, associated case number, client info, and dates. Check the Required Services section to see what the client needs.', side: 'bottom' },
            { element: '[data-tour="referral-actions"]', title: 'Your Actions', description: 'Accept pending referrals to begin processing, update status as work progresses, or reject with a reason if the referral is outside your scope.', side: 'bottom' },
            { element: '[data-tour="referral-timeline"]', title: 'Track Progress', description: 'The timeline shows every event — when the referral was sent, status changes, and milestones. Add milestones to record progress toward completion.', side: 'left' },
            { element: '[data-tour="referral-documents"]', title: 'Compliance Documents', description: 'Required documents are listed per service. Upload files, mark items as complied, or replace outdated uploads to keep the referral moving.', side: 'top' },
            { element: '[data-tour="referral-comments"]', title: 'Coordinate with Case Manager', description: 'Use comments to ask questions, share updates, or request additional information. Reply threads keep conversations organized.', side: 'left' },
        ],
    },
};

/** Look up the guide for a route name, optionally scoped to a role. */
export function getPageGuide(routeName: string, role?: string): PageGuide | null {
    if (role) {
        const roleKey = `${role}:${routeName}`;
        const roleGuide = rolePageGuides[roleKey];
        if (roleGuide) return roleGuide;
    }
    return pageGuides[routeName] ?? null;
}
