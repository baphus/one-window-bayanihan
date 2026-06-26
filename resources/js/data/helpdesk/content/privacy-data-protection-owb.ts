const content = `# Privacy and Data Protection in One Window Bayanihan

The One Window Bayanihan system is committed to protecting the privacy and personal data of all users in compliance with the Data Privacy Act of 2012 (Republic Act 10173).

## Data Privacy Act of 2012 (RA 10173)

The Data Privacy Act protects the fundamental right to privacy of communication while ensuring the free flow of information. It establishes the principles for processing personal data and the rights of data subjects in the Philippines.

## What Personal Data the System Collects

### For OFWs and Clients
- Full name and date of birth
- Contact information (phone number, email address)
- Current and permanent address
- Employment information (employer, position, industry, country)
- Next of kin details (name, relationship, contact number)
- Case-related information and supporting documents
- Tracker number and case history

### For System Users
- Name and email address
- Role and agency affiliation
- Login activity and audit logs
- Actions performed within the system

## How Data Is Protected

### Encryption

| Stage | Technology | What It Protects |
|-------|-----------|------------------|
| In Transit | TLS 1.2+ | All data moving between your browser and our servers |
| At Rest | AES-256 | All data stored in the database |
| Backups | AES-256 | All backup copies of the data |

### Access Controls

The system implements multiple layers of access control:

**Role-Based Access Control (RBAC)**
Each user role has specific permissions:
- CASE_MANAGER — access to assigned cases and referrals
- AGENCY — access to referrals sent to their agency only
- ADMIN — full system access (audited)

**Lane Isolation**
Users can only see data relevant to their role and assignments:
- Case Managers see only their own cases
- Agency users see only referrals to their agency
- Admin access is fully logged and auditable

**Audit Trails**
Every access to sensitive data is recorded:
- Who accessed the record
- When it was accessed
- What action was taken
- From which IP address

## User Rights Under the DPA

As a data subject, you have the following rights:

| Right | Description | How to Exercise |
|-------|-------------|-----------------|
| Right to be Informed | Know what data is collected and why | See this article or contact DPO |
| Right to Access | Request a copy of your personal data | Submit a request to DMW DPO |
| Right to Correction | Correct inaccurate data | Contact your Case Manager |
| Right to Deletion Objection | Object to data processing in certain cases | Submit a formal request |
| Right to Data Portability | Receive your data in a portable format | Available upon request |

To exercise any of these rights, contact the DMW Region VII Data Protection Officer (DPO).

## Data Retention and Disposal

| Data Type | Retention Period | Disposal Method |
|-----------|-----------------|-----------------|
| Active case records | Until case closure + 5 years | Anonymized after retention |
| User accounts | Duration of employment/engagement | Deactivated, then deleted after 1 year |
| Audit logs | 3 years | Archived, then deleted |
| System backups | 30 days rolling | Overwritten automatically |

## Who Has Access to Your Data

- **DMW Case Managers** — processing your case
- **Agency Focal Persons** — only for referrals to their specific agency
- **System Administrators** — for maintenance and support (fully logged)
- **DMW DPO** — for privacy compliance and data subject requests

Your data is never shared with unauthorized third parties.

## Reporting Privacy Concerns

If you believe your privacy has been violated:

1. Contact the **DMW Region VII Data Protection Officer**
2. Email or visit the DMW office in person
3. You may also file a complaint with the **National Privacy Commission (NPC)**

> **The protection of your personal data is our priority. We are committed to handling your information with the utmost care and in full compliance with the law.**
`;

export default content;
