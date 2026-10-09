const content = `# Adding Milestones to Referrals: Complete Guide

Milestones record meaningful referral progress on the referral timeline. Each milestone has a title and description, then notifications are sent to relevant users.

![Referral details timeline](/assets/helpdesk/referrals-show.png)

## When to add a milestone

Add one when the agency reviewed the referral, contacted the client, received documents, accepted compliance, rendered service, changed status, or completed work. Use comments for questions; use milestones for timeline-worthy progress.

## Steps

1. Open the referral details page.
2. Choose the add milestone action.
3. Enter a short title.
4. Write a factual description.
5. Save and confirm it appears in the timeline.

![Add milestone form](/assets/helpdesk/referrals-add-milestone.png)

## Strong titles

Good titles are brief and action-oriented: "Reviewed submitted employment documents," "Contacted client for compliance instructions," or "Completed agency assessment." Avoid vague titles such as "Update" or "Done."

## Useful descriptions

Include what was done, who was involved, what was found, and the next step. Do not include passwords, one-time codes, or unrelated sensitive details.

## Status connection

Status changes follow the referral status workflow (see *Referral status reference*). Self-transitions are idempotent no-ops. A referral cannot jump directly from **PENDING** to **COMPLETED**, and terminal statuses (**COMPLETED**, **REJECTED**) are locked.

When a referral becomes **COMPLETED**, the system triggers the completion handling: a client survey invitation is issued against your agency's active survey form, and notifications go out. Add a final milestone so the result is understandable. If requirements remain **PENDING**, do not treat the referral as complete.
`;
export default content;
