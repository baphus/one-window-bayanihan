const content = `# Getting Started for Agency Focal Persons

Welcome to the One Window Bayanihan system! This guide will help you get started as an Agency Focal Person handling referrals from DMW Region VII.

## Step 1: Logging In

1. Navigate to the system login page
2. Enter your **email address** and **password**
3. Check your email for the **6-digit OTP code**
4. Enter the OTP code to complete authentication
5. You arrive at your **Agency Dashboard**

## Step 2: Dashboard Overview

Your agency-specific dashboard shows:

- **Referrals Received**: total referrals sent to your agency
- **In Progress**: referrals currently being processed
- **Completed This Month**: successfully closed referrals
- **Overdue**: referrals past the SLA processing time
- **Recent Referrals List**: newest referrals requiring action

## Step 3: Viewing Incoming Referrals

1. Click **"Referrals"** in the sidebar
2. The list shows all referrals sent to your agency
3. Use filters to view by status: **Pending**, **Processing**, **Completed**, or **Rejected**
4. Click any referral to view full details including case context and attached documents

## Step 4: Accepting or Rejecting a Referral

When you receive a new referral:

1. Open the referral details
2. Review the **referral notes** and **attached documents**
3. Decide whether your agency can handle the request
4. Click **"Accept"** to start processing, or **"Reject"** to decline

> **Important:** If rejecting, you **must** provide a reason in the comments. This helps the Case Manager reassign the referral appropriately.

## Step 5: Processing Referrals and Adding Milestones

While processing a referral:

1. Update the status to **PROCESSING** when work begins
2. Add **milestone entries** at each significant progress point:
   - Initial assessment completed
   - Client interview conducted
   - Documents verified
   - Service rendered
3. If more information is needed, set status to **FOR COMPLIANCE** and specify what is required

## Step 6: Updating Referral Status

The valid status flow is:

| From | To | When |
|------|-----|------|
| PENDING | PROCESSING | Work has started |
| PROCESSING | FOR COMPLIANCE | More info needed from client |
| FOR COMPLIANCE | PROCESSING | Compliance received |
| PROCESSING | COMPLETED | All services rendered |
| Any | REJECTED | Agency cannot fulfill |

Each status change requires a brief note explaining the reason.

## Step 7: Communicating with Case Managers

Use the **comments section** on each referral to communicate:

- Ask clarifying questions about the referral
- Provide progress updates
- Flag any issues or delays
- Confirm completion details

Comments support threaded replies for organized conversations.

## Step 8: Managing Service Offerings

Keep your agency profile accurate:

1. Go to **"Services"** in the sidebar
2. Review your list of offered services
3. Update **processing time estimates** (SLA) as needed
4. Deactivate services your agency is currently unable to provide

## Tips for Success

- Check the dashboard **at least twice daily** for new referrals
- Respond to new referrals **within 24 hours** (accept or reject)
- Add milestones for **every significant action** taken
- Communicate **delays proactively** — do not wait for the Case Manager to ask
- Keep your service listings **accurate and up to date**
`;
export default content;
