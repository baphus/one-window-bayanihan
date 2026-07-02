const content = `# Managing Overdue Referrals

Overdue referrals can delay case resolution and affect client satisfaction. This guide covers how to identify, manage, and prevent overdue referrals.

## Accessing the Overdue Referral Dashboard

Navigate to **"Overdue Referrals"** in the sidebar. This page shows:

- All referrals past their SLA deadline
- Sortable by **days overdue**, **agency**, or **service type**
- Color-coded urgency indicators
- Quick action buttons for follow-up

## Understanding SLA Tracking

Each service has a defined **Service Level Agreement (SLA)** measured in processing days:

| SLA Status | Color | Meaning |
|------------|-------|---------|
| On Track | Green | Within SLA period |
| Approaching | Yellow | Less than 24 hours until deadline |
| Overdue | Orange | 1-3 days past deadline |
| Critical | Red | More than 3 days past deadline |

The SLA is calculated from the date the agency accepts the referral.

## Sending Reminder Notifications

1. Open the overdue referral
2. Click **"Send Reminder"**
3. The agency focal person receives an automatic notification
4. A log entry is added to the case timeline

> **Tip:** Send a reminder as soon as a referral approaches the SLA deadline, not after it becomes overdue.

## Escalation Procedures for Chronically Overdue Referrals

If a referral remains overdue despite reminders, follow the escalation ladder:

### Level 1: Direct Contact
Contact the agency focal person directly by phone or internal message. Clarify the reason for the delay and agree on a revised timeline.

### Level 2: Agency Supervisor
If the focal person is unresponsive, escalate to the agency supervisor. Provide a summary of the referral history and previous communication attempts.

### Level 3: DMW Coordinator
Notify the DMW coordinator responsible for agency relations. They can facilitate a resolution at the management level.

### Level 4: Director Review
For critical cases more than 10 days overdue, escalate to the DMW Region VII Director for formal intervention.

## Documenting Escalations

Every escalation step must be documented:

- Record the date and method of contact
- Note who was contacted and their response
- Update the referral notes with the outcome
- Attach any relevant correspondence

## Preventing Overdue Referrals

### Proactive Communication
- Introduce yourself to agency focal persons early
- Confirm receipt of referrals within 24 hours
- Check in mid-way through the SLA period

### Clear Referral Notes
Write referrals that set the agency up for success:

- Specify exactly what services are needed
- Include relevant case context
- Attach all supporting documents upfront
- State any deadlines or urgency clearly

### Realistic Deadlines
- Understand each agency's typical processing times
- Discuss timelines with the agency if unsure
- Build in buffer time for complex cases

## Monitoring Agency Performance

Use the dashboard to track agency performance trends:

- Average processing time per agency
- On-time completion rate
- Number of overdue referrals over time

Share this data with agency supervisors during regular coordination meetings. Use it to identify agencies that may need additional support or training.
`;
export default content;
