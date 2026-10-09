const content = `# Referral Documents and Compliance Uploads

Documents reach a referral in two ways: they are **attached while creating the referral**, or they are **uploaded later to fulfill a compliance requirement** that was deferred at creation. This article walks both flows and the rules that apply to every file.

Note the split: **case documents** (the case-level file store) and **referral attachments** (files tied to one referral) are separate stores with separate permissions. Case documents can be read by Case Managers, Agency Focal Persons, and Admins, but only Case Managers and Admins can upload or delete them.

![For Compliance section on a referral](/assets/helpdesk/referrals-compliance.png)

## Who can work with referral documents

A referral (and its documents) is visible to:

- the **Case Manager** who owns the case,
- the **Agency Focal Person** of the referred agency, and
- **Administrators**.

> Everything uploaded to a referral's compliance section is viewable by the referred agency — the page reminds you: *"Everything uploaded here will only be viewable by the referred agency."*

## Flow 1 — Attaching documents when creating the referral

In the referral wizard's **Select Service** step, each selected service lists its required documents under **Service Requirements**. For every requirement you choose one of two modes:

1. **Upload File** — click **Choose file** and attach the document now. A green *Attached: filename* confirms the upload; a red *"Upload is required for this document"* means the requirement is still empty.
2. **For Compliance** — defer the document. The requirement is tagged **FOR COMPLIANCE** and becomes a pending item on the referral after submission.

You cannot submit while a requirement in **Upload File** mode has no file — the wizard warns *"Upload all required documents before submitting."*

Files attached here are stored against the referral and named after their service and requirement (for example, *Legal Counseling / Affidavit — affidavit.pdf*), so the agency can tell which requirement each file satisfies.

## Flow 2 — Fulfilling a compliance requirement later

Requirements deferred at creation appear in the **For Compliance** section of the referral page, each showing its service, requirement name, and a status badge:

- **PENDING** (orange) — the document is still owed. A **Upload to Fulfill** control sits under the requirement.
- **COMPLIED** (green) — the document has been submitted; the card shows *Fulfilled <date> by <name>*.

To fulfill a pending requirement, use **Upload to Fulfill** and pick the file. The requirement flips to **COMPLIED** immediately and records who fulfilled it and when. Only pending requirements accept an upload — a complied requirement cannot be fulfilled twice.

## File rules

> **Accepted types:** PDF, DOC, DOCX, JPG, JPEG, PNG.
> **Size limit:** up to 20 MB per file by default (set by the upload-size configuration; your administrator can change it — the referral creation form may enforce a smaller per-file cap, so follow the on-screen message if an upload is rejected for size).

Uploads that fail these checks are rejected with an error before anything is saved, and every file is malware-scanned on upload — infected files are rejected.

## Behind the scenes

- Every document upload is recorded in the **audit log** automatically.
- Files live in object storage and are served through expiring links (24-hour validity) rather than public URLs.
- Replacing an attachment keeps the earlier copy server-side: the per-file version history is available through the attachment-versions endpoint on the referral, so prior versions are retrievable instead of lost.

## Related articles

- *Creating a Referral: Choosing Agency and Service* — the full three-step wizard, including duplicate-referral warnings.
- *Managing Compliance Requirements* — how agencies and case managers track outstanding requirements across referrals.
`;
export default content;
