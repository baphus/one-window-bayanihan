const content = `# Creating a Referral: Choosing the Agency

A referral formally asks a partner agency to take on work for one of your cases. The referral wizard has three steps — **Select Case**, **Select Agency**, **Details** — and won't let you continue until each step is complete.

You choose the **agency**, not the service. The receiving agency decides which of its own services the referral applies for, and then accepts or rejects the referral.

![Referral creation wizard](/assets/helpdesk/referrals-create.png)

## Step 1 — Select Case

Only **OPEN** cases can be referred. Search by tracker number, case number, or the client's name; results are paginated. Pick the case and continue.

## Step 2 — Select Agency

Choose the receiving agency. Each agency card shows how many services it currently offers, so you can compare before picking — see *Using the stakeholder directory* for help choosing.

The wizard blocks a duplicate: if the case **already has an active referral to that agency** (any referral still **PENDING**, **PROCESSING**, or **FOR_COMPLIANCE**), it flags this and stops you. Coordinate on the existing referral instead of creating a second one.

## Step 3 — Details

1. Optionally add **remarks** — context that helps the agency understand the request.
2. Optionally attach **supporting documents**. Accepted formats are PDF, DOC, DOCX, JPG, JPEG and PNG, up to 10 MB per file.
3. Submit. You'll land on the new referral page with **"Referral created successfully."**

The referral is created with status **PENDING** and is sent to the agency you chose.

## What the agency does next

1. The agency reviews the referral and **adds the service** it will apply for, from its own list of available services. Until it does, the *Required Services* section shows *"No services assigned yet."*
2. The agency makes its first disposition from the referral page:
   - **Accept** — choosing **PROCESSING** or **FOR_COMPLIANCE** *(waiting on client)* and adding a required remark, or
   - **Reject** — with a required reason and remark.
3. Only after that does the referral move out of **PENDING**.

![Referral detail page](/assets/helpdesk/referrals-show.png)

## What happens to the uploaded documents

Documents attached in the wizard become the referral's attachments and are visible to the receiving agency. Anything the agency still needs is raised afterwards as a compliance requirement, which the agency fulfils with the **Upload to Fulfill** action on the referral page — those uploads are visible only to the referred agency. See *Managing compliance requirements*.

## After sending

- Track the referral's status and milestones on the referral page — see *Referral status reference*. Repeating the current status is a harmless no-op that sends no notification.
- Services can still be adjusted afterwards: the receiving agency can add or remove services on the referral page and manage each service's document requirements as needs change.
- For document handoffs that need the client directly, the agency can open a client request with a secure, time-limited private link instead of routing everything through comments.
- Use referral **comments** to coordinate with the agency, choosing the right visibility for each note — see *Using referral comments*.
- Watch aging referrals on the **Overdue referrals** page and your notifications feed. A referral is considered overdue once there has been no activity for more than the configured number of days (7 by default). When the referral is finally marked **COMPLETED**, the client automatically receives a survey invitation.

> A well-targeted referral — the right agency, clear remarks, complete documents — is the single biggest factor in fast processing. Referrals that leave the agency guessing typically bounce back as *For Compliance*, adding days of back-and-forth.
`;
export default content;
