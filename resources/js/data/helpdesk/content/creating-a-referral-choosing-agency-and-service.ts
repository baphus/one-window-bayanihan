const content = `# Creating a Referral: Choosing Agency and Service

A referral formally asks a partner agency to deliver a service for one of your cases. The referral wizard walks you through three steps — **Select Case**, **Select Agency**, **Select Service** — and won't let you continue until each step is complete.

![Referral creation wizard](/assets/helpdesk/referrals-create.png)

## Step 1 — Select Case

Only **OPEN** cases can be referred. Search by tracker number, case number, or the client's name; results are paginated. Pick the case and continue.

## Step 2 — Select Agency

Choose the receiving agency. If the case **already has a referral to that agency**, the wizard flags it and blocks the duplicate — coordinate on the existing referral instead of creating a second one. To compare agencies before choosing, see *Using the stakeholder directory*.

## Step 3 — Select Service and attach requirements

1. Choose one or more of the agency's services ("Choose services and attach requirements").
2. Each selected service lists its **document requirements**. For every requirement, either:
   - **Upload the document** now, or
   - **Mark it for compliance** — the referral is sent without it, and the item becomes a compliance requirement the agency will formally request.
3. The wizard warns *"Upload all required documents before submitting"* if a requirement is neither uploaded nor marked for compliance.
4. Submit. You'll land on the new referral page with **"Referral created successfully."**

## What happens to the uploaded documents

Documents attached in the wizard become the referral's attachments, labeled per requirement, and are visible to the receiving agency. Items you marked for compliance appear on the referral as open compliance requirements — they are fulfilled later with the **Upload to Fulfill** action on the referral page (those uploads are visible only to the referred agency). See *Managing compliance requirements*.

## After sending

- Track the referral's status (*Pending → Processing → … → Completed*) and milestones on the referral page — see *Referral status reference*.
- Use referral **comments** to coordinate with the agency, choosing the right visibility for each note — see *Using referral comments*.
- Watch aging referrals on the **Overdue referrals** page and your notifications feed.

> A well-targeted referral — right service, complete documents — is the single biggest factor in fast processing. Referrals with missing documents typically bounce back as *For Compliance*, adding days of back-and-forth.
`;
export default content;
