# Operations runbook

How to deploy, monitor, back up, restore and roll back Call4Counsel on Hostinger.

## First deployment

1. **Database.** In hPanel → Databases, create a MySQL database and user. The app connects to host `127.0.0.1`.
2. **Code.** Use hPanel → Advanced → Git to deploy branch `main` of `cxsmtp/C4C` into `public_html`, with auto-deployment on.
3. **Configuration.** Create `app/config.php` from `app/config.sample.php` in the File Manager and set its permissions to `600`. Fill in:
   - the database details;
   - `app_key`: 64 random hex characters. It seals backups and secrets, so keep a copy in your password manager. Backups cannot be read without it.
   - `install_key`: any long random value, used once.
4. **Install.** Open `https://call4counsel.com/install` and enter the install key and the first Owner's details. Then delete `install_key` from `app/config.php`.
5. **Scheduled jobs.** In hPanel → Advanced → Cron Jobs, run this every hour:
   ```
   /opt/alt/php83/usr/bin/php -d extension=sodium.so /home/<user>/domains/call4counsel.com/public_html/cron.php
   ```
   Hostinger's default command-line PHP (`/usr/bin/php`) does not load the `sodium` extension the site needs, so a job using it fails at the backup step. The job's last output appears under hPanel → Cron Jobs → View output, and Settings → Backups & health shows when it last ran.
6. **Finish the checklist** in Settings → Backups & health → *Ready for production?* until every row reads Ready or As meant. Items include:
   - email tested;
   - two Owners;
   - two-step sign-in for every staff account;
   - company details;
   - an off-server backup copy.

## Releasing a new version

1. Update `VERSION` (for example `1.1.0`, shown as `C4C-01.01.00`) and add a section to the top of `CHANGELOG.md`.
2. Run `php tests/run.php`, then `php scripts/make-manifest.php`, and commit.
3. Open a pull request. CI must pass, which covers lint, tests, the manifest check, the secret check, the smoke test and a short load test. A Checkmarx scan must also show no unresolved findings.
4. Merge to `main`. Hostinger auto-deploy updates the site.
5. On the first request, database updates apply under a lock. They are listed in Settings → Updates. Software integrity there must read **Genuine**.

## Monitoring

- **Uptime:** point a monitor at `https://call4counsel.com/health`. It returns `{"status":"ok"}` with HTTP 200, or 503 if the database is down.
- **Daily:** look at the Dashboard banner and Settings → Backups & health.
- **Email:** Settings → Email log shows every message the site sent and why any failed. Enquiries are always saved in the admin panel, even when email fails.

## Backups

- **When:** nightly through cron, before every database update, before every restore, and on demand.
- **Retention:** the newest 14 are kept (configurable).
- **Format:** gzip-compressed JSON of every table, sealed with libsodium using a key derived from `app_key`.
- **Storage:** `storage/backups/`, which is blocked from the web.
- **Off-server copy:** download one every week to company storage.
- **Shared documents:** the files themselves stay in `storage/uploads/`. Copy that folder too, using File Manager → Compress.

## Restore

1. Open Settings → Backups & health and choose **Restore** on the backup you want.
2. The app takes a safety backup of the current data first. It then replaces every table except the audit log, which is append-only and never rewound.
3. If you are moving to a new server, upload the backup to `storage/backups/` and use the **same** `app_key`.

## Rolling back a release

1. Revert the merge on GitHub. Auto-deploy puts the previous code back.
2. If the release had a database update and the old code cannot run on it, restore the `pre-update` backup taken just before it.

## Incidents

- **Suspected breach:** turn on maintenance mode (Settings → General). Then:
  - sign everyone out (Settings → People → select all → Sign out everywhere);
  - change `app_key` only after downloading a backup, because old backups need the old key;
  - rotate the database and SMTP passwords;
  - review the Audit log and export it.
- **Locked out:** another Owner can unlock you or reset your two-step sign-in. If no Owner can sign in, an administrator with server access can run `php -r` against the database to set `locked_until = NULL`, or use a recovery code.
