const content = `# Getting Started for System Administrators

Welcome to the One Window Bayanihan system! This guide covers the administrative functions you need to manage the platform effectively.

## Step 1: Logging In

Administrator accounts may have additional security:

1. Your IP address may need to be on the **approved whitelist**
2. Enter your **email** and **password**
3. Complete the **OTP verification** step
4. You are logged in with full administrative access

## Step 2: User Management

Navigate to **Admin > Users** to manage all system users.

### Creating a New User

1. Click **"New User"**
2. Fill in the required fields:
   - **Name** — full name of the user
   - **Email** — must be unique in the system
   - **Password** — minimum 8 characters
   - **Role** — CASE_MANAGER, AGENCY, or ADMIN
   - **Agency** — required only for AGENCY role users
3. Click **"Save"** to create the account

### Managing Existing Users

- **Edit** user details as needed
- **Deactivate** accounts for users on leave or reassigned
- Use deactivation rather than deletion to preserve audit trails

## Step 3: Agency Management

Navigate to **Admin > Agencies** to register and configure partner agencies.

1. Click **"New Agency"**
2. Enter the agency name, short code, and contact information
3. Assign at least one user with the **AGENCY** role
4. The agency can now receive referrals from Case Managers

## Step 4: Service Management

Services define what each agency can handle:

1. Go to **Admin > Services**
2. Add a new service with:
   - **Service name** — clear and descriptive
   - **Agency** — which agency provides this service
   - **Processing days** — SLA target for completion
3. Update or deactivate services as agency capacity changes

## Step 5: System Settings

Navigate to **Admin > System Settings** to configure:

| Setting | Purpose | Recommendation |
|---------|---------|----------------|
| OTP Debug Mode | Auto-fills OTP in dev | Enable only in development |
| Session Timeout | User session duration | Default 120 minutes |
| File Upload Limit | Max document size | 10MB |
| Allowed File Types | Accepted formats | PDF, JPG, PNG |

> **Warning:** Never enable OTP Debug Mode in production. It bypasses real OTP verification.

## Step 6: Helpdesk CMS Overview

The system includes a knowledge base for users:

- **Articles** — markdown-based help content with live preview
- **Categories** — organize articles by topic
- **Tags** — cross-reference articles by keyword

Navigate to **Admin > Helpdesk** to manage content. You can create, edit, preview, and publish articles. Each edit creates a revision for rollback capability.

## Step 7: Audit Log Basics

Every action in the system is logged:

- User logins and logouts
- Case creation and updates
- Referral processing
- Document access
- Configuration changes

Use the **Audit Log** page to review activity. Filter by action type, module, user, or date range. Regularly review logs for unusual access patterns.

## Step 8: Maintenance Tasks

- Run \`php artisan queue:listen\` to process background jobs
- Run \`php artisan cache:clear\` if configuration changes do not take effect
- Monitor the \`failed_jobs\` table for queue errors
- Ensure \`php artisan storage:link\` has been run for file uploads
`;

export default content;
