# Transactional Email Delivery Requirements

> **Version:** 2.1.0 | **Updated:** 2026-07-27 | **Supersedes:** `EMAIL_DELIVERY_v2.0.0.md`
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
5. **Delivery observability** — provider-side delivery logs, the application's own
   `email_logs` table, and provider delivery events reconciled into it (§7).

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
- Weak auditability — insufficient for the logging and traceability controls in §9

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
MAIL_MAILER=resend               # the API transport installed in this deployment
RESEND_API_KEY=<secret>          # send-only scope
RESEND_WEBHOOK_SECRET=<secret>   # Svix signing secret, "whsec_..."; see section 7
MAIL_FROM_ADDRESS="noreply@<domain>"
MAIL_FROM_NAME="${APP_NAME}"

# Local development
MAIL_MAILER=log                  # writes mail to the log
# or point smtp at a local mail-catcher container

CONTACT_RECIPIENT_EMAIL=         # defaults to MAIL_FROM_ADDRESS
```

`config/mail.php` and `.env.example` are authoritative for the keys this
deployment actually reads. Credentials are injected as runtime environment
variables only — never committed (`DEPLOYMENT_GUIDE_v3.0.0.md` §4).

### 4.1 Before the sending domain is verified

An unverified domain restricts the provider to its onboarding sender. In that
state:

- `MAIL_FROM_ADDRESS` must be the provider's sandbox sender (`onboarding@resend.dev`).
- Mail can only be delivered to the **provider account owner's own address**.
- Webhooks are unaffected — they are authenticated by a signing secret, not by the
  sending domain, so delivery tracking can be verified before DNS is done.

This is a temporary state for pre-launch testing. The multi-provider delivery
check in §5 cannot be completed until verification is finished.

## 5. Rollout checklist

- [ ] Register/confirm the organisation-owned domain
- [ ] Point the site domain or subdomain at the deployment target
- [ ] Provision the transactional email transport (SMTP or HTTPS API)
- [ ] Verify the sending domain at the provider
- [ ] Publish SPF, DKIM, and DMARC records at the DNS provider
- [ ] Switch `MAIL_FROM_ADDRESS` off the sandbox sender onto the verified domain
- [ ] Confirm whether the deployment target permits outbound SMTP; if not, switch to the HTTPS API transport
- [ ] Set `MAIL_*` and provider variables in the platform's secret store
- [ ] Register the webhook endpoint at the provider and set `RESEND_WEBHOOK_SECRET` (§7)
- [ ] Run `php artisan mail:verify-transport <address>` **from the deployed environment**
- [ ] Confirm delivery to at least three mailbox providers, including one non-major provider
- [ ] Verify `email_logs` records send + failure events
- [ ] Verify a delivery webhook advances a row to `delivered` (§7.4)
- [ ] Verify bounce/complaint handling and the suppression list are active
- [ ] Monitor delivery logs through launch week
- [ ] Record the transport, sender identity, and credential owner in the supplier register

## 6. Verifying configuration from the deployed environment

```
php artisan mail:verify-transport <recipient>          # report config and send a test
php artisan mail:verify-transport <recipient> --no-send # report config only
```

Reports the resolved mailer, sender identity, and whether the API key and webhook
secret are present — **masked, never printed**. Then sends one real message and
reports either the provider's message id or the provider's error verbatim.

Run this **on the deployed environment, not a laptop**: egress rules and injected
environment variables differ, and the provider dashboard says nothing about
whether this application's own configuration works.

## 7. Delivery event tracking

### 7.1 Why it is required

Without it, `email_logs.status = 'sent'` means only that the provider accepted an
API call. A message that later bounced, was suppressed, or was marked as spam is
indistinguishable from one that reached the inbox — which makes "the user never
received their OTP" undiagnosable from inside the application.

### 7.2 How messages are correlated

The Resend transport stamps `X-Resend-Email-ID` on each message after a
successful send. `EmailEventSubscriber` records it as
`email_logs.provider_message_id`. Every provider webhook carries the same value
at `data.email_id`, which joins an outbound send to its later delivery events.

The header is absent for the `log`, `smtp`, and `array` transports, so the column
is nullable and local development is unaffected.

### 7.3 Endpoint

```
POST /api/webhooks/resend
```

- Declared in `routes/api.php`, deliberately **not** `web.php`: the web group
  appends session, CSRF, MFA, and Inertia middleware that would reject legitimate
  server-to-server callbacks.
- Authenticated by **Svix HMAC-SHA256 signature**, not by session or bearer token:
  signed content is `{svix-id}.{svix-timestamp}.{raw body}`, keyed on the
  base64-decoded portion of `RESEND_WEBHOOK_SECRET` after `whsec_`, compared in
  constant time. A ±5 minute timestamp tolerance provides replay defence, and all
  `v1` signatures are checked so secret rotation does not cause downtime.
- **Fails closed.** With `RESEND_WEBHOOK_SECRET` unset, every request is rejected
  with `401`. An unauthenticated writer to `email_logs` would be a spoofing vector.
- Idempotent: `email_events.svix_id` is unique, so provider retries cannot create
  duplicates.
- Rate limited to 300 requests/minute.

Response codes: `200` processed, ignored, or already-seen; `401` bad signature or
stale timestamp; `422` malformed payload. Unknown event types return `200` on
purpose — a `4xx` would be retried indefinitely and can get the endpoint disabled
provider-side.

### 7.4 Status vocabulary

`email_logs.status` holds the current delivery state; `email_events` holds the
append-only event history behind it.

| Provider event | Status | Meaning |
|---|---|---|
| `email.sent`, `email.scheduled` | `sent` | Provider accepted it; **delivery not confirmed** |
| `email.delivery_delayed` | `delayed` | Temporary problem; provider still retrying |
| `email.delivered` | `delivered` | Confirmed to the recipient's mail server |
| `email.suppressed` | `suppressed` | Blocked by the provider's suppression list |
| `email.failed` | `failed` | Could not be sent |
| `email.bounced` | `bounced` | Permanently rejected |
| `email.complained` | `complained` | Delivered, then marked as spam |
| `email.opened`, `email.clicked` | *(recorded, no status change)* | Unreliable as a delivery signal |

**Statuses only advance.** Webhook delivery is unordered and retried, so each
status carries a rank and an incoming event must outrank the current one to
replace it. A retried `email.sent` arriving after `email.bounced` therefore
cannot report a bounced message as sent. Terminal outcomes (bounce, complaint)
outrank informational ones.

`sent` is **not** a success state and is not styled as one in the admin UI. Only
`delivered` is confirmed.

### 7.5 Retention

`email_events` rows are deliberately **not** deleted with their parent
`email_logs` row (`emails:prune`); the foreign key is nullable so the audit trail
survives pruning. `email_events` has no retention clock of its own yet — a known
follow-up, tracked in §9.

### 7.6 Suppression

Suppression is maintained provider-side and surfaced as `email.suppressed`. The
application deliberately keeps no local suppression list, which would create a
second source of truth that can drift.

## 8. Availability

A single mail transport is a single point of failure for login. `MAIL_MAILER` can
be repointed at another configured mailer (or Laravel's `failover` transport) and
redeployed, but **no fallback transport is configured today**. See §9.

## 9. Standards-readiness check

| Requirement | Standard reference | Status | Action if gapped |
|---|---|---|---|
| Sender authentication (SPF/DKIM/DMARC) | ISO 27001 A.8.24; anti-phishing good practice | ⚠️ Blocked on DNS — domain unverified | Verify domain; publish all three records; DMARC at least `p=quarantine` |
| Bounce and complaint handling | ISO 27001 A.8.24 | ✅ Delivery webhooks reconcile bounces/complaints into `email_logs` | Confirm the endpoint is registered at the provider |
| Webhook authenticity / anti-spoofing | ISO 27001 A.8.24 | ✅ Svix HMAC, constant-time compare, replay window, fails closed | Rotate the signing secret on personnel change |
| Credentials protected, least privilege | ISO 27001 A.8.2/A.8.24; SOC 2 CC6.1 | ✅ Runtime env injection, send-only scope, masked in tooling | Rotate on personnel change and record it |
| Delivery logging and traceability | ISO 27001 A.8.15; ISO 9001 7.5.3 | ✅ `email_logs` + append-only `email_events` + provider logs | Retain per the retention policy |
| Log retention defined | ISO 27001 A.8.15 | ⚠️ `email_events` has no retention clock | Extend `emails:prune` to cover `email_events` (§7.5) |
| Availability of an authentication-critical dependency | ISO 27001 A.5.30; BCP/DR plan | ⚠️ Single transport, no configured fallback | Configure a `failover` mailer; document the switch procedure |
| Personal data in transit to a processor | ISO 27001 A.5.19–A.5.22; DPTM data-protection | ⚠️ Mail bodies and webhook payloads contain personal data | Record the provider in the supplier register with processing terms and residency |
| Portability of the identity | ISO 27001 A.5.19 (supplier exit) | ✅ Organisation-owned domain | Keep registrar and DNS control in-house, never in a vendor-managed account |

Four ⚠️ items remain. Two are deliberate deferrals (fallback transport, DNS
verification) and two are documentation/housekeeping follow-ups. All four need
action before a certification audit.

## 10. Changelog

| Version | Date | Change |
|---|---|---|
| 2.1.0 | 2026-07-27 | Added §6 (deployed-environment verification command), §7 (delivery event tracking: correlation via provider message id, Svix-signed webhook endpoint, monotonic status vocabulary, retention, suppression), §8 (availability). Extended §4 with webhook secret and §4.1 unverified-domain restrictions; extended the §5 checklist. Reworked the standards-readiness check: bounce/complaint handling and delivery traceability now ✅; added rows for webhook authenticity and `email_events` retention. |
| 2.0.0 | 2026-07-27 | Retitled and rewritten to be platform- and vendor-neutral (`EMAIL_DOMAIN_RESEND.md` → `EMAIL_DELIVERY_v2.0.0.md`). Replaced the single named provider and named host platform with a transport-selection rule (SMTP vs HTTPS API) driven by platform egress, a capability table for any transactional transport, generic `MAIL_*` configuration, and a standards-readiness check. Kept and generalised the consumer-mailbox-SMTP prohibition and the domain-ownership rationale. |
| 1.0.0 | 2026-07-12 | Previous revision: named a specific email provider and host platform as the required MVP path. |
