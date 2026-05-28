# Bayanihan One Window — Security Requirements

> **Source:** SRS v1.2 (May 19, 2026) — §5.3 Security Requirements, §6.1 Legal and Regulatory Requirements
> **Last Updated:** 2026-05-28

---

## 1. Security Architecture Overview

The system processes regulated OFW personal information, case records, referral histories, documentary evidence, and inter-agency workflow data. Security controls are enforced across **six layers**:

```
Layer 1: Network Security     — TLS 1.2+, IP whitelisting
Layer 2: Authentication       — OTP multi-factor, session management
Layer 3: Authorization        — RBAC, lane isolation, least privilege
Layer 4: Application Security — CSRF, XSS, SQL injection prevention
Layer 5: Database Security    — Encryption at rest, RLS, TLS connections
Layer 6: Audit & Accountability— Immutable logs, event tracking
```

---

## 2. SRS Security Requirements Traceability

### 2.1 Authentication & Identity Security (§5.3.1)

| SRS ID | Requirement | Implementation | Status |
|---|---|---|---|
| NFR-SEC-001 | All admin users require authenticated access | Laravel `auth` middleware on all protected routes | ✅ |
| NFR-SEC-002 | Admin MFA via OTP | OtpService — 6-digit code, 5-min TTL | ✅ |
| NFR-SEC-003 | OFW tracking requires OTP | TrackController — Tracker Number + OTP | ✅ |
| NFR-SEC-004 | Credentials bcrypt hashed | Laravel default `Hash::make()` | ✅ |
| NFR-SEC-005 | No plaintext password storage | Enforced by Laravel Auth | ✅ |
| NFR-SEC-006 | Rate-limit failed auth | `throttle:login` (6/min), `throttle:otp` (3/min) | ✅ |
| NFR-SEC-007 | Reject expired/invalid OTP | OtpService validates expiry, single-use | ✅ |
| NFR-SEC-008 | Session timeout | Configurable via `session.lifetime` | ✅ |
| NFR-SEC-009 | Session hijacking protection | Laravel session security defaults | ✅ |

### 2.2 Authorization & Access Control (§5.3.2)

| SRS ID | Requirement | Implementation | Status |
|---|---|---|---|
| NFR-SEC-010 | RBAC enforced server-side | Spatie `laravel-permission`, middleware `role:ADMIN\|AGENCY\|CASE_MANAGER` | ✅ |
| NFR-SEC-011 | Lane-based agency isolation | Service layer filters by `agcy_id` | ✅ |
| NFR-SEC-012 | Cross-agency access prohibited | Lane isolation + authorization gates | ✅ |
| NFR-SEC-013 | Only DMW performs case governance | Role check: `role:CASE_MANAGER` on create/close | ✅ |
| NFR-SEC-014 | Only ADMIN manages config | `role:ADMIN` on all admin routes | ✅ |
| NFR-SEC-015 | Principle of Least Privilege | Role-scoped route groups, service permissions | ✅ |
| NFR-SEC-016 | Service accounts restricted | App DB credentials have minimum grants | ✅ |
| NFR-SEC-017 | Unauthorized access auditable | AuditLog records 403 events | ✅ |

### 2.3 Database Security (§5.3.3)

| SRS ID | Requirement | Implementation | Status |
|---|---|---|---|
| NFR-SEC-018 | PostgreSQL as system of record | Supabase PostgreSQL 17 | ✅ |
| NFR-SEC-019 | Encryption at rest (AES-256) | Supabase managed encryption | ✅ |
| NFR-SEC-020 | Backups inherit encryption | Supabase auto-backup encrypted | ✅ |
| NFR-SEC-021 | TLS-secured DB connections | `DB_CONNECTION=pgsql` with SSL | ✅ |
| NFR-SEC-022 | Certificate validation | Verified in DB config | ✅ |
| NFR-SEC-023 | No plaintext DB communication | Enforced | ✅ |
| NFR-SEC-024 | Sensitive field encryption | Application-layer encryption planned for passport, address, emergency contact | 🟡 Partial |
| NFR-SEC-025 | Restricted DB admin access | Supabase project-level access control | ✅ |
| NFR-SEC-026 | No default/weak credentials | Enforced | ✅ |
| NFR-SEC-027 | Credentials not in source code | `.env` only, excluded from VCS | ✅ |
| NFR-SEC-028 | RLS for lane isolation | Available in PostgreSQL, not yet fully configured | 🟡 Partial |

