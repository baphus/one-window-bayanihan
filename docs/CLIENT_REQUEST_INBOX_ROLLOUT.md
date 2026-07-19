# Client Request Inbox Rollout

## Scope

The initial release enables receiving agencies to send client-facing document requests, questions, and information updates. Clients use a seven-day, request-specific access link to view a request and reply from the tracking experience.

Client file uploads are intentionally unavailable in this release. Do not enable upload controls until production malware scanning is fail-closed or uploads are quarantined and promoted only after an asynchronous scan succeeds.

## Deployment Order

1. Apply the referral-client request migrations before deploying routes or UI.
2. Deploy the backend request lifecycle, access-link, tracking-session, document-isolation, notification, and mail changes.
3. Confirm the queue worker is running and the configured mail transport can deliver the encrypted queued `ClientRequestMail` job.
4. Deploy the frontend tracking request panel and referral-detail workspace.
5. Verify normal tracking still requires the case client's matching email OTP and remains read-only.
6. Enable agency request creation after completing the smoke checks below.

## Configuration and Security Checks

- Use HTTPS in non-local environments.
- Confirm queue processing and failed-job monitoring are active before enabling agency delivery actions.
- Confirm the request email route uses a seven-day opaque access token and the token is not present in audit logs, database notifications, error context, or client request history.
- Confirm token pages return `Cache-Control: no-store` and `Referrer-Policy: no-referrer`.
- Confirm normal tracking OTP is bound to both the tracker number and the matching client email.
- Confirm receiving agencies can access only documents linked to their own referral; case-level documents must not be exposed to agency users.
- Confirm public request routes expose only the minimal request panel, not the standard tracking/case payload.

## Smoke Checks

1. As a receiving agency user, create a document request with a checklist and a client email.
2. Confirm the client request email is queued and its body contains only the agency name, optional due date, and action link.
3. Open the link and confirm it redirects to a token-free request page.
4. Confirm the panel shows the request/checklist and permits a text reply, but has no document upload control.
5. Confirm the agency and owning case manager receive a safe in-platform notification after the client reply.
6. Confirm another agency attached to the same case cannot open the request or its referral documents.
7. Reissue the request link and confirm the old link no longer works.

## Support Playbook

### Client says the link expired or was lost

1. Locate the request from the referral detail page.
2. Confirm the referral and request are still active.
3. Use **Reissue access link**. This atomically revokes every prior usable link for that request and queues a fresh seven-day link to the client email already stored on the case.
4. Never ask support staff to send a link to an arbitrary email address.

### Client requests a replacement through the tracking panel

The public action returns a generic acknowledgement and notifies the receiving agency. It does not automatically issue a replacement link or accept a destination email.

### Client has no email address

The request remains in the referral workspace with a `no email` delivery outcome. Agency staff must use the approved offline contact procedure or update the client contact information through the normal authorized workflow before reissuing access.

### Suspected compromised link

An active receiving-agency user, owning case manager, or administrator can revoke the access link. Reissue only after confirming the appropriate contact channel.

## Rollback

1. Disable agency request creation and public request routes/UI through the deployment rollback or feature flag process.
2. Stop queueing new `ClientRequestMail` jobs; do not delete request, message, access-link, delivery, or audit records.
3. Revoke any still-usable access links if public access must be immediately disabled.
4. Keep the schema in place during rollback so existing audit evidence and migration integrity remain intact.
5. Do not roll back the tracking OTP or referral-document isolation hardening; they are security fixes independent of the request inbox.
