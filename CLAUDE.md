# Working on Call4Counsel

## Rules for every change

- Run `php tests/run.php` before pushing. Run `bash tests/smoke.sh` against a local server whenever you change pages or routes.
- A release changes `VERSION` (semver, shown as `C4C-XX.YY.ZZ`) and adds a section to the top of `CHANGELOG.md`.
  - The section is written in plain, user-facing language: bold headline bullets that say the outcome, then details. End it with a **Status:** line giving the test results.
- After any change to shipped files, run `php scripts/make-manifest.php` and commit SHA256SUMS. CI fails otherwise.
- Database changes go in a new entry in `app/lib/migrate.php`. Never edit a released migration.
- Every admin route starts with `require_perm('<permission>')`. A new permission goes in `permission_catalog()` and the built-in roles.
- Record every change and every refusal with `audit()`. Use `refuse()` for refusals so they are both recorded and explained.
- Never use inline `<script>` or `style=`: the CSP forbids both. Put behaviour in `assets/site.js` and styles in `assets/site.css`.
- Escape every value with `e()`. Use `md()` for Markdown. Never echo raw input.
- Confirm destructive actions with `data-confirm` on the form, which opens the in-app dialog. Never call `confirm()`.
- Secrets never go in the repository. They live in `app/config.php`, or are sealed with `seal()` in settings.
- The writing style is plain, short, second person, and says what happens: "There must always be at least one active Owner."