### 2.4 Network & Communication Security (§5.3.4)

| SRS ID | Requirement | Implementation | Status |
|---|---|---|---|
| NFR-SEC-029 | HTTPS/TLS for all browser communication | Enforced at Render/Cloudflare level | ✅ |
| NFR-SEC-030 | HTTP redirect/prevent | Render enforces HTTPS redirect | ✅ |
| NFR-SEC-031 | No direct client-to-DB | Mediated architecture — all requests pass through Laravel | ✅ |
| NFR-SEC-032 | Internal encrypted transport | TLS-secured PostgreSQL connections | ✅ |
| NFR-SEC-033 | External API authenticated/encrypted | API keys + HTTPS for Cloudinary, SMTP | ✅ |
| NFR-SEC-034 | No deprecated crypto | TLS 1.2 minimum | ✅ |

### 2.5 Application Security (§5.3.5)

| SRS ID | Requirement | Implementation | Status |
|---|---|---|---|
| NFR-SEC-035 | OWASP Top 10 protections | CSRF tokens, XSS escaping (Blade/React), SQL injection (Eloquent), input validation | ✅ |
| NFR-SEC-036 | Server-side business logic validation | Form Request classes for all inputs | ✅ |
| NFR-SEC-037 | Client-side validation not sole control | Server-side is authoritative | ✅ |
| NFR-SEC-038 | Authorization before sensitive actions | Gates + middleware on all mutations | ✅ |
| NFR-SEC-039 | Fail secure | Errors return safe defaults | ✅ |

### 2.6 Document & Media Security (§5.3.6)

| SRS ID | Requirement | Implementation | Status |
|---|---|---|---|
| NFR-SEC-040 | Documents not publicly enumerable | Cloudinary authenticated delivery | ✅ |
| NFR-SEC-041 | Document access requires auth | Files served through application, not direct URL | ✅ |
| NFR-SEC-042 | Signed expiring URLs | Cloudinary signed delivery | ✅ |
| NFR-SEC-043 | Upload validation | File type, MIME, size, malware scanning (configurable) | ✅ |
| NFR-SEC-044 | Unauthorized document access denied | Authorization gate on document routes | ✅ |

### 2.7 Audit Logging & Accountability (§5.3.7)

| SRS ID | Requirement | Implementation | Status |
|---|---|---|---|
| NFR-SEC-045 | Security events logged | AuditLog model — authentication, referrals, milestones, admin changes, document access | ✅ |
| NFR-SEC-046 | Append-only, tamper-resistant | INSERT-only in application code; no update/delete routes | ✅ |
| NFR-SEC-047 | Audit log access is auditable | VIEW events tracked on audit log access | ✅ |
| NFR-SEC-048 | Corrections as new entries | New audit record, never in-place edit | ✅ |

### 2.8 External Service Security (§5.3.8)

| SRS ID | Requirement | Implementation | Status |
|---|---|---|---|
| NFR-SEC-049 | Only approved providers | Render, Supabase, Cloudinary, SMTP | ✅ |
| NFR-SEC-050 | Contractual confidentiality | Vendor DPAs required | 🟡 Partial |
| NFR-SEC-051 | Authenticated secure channels | API keys + HTTPS for all external | ✅ |
| NFR-SEC-052 | Secure credential management | Environment variables, not in code | ✅ |
| NFR-SEC-053 | Provider access governed | Organizational policy | 🟡 Partial |

### 2.9 Privacy & Cross-Border Governance (§5.3.9)

| SRS ID | Requirement | Implementation | Status |
|---|---|---|---|
| NFR-SEC-054 | Cross-border privacy controls | Documented in deployment governance | 🟡 Partial |
| NFR-SEC-055 | Privacy risk assessments | Required before production deployment | 🔴 Not Done |
| NFR-SEC-056 | No unnecessary 3rd-party data sharing | Minimized data in OTP/email notifications | ✅ |
| NFR-SEC-057 | Controlled vendor support access | Supabase/Render admin access restricted | 🟡 Partial |

---

## 3. Specialized Security Controls

