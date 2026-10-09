# September 2026 website scanner remediation

The September 1 light scan reported three low-risk and two informational findings.
It did not test SQL injection, XSS, command injection, or other deep scan checks.

| Finding | Remediation / interpretation |
| --- | --- |
| CSP script sources | Production/staging uses a per-response nonce and `strict-dynamic`, removing blanket same-origin script trust. Vite, Ziggy, prefetch, and dynamically loaded CAPTCHA scripts retain their loading paths. Local hot reload retains its development policy. |
| Deprecated CSP reporting | Added `report-to` and `Reporting-Endpoints`; corrected the default path to `/api/csp/report`. The receiver handles Reporting API batches and legacy `application/csp-report`. `report-uri` remains deliberately as a compatibility fallback. |
| robots.txt | Removed the inventory of staff routes. Public pages and preview images remain crawlable; tracking, survey, API, and chatbot exclusions remain. Authorization protects private routes; the indexing configuration still controls `noindex`. |
| Technology disclosure | Removed Laravel/PHP version props from public pages, stripped application technology headers, and disabled nginx version tokens. PHP already has `expose_php=Off`. Proxies can add their own headers; recognizable client assets and required protocol headers are not secrets. |
| Missing security.txt | Added `public/.well-known/security.txt` with the owner-confirmed reporting address. Renew its expiry before September 1, 2027. |
| Email exposure | The homepage now selects only the agency fields used by its cards/maps. Dedicated public agency pages retain their intentional office contacts; removing those would break the directory's purpose. |

## Deployment verification

Deploy the application and rebuilt nginx configuration through the normal release
process. If an environment explicitly sets `CSP_REPORT_URI=/csp/report`, change it
to `/api/csp/report` and rebuild Laravel's configuration cache. External reporting
URLs and an explicitly empty setting remain supported.

After deployment, verify public pages and login render, CAPTCHA loads, Inertia
navigation and maps work, and `/.well-known/security.txt` returns plain text over
HTTPS. Recheck the deployed response headers, including those added by the edge
proxy, and rerun the scanner. A residual `report-uri` warning is the intentional
browser compatibility fallback, not a missing `report-to` implementation.

## Sources

- [Laravel 13: Vite CSP nonces](https://laravel.com/docs/13.x/vite#content-security-policy-csp-nonce)
- [Laravel 13: deployment and configuration caching](https://laravel.com/docs/13.x/deployment)
- [MDN: report-to and legacy fallback](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Content-Security-Policy/report-to)
- [Turnstile: CSP and strict-dynamic support](https://developers.cloudflare.com/turnstile/reference/content-security-policy/)
- [RFC 9116: security.txt](https://www.rfc-editor.org/rfc/rfc9116.html)
