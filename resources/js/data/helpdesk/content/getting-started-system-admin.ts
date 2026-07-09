const content = `# Getting Started for System Administrators

This tutorial is for users with the **ADMIN** role. Administrators maintain the One Window Bayanihan platform: users, agencies, services, case reference data, settings, audit logs, maintenance, and security-related configuration.

## 1. Sign in securely

![Login page](/assets/helpdesk/login-page.png)

1. Open the login page from an approved network location if IP restrictions are enabled.
2. Enter your administrator **email address** and **password**.
3. Complete OTP/MFA verification.

![OTP verification](/assets/helpdesk/login-otp.png)

> **Important:** OTP codes expire after **5 minutes**. Request a new code if the first one expires. Never ask another user to share an OTP.

After successful verification, you will see the administrator dashboard.

![Admin dashboard](/assets/helpdesk/dashboard-admin.png)

## 2. Understand administrator responsibilities

Administrators configure the system so Case Managers and Agency users can work correctly. Your role is powerful, so changes should be deliberate and documented.

| Area | Why it matters |
|---|---|
| Users | Determines who can access the platform and which role they receive. |
| Agencies | Controls partner agency records and referral routing. |
| Services | Defines what services can be selected for referrals. |
| Case reference data | Keeps categories, issues, statuses, and related lists consistent. |
| Settings and security | Affects authentication, maintenance behavior, and system-wide rules. |
| Audit logs | Provides accountability for sensitive activity. |

Use the least-privilege principle: assign users only the role they need.

## 3. Manage users

![Admin users](/assets/helpdesk/admin-users.png)

Go to the administrator user-management area to create or maintain accounts.

The supported application roles are:

| Role | Typical user |
|---|---|
| **CASE_MANAGER** | DMW staff who create cases, manage drafts, publish cases, create referrals, monitor dashboards, and view reports. |
| **AGENCY** | Partner agency focal persons who work only on referrals scoped to their agency. |
| **ADMIN** | System administrators who maintain users, agencies, services, settings, audit logs, and maintenance/security pages. |

When creating or editing a user:

1. Verify the person's name and official email address.
2. Assign the correct role.
3. For **AGENCY** users, associate the user with the correct agency so referral scoping works.
4. Deactivate accounts that should no longer access the system instead of removing historical accountability.

> **Warning:** Do not reuse accounts for different people. The audit trail depends on each action being tied to the correct user.

## 4. Manage agencies

![Admin agencies](/assets/helpdesk/admin-agencies.png)

Agency records represent the partner offices that receive referrals. Keep agency names, contact details, and identifiers accurate so Case Managers can route work correctly and Agency users see the right workload.

Recommended process:

1. Create or update the agency record.
2. Confirm official contact information.
3. Assign or verify agency focal users with the **AGENCY** role.
4. Review the agency's services before allowing routine referrals.

If an agency is temporarily unable to accept work, update services or availability through the supported admin screens rather than asking Case Managers to remember an informal rule.

## 5. Manage services

![Admin services](/assets/helpdesk/admin-services.png)

Services define what Case Managers can request from partner agencies. A service should be clear enough that the receiving agency understands the expected action.

For each service, confirm:

- The service name is descriptive.
- It is linked to the correct agency.
- Processing time or SLA information is accurate if the field is available.
- Inactive or obsolete services are not offered for new referrals.

Avoid creating duplicate services with slightly different names. Duplicates make reports harder to interpret and increase the chance of routing errors.

## 6. Maintain case reference data and settings

Administrators may maintain case categories, case issues, case statuses, and system settings through the available admin pages.

Current case statuses in the application are:

| Internal status | Public tracking status |
|---|---|
| **DRAFT** | **BEING_PREPARED** |
| **OPEN** | **IN_PROGRESS** |
| **CLOSED** | **RESOLVED** |
| **ARCHIVED** | **ARCHIVED** |

Cases are draft-first: when a Case Manager saves a new case, the case is created as **DRAFT**. Publishing changes it to **OPEN**. Keep this workflow in mind when reviewing reports or assisting users who cannot find a case in an active workload list.

Referral statuses are **PENDING**, **PROCESSING**, **FOR_COMPLIANCE**, **COMPLETED**, and **REJECTED**. Do not rename these meanings in help text, training material, or reference configuration without confirming code support.

When changing settings:

1. Record why the change is needed.
2. Make the smallest change that solves the problem.
3. Test the affected workflow with the appropriate role.
4. Communicate visible workflow changes to users.

## 7. Review audit logs

![Admin audit log](/assets/helpdesk/admin-audit-log.png)

The audit log helps administrators investigate activity such as logins, case changes, referral actions, document activity, and configuration changes. Use filters such as user, module, action, and date range when available.

Review audit logs when:

- A user reports unexpected changes.
- Sensitive case information may have been accessed incorrectly.
- A configuration change affects production workflow.
- You are preparing an administrative incident report.

> **Reminder:** Audit logs are for accountability and troubleshooting. Do not export or share them outside authorized channels.

## 8. Maintenance and security tasks

![Admin maintenance](/assets/helpdesk/admin-maintenance.png)

Use the maintenance and security pages, if available, for operational checks and controlled administrative actions. Depending on deployment, some tasks may also be performed by technical staff through server tools.

Common administrator checks include:

| Check | Purpose |
|---|---|
| User access review | Remove or deactivate accounts for reassigned staff. |
| Agency and service review | Prevent referrals to inactive or incorrect services. |
| Audit review | Detect unusual access or configuration changes. |
| File/storage check | Confirm uploaded documents remain accessible to authorized users. |
| Queue/job review | Ensure notifications and background tasks are processing. |

Follow your office's change-control process for production maintenance. Avoid making major settings changes during peak intake hours unless necessary.

## 9. Administrator daily checklist

1. Check the **Dashboard** for system-level indicators.
2. Review pending user or agency updates.
3. Confirm agency services are current when new partners or programs are added.
4. Review selected audit log entries for unusual patterns.
5. Coordinate with technical support for maintenance, failed jobs, storage, or deployment issues.
6. Document user-facing changes so Case Managers and Agency users know what changed.

Good administration keeps the platform reliable without interrupting day-to-day case and referral work.
`;
export default content;
