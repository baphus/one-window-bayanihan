const content = `# Managing the Helpdesk Knowledge Base

One Window Bayanihan helpdesk articles are maintained as static TypeScript markdown files in \`resources/js/data/assets/helpdesk/content\`. There is no runtime helpdesk CMS in the current application, so publishing changes requires editing the article file and, for new articles, registering it in the helpdesk article index maintained by the development team.

![Helpdesk index](/assets/helpdesk/helpdesk-index.png)

## What administrators should know

The helpdesk is designed for stable operational guidance: onboarding, case processing, referrals, audit logs, reports, privacy, and troubleshooting. Content should be written for real users first. Avoid internal code names unless they help support staff identify the correct page or field.

Each article is a default export containing a markdown template string:

\`\`\`ts
const content = \`# Article title

Article body...
\`;
export default content;
\`\`\`

## Updating an existing article

1. Find the article file under \`resources/js/data/assets/helpdesk/content\`.
2. Edit the markdown inside the template string only.
3. Keep headings clear and scannable.
4. Use exact role names, status names, and field names shown in the application.
5. Run the frontend validation normally used by the project before release.

Use screenshot references only when they help a user confirm they are on the right page. The available helpdesk screenshots include admin users, agencies, services, settings, audit log, maintenance, reports, cases, referrals, helpdesk index, and admin dashboard.

## Adding a new article

Creating a new content file alone is not enough. New articles must also be added to the article registry used by the helpdesk UI. The current task may restrict edits to content files only; in that case, create the file and leave article-index registration for the follow-up change.

Recommended file naming:

- Use lowercase words separated by hyphens.
- Match the article topic, for example \`user-management-guide.ts\`.
- Keep one primary topic per file.

## Writing style

- Start with what the user can accomplish.
- Prefer short paragraphs and direct instructions.
- Use numbered steps for procedures.
- Use tables for reference material such as statuses or filters.
- Avoid unsupported promises such as automated legal compliance, automatic redaction, or runtime content publishing.

## Review checklist

Before publishing, confirm that the article:

- Uses current route/page names such as Admin/User, Agency, Service, Security, ActiveSessions, CaseCategory, CaseIssue, CaseStatus, DataExport, Maintenance, EmailLogs, LogViewer, and OverdueReferrals.
- Uses exact case statuses: OPEN, CLOSED, ARCHIVED. Drafts are a separate concept and not a case status.
- Uses exact referral statuses: PENDING, PROCESSING, FOR_COMPLIANCE, COMPLETED, REJECTED.
- Does not describe features that are not present in the application.
- Does not expose secrets, credentials, private URLs, or personal data in examples.

## Maintenance responsibility

Treat helpdesk content as part of the product. Update articles when workflows, labels, screenshots, or policy wording changes. For major releases, review admin, reporting, privacy, and troubleshooting articles first because outdated guidance in these areas can cause incorrect operational decisions.
`;
export default content;
