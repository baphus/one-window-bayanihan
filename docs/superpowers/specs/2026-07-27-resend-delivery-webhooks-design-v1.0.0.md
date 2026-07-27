# Resend Transport + Delivery-State Webhooks — Design

> **Version:** 1.0.0 | **Date:** 2026-07-27 | **Status:** Approved for implementation
> **Related:** `docs/EMAIL_DELIVERY_v2.0.0.md` (requirements), `docs/SECURITY_REQUIREMENTS_v2.1.0.md`

## 1. Problem

The application can hand mail to Resend but never learns what happened next.

`app/Listeners/EmailEventSubscriber.php` writes an `email_logs` row with
`status = 'sent'` when `Illuminate\Mail\Events\MessageSent` fires, and a
`status = 'failed'` row when the queued mail job throws. "Sent" here means only
*the provider accepted the API call*. A message that Resend subsequently bounces,
that the recipient marks as spam, or that is silently suppressed is
indistinguishable in `email_logs` from one that landed in the inbox.

Because login OTP, MFA, account lifecycle, and referral notifications are all
email-gated, "the user never got the OTP" is an authentication incident. Today it
is undiagnosable from inside the application: the only recourse is reading the
provider dashboard by hand.

`docs/EMAIL_DELIVERY_v2.0.0.md` §6 already records this as a pre-launch gap —
"Bounce and complaint handling ⚠️ Required before launch" (ISO 27001 A.8.24).

## 2. Scope

**In scope**

1. Correlate every outbound message with its Resend message ID.
2. Receive, authenticate, and record Resend delivery webhooks.
3. Reflect real delivery state (`delivered`, `bounced`, `complained`, …) in
   `email_logs` and in the admin email-log screen.
4. An artisan command to verify transport configuration from a deployed
   environment.
5. Configuration and documentation, including the DNS/domain-verification
   checklist.

**Out of scope (explicitly deferred)**

- A failover mail transport. `EMAIL_DELIVERY_v2.0.0.md` §6 flags a single
  transport as a single point of failure for login (ISO 27001 A.5.30). That row
  stays ⚠️ after this change. Deferred by decision, not oversight.
- Application-side suppression list. Resend maintains suppression provider-side
  and returns `email.suppressed`; duplicating that state locally would create two
  sources of truth.
- Changing which mailer any environment uses. Local development stays on Mailpit.

## 3. Constraint: the sending domain is not yet verified

The Resend account exists but its domain is unverified. In that state Resend
permits only `from: onboarding@resend.dev`, and only delivery **to the address
that owns the Resend account**.

Consequences, which the implementation must not paper over:

- The `EMAIL_DELIVERY_v2.0.0.md` §5 item "confirm delivery to at least three
  mailbox providers" **cannot pass** until DNS verification is done. It is
  recorded as blocked-on-DNS, not as satisfied.
- Webhooks *can* be developed and tested fully without a verified domain: they are
  authenticated by a signing secret, not by the sending domain.
- `mail:verify-transport` must print the provider's error verbatim, so an
  unverified-domain rejection is self-explanatory rather than a generic failure.

## 4. Correlation key

`Illuminate\Mail\Transport\ResendTransport::doSend()` adds an
`X-Resend-Email-ID` header to the message after the API call succeeds. Because
`MessageSent` exposes that same message object (`__get('message')` returns
`$this->sent->getOriginalMessage()`), the listener can read the value:

```
$event->message->getHeaders()->get('X-Resend-Email-ID')?->getBodyAsString()
```

Every Resend webhook payload carries the same value at `data.email_id`. That is
the join between an outbound send and its later delivery events.

Two secondary benefits:

- The existing duplicate-suppression heuristic in `handleMessageSent` — "skip if
  an identical row was created within the last 2 seconds" — can be replaced by an
  exact match on `provider_message_id`. The time window is fragile in both
  directions: it drops genuine repeat sends to the same address inside 2 seconds,
  and fails to catch duplicates that arrive later.
