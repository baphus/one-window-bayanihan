const content = `# Case Closure Checklist and Procedures

Closing a case properly ensures complete documentation and a smooth transition for the client. Follow this checklist to avoid common errors.

## Pre-Closure Verification

Before closing any case, verify each of the following:

### 1. All Referrals Are in Terminal States

Check every referral created for this case:

| Status | Allow Closure? |
|--------|---------------|
| COMPLETED | Yes |
| REJECTED | Yes |
| PENDING | No — must be resolved first |
| PROCESSING | No — must be completed or reassigned |
| FOR COMPLIANCE | No — must be resolved first |

If any referral is not in a terminal state, contact the agency or reassign the referral before proceeding.

### 2. Client Confirmation Received

- Confirm that the client has been informed of the case outcome
- Document the client's acknowledgment (verbal confirmation is acceptable, written is preferred)
- If the client cannot be reached, document all attempts made

### 3. All Required Documents Are Uploaded

Ensure the case file contains:

- **Initial intake documents** — verified and complete
- **Referral documents** — referral forms and agency responses
- **Milestone records** — all significant actions documented
- **Final case summary** — outcome documentation

### 4. Final Case Summary Completed

Write a comprehensive closure summary including:

- Brief overview of the case
- Actions taken by DMW and partner agencies
- Outcome and resolution details
- Any pending follow-up items or recommendations
- Client feedback if available

## The Closure Process

1. Navigate to the **case details page**
2. Click the **"Close Case"** button
3. Read the confirmation dialog carefully
4. Confirm by clicking **"Yes, Close Case"**
5. The case status changes to **CLOSED**

> **Warning:** Closing a case is irreversible. Make sure all steps above are completed before confirming.

## Closing vs Archiving

| Action | What It Does | When to Use |
|--------|-------------|-------------|
| Close | Marks the case as resolved, no further action needed | Case is fully resolved |
| Archive | Hides the case from active lists but preserves all data | Case is closed and no longer needed in regular view |

After closing, the case is automatically archived after a set period.

## Post-Closure Procedures

### Client Feedback

Once closed, the client receives a notification inviting them to provide feedback:

- **SERVQUAL survey** — rates service quality across five dimensions
- Feedback is optional but encouraged
- Results are anonymized for quality reporting

### Documentation Review

- Verify the case file is complete
- Ensure all notes are finalized
- Confirm the audit trail is unbroken

### Performance Tracking

Closed cases contribute to monthly performance metrics:
- Cases closed per Case Manager
- Average processing time
- Client satisfaction scores
- Agency performance data

## Common Closure Errors

- Closing with **active referrals** — always resolve referrals first
- Missing **final summary** — write the summary before closing
- Forgetting to **notify the client** — inform them of the outcome
- Skipping **document verification** — ensure all files are uploaded
`;
export default content;