### 3.1 Debug OTP Mode
The system includes a development-only "Debug OTP" mode controlled via the `debug_otp_enabled` system setting.
- **Functionality:** When enabled, the current OTP for a user is included in the meta-props of the Inertia response, allowing the frontend to auto-fill the OTP input.
- **Risk:** High exposure of authentication secrets.
- **Control:** Must be disabled in production. The UI in `SystemSettings/Index.jsx` explicitly warns that this exposes OTP values in page responses.

### 3.2 Chatbot API Key Security
- **Storage:** API keys for AI providers (OpenAI, Anthropic) are stored in the `system_settings` table.
- **Enforcement:** Keys are never exposed to the frontend. Only the `enabled` status and the name of the `provider` are sent to the client.
- **Note:** Current implementation stores keys as plain text in the database (documented technical debt). Future remediation includes application-layer encryption for the `value` column in the settings table.

### 3.3 Case Document Visibility
Access to documents uploaded directly to a case is restricted via authorization gates in the `CaseDocumentController`.
- **Enforcement Layer:** Controller-level logic (not just DB RLS).
- **Access Rules:**
    - **ADMIN:** Full access to all documents.
    - **CASE_MANAGER:** Access to documents in cases they created or manage.
    - **AGENCY:** Access is only granted if the agency has an active (non-terminal) referral linked to the specific case.
- **Delivery:** Files are served via Cloudinary signed URLs that expire after a short duration.

---

## 4. Legal & Regulatory Compliance (§6.1)

### 3.1 RA 10173 — Data Privacy Act of 2012

| Requirement | Implementation | Status |
|---|---|---|
| LEGAL-001 | Processing for legitimate purposes only | Case/Referral management scope defined | ✅ |
| LEGAL-002 | Minimum necessary data collection | Schema designed for operational minimum | ✅ |
| LEGAL-003 | Technical/organizational safeguards | Multi-layer security architecture | ✅ |
| LEGAL-004 | Access restricted to authorized users | RBAC + lane isolation | ✅ |
| LEGAL-005 | Auditability & traceability | AuditLog + VIEW tracking + append-only | ✅ |
| LEGAL-006 | Data retention/disposal governance | Not yet implemented | 🔴 Not Done |
| LEGAL-007 | Prohibit unauthorized disclosure | Access controls + encryption | ✅ |

### 3.2 RA 11641 — DMW Act Compliance

| Requirement | Implementation | Status |
|---|---|---|
| LEGAL-008 | DMW as primary case authority | DMW CASE_MANAGER role creates/closes cases | ✅ |
| LEGAL-009 | DMW controls case lifecycle | Intake, referral, closure gated to DMW | ✅ |
| LEGAL-010 | Agencies within delegated lanes | Lane isolation per agency | ✅ |
| LEGAL-011 | Inter-agency accountability | Append-only audit + mandatory comments | ✅ |

### 3.3 DICT Cloud First Policy

| Requirement | Implementation | Status |
|---|---|---|
| LEGAL-012 | Cloud infrastructure approved | Render, Supabase, Cloudinary | ✅ |
| LEGAL-013 | Formal provider approval | Vendor selection documented | 🟡 Partial |
| LEGAL-014 | Risk assessment + privacy review | PIA required before production | 🔴 Not Done |
| LEGAL-015 | Defined security responsibilities | Architecture document defines boundaries | ✅ |
| LEGAL-016 | Cloud dependencies documented | ARCHITECTURE.md §9 | ✅ |

### 3.4 Cross-Border Processing & International Data Governance (§6.1.4)

| Requirement | Implementation | Status |
|---|---|---|
| LEGAL-017 | Cross-border privacy controls for regulated OFW data | Documented in deployment governance | 🟡 Partial |
| LEGAL-018 | Documented organizational approval for third-party infra providers | Vendor selection process needed | 🟡 Partial |
| LEGAL-019 | Third-party providers bound by confidentiality and privacy obligations | Vendor DPAs required before production | 🟡 Partial |
| LEGAL-020 | Vendor access to production data controlled and justified | Supabase/Render admin access restricted | 🟡 Partial |
| LEGAL-021 | No unnecessary exposure of OFW data to third-party services | Minimized in OTP/email notifications | ✅ |
| LEGAL-022 | International processing risks evaluated through privacy review | PIA scoping needed | 🔴 Not Done |

### 3.5 Privacy Impact Assessment Requirements (§6.1.5)

