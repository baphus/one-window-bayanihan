const content = `# Getting Started for Case Managers

Welcome to the One Window Bayanihan system! This guide will help you get started as a Case Manager and walk you through your daily workflow.

## Step 1: Logging In

1. Navigate to the system login page
2. Enter your **email address** and **password**
3. Check your email for the **6-digit OTP code** sent to your registered email
4. Enter the OTP code on the verification screen
5. You are now logged in and redirected to the **Dashboard**

> **Note:** OTP codes expire after 5 minutes. If yours expires, click **"Resend OTP"** to get a new one.

## Step 2: Dashboard Overview

The Dashboard is your command center. It shows:

- **KPI Stat Cards** at the top: total open cases, pending referrals, completed this month, and overdue referrals
- **Recent Cases List**: the most recently updated cases with status indicators
- **Pending Referrals**: referrals awaiting agency action
- **Case Trends Chart**: a visual overview of case volume over time

Take a moment to study each widget. The dashboard updates in real time as cases and referrals are processed.

## Step 3: Navigation Sidebar

The sidebar on the left gives you access to:

| Menu Item | Description |
|-----------|-------------|
| Dashboard | Your main monitoring hub |
| Cases | Full list of all cases with filtering and search |
| Referrals | All referrals sent to agencies |
| Overdue Referrals | Referrals past their SLA deadline |
| Reports | Generate case and referral reports |

## Step 4: Creating Your First Case

1. Click **"Cases"** in the sidebar
2. Click the **"New Case"** button
3. Fill out the intake form with the following sections:
   - **OFW Information**: full name, date of birth, nationality, contact number, email address
   - **Address Information**: current address, province, city/municipality
   - **Employment Information**: employer name, position, industry, country of employment
   - **Next of Kin**: name, relationship, contact number of a family member
   - **Case Summary**: detailed description of the concern or issue
   - **Documents**: upload supporting documents (PDF, JPG, or PNG, max 10MB each)
4. Click **"Save"** to create the case

The system generates a **Case Number** (internal) and a **Tracker Number** (public-facing, format OWBAP-XXXXXXX).

## Step 5: Viewing Case Details and Timeline

Open any case to see:

- **Case Information**: all details from the intake form
- **Timeline**: a chronological log of all activities, status changes, and milestones
- **Referrals**: referral records sent to partner agencies
- **Documents**: uploaded files organized by type

## Step 6: Creating a Referral

1. Open the case you want to refer
2. Click **"Create Referral"**
3. Select the **agency** and **service** needed
4. Write detailed referral notes explaining what is required
5. Attach relevant case documents
6. Click **"Submit"** — the agency receives an automatic notification

## Daily Workflow Routine

A productive daily routine looks like this:

1. **Start with the dashboard** — review new cases and pending referrals
2. **Process new intake** — review and categorize new cases
3. **Check referral updates** — see if agencies have responded
4. **Follow up on overdue items** — contact agencies or escalate
5. **Update case records** — add notes, upload new documents
6. **End with a dashboard review** — confirm everything is on track

## Tips for Efficiency

- Use the **search and filter** features to quickly locate cases
- Set aside time each morning for **proactive case review**
- Keep case notes **concise and factual** — they form the audit trail
- Use **referral milestone updates** to track agency progress
- Flag cases needing attention using the **priority indicators**
`;
export default content;
