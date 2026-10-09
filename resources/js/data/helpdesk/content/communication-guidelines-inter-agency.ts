const content = `# Communication Guidelines for Inter-Agency Coordination

Referral coordination uses three distinct channels. Picking the right one keeps the record clear and avoids leaking internal discussion to the wrong audience.

![Referral comments panel](/assets/helpdesk/referrals-comments.png)

## The three channels

- **Referral comments** (\`POST /referrals/{referral}/comments\`) — case-linked coordination between Case Managers and agency focal persons. Visibility is **INTERNAL** by default. Use replies to keep threads together. See *Using referral comments*.
- **Agency message thread** ("Other Agencies on This Case", \`GET /api/referrals/{referral}/messages\`) — cross-agency discussion on the same case, managed by the message controller. Use this when another agency on the case needs context, not for client outreach.
- **Client requests** (\`POST /referrals/{referral}/client-requests\`, AGENCY only) — direct outreach to the client through the secure \`/track/request/*\` token flow, with its own message thread per request. Use this when you need something from the client, not for internal coordination.

Appropriate comment uses include asking for missing details, confirming who will contact the client, explaining why a document is insufficient, requesting an overdue update, or clarifying whether a referral should be completed or rejected.

## Comment structure

1. **Context**: what record or requirement you mean.
2. **Action needed**: the decision, document, or update requested.
3. **Timeframe**: when a response is needed, if urgent.

Example: "The uploaded contract is missing the signature page. Please confirm whether the client can submit a complete copy by Friday so assessment can proceed."

## Milestones and compliance

If the action is completed progress, add a milestone rather than only a comment.

![Add milestone form](/assets/helpdesk/referrals-add-milestone.png)

If the agency needs a document before proceeding, use compliance requirements. Requirements have **PENDING** and **COMPLIED** states and are fulfilled through an attachment.

![Compliance requirements](/assets/helpdesk/referrals-compliance.png)

## Professional standards

Use respectful language, avoid blame, avoid unexplained acronyms, and never include passwords, one-time codes, or unnecessary sensitive details. Sign-in uses password plus optional authenticator (TOTP) — codes are never shared in comments. Keep the system record factual even when separate escalation is needed.
`;
export default content;
