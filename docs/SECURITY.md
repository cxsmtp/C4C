# Security

What protects Call4Counsel, and how to report a problem.

## Reporting a vulnerability

Email the address in `https://call4counsel.com/.well-known/security.txt`. We reply within two business days.

## Controls

| Area | Control |
|---|---|
| Transport | HTTPS only (`.htaccess` redirect). HSTS with configurable age, subdomains and preload. `__Host-` session cookie that is Secure, HttpOnly and SameSite=Lax. |
| Browser | A CSP with no inline script or style, X-Frame-Options DENY, nosniff, a strict Referrer-Policy, a Permissions-Policy, and COOP and CORP same-origin. |
| Forms | A per-session CSRF token on every post, plus an Origin check. Adding to the quote list uses Fetch Metadata instead. Public forms also have a honeypot, a 3-second minimum fill time and per-address rate limits. |
| Input and output | Prepared statements only; table names come from code, never from input. Every value is HTML-escaped on output. Markdown is escaped first, then a small safe subset is rendered, and only http, https, mailto and relative links are allowed. |
| Sign-in | Passwords hashed with bcrypt, at least 12 characters, and refused if common or containing the email name. Accounts lock after repeated failures. Timing is the same whether or not an account exists. |
| Two-step sign-in | TOTP (RFC 6238), and each code works once. The secret is sealed at rest. Ten recovery codes are stored only as keyed hashes. Required for staff by default. |
| Sessions | Regenerated at sign-in. Idle and absolute timeouts apply. A password change, a reset or "sign out everywhere" ends every session. |
| Single sign-on | OpenID Connect with PKCE, state and nonce. The ID token's RS256 signature is checked against the provider's keys, along with the issuer, audience and expiry. Email domains can be restricted. |
| Authorisation | A permission check on every admin route. Nobody grants beyond what they hold, only an Owner gives the Owner role, and there is always an active Owner. Refusals are recorded. |
| Files | Uploads are checked against an allow-list of types (by content and extension) and a 20 MB limit. They are stored outside the web root under random names and served as attachments with a sandbox CSP. |
| Secrets | `app/config.php` is never committed and is blocked from the web. The SMTP password and SSO secret are sealed in the database and never shown again. |
| Records | The audit log is HMAC-chained, so any edit or deletion is detectable, and restores never rewind it. IP addresses are stored only as keyed hashes. |
| Integrity | SHA256SUMS holds the SHA-256 of every shipped file (check by hand with `sha256sum -c SHA256SUMS`). Settings → Updates checks the live files against it. |
| Email | Headers are stripped of line breaks and recipients are validated. The site never emails the address a visitor typed, so its forms cannot be used to send spam. |

## Testing

- CI runs `php tests/run.php`, the smoke test (which also checks the security headers and that a post without a token is refused) and a short load test.
- Each release is scanned with Checkmarx One (SAST, SCA, IaC and secrets) before it is merged.
