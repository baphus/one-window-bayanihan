# Business Continuity & Disaster Recovery Plan

**Document ID:** D90-2-BCP  
**Version:** 1.0  
**Date:** 2026-07-09  
**Standard:** ISO 27001 5.29/5.30, ISO 20000-1 8.7.2  
**Owner:** Information Security Manager (ISM)

## Purpose

Ensure critical business functions of the One Window Bayanihan system can continue during and after a disruptive event, and define procedures for recovery of IT infrastructure and data.

## Business Impact Analysis

| Service | Criticality | Max Downtime | Data Loss Tolerance | Recovery Priority |
|---------|-------------|-------------|--------------------|--------------------|
| Public Case Tracking | Critical | 2 hours | 0 (no data created) | 1 |
| Case Management | Critical | 4 hours | 24 hours | 2 |
| Referral Management | High | 4 hours | 24 hours | 3 |
| Reporting & Analytics | Medium | 24 hours | 24 hours | 4 |
| Feedback & SERVQUAL | Low | 48 hours | 24 hours | 5 |
| Chatbot | Low | 48 hours | 0 (stateless) | 6 |

## Recovery Objectives

| Metric | Target |
|--------|--------|
| Recovery Time Objective (RTO) | 4 hours for critical services |
| Recovery Point Objective (RPO) | 24 hours (daily backup) |
| Maximum Tolerable Downtime (MTD) | 8 hours for critical services |

## Disaster Scenarios

### Scenario A: Application Failure

**Symptoms:** /up returns non-200, Sentry spike, users see 500 errors  
**Response:**
1. Check Render dashboard for deployment health
2. Review Sentry for recent error trend
3. Rollback to last known-good deployment via Render
4. If rollback fails, trigger Render redeploy from clean build
5. Escalate to Render support if platform-level issue

### Scenario B: Database Corruption

**Symptoms:** Query errors, constraint violations, missing data  
**Response:**
1. Isolate application (maintenance page or read-only mode)
2. Identify scope from error logs and Sentry
3. Restore from latest clean backup using `scripts/restore-test.sh`
4. Verify restore integrity
5. Point application to restored database
6. Notify users of data loss window (within RPO)

### Scenario C: Third-Party Outage

**Symptoms:** Supabase/Cloudinary/OpenAI returns errors  
**Response:**
1. Confirm outage via provider status page
2. Enable degraded mode (cached data, offline fallback)
3. Notify users of limited functionality
4. Monitor provider status for restoration
5. Restore full functionality when provider recovers
6. Consider alternative provider if outage exceeds 24 hours

### Scenario D: Security Breach

**Symptoms:** Unauthorized access detected (from incident response)  
**Response:**
1. Follow Incident Response Procedure
2. Contain affected systems
3. Preserve forensic evidence
4. If data compromised, notify affected parties per RA 10173
5. Rotate all credentials
6. Restore from pre-compromise backup if needed

### Scenario E: Render Platform Outage

**Symptoms:** Application unreachable, Render dashboard showing incident  
**Response:**
1. Confirm outage on Render status page
2. If short outage (<1 hour): Wait for Render recovery
3. If extended outage: Begin DR failover process
4. For CRITICAL services only: Deploy to secondary Render service in different region
5. Update DNS to point to secondary deployment
6. Notify DMW IT of degraded service

## Communication Plan

| Event | Channel | Recipients | Timeline |
|-------|---------|------------|----------|
| P1/P2 incident | Slack #security | All team + ISM | Immediate |
| DR activation | Phone + Slack | Technical Lead + ISM | Within 15 min |
| Service degradation | Email | DMW IT contact | Within 1 hour |
| Recovery complete | Slack + Email | All stakeholders | Within 30 min |

## DR Testing Schedule

| Test Type | Frequency | Scope |
|-----------|-----------|-------|
| Backup restore test | Monthly | Restore backup to test DB, verify data integrity |
| Application failover | Quarterly | Deploy to secondary region, verify /up |
| Tabletop exercise | Annually | Walk through DR scenarios with full team |

## Related Documents

- Backup and Restore Procedure (`docs/DEPLOYMENT_GUIDE.md`)
- Incident Response Procedure (`docs/procedures/incident-response.md`)
- Service Catalogue (`docs/management/service-catalogue.md`)
- Deployment Guide (`docs/DEPLOYMENT_GUIDE.md`)
