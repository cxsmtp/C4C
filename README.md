# Call4Counsel

Website for Call4Counsel: application security, AI & agentic security, IT services and product resale.

- Public site with a services and products catalogue, quote list and a free AI & process assessment
- Enquiries (contact, quote, assessment, job, portal) are stored in the database and emailed to the addresses set in **Admin > Settings**
- Admin panel at `/admin`: enquiries, clients, quotes, documents, catalogue, insights, case studies, jobs, pages, settings, admin users and activity log
- Client portal at `/portal`: quotes (accept or decline), shared documents, assessment results and requests

Plain PHP 8.1+ and MySQL, no third-party PHP packages. Version `C4C-01.00.00`; see [CHANGELOG.md](CHANGELOG.md).

- [docs/ADMIN-GUIDE.md](docs/ADMIN-GUIDE.md): every admin and settings page
- [docs/OPERATIONS.md](docs/OPERATIONS.md): deploy, release, monitor, back up, restore, roll back
- [docs/SECURITY.md](docs/SECURITY.md): controls and vulnerability reporting
- [docs/performance.md](docs/performance.md): load test results and how to repeat them

Checks before every push: `php tests/run.php`, `bash tests/smoke.sh` and `php scripts/make-manifest.php --check`. CI runs all of them.

## Deploy on Hostinger

1. Create a MySQL database and user in hPanel.
2. Upload the repository to `public_html` of the domain.
3. Copy `app/config.sample.php` to `app/config.php` and fill in the database, `app_key`, `install_key` and SMTP mailbox password.
4. Open `https://call4counsel.com/install`, enter the install key and create the first Owner.
5. Remove `install_key` from `app/config.php` and add the hourly cron job (`cron.php`).
6. Work through **Settings → Backups & health → Ready for production?** until every row is ready.

`app/` and `storage/` are blocked from the web by `.htaccess`; only `index.php` executes PHP.
