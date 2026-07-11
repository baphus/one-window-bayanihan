const content = `# Managing Client Records

The **Clients** page is the registry of every person the office assists. It's where you look up a client's history, contact details, addresses, and employment records without opening each case individually.

![Clients page](/assets/helpdesk/clients-index.png)

## Who sees which clients

Access is scoped by role:

- **Case Managers** see clients on their own cases.
- **Agency Focal Persons** see clients whose cases have a referral to their agency.
- **Administrators** see all clients.

## Finding a client

The search box matches **first name, last name, middle initial, email, contact number**, and the case's **case number or tracker number** — so a client can be found from whatever detail you have on hand.

Narrow results with the filters: **Client Type**, **Sex**, **Case Status**, **Vulnerability**, **Category**, **Issue/Concern**, and **Referred To** (agency). Results can be sorted and paginated, and **Export Excel** downloads the current filtered list.

## The client details page

Opening a client shows:

- **Client Information** — identity and contact details, with the client's photo (staff can upload or replace it).
- **Case Summary** — the client's case at a glance, linked to the full case record.
- **Addresses** — each recorded address.
- **Employment History** — employer and position records relevant to the case.
- **Activity Timeline** — what has happened on this client's records over time.

## Good practice

1. **Search before creating.** When opening a new case, check whether the client already exists to avoid duplicate records.
2. **Keep contact details current.** OTP-based tracking and feedback invitations go to the client's registered mobile number and email — a stale contact means the client loses access to their own updates.
3. **Use the filters for caseload reviews.** For example, *Case Status = OPEN* + *Vulnerability* filters give you the at-risk clients needing attention.
4. Personal data on this page is covered by the privacy policy — see *Privacy and Data Protection in OWB* before exporting or sharing.
`;
export default content;
