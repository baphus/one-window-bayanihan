const content = `# Admin Guide: Case Categories, Issues, and Statuses

Administrators maintain the reference data used to classify cases and report outcomes. These sections are found in the admin area as CaseCategory, CaseIssue, and CaseStatus.

![Admin case categories](/helpdesk/admin-case-categories.png)

![Admin case issues](/helpdesk/admin-case-issues.png)

![Admin case statuses](/helpdesk/admin-case-statuses.png)

## Categories and issues

Case categories group related assistance concerns. Case issues provide more specific classification for intake, reporting, and trend analysis. Keep names short, clear, and meaningful to case managers.

## Active and inactive records

Categories, issues, and statuses can be active or inactive. Inactive values should generally remain available for historical records but should not be selected for new work unless reactivated.

## Deleting records

The project uses a soft-delete flag pattern. Removed records are marked as deleted rather than physically erased. System statuses cannot be deleted because they are required by case workflow and reporting.

## Status reference

The core case statuses are DRAFT, OPEN, CLOSED, and ARCHIVED. Do not rename these casually; changing labels can confuse users and reports.

## Maintenance tips

- Avoid duplicate categories with slightly different wording.
- Review report needs before adding new issues.
- Deactivate outdated values instead of reusing them for a different meaning.
- Communicate changes to case managers before they take effect.
`;
export default content;
