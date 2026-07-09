# Capacity Plan

**Document ID:** D90-3-CP  
**Version:** 1.0  
**Date:** 2026-07-09  
**Standard:** ISO 27002 8.6, ISO 20000-1 8.6.1  
**Owner:** Technical Lead

## Purpose

Ensure the One Window Bayanihan system has sufficient capacity to meet current and projected demand while maintaining performance targets.

## Current Baseline

### Infrastructure

| Component | Specification | Provider |
|-----------|---------------|----------|
| Application Server | 1 vCPU, 512 MB RAM, 10 GB disk | Render (starter) |
| Database | PostgreSQL 15, 1 GB RAM, 10 GB disk | Supabase (free tier) |
| File Storage | S3-compatible, 5 GB used | Supabase Storage |
| CDN/Media | Image transformation + CDN | Cloudinary |
| AI | GPT-4o-mini | OpenAI API |

### Database Size

| Metric | Value |
|--------|-------|
| Total DB size | ~50 MB (development, seeded) |
| Tables | 25 |
| Largest table | `cases` (~1,000 rows seeded) |
| Daily data growth | ~100 rows/day (estimated) |

### Request Volume

| Endpoint | Daily Requests (est.) | Peak Rate |
|----------|----------------------|-----------|
| Login + OTP | 500 | 10/min |
| Case CRUD | 1,000 | 20/min |
| Referral CRUD | 500 | 10/min |
| Tracking (public) | 200 | 5/min |
| Chatbot | 1,000 | 10/min |
| Reports (dashboard) | 100 | 2/min |

## Capacity Targets

| Resource | Current | Target | Growth Horizon | Action Trigger |
|----------|---------|--------|----------------|----------------|
| Concurrent users | <10 (dev) | 144 (from SRS) | 6 months | CPU >70% sustained |
| DB connections | 15 (pooled) | 50 | 6 months | Connection errors |
| DB storage | 50 MB | 5 GB | 12 months | <20% free space |
| File storage | 5 GB | 50 GB | 12 months | <20% free space |
| API rate limits | 60/min (global) | 200/min | 6 months | >50% rate limit hits |

## Scaling Strategy

### Vertical (Current Phase)

Upgrade Render instance:
- **Stage 1** (3-6 months): 1 vCPU → 2 vCPU, 512 MB → 1 GB RAM
- **Stage 2** (6-12 months): 2 vCPU → 4 vCPU, 1 GB → 2 GB RAM

Upgrade Supabase:
- **Stage 1:** Free → Pro ($25/mo, 8 GB DB, 100 GB storage)
- **Stage 2:** Pro → Team ($599/mo, 16 GB DB, unlimited storage)

### Horizontal (Future Phase)

- Add queue worker replicas for parallel job processing
- Add read-replica database for reporting queries
- Implement Redis for session caching and rate limiting

## Load Testing

Run quarterly load tests using the script at `scripts/load-test.sh`:
- Simulate 50 concurrent users
- Measure response times and error rates
- Document results in this plan

## Monitoring

| Metric | Tool | Alert Threshold |
|--------|------|----------------|
| CPU usage | Render dashboard | >70% for 10 min |
| Memory usage | Render dashboard | >80% for 10 min |
| Error rate | Sentry | >1% for 5 min |
| Response time | Sentry | p95 >3s for 5 min |
| DB connections | Supabase dashboard | >80% of max |

## Related Documents

- Service Catalogue (`docs/management/service-catalogue.md`)
- Load Test Script (`scripts/load-test.sh`)