- Support staff can quote a provider message ID when contacting Resend.

The header is absent for non-Resend transports (`log`, `smtp`), so
`provider_message_id` stays nullable and the listener must tolerate its absence.

## 5. Data model

### 5.1 `email_logs` (existing table, additive change)

| Column | Type | Purpose |
|---|---|---|
| `provider_message_id` | `string` nullable, indexed | Resend `email_id`; webhook join key |
| `delivered_at` | `timestamp` nullable | Set once on first `email.delivered` |

The existing `status` column is a plain `string` with no CHECK constraint
(verified in `2026_06_01_000006_create_monitoring_tables.php`), so widening its
vocabulary needs no constraint migration.

### 5.2 `email_events` (new table, append-only)

| Column | Type | Purpose |
|---|---|---|
| `id` | uuid pk | Consistent with `UsesUuid` elsewhere |
| `email_log_id` | uuid nullable, indexed | FK to `email_logs`; null when no matching row |
| `provider_message_id` | `string` nullable, indexed | Correlation even when unmatched |
| `event_type` | `string` | e.g. `email.delivered` |
| `occurred_at` | `timestamp` nullable | Provider's `created_at` |
| `svix_id` | `string` **unique** | Idempotency key |
| `payload` | `json` | Verbatim event body |

**Why a table rather than a JSON column on `email_logs`.** The alternative
considered was a `provider_events` JSON array mutated in place. Resend emits
several events for one message in close succession (`sent`, `delivered`,
`opened`) and retries failed deliveries, so concurrent workers would perform
overlapping read-modify-write cycles on the same row and silently lose events.
Separate INSERTs cannot race. The append-only shape is also a better fit for the
traceability controls in ISO 27001 A.8.15 and ISO 9001 7.5.3, but the deciding
factor is data loss, not audit preference.

**Idempotency comes free.** Svix retries reuse the same `svix-id`. A unique
constraint on `svix_id` makes replay a caught constraint violation rather than a
duplicate row, with no extra bookkeeping.

Rows are kept when the parent `email_logs` row is pruned by
`PruneEmailLogs` — see §9.

## 6. Webhook endpoint

### 6.1 Placement

`POST /api/webhooks/resend`, declared in `routes/api.php`.

`routes/web.php` is the wrong home. The `web` group appends `CheckUserActive`,
`EnsureMfaSession`, `CheckMfaEnrolled`, `ContentSecurityPolicy`, and
`HandleInertiaRequests` (`bootstrap/app.php`), plus CSRF and session handling —
all meaningless for a server-to-server callback and each an opportunity to reject
a legitimate webhook. The `api` group carries none of them. This also avoids
adding a CSRF exclusion, which would be an easy control to lose track of.

Rate limit: `throttle:300,1`, consistent with the other public endpoints in
`routes/api.php` and comfortably above expected event volume.

### 6.2 Signature verification

Resend signs webhooks with Svix. The scheme:

1. Read headers `svix-id`, `svix-timestamp`, `svix-signature`.
2. Reject if the timestamp is outside a ±5 minute tolerance (replay defence).
3. Build the signed content as `{svix-id}.{svix-timestamp}.{raw body}`.
4. Key = `base64_decode()` of the portion of `RESEND_WEBHOOK_SECRET` after the
   `whsec_` prefix.
5. `hash_hmac('sha256', $signedContent, $key, true)`, then base64-encode.
6. `svix-signature` holds space-delimited `v1,<sig>` pairs. Accept if **any** `v1`
   entry matches under `hash_equals` (constant-time). Multiple signatures exist so
   the secret can be rotated without downtime; checking only the first would break
   rotation.

The body must be the raw `$request->getContent()`. Any re-encoding of the decoded
JSON invalidates the signature.

Implemented in-repo rather than pulling in `svix/svix`: the algorithm is short
and fully specified, and it avoids a dependency in the request path of an
authentication-critical subsystem. It lives in a dedicated class so it is unit
testable in isolation.

