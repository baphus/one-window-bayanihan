const content = `# Referral Documents and Compliance Uploads

Documents reach a referral in two ways: they are **attached while creating the referral**, or they are **uploaded later to fulfill a compliance requirement** raised by the receiving agency. This article walks both flows and the rules that apply to every file.

Note the split: **case documents** (the case-level file store) and **referral attachments** (files tied to one referral) are separate stores with separate permissions. Case documents can be read by Case Managers, Agency Focal Persons, and Admins, but only Case Managers and Admins can upload or delete them.

![For Compliance section on a referral](/assets/helpdesk/referrals-compliance.png)

## Who can work with referral documents

A referral (and its documents) is visible to:

- the **Case Manager** who owns the case,
- the **Agency Focal Person** of the referred agency, and
- **Administrators**.

> Everything uploaded to a referral's compliance section is viewable by the referred agency — the page reminds you: *"Everything uploaded here will only be viewable by the referred agency."*

## Flow 1 — Attaching documents while creating the referral

The referral wizard has three steps — **Select Case**, **Select Agency**, **Details** — and it never asks you to choose a service.

In the **Details** step you can optionally attach supporting documents:

1. Add any **remarks** that help the receiving agency understand the request.
2. Attach files with the document control. Accepted types are PDF, DOC, DOCX, JPG, JPEG and PNG, up to 10 MB per file.
3. Submit.

Files attached here are stored against the referral and are visible to the receiving agency as referral attachments. Because the agency assigns the service *after* receiving the referral, these attachments are not tied to a particular service requirement — they are simply the evidence you already have.

## Flow 2 — Fulfilling a compliance requirement later

Compliance requirements are created by the **receiving agency** after it has accepted the referral, not by the sender at creation.

Requirements appear in the **For Compliance** section of the referral page, each showing its service, requirement name, and a status badge:

- **PENDING** (orange) — the document is still owed. An **Upload to Fulfill** control sits under the requirement.
- **COMPLIED** (green) — the document has been submitted; the card shows *Fulfilled <date> by <name>*.

To fulfill a pending requirement, use **Upload to Fulfill** and pick the file. The requirement flips to **COMPLIED** immediately and records who fulfilled it and when. Only pending requirements accept an upload — a complied requirement cannot be fulfilled twice.

While a referral is in **FOR_COMPLIANCE** it is *waiting on client*: the referral does not progress until the outstanding items arrive. If the client needs a secure way to send files, the agency can issue a private document-request link with a time-limited, session-bound exchange instead.

## File rules

> **Accepted types:** PDF, DOC, DOCX, JPG, JPEG, PNG.
> **Size limit:** up to 20 MB per file by default (set by the upload-size configuration; your administrator can change it — the referral creation form may enforce a smaller per-file cap, so follow the on-screen message if an upload is rejected for size).

Uploads that fail these checks are rejected with an error before anything is saved, and every file is malware-scanned on upload — infected files are rejected.

## Behind the scenes

- Every document upload is recorded in the **audit log** automatically.
- Files live in object storage and are served through expiring links (24-hour validity) rather than public URLs.
- Replacing an attachment keeps the earlier copy server-side: the per-file version history is available through the attachment-versions endpoint on the referral, so prior versions are retrievable instead of lost.

## Related articles

- *Creating a Referral: Choosing the Agency* — the full three-step wizard, including duplicate-referral warnings.
- *Managing Compliance Requirements* — how agencies and case managers track outstanding requirements across referrals.
`;
export default content;
