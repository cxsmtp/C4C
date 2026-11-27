# Call4Counsel version log

Newest first. Each release says, in plain language, what changed for the people who use the site.

## C4C-01.01.00 — 2026-11-27

- **A calmer, more professional look.** The site now uses a light theme: navy text on white, a single blue for buttons and links, soft grey section bands, and green, amber and red only for status. The pink accent and the contour-map graphics are gone. The home page shows a simple four-step "How we work" panel instead.
- **Fonts that are already on every device.** Text uses the system font of each device: Segoe UI on Windows, San Francisco on Apple devices and Roboto on Android. Nothing is downloaded from Google Fonts, so pages load faster and the security policy no longer needs to allow any other site.
- **A cleaner logo.** It is a navy shield with a check mark.
- **Existing sites switch over by themselves.** A database update changes the old pink accent to blue. A colour already chosen in Settings → Branding is kept.
- **Status:** 26 unit and integration tests pass, and 405 page views across nine screen sizes show no horizontal scrolling.

## C4C-01.00.00 — 2026-11-27

- **A complete website for selling security, AI and IT services and products.** Pages for AI & agentic security, application security, IT services and data engineering, a catalogue of 19 services and 17 products with a quote list, case studies, insights, careers, contact, privacy and terms. Example case studies are labelled "Sample scenario" until real client stories are published with permission.
- **A free AI & process assessment that recommends what to buy.** Visitors describe how they use AI assistants, agents, software and IT. They see findings at once, mapped to the OWASP Top 10 for LLM Applications, NIST and ISO/IEC 42001, with the services and products that close the gaps. Every assessment reaches the team for a follow-up proposal.
- **Every enquiry is kept and emailed.** Contact forms, quote requests, assessments, job applications and portal requests are stored in the admin panel and sent to the addresses in Settings → General. Public forms are protected by a hidden field, a minimum fill time, rate limits and a check that the post came from this site.
- **A client portal.** Clients accept or decline quotes, download the reports shared with them, see their assessments and send requests.
- **Settings the way FTR Command Center does them.** People, Teams, Roles & permissions and Sign-in; Rates & currencies and Quote estimate; the Audit log and the Email log; General, Branding, Email (SMTP), Single sign-on, HTTPS, Updates, Backups & health, Get help and About.
  - Six built-in roles (Owner, Administrator, Sales, Consultant, Content editor, Viewer) and custom roles. Nobody gives a permission they do not hold, only an Owner gives the Owner role, nobody changes their own role or access, and there is always an active Owner.
  - Two-step sign-in with any authenticator app and ten one-time recovery codes. It is required for staff by default. Accounts lock after repeated wrong passwords, and sessions end when idle or after a fixed time. "Sign out everywhere" and password changes end every other session.
  - Single sign-on for staff with Microsoft Entra ID, Google Workspace or any OpenID Connect provider, with PKCE and a signature check of every ID token.
  - Every action, and every refusal, is in an audit log where each entry is chained to the one before, so an edit or deletion is detectable. Export it to CSV with the filters you set.
  - The SMTP password and the single sign-on secret are stored encrypted and never shown again.
- **Ready for production, and able to prove it.** Backups & health lists 20 checks with their state, and shows the health of the runtime, database, storage, security, email and backups. Backups are encrypted and taken nightly, before every database update and before every restore. A restore never rewinds the audit log. Database updates apply themselves once per release. Settings → Updates checks every file against the release manifest. `/health` serves uptime monitors and `/.well-known/security.txt` tells researchers where to report.
- **Made for every screen.** Nothing scrolls sideways at any width. Tables become stacked, labelled cards when space is narrow. The admin and settings menus fold into a button on phones, and form controls look the same in Safari, Chrome, Edge and Firefox. People → Who can do what explains each role in plain words.
- **Status:** 405 page views across nine screen sizes (320 px to 1920 px, iPhone, iPad and Pixel) show no horizontal scrolling. 26 unit and integration tests pass, browser tests of every journey and settings page pass, and the load test is in docs/performance.md.
