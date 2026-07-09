# Service Catalogue

**Document ID:** D90-1-SC  
**Version:** 1.0  
**Date:** 2026-07-09  
**Standard:** ISO 20000-1 8.2.3  
**Owner:** Technical Lead

## Service Overview

The One Window Bayanihan system provides a unified case management platform for DMW Region VII, enabling end-to-end tracking of OFW cases from intake to resolution.

## Service Catalogue

### S1 — Case Management

| Attribute | Detail |
|-----------|--------|
| **Description** | Create, update, track, and close OFW cases. Includes client intake, document upload, case notes, status transitions, and history audit. |
| **Users** | DMW case managers, administrators |
| **Availability** | 99% during business hours (Mon–Fri, 8 AM – 6 PM PH time) |
| **Throughput** | 100 concurrent users, 500 cases/day |
| **Response Time** | Page load <2s, form submit <3s |
| **Data Retention** | 10 years after case closure |
| **Dependencies** | PostgreSQL (Supabase), S3 storage (Supabase), email (SMTP) |

### S2 — Referral Management

| Attribute | Detail |
|-----------|--------|
| **Description** | Multi-agency referral workflow: create referral → agency assignment → compliance tracking → milestone completion. Supports decision (accept/reject/return) and file attachments. |
| **Users** | DMW case managers, agency staff, administrators |
| **Availability** | 99% during business hours |
| **Throughput** | 50 concurrent users, 200 referrals/day |
| **Response Time** | Page load <2s, referral submit <3s |
| **Data Retention** | 10 years after case closure |
| **Dependencies** | PostgreSQL (Supabase), S3 storage (Supabase) |

### S3 — Public Case Tracking

| Attribute | Detail |
|-----------|--------|
| **Description** | OFW public portal: enter tracker number → email OTP → view case status, timeline, and assigned agencies. No authentication required. |
| **Users** | OFWs, employers, general public |
| **Availability** | 99.5% (24/7) |
| **Throughput** | 500 concurrent lookups/day |
| **Response Time** | Page load <2s, OTP delivery <30s |
| **Data Retention** | No stored PII (email used only for OTP delivery, 5-min TTL) |
| **Dependencies** | PostgreSQL (Supabase), email (SMTP), cache (database) |

### S4 — Feedback & SERVQUAL

| Attribute | Detail |
|-----------|--------|
| **Description** | Collect service quality feedback from OFWs using SERVQUAL methodology. Configurable questionnaires per agency. |
| **Users** | OFWs, agency staff (config), DMW administrators |
| **Availability** | 99% during business hours |
| **Throughput** | 200 responses/day |
| **Response Time** | Form load <2s, submit <3s |
| **Data Retention** | 5 years |
| **Dependencies** | PostgreSQL (Supabase) |

### S5 — Chatbot

| Attribute | Detail |
|-----------|--------|
| **Description** | AI-powered assistant for common OFW inquiries. Routes to case management context when needed. Prompt-injection guarded. |
| **Users** | General public (unauthenticated) |
| **Availability** | 99% (24/7) |
| **Throughput** | 1,000 conversations/day |
| **Response Time** | First response <3s |
| **Data Retention** | Conversations stored for quality improvement (anonymized after 90 days) |
| **Dependencies** | OpenAI API, PostgreSQL (Supabase) |

### S6 — Reporting & Analytics

| Attribute | Detail |
|-----------|--------|
| **Description** | Dashboards, case metrics, referral metrics, compliance reports. Excel export with 10,000 row limit. |
| **Users** | DMW case managers, administrators, management |
| **Availability** | 99% during business hours |
| **Throughput** | 20 concurrent report views |
| **Response Time** | Dashboard load <3s, export <10s |
| **Data Retention** | Reports are generated on-demand, not stored |
| **Dependencies** | PostgreSQL (Supabase), PhpSpreadsheet |

## Service Level Agreements (SLA)

| Service | Availability | Response Time | Support Hours |
|---------|-------------|---------------|---------------|
| Case Management | 99% | <2s page load | Business hours |
| Public Tracking | 99.5% | <2s page load | 24/7 (automated) |
| Chatbot | 99% | <3s response | 24/7 (automated) |
| All services | 99% | Incident response per severity | Business hours + P1 24/7 |

## Operational Level Agreements (OLA)

| Partner | Service | OLA Target | Monitoring |
|---------|---------|------------|------------|
| Render (hosting) | App + worker hosting | 99.9% uptime | Render status page |
| Supabase (PostgreSQL) | Database + file storage | 99.9% uptime | Supabase status page |
| Cloudinary | Media transformation | 99.9% uptime | Cloudinary status page |
| OpenAI | Chatbot AI | 99.5% uptime | OpenAI status page |
| SMTP provider | Email delivery | <5 min delivery | Email logs |

## Related Documents

- BCP/DR Plan (`docs/management/bcp-dr-plan.md`)
- Capacity Plan (`docs/management/capacity-plan.md`)
- Quality Objectives (`docs/management/quality-objectives.md`)
