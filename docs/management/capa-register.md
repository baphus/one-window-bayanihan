# CAPA Register — Corrective and Preventive Action

> **Governance status (reviewed 2026-11-01):** Roles, targets, schedules, and sign-off requirements describe intended procedure. Repository text alone does not prove approval, assigned personnel, completed reviews/drills, measured service levels, or legal/compliance acceptance. Keep operational evidence separately.


**Document ID:** D60-10-CAPA  
**Version:** 1.0  
**Date:** 2026-07-09  
**Standard:** ISO 9001 10.2, 27001 10.1  
**Owner:** Quality Management Representative

## Purpose

Track corrective actions (responses to identified non-conformities) and preventive actions (proactive improvements) in a systematic manner.

## Register Format

| ID | Date | Type | Source | Description | Root Cause | Action | Owner | Status | Target Date | Closure Date | Evidence |
|----|------|------|--------|-------------|------------|--------|-------|--------|-------------|--------------|----------|
| CAPA-001 | 2026-07-09 | Corrective | Security audit | PII fields stored as plaintext | Missing encryption cast on date fields | Added casts for selected fields; client names, email, and phone remain plaintext | Dev Team | Partial (scope correction) | 2026-07-16 | 2026-07-09 | Client, ClientAddress, ClientEmployment, NextOfKin model casts; closure approval unverified |
| CAPA-002 | 2026-07-09 | Corrective | Security audit | Notification IDOR: users could read others' notifications | Missing scope to user's notification relationship | Scoped `findOrFail` to `user->notifications()` | Dev Team | Closed | 2026-07-16 | 2026-07-09 | PR #, test case |
| CAPA-003 | 2026-07-09 | Preventive | Risk assessment | No error monitoring in production | Missing Sentry integration | Added sentry-laravel package | Dev Team | Open | 2026-08-01 | — | Config, exception handler |
| CAPA-004 | 2026-07-09 | Preventive | Risk assessment | Chatbot vulnerable to prompt injection | No output validation | Added refusal patterns + output cap | Dev Team | Closed | 2026-07-16 | 2026-07-09 | Controller, service changes |
| CAPA-005 | 2026-07-09 | Corrective | Code review | SQL injection risk in RLS middleware | String interpolation in SET SESSION | Parameterized with set_config() | Dev Team | Closed | 2026-07-16 | 2026-07-09 | Middleware change |

## Status Definitions

| Status | Meaning |
|--------|---------|
| Open | Action identified, not yet started |
| In Progress | Action being implemented |
| Verified | Action complete, verification pending |
| Closed | Action verified and effective |
| Reopened | Previously closed, recurrence detected |

## Review Cadence

- Open items reviewed weekly during standup
- Closed items reviewed quarterly during management review
- Register audited annually

## Related Documents

- Quality Policy (`docs/management/quality-policy.md`)
- Quality Objectives (`docs/management/quality-objectives.md`)
- Risk Register (`docs/compliance/information-risk-register-v1.0.0.md`)
