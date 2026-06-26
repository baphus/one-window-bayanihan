const content = `# Understanding Case Statuses and Tracker Numbers

This guide explains the numbering system and status lifecycle used in the One Window Bayanihan system.

## Case Numbers vs Tracker Numbers

The system uses two different identifiers:

| Identifier | Format | Purpose | Who Sees It |
|------------|--------|---------|-------------|
| Case Number | UUID (internal) | Internal DMW record keeping | Case Managers and admins |
| Tracker Number | OWBAP-XXXXXXX | Public tracking | OFWs and the public |

The **Tracker Number** is specifically designed for public use. It is shorter, easier to communicate, and does not expose internal system identifiers.

## Case Lifecycle

Every case moves through these stages:

### 1. OPEN
The case has been submitted and is awaiting review. A Case Manager has not yet been assigned or initial assessment has not started.

### 2. PROCESSING
A Case Manager is actively working on the case. This may involve:
- Reviewing submitted documents
- Contacting the client for additional information
- Coordinating with partner agencies
- Creating referrals

### 3. FOR COMPLIANCE
The case is waiting for the OFW or another party to provide additional documents or information. The Case Manager has specified what is needed. The clock pauses until compliance is received.

### 4. CLOSED
The case has been resolved. All referrals are in terminal states (COMPLETED or REJECTED). Final documentation has been completed. The client may now provide feedback.

## Referral Statuses

When a case is referred to a partner agency, the referral follows its own lifecycle:

| Status | Meaning |
|--------|---------|
| PENDING | Awaiting agency acceptance |
| PROCESSING | Agency is actively working on the referral |
| FOR COMPLIANCE | Agency needs more information from the client |
| COMPLETED | Agency has rendered all required services |
| REJECTED | Agency declined the referral (with reason) |

## Milestones and Timeline

Every status change and significant action creates a **timeline entry**. This provides a complete, immutable history of the case. Both internal users and the public (via the tracking portal) can view the timeline, though internal notes remain confidential.

## Why This Matters for OFWs

- Your **Tracker Number** is your key to checking case status anytime
- **FOR COMPLIANCE** means action is needed from you — check what documents are required
- **CLOSED** means your case is resolved — look for the feedback invitation
- Each status change gives you visibility into how your case is progressing
`;

export default content;
