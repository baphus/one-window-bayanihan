const content = `# Privacy and Data Protection in One Window Bayanihan

One Window Bayanihan handles sensitive OFW and family-assistance information. Users must process data only for official purposes and follow the Data Privacy Act of 2012 (RA 10173), agency policy, and local operating procedures.

![Admin audit log](/assets/helpdesk/admin-audit-log.png)

## Built-in safeguards

- Role-based access control limits pages and actions by role: CASE_MANAGER, AGENCY, and ADMIN.
- Audit logs record significant actions for accountability.
- Data is stored in PostgreSQL/Supabase-backed infrastructure configured for the deployment.
- File access uses storage controls such as temporary URLs where implemented.
- Sensitive settings may be stored encrypted by the application.

These safeguards support privacy operations, but they do not replace user responsibility. Authorized access must still be necessary, proportionate, and work-related.

## Handling case data

Case records may include OFW details, addresses, vulnerability information, employment information, issue summaries, next-of-kin details, receiving parties, documents, notes, and referral history. Treat all of these as confidential.

Do not:

- Share screenshots containing personal data in unsecured channels.
- Download exports unless needed for official work.
- Reuse tracker numbers or case details as training examples outside approved materials.
- Leave exported Excel files on shared desktops or personal drives.

## Exports and reports

Reports and case exports can contain personal or operationally sensitive data. Before exporting, confirm the purpose, recipient, and retention period. Prefer aggregate reports where individual case details are not needed.

## Audit and accountability

Audit logs help determine who accessed or changed information. Administrators should use audit logs for investigations and compliance checks, but audit data should also be handled carefully because it can reveal user activity and case relationships.

## Practical privacy checklist

1. Use your own account only.
2. Open only cases and referrals required for your work.
3. Verify recipients before sending referrals or exports.
4. Remove local files when the operational need has ended.
5. Report suspected unauthorized access, wrong-recipient disclosure, or lost exported files immediately.
`;
export default content;