**If `RESEND_WEBHOOK_SECRET` is unset, the endpoint rejects everything.** It must
never fall open — an unauthenticated writer to `email_logs` would be a spoofing
vector (ISO 27001 A.8.24).

### 6.3 Response codes

| Situation | Status | Reason |
|---|---|---|
| Verified and processed | `200` | |
| Verified, event type not of interest | `200` | Retrying would never succeed |
| Bad/missing signature, or stale timestamp | `401` | |
| Malformed body / no `data.email_id` | `422` | |
| Duplicate `svix_id` | `200` | Already handled; suppress retries |
| Unexpected server error | `500` | Let Svix retry |

Returning `4xx` for unknown event types would make Resend retry a request that
can never succeed, and could get the endpoint disabled provider-side.

## 7. Status mapping and ordering

| Resend event | `email_logs.status` | Rank |
|---|---|---|
| `email.sent` | `sent` | 1 |
| `email.scheduled` | `sent` | 1 |
| `email.delivery_delayed` | `delayed` | 2 |
| `email.opened` | *(no status change)* | — |
| `email.clicked` | *(no status change)* | — |
| `email.delivered` | `delivered` | 3 |
| `email.suppressed` | `suppressed` | 4 |
| `email.failed` | `failed` | 5 |
| `email.bounced` | `bounced` | 6 |
| `email.complained` | `complained` | 7 |

**Monotonic advance.** Webhook delivery is not ordered, and retries can arrive
long after later events. `status` is only updated when the incoming event's rank
is **strictly greater** than the current status's rank; otherwise the event is
still recorded in `email_events` but `email_logs.status` is left alone. Without
this, a retried `email.sent` arriving after `email.bounced` would report a bounced
message as successfully sent — precisely the failure this feature exists to
prevent.

Terminal states outrank informational ones: a bounce or complaint must never be
overwritten by a delivery notification for the same message.

`email.opened` and `email.clicked` are recorded as events but change no status.
Open tracking depends on remote image loading and is unreliable; treating it as a
delivery state would be misleading. They are retained because they are useful
evidence when a user disputes receiving a message.

`bounce.message`/`bounce.subType` (and the equivalent failure reason) are written
to `error_message` so the admin screen shows *why*, not just *that*.

Events whose `data.email_id` matches no `email_logs` row are still inserted into
`email_events` with a null `email_log_id`. Dropping them would hide exactly the
cases worth investigating — e.g. mail sent before this feature shipped, or
pruned rows.

## 8. Configuration

```env
# config/services.php -> services.resend
RESEND_API_KEY=              # send-only scope
RESEND_WEBHOOK_SECRET=       # whsec_... from the Resend webhook dashboard
```

- `services.resend.webhook_secret` is added; `services.resend.key` already exists.
- `.env.example` gains `RESEND_WEBHOOK_SECRET` with a comment on where to obtain
  it and the fact that the endpoint fails closed without it.
- **No secret values are committed.** Both are injected as runtime environment
  variables in the platform secret store (`DEPLOYMENT_GUIDE_v3.0.0.md` §4).
- Local `.env` is not modified: development stays on Mailpit so this change cannot
  cause accidental live sends from a laptop.

## 9. Retention

`PruneEmailLogs` currently deletes old `email_logs` rows. `email_events` rows are
deliberately **not** cascade-deleted with their parent — an append-only audit
record that disappears when its index row is pruned provides weaker evidence than
one that does not. The `email_log_id` FK is nullable precisely so orphaned events
remain valid.

Pruning `email_events` on its own retention clock is a follow-up, noted so the
table's growth is a known quantity rather than a surprise.

## 10. Verification command

`php artisan mail:verify-transport {email}`

Prints the resolved mailer, the from-address, and whether the API key and webhook
secret are present (**masked** — never the values). Sends one real test message,
then reports the returned Resend message ID or the provider error verbatim.

