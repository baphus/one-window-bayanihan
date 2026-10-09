const content = `# Building Client Survey Forms

Agencies control the questions clients answer when they rate a completed service. Survey forms are managed on the **Survey Forms** page, available to Agency Focal Person accounts under **Feedback → Survey Forms** (AGENCY only).

## How forms are chosen for a client

Each agency has **one active form** at a time. When a referral is completed and a survey invitation is sent, the client's link uses your agency's currently active form. Activating a different form changes future invitations only — links already sent keep working against the form they were issued with.

Invitations expire after **30 days**. Expired links stop accepting answers; complete a new referral cycle to issue a fresh invitation.

## Creating a form

1. Open **Feedback → Survey Forms** and select **Create form**.
2. Enter a **Title** (for example, *Client Satisfaction Survey*) and an optional **Description**. The title is for your team; clients see the questions, not the form name.
3. Add questions with **+ Add question**. Each question has a type:
   - **Likert** — five-point agree/disagree scale with faces (Strongly Disagree → Strongly Agree).
   - **Rating** — five-star scale (Poor → Excellent).
   - **Text** — free-written answer.
   - **Radio** — single choice from a list.
   - **Checkbox** — multiple choices from a list.
4. Mark required questions where an answer must be given.
5. Select **Save**. New forms start inactive.

## Activating a form

Only one form per agency can be active. Open the form and select **Activate** — the previously active form is deactivated automatically, and new invitations start using the newly activated one.

## Editing and deleting

- A form can be edited only while **no invitations have been sent from it**. Once invitations exist, the form becomes immutable and the system asks you to **create a replacement form instead** — this protects the meaning of answers already collected.
- Inactive forms with no invitations can be deleted. An active form cannot be deleted while other forms exist; activate another form first.

## Where responses go

Client answers appear under **Feedback → Survey Responses**, one row per submitted invitation. See *Reading your agency feedback* for how to interpret the results.
`;
export default content;
