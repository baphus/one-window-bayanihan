const content = `# Exporting Case Data to Excel

Case Excel exports are useful for authorized reporting, reconciliation, and operational review. They can contain personal and sensitive data, so export only when there is an official purpose.

![Admin data export](/assets/helpdesk/admin-data-export.png)

## Included columns

The case export column map can include case_number, status, tracker_number, client_type, OFW fields, address, vulnerability, employment, issue, summary, receiving_parties, next-of-kin fields, and exported_at.

## Before exporting

- Confirm your role permits the export.
- Apply the narrowest useful filters.
- Confirm the recipient and purpose.
- Prefer aggregate reports when individual records are not required.

## After exporting

Save the file only in approved locations. Do not email exports to personal accounts or store them in public folders. Remove local copies when the work is complete and report any accidental disclosure promptly.

## Troubleshooting

If an export is missing expected records, check filters, date ranges, and role-based access. If the export fails, record the time and filters used, then ask an administrator to review maintenance logs or data export records.
`;
export default content;
