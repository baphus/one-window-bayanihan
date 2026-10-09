const content = `# Using Referral Comments

Referral comments keep coordination attached to the referral record. They support content, visibility, and replies. Visibility is **INTERNAL** by default — these are staff coordination notes, not client messages.

![Referral comments](/assets/helpdesk/referrals-comments.png)

## Comments are one of three channels

- **Referral comments** (this page) — INTERNAL staff coordination tied to one referral.
- **Agency message thread** ("Other Agencies on This Case", \`/api/referrals/{referral}/messages\`) — cross-agency discussion on the case.
- **Client-request messages** (\`/track/request/*\` token flow) — direct outreach to the client. Agency accounts create requests; the client responds through a secure token link.

Do not use referral comments to contact the client. Create a client request instead.

## When to comment

Use comments to ask an agency focal person to confirm eligibility, tell a Case Manager that a document is unreadable, reply about client contact details, explain why a referral cannot proceed, or coordinate follow-up on an overdue referral.

Use a milestone for completed progress. Use compliance requirements when a document or proof must be submitted.

## Clear comment pattern

Include the issue, requested action, and deadline when needed.

Weak: "Please update."

Better: "Please confirm whether the contract copy is sufficient for assessment. If not, identify the missing page so the Case Manager can request it today."

## Replies and sensitivity

Use replies for direct answers so the thread stays together. If the reply reports completed work, add a milestone too.

Internal does not mean informal. Comments may be audited or reviewed. Do not include passwords, one-time codes, unnecessary ID numbers, personal opinions, unrelated medical or family details, or side conversations.

![Referrals index](/assets/helpdesk/referrals-index.png)

## Checklist

- Is the message tied to this referral?
- Is the requested action clear?
- Is the responsible party obvious?
- Should this be a milestone, a message-thread post, a client request, or a compliance requirement instead?
`;
export default content;
