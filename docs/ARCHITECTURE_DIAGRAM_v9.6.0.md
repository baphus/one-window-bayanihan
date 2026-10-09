# System Architecture — High-Level with Services

> **Version:** 9.6.0 | **Updated:** 2026-10-02 | **Audience:** DICT + general reviewers
> **Note:** earlier drafts v1–v8 were removed; this file is the single current copy.
> **Scope:** Production. Single container on AWS Lightsail. No Redis.

## Diagram

```mermaid
flowchart TB
  subgraph USERS["People"]
    P["Browser — public, OFW,\nDMW staff, agency focal users"]
  end

  subgraph APP["Application — one container"]
    WEB["Web app dmw7.owbap.app — pages,\nlogin with MFA, cases, referrals, dashboards"]
    WORK["Background worker — Laravel queue worker\n+ scheduler: sends mail, reminders,\naudit sealing, nightly cleanup"]
  end

  subgraph DATA["Data — AWS + Cloudflare"]
    DB[("AWS Lightsail managed PostgreSQL\nrecords, logins, job lists, audit trail")]
    FILES[("Cloudflare R2 object storage\ncase documents, photos")]
    ARCH[("Cloudflare R2 — audit archive bucket\nsealed audit bundles, checksum-verified\nold entries pruned only after archiving")]
  end

  subgraph SERVICES["External services"]
    MAIL["Resend — sends email,\nreports bounces back"]
    IMG["Cloudinary CDN — serves profile photos,\nagency logos"]
    AI["OpenRouter — AI chatbot answers\nfree-tier models"]
    BOT["Cloudflare Turnstile — blocks bots at login"]
    ERR["Sentry — error tracking and monitoring\napp + browser"]
  end

  subgraph PLATFORM["Hosting + backups — AWS Singapore (Lightsail)"]
    HOST["Lightsail container\nweb server + app + worker"]
    SHIP["CI/CD — GitHub Actions\ntests on PR, manual image build,\nmanual release to production"]
    BK[("Lightsail database snapshots\npre-deploy snapshot per release\n+ automated DB backups")]
  end

  P -->|"uses"| WEB
  WEB <-->|"saves and reads"| DB
  WEB <-->|"stores files"| FILES
  WORK -->|"monthly audit sealing"| ARCH
  WEB <-->|"delegates slow tasks"| WORK
  WORK <-->|"reads job list"| DB
  WEB <-->|"sends mail, gets delivery status"| MAIL
  WEB <-->|"serves photos via CDN"| IMG
  WEB -->|"asks help questions via OpenRouter"| AI
  WEB -->|"checks humans at login\n+ password reset"| BOT
  WEB -->|"reports errors to"| ERR
  SHIP -.->|"delivers new version"| HOST
  SHIP -.->|"takes snapshot before deploy"| BK
  DB -.->|"backed up to"| BK
  HOST --- WEB
  HOST --- WORK
```

## Services used

- **Lightsail containers (AWS Singapore)** — runs the whole app in one box: web server + application + background worker.
- **Lightsail managed PostgreSQL (AWS)** — the master record book: people, cases, referrals, logins, job lists, audit trail.
- **Cloudflare R2 object storage** — case documents and photos. Private, not public. (Config supports any S3-compatible endpoint; production uses R2 via `FILESYSTEM_DISK=r2`.)
- **Cloudflare R2 — audit archive bucket** — sealed, checksum-verified audit bundles; a monthly scheduled job archives expired entries first, and old entries are pruned only after archiving.
- **Resend** — sends login codes, notices, reminders; reports bounces and delivery status back via webhook.
- **Cloudinary CDN** — serves profile photos and agency logos from Cloudinary's CDN (`res.cloudinary.com`); uploads go through the app, browsers load images straight from the CDN.
- **OpenRouter** — AI chatbot answers via OpenRouter free-tier models (Laravel AI SDK, `AI_CHATBOT_PROVIDER=openrouter`, key from `OPENROUTER_API_KEY`).
- **Cloudflare Turnstile** — human-vs-robot check at login and password reset (verified against Cloudflare).
- **Sentry** — error tracking and monitoring for the app and the browser (backend SDK + browser SDK via `initSentry`, scrubbed before send) so the team sees failures fast, including scheduled-job failures.
- **CI/CD — GitHub Actions (high level)** — pull requests and merges to `main` run lint, audits, builds, and tests; release images are built manually into AWS ECR; production releases are manual (typed confirmation) promoting a tested image tag, with a pre-deploy database snapshot, migrations inside the container, and `/up` + `/api/readyz` health gates before traffic switches over.
- **Lightsail database snapshots** — pre-deploy snapshot on every release plus automated DB backups, both kept in Lightsail (same region).
