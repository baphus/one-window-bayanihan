const content = `# Understanding and Using the Audit Log

The audit log records every significant action taken in the system, providing a complete trail of who did what and when.

## What the Audit Log Records

The system logs the following categories of events:

### Authentication Events
- **LOGIN** — successful user login
- **LOGOUT** — user logout
- **LOGIN_FAILED** — failed login attempt
- **OTP_SENT** — OTP code generated and sent
- **OTP_VERIFIED** — successful OTP verification

### Case Events
- **CASE_CREATED** — new case submitted
- **CASE_UPDATED** — case details modified
- **CASE_CLOSED** — case marked as resolved

### Referral Events
- **REFERRAL_CREATED** — referral sent to agency
- **REFERRAL_ACCEPTED** — agency accepted referral
- **REFERRAL_REJECTED** — agency rejected referral
- **REFERRAL_STATUS_CHANGED** — status updated
- **MILESTONE_ADDED** — milestone entry created

### Document Events
- **DOCUMENT_UPLOADED** — file added to a case
- **DOCUMENT_DOWNLOADED** — file accessed by a user
- **DOCUMENT_DELETED** — file removed

### Administration Events
- **USER_CREATED** — new user account created
- **USER_UPDATED** — user details changed
- **USER_DEACTIVATED** — account deactivated
- **SETTINGS_CHANGED** — system configuration updated

## Accessing Audit Logs

1. Navigate to **"Audit Logs"** in the sidebar (admin users only)
2. The page displays a paginated list of events in reverse chronological order
3. Each entry shows: **timestamp**, **user**, **action**, **module**, and **details**

## Filtering Audit Logs

Use filters to narrow down specific events:

### By Action Type
Select from: CREATE, UPDATE, DELETE, VIEW, LOGIN, LOGOUT, and others. This helps isolate specific kinds of activities.

### By Module
Filter by system module: Cases, Referrals, Users, Documents, Settings, Auth. This shows events related to a specific area.

### By User
Enter a user name or email to see all actions performed by that person.

### By Date Range
Set a start and end date to view events within a specific period.

## Understanding Audit Events in Context

Each audit entry contains:

| Field | Description |
|-------|-------------|
| Timestamp | Exact date and time of the action |
| User | Name and email of the person who performed the action |
| Action | The type of action (CREATE, UPDATE, DELETE, etc.) |
| Module | Which part of the system was affected |
| Target ID | The ID of the record that was affected |
| IP Address | The network address the action came from |
| User Agent | The browser or device used |
| Details | Additional context in JSON format |

## Security Monitoring Use Cases

### Detecting Unusual Access Patterns
- Multiple failed logins from the same IP
- Logins at unusual hours
- Access to records outside a user's normal scope
- Bulk downloads of documents

### Investigating Incidents
If a case record is modified unexpectedly:
1. Filter the audit log by that case ID
2. See every action taken on that record
3. Identify who made changes and when
4. Review the details of each change

### Compliance Verification
- Confirm that only authorized users access sensitive records
- Verify that required actions were performed within timelines
- Document that procedures were followed correctly

## Best Practices

- Review audit logs **weekly** for security monitoring
- **Export** logs periodically for long-term retention
- Investigate **unusual patterns** promptly
- Use audit data for **training** — identify common errors
- Retain logs according to **data privacy requirements**
`;

export default content;
