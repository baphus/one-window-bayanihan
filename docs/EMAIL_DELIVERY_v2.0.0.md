# Transactional Email Delivery Requirements

> **Version:** 2.0.0 | **Updated:** 2026-07-27 | **Supersedes:** `EMAIL_DOMAIN_RESEND.md` (v1.0.0)
> **Status:** Domain + verified transactional transport required before MVP production release
> **Applies to:** Any production deployment target, Laravel mail delivery, OTP/MFA/notification mail

## 1. Requirement

Before the MVP goes to production the project must have:

1. **An owned domain name** for the public site and the official sender identity.
2. **A transactional email transport** — either authenticated SMTP or an HTTPS
   email API — with the sending domain verified at the provider.
3. **DNS authentication records** published for that domain: **SPF**, **DKIM**,
   and **DMARC**.
4. **A verified sender address** on that domain, e.g. `noreply@<domain>`,
   `support@<domain>`, `notifications@<domain>`.
5. **Delivery observability** — provider-side delivery logs plus the
   application's own `email_logs` table.

This is a hard MVP dependency, not a nice-to-have: login OTP, MFA, account
lifecycle, referral notifications, and feedback invitations are all email-gated.
If mail does not deliver, users cannot authenticate.

## 2. Transport selection

The application supports both transport classes through `MAIL_MAILER`. Choose by
what the deployment target actually permits, not by vendor preference.

| Transport | When to choose it | Requirement on the platform |
|---|---|---|
| **Authenticated SMTP** | Platform allows outbound SMTP; an existing organisational mail relay is available | Outbound TCP 587 (STARTTLS) or 465 (TLS) permitted |
| **HTTPS email API** | Platform blocks or throttles outbound SMTP ports, or a serverless/managed runtime restricts egress | Outbound HTTPS 443 only — which every platform permits |

**Decision rule:** verify SMTP egress on the target *before* committing to SMTP.
Many managed container and serverless platforms block ports 25/465/587 by
default as an anti-abuse measure. If egress is blocked or unverifiable, use an
HTTPS API transport — it needs nothing beyond 443 and is therefore the portable
default across platforms.

### Do not use a personal mailbox provider's SMTP for production

Consumer mailbox SMTP (any provider) is unsuitable regardless of platform:

- Designed for human mailbox use, not application-generated transactional mail
- Low daily send limits relative to OTP and notification volume
- Automated mail is frequently flagged as suspicious or rate-limited
- No transactional delivery logs, webhooks, or bounce/complaint handling
- Sender identity is a personal account rather than the institution's domain
- Weak auditability — insufficient for the logging and traceability controls in §6

Use a purpose-built transactional email service (SMTP or API) with a verified
sending domain.

### What a suitable transactional transport must provide

| Capability | Why it matters |
|---|---|
| Verified sending domain with SPF/DKIM/DMARC | Inbox placement and anti-spoofing |
| Per-message delivery logs and status | Incident diagnosis for "I never got the OTP" |
| Bounce and complaint handling | Protects domain reputation |
| Suppression list | Stops repeat delivery to dead addresses |
| Throughput headroom above peak OTP volume | Login must not queue behind notifications |
| API or SMTP credential scoped to sending only | Least privilege (ISO 27001 A.8.2) |
| Data residency/processing terms compatible with the project's obligations | Personal data is in the message body |

## 3. Why an owned domain matters

Beyond the website URL, the domain underpins trust, deliverability, and
portability:

- **Official identity** — mail comes from the institution, not a personal account
- **Deliverability** — verified DNS records keep OTP mail out of spam
- **Anti-spoofing** — SPF, DKIM, and DMARC block impersonation of the sender
- **Consistent branding** — site URL and sender address share one identity
- **Room to grow** — `app.<domain>`, `api.<domain>`, `status.<domain>`
- **Provider portability** — the public identity survives a change of hosting or
  email provider; only DNS records change

That last point is the operational reason the domain must be **owned by the
organisation**, not by a vendor account: it is what makes every other platform
choice reversible.

## 4. Configuration