This exists because `EMAIL_DELIVERY_v2.0.0.md` §5 requires the launch test to run
*from the deployed environment*, not a developer laptop — the laptop has
different egress and different environment variables. A dashboard is not a
substitute: the command exercises this application's own configuration.

## 11. Admin UI

`EmailLogController::index` whitelists `['sent', 'failed']` for status filtering,
so any new status would be silently unfilterable. Extended to the full
vocabulary from §7.

`resources/js/Pages/Admin/System/EmailLogs/Index.jsx` gains badges for the new
statuses and shows the provider message ID and delivery timestamp.

`EmailLogController::resend` stays restricted to `status === 'failed'`. Bounced
and complained are **deliberately excluded**: re-sending to an address that hard
bounced, or to someone who marked the mail as spam, damages domain reputation and
is what suppression lists exist to prevent. This is a correctness decision, not
an omission.

## 12. Testing

| Area | Cases |
|---|---|
| Signature | Valid; wrong secret; tampered body; missing headers; stale timestamp; multiple `v1` signatures where the second matches (rotation) |
| Idempotency | Same `svix-id` twice → one `email_events` row, `200` both times |
| Mapping | Each event type in §7 sets the expected status |
| Ordering | `bounced` then a late `sent` leaves status `bounced`; `delivered` then `bounced` ends `bounced` |
| Unmatched | Unknown `email_id` → event stored with null `email_log_id`, `200` |
| Correlation | `MessageSent` with an `X-Resend-Email-ID` header persists `provider_message_id`; absent header tolerated |
| Failing closed | Unset `RESEND_WEBHOOK_SECRET` → `401` |
| Regression | Existing `EmailLoggingTest` still passes |

Signature fixtures are generated in-test from a known secret, so the tests verify
the algorithm rather than a hard-coded digest.

## 13. Standards-readiness check

Per project rule: this is a project document, not a certification artefact, so a
standards-readiness check applies. Items needing rework before certification are
flagged.

| Requirement | Reference | Status after this change | Action if gapped |
|---|---|---|---|
| Bounce/complaint handling | ISO 27001 A.8.24 | ✅ Closed by this change | — |
| Webhook authenticity, anti-spoofing | ISO 27001 A.8.24 | ✅ Svix HMAC, constant-time compare, replay window, fails closed | — |
| Delivery traceability and logging | ISO 27001 A.8.15; ISO 9001 7.5.3 | ✅ Append-only `email_events` + provider ID | — |
| Least-privilege credentials | ISO 27001 A.8.2; SOC 2 CC6.1 | ✅ Send-only key, secret only in env, masked in output | Record rotation owner in supplier register |
| Sender authentication SPF/DKIM/DMARC | ISO 27001 A.8.24 | ⚠️ **Blocked on DNS** — domain unverified | Verify domain; publish SPF/DKIM/DMARC (`p=quarantine` min) |
| Availability of an auth-critical dependency | ISO 27001 A.5.30 | ⚠️ **Still open** — single transport | Deferred by decision (§2); document fallback + switch procedure |
| Personal data to a processor | ISO 27001 A.5.19–A.5.22; DPTM | ⚠️ Mail bodies and now webhook payloads hold personal data | Record Resend in the supplier register with processing terms and residency |
| Log retention defined | ISO 27001 A.8.15 | ⚠️ `email_events` has no retention clock | Follow-up: extend `PruneEmailLogs` (§9) |

Two ⚠️ items are deliberate deferrals (A.5.30, DNS) and two are documentation
follow-ups outside the code change. None require rework *of this design* to
certify; all four need action before a certification audit.

## 14. Changelog

| Version | Date | Change |
|---|---|---|
| 1.0.0 | 2026-07-27 | Initial design: Resend message-ID correlation, append-only `email_events`, Svix-verified webhook endpoint, monotonic status mapping, verification command, admin UI updates. |
