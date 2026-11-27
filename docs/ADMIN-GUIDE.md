# Admin guide

One section per area of the admin panel at `/admin`. Each link in the menu appears only if your role allows it.

## Daily work

- **Dashboard:** new enquiries, assessments, quotes waiting on clients, and the production-readiness banner.
- **Enquiries:** every form submission.
  - Opening one marks it Open.
  - Set its status, assign it to a person (who gets an email), and add internal notes that clients never see.
  - **Create client and portal login** turns an enquiry into a client and sends the contact a password link.
- **Clients:** portal users, quotes, shared documents and the client's enquiries. Uploads may be PDF, Office files, PNG, JPG, CSV or TXT, up to 20 MB.
- **Quotes:** line items from the catalogue or free text, currency, tax and validity.
  - **Send to client portal** emails every portal user of that client.
  - Clients accept or decline in the portal, and you get an email.

## Website

- **Catalogue, Categories, Insights, Case studies, Jobs and Pages** are edited in Markdown (`## heading`, `- list`, `**bold**`, `[link](https://…)`).
- **Matching tags** on catalogue items decide which items the AI & process assessment recommends.
- **Vendors:** leave the Vendor field empty unless you have a reseller agreement.
- **Case studies:** keep "Sample scenario" ticked unless the client has given written permission to be named.

## Settings

### People

- **People:** add staff by sending an invitation link, and change role, team and manager.
  - Bulk actions: set role or team, send password links, unlock, sign out everywhere, and turn access on or off.
  - You cannot change your own role or access.
- **Teams:** a tree of teams with leaders.
- **Roles & permissions:** every role against every permission. Use **Find a permission** and **Show only the differences** to work through it. You can create a role from an existing one. Built-in roles and roles that people hold cannot be removed.
- **Sign-in:** password length, two-step sign-in for staff and clients, lockout, idle and absolute session limits, and the allowed staff email domains.

### Pricing

- **Rates & currencies:** the rate card (`name | amount | unit`), currencies, tax and quote numbering.
- **Quote estimate:** shows indicative price ranges, worked out from each service's typical duration and a day rate. A preview lists every service before you turn it on.

### Records

- **Audit log:** filter it, export it to CSV, and check it is intact.
- **Email log:** what was sent and why anything failed. Message bodies are not stored.

### System

- **General:** where enquiries go, public contact details, company details for the footer, Staging or Production, retention and maintenance mode.
- **Branding:** name, tagline, accent colour and footer text.
- **Email (SMTP):** server, port, user and a password that is stored encrypted. **Test connection and send** checks it end to end.
- **Single sign-on:** Microsoft Entra ID, Google Workspace or any OpenID Connect provider.
  - Give the provider the redirect URI shown on the page, then use **Check the provider configuration**.
  - Keep password sign-in on until you have tested it.
- **HTTPS:** the certificate's issuer and expiry, and HSTS strength.
- **Updates:** the running version and commit, software integrity against the release manifest, and database updates.
- **Backups & health:** the production-readiness checklist, backups (take, download, restore), health checks, the cron command, and a troubleshooting report with no secrets in it.
- **Get help:** send a request (numbered SUP-n or ENH-n) to the support address.
- **About:** version, licence, terms acceptance and what is new.
