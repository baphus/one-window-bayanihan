# Change Management Procedure

**Document ID:** D60-9-CMP  
**Version:** 1.0  
**Date:** 2026-07-09  
**Standard:** ISO 20000-1 8.5.1, ISO 27002 8.32  
**Owner:** Technical Lead

## Purpose

Ensure all changes to the One Window Bayanihan system are assessed, approved, implemented, and verified in a controlled manner to minimize service disruption and security risk.

## Scope

This procedure applies to all changes affecting:
- Application code (PHP, JavaScript, configuration files)
- Infrastructure (Docker, Render, Supabase, Cloudinary)
- CI/CD pipeline (GitHub Actions)
- Third-party integrations (API keys, service configurations)
- Database schema and migrations

## Change Types

| Type | Label | Examples | Approval |
|------|-------|----------|----------|
| Standard | Pre-approved, low-risk | Bug fixes, dependency updates, config changes | — (documented post-hoc) |
| Normal | Requires review | New features, schema changes, API integrations | Technical Lead + ISM |
| Emergency | Immediate fix needed | Security patches, hotfixes, outage recovery | Technical Lead (retroactive approval) |

## Change Process

### 1. Request

- [ ] Create GitHub issue with label `change-request`
- [ ] Describe: what, why, risk assessment, rollback plan
- [ ] Classify change type (Standard / Normal / Emergency)

### 2. Assess

- [ ] Identify affected components (code, DB, infrastructure, docs)
- [ ] Assess risk: blast radius, data loss potential, downtime estimate
- [ ] Define test plan: unit tests, feature tests, E2E tests
- [ ] Define rollback procedure

### 3. Approve

- Standard: Self-approved by implementer
- Normal: Approval in GitHub PR review by Technical Lead + ISM
- Emergency: Verbal approval from Technical Lead, documented retroactively

### 4. Implement

- [ ] Develop change on feature branch from `main`
- [ ] Include tests covering the change
- [ ] Run full test suite locally (`composer test`, `npm run test:run`)
- [ ] Open PR with description linking to change request issue

### 5. Verify

- [ ] CI pipeline passes (tests + TypeScript check)
- [ ] Health-gate passes at `/up` after staging deploy
- [ ] PR reviewed and approved (at least 1 reviewer for Normal changes)
- [ ] Smoke test in staging environment

### 6. Deploy

- [ ] Merge to `main` (triggers auto-deploy to staging)
- [ ] Monitor staging for 15 minutes (Sentry errors, /up health)
- [ ] Promote to production via manual approval in CI
- [ ] Verify production health endpoint

### 7. Close

- [ ] Update change request issue with deployment timestamp
- [ ] Document any incidents or rollbacks
- [ ] Close issue with `completed` state

## Emergency Change

For security patches and critical hotfixes:
1. Obtain verbal approval from Technical Lead
2. Implement and deploy directly to staging
3. Verify fix within 15 minutes
4. Deploy to production
5. Complete full PR review and documentation within 24 hours

## Rollback Triggers

Deployments should be rolled back if:
- Health-gate fails at `/up` after deployment
- Error rate increases >5% compared to pre-deploy baseline (via Sentry)
- Any P1/P2 incident is detected within 1 hour of deployment
- Key business metrics degrade (case creation rate, login success rate)

## Release Schedule

- Normal changes: Deployed Tuesday–Thursday, 9 AM–3 PM PH time
- Emergency changes: Any time with approval
- Freeze periods: No changes 24 hours before DMW audit or critical events

## Related Documents

- Incident Response Procedure (`docs/procedures/incident-response.md`)
- Deployment Guide (`docs/DEPLOYMENT_GUIDE.md`)
- Testing Strategy (`docs/TESTING_STRATEGY.md`)
