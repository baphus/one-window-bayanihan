const content = `# Building SERVQUAL Feedback Questionnaires

Agencies control the questions clients answer when they rate a completed service. Feedback forms are managed on the **SERVQUAL Configurations** page, available to Agency Focal Person accounts under **Feedback → SERVQUAL Configurations**.

![SERVQUAL configurations list](/assets/helpdesk/servqual-config-index.png)

## How forms are chosen for a client

Two kinds of forms exist, and the system picks between them automatically when a feedback invitation is sent:

- **Agency default** — a form not assigned to any service. It is used for every service that has no override. Only one default can be **active** at a time.
- **Service override** — a form assigned to one specific service. Overrides are always active independently, and each service can have at most one override.

> When an invitation is sent to a client, the form questions are **snapshotted** into that invitation. Editing a form later changes future invitations only — links already sent keep the questions they were issued with.

## Creating a form

1. Open **Feedback → SERVQUAL Configurations** and select **Create configuration**.
2. Enter a **Form name** (for example, *Default Client Satisfaction Form*). This name is for your team; clients do not see it.
3. Optionally assign the form to one of your services. Leave it unassigned to create an agency default.
4. Review the questions. New forms start with the **22 standard SERVQUAL questions** across five dimensions:
   - **Tangibles** (4 questions) — facilities, equipment, staff appearance
   - **Reliability** (5 questions) — keeping promises, dependable service, accurate records
   - **Responsiveness** (4 questions) — prompt service and willingness to help
   - **Assurance** (4 questions) — trust, safety, politeness
   - **Empathy** (5 questions) — individual attention and convenient hours
5. Edit question text, remove questions, or select **+ Add question** to add your own.
6. Select **Save Configuration**.

Your first agency default is activated automatically. Additional default forms are saved as **Inactive** until you make one active.

> Several standard SERVQUAL questions (in Responsiveness and Empathy) are deliberately phrased negatively — for example, "The agency's employees are NOT always willing to help you." This is part of the original SERVQUAL instrument. You may reword them if your team prefers consistently positive phrasing.

![SERVQUAL configuration form](/assets/helpdesk/servqual-config-form.png)

## Managing forms from the list

The configuration list shows each form's **Assignment**, **Questions** count, **Status**, and **Created** date, with these actions:

- **Make active** — promote an inactive default form. The previously active default is deactivated automatically.
- **Assign service** — turn a form into a service override. Choose the service in the dialog and select **Assign**. A service that already has a form cannot receive a second one.
- **Unassign** — remove a service override. If an agency default exists, the override is deleted and the default takes over for that service; if no default exists, the form becomes your agency default.
- **Edit** — change the name, assignment, or questions.
- **Delete** — remove a form. The active default cannot be deleted while other forms exist; activate another default first.

## Where responses go

Client answers appear on your **Feedback Dashboard**, scored per question (expectation and experience ratings from 1 to 5) and averaged per SERVQUAL dimension. See *Reading your agency feedback dashboard* for how to interpret the results.
`;
export default content;
