const content = `# Providing Feedback on Your Case

After a referral on your case is marked **COMPLETED**, you may receive a **survey invitation link** by email. Your answers help DMW Region VII and its partner agencies improve service quality. No login is needed — the link itself is your access.

## How invitations are sent

When an agency completes its referral, the system fires a completion event and the **SendSurveyRequest** handler creates one invitation per referral using that agency's active survey form, then emails you a personal link of the form **/survey/{token}**. Each link:

- belongs to one referral and one agency's form,
- can be submitted only once (one-shot),
- expires **30 days** after it is created.

## Before you start

- Use the link from your email. It is personal to your case and expires, so submit while it is valid.
- If the page says the link is invalid, already submitted, or expired, it has already been used or has lapsed — contact the office if you still want to share feedback.

## How the form works

Each agency designs its own questions, so forms differ, but you will meet these question styles:

- **Likert** — agreement scale from Strongly Disagree to Strongly Agree (1 to 5).
- **Rating** — numeric rating from 1 to 5.
- **Radio** — pick exactly one option from a list.
- **Checkbox** — pick one or more options from a list.
- **Text** — type your experience in your own words.

## Steps

1. Open the survey link from your email. The form shows the service being rated and, where available, your name.
2. Answer each question. Required questions must be answered before you can submit.
3. Optionally add written comments. Be honest and specific — but do not include passwords, OTPs, bank details, or other sensitive information.
4. Select submit. You will see a **Feedback Submitted** confirmation when it is recorded. Each link can be submitted only once.

## What happens to your answers

Your answers are stored with your case and appear — including any comments — on the survey responses pages used by the agency, DMW case managers, and administrators. Low scores are not held against you and do not affect your case; they are used to improve how services are delivered.

> A note for staff: never fill in or influence a client's survey. You may show clients where the link is and explain what the form is for. If feedback reveals an unresolved problem, handle it through the normal case process.
`;
export default content;