```env
# SMTP transport
MAIL_MAILER=smtp
MAIL_HOST=<smtp-host>
MAIL_PORT=587
MAIL_SCHEME=tls
MAIL_USERNAME=<user>
MAIL_PASSWORD=<secret>
MAIL_FROM_ADDRESS="noreply@<domain>"
MAIL_FROM_NAME="${APP_NAME}"

# HTTPS API transport (when SMTP egress is blocked)
MAIL_MAILER=<api-mailer-name>     # Laravel mailer key for the configured API transport
<PROVIDER>_API_KEY=<secret>       # send-only scope
MAIL_FROM_ADDRESS="noreply@<domain>"
MAIL_FROM_NAME="${APP_NAME}"

# Local development
MAIL_MAILER=log                   # writes mail to the log
# or point smtp at a local mail-catcher container

CONTACT_RECIPIENT_EMAIL=          # defaults to MAIL_FROM_ADDRESS
```

The API-transport key name follows whichever mailer is installed; see
`config/mail.php` and `.env.example` for the keys this deployment actually reads.
Credentials are injected as runtime environment variables only — never committed
(`DEPLOYMENT_GUIDE_v3.0.0.md` §4).

## 5. Rollout checklist

- [ ] Register/confirm the organisation-owned domain
- [ ] Point the site domain or subdomain at the deployment target
- [ ] Provision the transactional email transport (SMTP or HTTPS API)
- [ ] Verify the sending domain at the provider
- [ ] Publish SPF, DKIM, and DMARC records at the DNS provider
- [ ] Confirm whether the deployment target permits outbound SMTP; if not, switch to the HTTPS API transport
- [ ] Set `MAIL_*` variables in the platform's secret store
- [ ] Send a test OTP from the deployed environment (not from a laptop)
- [ ] Confirm delivery to at least three mailbox providers, including one non-major provider
- [ ] Verify `email_logs` records send + failure events
- [ ] Verify bounce/complaint handling and the suppression list are active
- [ ] Monitor delivery logs through launch week
- [ ] Record the transport, sender identity, and credential owner in the supplier register

## 6. Standards-readiness check

| Requirement | Standard reference | Status | Action if gapped |
|---|---|---|---|
| Sender authentication (SPF/DKIM/DMARC) | ISO 27001 A.8.24; anti-phishing good practice | ⚠️ Required before launch | Publish all three records; DMARC at least `p=quarantine` |
| Credentials protected, least privilege | ISO 27001 A.8.2/A.8.24; SOC 2 CC6.1 | ✅ Runtime env injection, send-only scope | Rotate on personnel change and record it |
| Delivery logging and traceability | ISO 27001 A.8.15; ISO 9001 7.5.3 | ✅ `email_logs` + provider logs | Retain per the retention policy |
| Availability of an authentication-critical dependency | ISO 27001 A.5.30; BCP/DR plan | ⚠️ Single transport is a single point of failure for login | Document a fallback transport and the switch procedure (`MAIL_MAILER` change + redeploy) |
| Personal data in transit to a processor | ISO 27001 A.5.19–A.5.22; DPTM data-protection | ⚠️ Mail bodies contain personal data | Record the provider in the supplier register with processing terms and residency |
| Portability of the identity | ISO 27001 A.5.19 (supplier exit) | ✅ Organisation-owned domain | Keep registrar and DNS control in-house, never in a vendor-managed account |

## 7. Changelog

| Version | Date | Change |
|---|---|---|
| 2.0.0 | 2026-07-27 | Retitled and rewritten to be platform- and vendor-neutral (`EMAIL_DOMAIN_RESEND.md` → `EMAIL_DELIVERY_v2.0.0.md`). Replaced the single named provider and named host platform with a transport-selection rule (SMTP vs HTTPS API) driven by platform egress, a capability table for any transactional transport, generic `MAIL_*` configuration, and a §6 standards-readiness check. Kept and generalised the consumer-mailbox-SMTP prohibition and the domain-ownership rationale. |
| 1.0.0 | 2026-07-12 | Previous revision: named a specific email provider and host platform as the required MVP path. |
