const content = `# Using Reports and Analytics

The **Reports** page turns operational data into charts for workload and performance review. Use it for operational insight, not as a replacement for case-level verification. What you see is scoped to your role — agencies see their own activity, admins see everything.

![Reports](/assets/helpdesk/reports.png)

## Report sections

Sections are collapsible and load as you open them. They include **Cases Over Time**, **Case Trends (12 Months)**, **Referral Trends**, **Referral Aging** (how long active referrals have been waiting), **Referrals by Agency**, **Case Issue Distribution**, **Demographics** (gender, client type, age group), **Vulnerability Indicators**, **Geographic Distribution** and **City/Municipality Distribution**, and **Employment Analytics**. Each answers a different question: how many cases are active, where referrals slow down, which issues are common, and how agencies are performing.

## AI Insights

The **AI Insights** button generates a narrative summary of the report data for your selected date range — useful as a starting point for a management briefing. Treat it as a draft written by an assistant:

1. Set your date range first; the analysis follows it.
2. Select **AI Insights** and wait for the analysis.
3. **Verify every number against the charts before quoting it.** If generation fails ("Failed to generate AI insight"), try again — the AI service may be momentarily busy.

## Exports

- **Export PDF** — a formatted snapshot of the report for circulation.
- **Export Excel** — the underlying figures for further analysis.

Exports may contain sensitive aggregate and case information depending on filters. Store files securely and delete local copies when no longer needed — see *Privacy and Data Protection in OWB*.

## Good reporting practice

1. Choose the correct date range before reading anything else.
2. Confirm whether you need aggregate totals or individual case details (for the latter, use the Cases/Referrals pages and their exports).
3. Review filters before sharing results.
4. Avoid identifying individuals unless the audience has an official need.

## Interpreting results

Use reports as indicators. A high number of PENDING referrals may mean agencies need follow-up (check **Referral Aging** and the *Overdue referrals* page). A long cycle time may point to document delays, compliance requirements, or workload. Always confirm with the underlying cases or referrals before taking corrective action.
`;
export default content;
