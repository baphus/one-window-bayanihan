# MVP Email Sending: Domain and Resend Requirement

> **Status:** Required before MVP production deployment  
> **Updated:** 2026-07-12  
> **Applies to:** Render-hosted production deployment, Laravel mail delivery, OTP and notification emails

## Summary

Before deploying the MVP, the project must have a purchased domain name and verified email sending through Resend.

Render should not be treated as a reliable path for Gmail SMTP-based production email. Render commonly blocks or restricts direct SMTP traffic patterns that Gmail SMTP depends on, and Gmail SMTP is not designed for application-scale transactional email. For this project, production email should use Resend's API-based delivery instead.

## MVP Requirement

The MVP deployment must include:

1. **A purchased domain name** for the public website and official sender identity.
2. **Resend configured as the production email provider.**
3. **Domain verification in Resend** using the required DNS records.
4. **API-based email sending capability** from the Laravel application instead of relying on Gmail SMTP.
5. **A verified sender address**, for example:
   - `noreply@<domain>`
   - `support@<domain>`
   - `notifications@<domain>`

## Why Gmail SMTP Should Not Be Used on Render

Gmail SMTP is not recommended for this production deployment because:

- Render may block or limit SMTP usage, which can prevent email delivery.
- Gmail SMTP is intended for mailbox use, not application transactional email.
- Gmail can flag automated system emails as suspicious.
- Sending limits are low compared with transactional email providers.
- Deliverability, auditability, and production support are weaker than API-based providers.

Because this system depends on email for OTP, MFA, account, and notification flows, production email delivery must be dependable.

## Why Use Resend

Resend is a better fit for the MVP because it supports API-based transactional email delivery. This avoids SMTP port issues and works well with cloud hosting platforms like Render.

Benefits include:

- **API-based sending:** Email is sent over HTTPS API calls instead of SMTP ports.
- **Better deliverability:** Domain verification, SPF, DKIM, and DMARC support improve inbox trust.
- **Operational reliability:** Designed for application-generated transactional emails.
- **Clear delivery visibility:** Logs and delivery status help troubleshoot failed or delayed messages.
- **Production-ready sender identity:** Emails can come from an official project domain.
- **Scalability:** More suitable for growing OTP, notification, and system email volume.

## Benefits of Buying a Domain Name

A domain name is not only for the website URL. It is also important for trust, email delivery, and long-term project credibility.

Key benefits:

- **Professional identity:** Users receive emails from an official domain instead of a personal Gmail account.
- **Improved trust:** A real domain makes the MVP look legitimate and production-ready.
- **Better email deliverability:** Verified domain DNS records help prevent emails from going to spam.
- **Security controls:** SPF, DKIM, and DMARC protect against spoofing and phishing.
- **Consistent branding:** The website URL and sender email can use the same identity.
- **Future flexibility:** The same domain can support subdomains such as `app.<domain>`, `api.<domain>`, or `status.<domain>`.
- **Provider portability:** The domain can be moved between hosting or email providers without changing the public identity.

## Deployment Checklist

- [ ] Buy the project domain name.
- [ ] Point the website domain or subdomain to Render.
- [ ] Create a Resend account.
- [ ] Add and verify the domain in Resend.
- [ ] Add Resend DNS records at the domain registrar or DNS provider.
- [ ] Configure Laravel production environment variables for Resend/API email delivery.
- [ ] Send a test OTP email from the deployed Render environment.
- [ ] Confirm delivery to Gmail, Outlook, and at least one non-Google mailbox if available.
- [ ] Monitor Resend logs during MVP launch.

## Recommended Production Direction

For the MVP, use:

```text
Website hosting: Render
Email provider: Resend
Email transport: API-based sending
Sender domain: Purchased and verified project domain
Avoid: Gmail SMTP for production delivery
```

This setup gives the project a more reliable, secure, and professional email foundation for launch.