| Requirement | Implementation | Status |
|---|---|---|
| LEGAL-023 | PIA or equivalent privacy risk evaluation maintained for deployment architecture | Not yet conducted | 🔴 Not Done |
| LEGAL-024 | Material architectural changes require updated privacy review | Process not yet established | 🔴 Not Done |

### 3.6 Third-Party Service Governance (§6.1.6)

| Requirement | Implementation | Status |
|---|---|---|
| LEGAL-025 | Third-party providers used only for legitimate operational purposes | Render, Supabase, Cloudinary, SMTP all operational | ✅ |
| LEGAL-026 | Providers not treated as unrestricted controllers of OFW data | Data processing agreements scoping | 🟡 Partial |
| LEGAL-027 | Provider permissions limited to required functionality | Least-privilege principle applied | ✅ |
| LEGAL-028 | Provider credentials and secrets securely governed | Environment variables, not in source code | ✅ |
| LEGAL-029 | Provider dependency risk acknowledged in governance planning | Architecture documentation identifies dependencies | ✅ |

### 3.7 Procurement & Public Sector Governance Alignment (§6.1.7)

| Requirement | Implementation | Status |
|---|---|---|
| LEGAL-030 | Infrastructure documented for technical review, audit, procurement | ARCHITECTURE.md §9, DEPLOYMENT_GUIDE.md | ✅ |
| LEGAL-031 | Security controls, vendor dependencies, cloud constraints documented | ARCHITECTURE.md + SECURITY_REQUIREMENTS.md | ✅ |
| LEGAL-032 | Technology decisions preserve maintainability and auditability | Laravel + React + Inertia standard stack | ✅ |

### 3.8 AI Governance Requirements (§6.1.8) — If Enabled

| Requirement | Implementation | Status |
|---|---|---|
| LEGAL-033 | AI restricted to approved operational functions | Not yet implemented | 🔴 Not Done |
| LEGAL-034 | No unnecessary transmission of OFW PII to AI services | PII masking layer needed | 🔴 Not Done |
| LEGAL-035 | Case narratives, identifiers, evidence not exposed to AI without governance approval | Governance framework needed | 🔴 Not Done |
| LEGAL-036 | AI usage subordinate to privacy, operational, security governance | Policy documentation needed | 🔴 Not Done |

---

## 4. Security Controls Matrix

### 4.1 Rate Limiting

| Endpoint | Limit | Implemented |
|---|---|---|
| Login (POST /login) | 6 req/min | `throttle:login` |
| OTP Verify (POST /login/otp) | 3 req/min | `throttle:otp` |
| Tracking OTP (POST /track/send-otp) | 5 req/min | `throttle:tracking` |
| Tracking OTP Verify (POST /track/verify-otp) | 5 req/min | `throttle:tracking` |

### 4.2 IP Whitelist

- **Scope:** All `/admin/*` routes
- **Implementation:** `app/Http/Middleware/IpWhitelist.php`
- **Config:** `ALLOWED_IPS` in `.env` (CIDR notation supported)
- **Bypass:** When `APP_ENV=local` or `ALLOWED_IPS` is empty

### 4.3 Password Policy

- Minimum length: 8 characters (Laravel default)
- Bcrypt hashing (cost factor 12)
- No password expiration in v1.0
- Rate-limited attempts

### 4.4 Session Security

- Driver: `database` (sessions table)
- Lifetime: 120 minutes (configurable via `session.lifetime`)
- Regenerate on login
- HTTP-only cookies
- SameSite: Lax

---

## 5. Security Gaps & Remediation

| Gap | Risk | Priority | Remediation |
|---|---|---|---|
| No formal Privacy Impact Assessment | HIGH | P1 | Conduct PIA before production deployment |
| No data retention/disposal policy | MEDIUM | P2 | Define retention schedules for cases, audits, logs |
| AI chatbot security controls not implemented | MEDIUM | P3 | Implement when AI feature is enabled |
| Application-layer encryption for sensitive fields not implemented | MEDIUM | P2 | Encrypt passport, address, emergency contact at app layer |
| PostgreSQL RLS policies not fully configured | MEDIUM | P2 | Create RLS policies for lane isolation |
| No rate limiting on non-auth public routes | LOW | P3 | Audit and add throttles where needed |
| No security headers (CSP, HSTS, X-Frame-Options) | LOW | P3 | Add via middleware or Render config |
