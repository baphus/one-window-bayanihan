# Access Review Procedure

**Document ID:** D90-7-ARP  
**Version:** 1.0  
**Date:** 2026-07-09  
**Standard:** ISO 27002 5.18/5.11/5.19, RA 10173  
**Owner:** Information Security Manager (ISM)

## Purpose

Define a structured process for reviewing and managing user access rights to the One Window Bayanihan system, ensuring that only authorized individuals have appropriate access.

## Scope

This procedure covers all user accounts in the One Window Bayanihan system:
- DMW staff (case managers, administrators)
- Agency staff (partner agency users)
- System accounts (queue workers, scheduled tasks)
- Third-party API integrations (OpenAI, Cloudinary, Supabase)

## Access Review Cadence

| Review Type | Frequency | Performer | Scope |
|-------------|-----------|-----------|-------|
| Privileged user review | Quarterly | ISM | ADMIN role users, API keys, service accounts |
| All user review | Semi-annual | Technical Lead | All active users, role assignments |
| Offboarding check | On event | HR + Technical Lead | Departing staff, contract end |
| Supplier access review | Annually | ISM | Third-party API keys, integration tokens |

## Review Process

### 1. Quarterly Privileged User Review

- [ ] Export list of ADMIN role users from database
- [ ] Verify each admin user is still active and requires admin access
- [ ] Check for dormant admin accounts (no login in 90 days)
- [ ] Rotate API keys for service accounts
- [ ] Document review findings

### 2. Semi-Annual All User Review

- [ ] Export all active users with roles
- [ ] Verify role assignment matches job function
- [ ] Identify and disable accounts inactive >90 days
- [ ] Check for appropriate role separation (no user in conflicting roles)
- [ ] Update access matrix if needed

### 3. Offboarding Procedure

When an employee or contractor departs:

- [ ] ISM notified by HR within 24 hours
- [ ] Disable user account immediately
- [ ] Revoke API keys and personal access tokens
- [ ] Remove from Slack, GitHub, Render, Supabase teams
- [ ] Transfer case assignments to another staff member
- [ ] Document offboarding with timestamp and evidence
- [ ] Retain offboarding record for minimum 3 years

### 4. Supplier Access Review

- [ ] Review all third-party integrations and their access scope
- [ ] Verify DPAs are current and signed
- [ ] Rotate shared secrets if any
- [ ] Remove unused API keys
- [ ] Check provider's SOC 2 / ISO 27001 certification status

## Access Request Process

### New User Provisioning

1. Manager submits access request via DMW IT
2. ISM approves based on role requirements
3. Technical Lead creates account with minimum necessary privileges
4. User completes onboarding (MFA enrollment, password setup)
5. Access granted notification sent to manager

### Role Change

1. Manager submits role change request
2. ISM reviews and approves
3. Technical Lead updates role assignment
4. Previous privileges revoked if no longer needed

## Role Access Matrix

| Feature | ADMIN | CASE_MANAGER | AGENCY |
|---------|-------|--------------|--------|
| Case CRUD | Full | Own cases only | Referred cases only |
| Referral CRUD | Full | Own referrals | Own referrals |
| User Management | Full | None | None |
| System Settings | Full | None | None |
| Reports | All | Own data | Own agency data |
| Feedback Config | None | None | Own agency |
| Public Tracking | Read | Read | Read |

## Password & MFA Policy

| Requirement | Value |
|-------------|-------|
| Minimum password length | 8 characters (configurable) |
| Password complexity | Require uppercase, lowercase, number, special char |
| Password expiry | 90 days (configurable) |
| MFA | Required for all staff accounts |
| Session timeout | 60 minutes of inactivity (configurable) |
| Account lockout | After 5 failed attempts, 15-min lockout |

## Records Retention

| Record Type | Retention Period |
|-------------|-----------------|
| Access review records | 3 years |
| Offboarding records | 3 years post-employment |
| Access request forms | 1 year post-closure |
| Audit logs (login) | 1 year |

## Related Documents

- Incident Response Procedure (`docs/procedures/incident-response.md`)
- Quality Policy (`docs/management/quality-policy.md`)
- Change Management Procedure (`docs/procedures/change-management.md`)
