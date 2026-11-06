# Windows setup, configuration, and database safety

## Local setup (XAMPP / PowerShell)

1. Install XAMPP with PHP 8.1 or newer and MySQL/MariaDB. Start Apache and MySQL.
2. Place the project at `C:\xampp\htdocs\safaritrak`.
3. Create a fresh local database named `safaritrak`, then import `backend\sql\schema.sql`.
4. Create `backend\config\env.local.php` returning an array of `SAFARITRAK_*` values; an example is in the repository README. This file is ignored by Git.
5. In PowerShell from the project folder, run `composer install` and `composer test`.
6. Back up any existing database, compare it against the schema snapshot, then explicitly initialise and baseline it: `php backend\scripts\migrate.php init`, followed by `php backend\scripts\migrate.php baseline`. Review `php backend\scripts\migrate.php status`, then apply only the listed additive migrations with `php backend\scripts\migrate.php up`.
7. Visit `http://localhost/safaritrak/` or `http://localhost/safaritrak/welcome.php`. The public welcome, login, and registration views can be inspected without MySQL, but authentication and protected pages require the configured database and migrations. If local email is not configured, use the development verification link only with `SAFARITRAK_DEV_MODE=1`.

To create synthetic school enrollment test data in a development database, run `php backend\scripts\seed-demo-school.php --apply`. The script creates an approved local test school and separate admin, student, and guardian accounts; it prints randomly generated passwords once and does not reset them on repeat runs. Do not run this against production or share its generated credentials.

PowerShell does not run a local `composer.phar` or script from the current directory unless invoked explicitly (for example `.\composer.phar install`). If PowerShell blocks a trusted local script due to execution policy, prefer the equivalent `php`/`composer` command; do not weaken machine-wide policy to run the app.

Alternative PHP-only smoke server from the repository root: `php -S 127.0.0.1:8000`. It does not start MySQL and is not a production server.

## Configuration

`backend/config/env.php` optionally loads the ignored `backend/config/env.local.php`. Existing process/Apache environment values win; local values fill only missing variables. `.env.example` is a key/value reference and is **not** parsed automatically by PHP. In production, set environment variables in the hosting platform and do not deploy `env.local.php`.

| Variable | Purpose | Local default / requirement |
| --- | --- | --- |
| `SAFARITRAK_ENV` | Runtime environment | `development` locally; `production` disables PHP error display and enforces production credential checks |
| `SAFARITRAK_DB_HOST` | MySQL host | `127.0.0.1` |
| `SAFARITRAK_DB_NAME` | Database name | `safaritrak` |
| `SAFARITRAK_DB_USER` | Database account | `root` for local XAMPP only; least-privilege user in production |
| `SAFARITRAK_DB_PASS` | Database password | Empty for default XAMPP only; set a secret in production |
| `SAFARITRAK_RATE_LIMIT_SECRET` | HMAC secret for pseudonymous authentication rate-limit keys | Use at least 32 random characters in production; local fallback is for development only |
| `SAFARITRAK_MAIL_HOST` | SMTP host | Provider-specific |
| `SAFARITRAK_MAIL_PORT` | SMTP port | `587` |
| `SAFARITRAK_MAIL_USERNAME` / `SAFARITRAK_MAIL_PASSWORD` | SMTP credentials | Optional locally; secrets in production |
| `SAFARITRAK_MAIL_FROM` / `SAFARITRAK_MAIL_FROM_NAME` | Sender identity | Use a verified sender in production |
| `SAFARITRAK_APP_URL` | Absolute public base URL for email links | Local URL in development; HTTPS production URL |
| `SAFARITRAK_DEV_MODE` | Enables development-only token links | `0` in production; never enable on a public deployment |

## Existing database and migrations

The current project has a schema snapshot at `backend/sql/schema.sql` and two historical SQL patches. The schema snapshot already includes `platform_admins` and the `is_suspended` columns which are also added by `migration_platform_admin.sql`; blindly applying every historical patch to a fresh schema can fail on duplicates. The snapshot originally lacked `sos_alert_notifications` and `product_update_emails`, although the settings page expects them and `migration_notification_settings.sql` adds them. Journey APIs also reference `journey_locations` and `journeys.covered_km`, which were absent from the earlier snapshot. The new additive migrations reconcile these known differences without dropping data; they do not prove that every live database matches the snapshot.

Migration and restore behavior has been validated against an isolated MySQL/MariaDB fixture with synthetic data. The local XAMPP `safaritrak` database was also backed up, restored into a temporary database, and verified against table definitions and row counts before applying the additive privacy-consent migration `0004`. Migration status now reports all migrations applied locally. This does not certify a production database or deployment. Before applying future migrations to an existing app database:

1. Identify the actual database and back it up. On XAMPP PowerShell, create a private backup folder outside `htdocs`, then run `& 'C:\xampp\mysql\bin\mysqldump.exe' --single-transaction --routines --triggers --result-file='C:\safaritrak-backups\safaritrak-before-phase2.sql' safaritrak`. Store the dump securely; it may contain personal and location data.
2. Compare live `SHOW CREATE TABLE` output with the schema snapshot and check the history of applied patches.
3. Use the new explicit `init`, `baseline`, `status`, and `up` commands only after inspecting a backup. `baseline` verifies required legacy tables/columns and records the schema snapshot checksum; it does not apply the snapshot or alter application tables.
4. Make any new change a forward-only, versioned migration with a unique identifier and reviewed preconditions. Current migrations add missing notification preference columns, authentication rate-limit storage, legacy journey-location compatibility, and the privacy-consent ledger. A rollback must never silently drop user/location data.
5. Test forward behavior against a disposable restored copy; record backup restore steps and expected row counts. `composer test:mysql-migrations` creates a pre-migration schema from the checked-in snapshot, dumps it, restores it to a second uniquely named loopback-only database, runs `init`, `baseline`, and `up`, and compares table inventories and row counts before applying migrations. It checks the resulting compatibility tables and sentinel row, then removes both temporary databases and the dump. The test requires `SAFARITRAK_TEST_DB_HOST=127.0.0.1`, `SAFARITRAK_TEST_DB_USER`, and an optional `SAFARITRAK_TEST_DB_PASS`; it does not read or use `SAFARITRAK_DB_*`.
6. Apply during a controlled maintenance window, verify key records and application flows, then retain the pre-migration backup under the retention policy.

For the local XAMPP database, `composer test:mysql-live-backup` performs a non-migrating backup/restore verification against `safaritrak`. The post-migration run verified matching definitions and row counts across 21 tables. It requires loopback database access and permission to create/drop one uniquely named disposable database. The script checks that source tables use InnoDB, stores the dump in the system temporary directory, compares restored table definitions and row counts, and removes the dump and restore database. It is not a substitute for a separate, encrypted backup stored outside the machine, and is not a production recovery/retention plan.

The new runner refuses to run `up` without an explicitly verified baseline, serializes runners with a MySQL advisory lock, and checks applied-file checksums. It is CLI-only and does not migrate automatically. The disposable test validates the currently checked-in path; it does not prove that the application's existing database matches the legacy baseline. Migration `0003` preserves an existing `journey_locations` table and validates its expected columns; if its structure differs, it stops for manual review rather than replacing it. Migration `0008_planned_journeys` adds the `planned` journey status required for scheduled trips; existing XAMPP databases must apply it with the documented backup-and-migration process before scheduling is available. The SQL snapshot/import path is an existing legacy setup, not a turnkey guarantee that all application features work against a new database.

## Phase 2 authentication foundation

- `st_require_method('POST')` verifies a session-bound token and, when supplied, same-origin `Origin`/`Referer` before processing mutations. The shared browser fetch wrapper requests a token for JSON and multipart posts; native clients need a separately designed authentication and anti-CSRF strategy.
- PHP sessions use strict mode, cookie-only IDs, HttpOnly/SameSite=Lax cookies, and Secure on HTTPS. Authenticated sessions are persisted in the existing `sessions` table with a random validator whose hash is stored server-side. Ordinary sessions expire after 30 minutes idle or 12 hours absolute; Remember Me sessions expire after 7 days idle or 30 days absolute. Logout and password changes revoke persistent sessions. The integration test uses an isolated SQLite database and never alters the XAMPP database.
- Login is rate-limited per pseudonymous client address and identifier; personal and organization registration, reset, and verification-resend requests have per-address limits. Newly set passwords require at least 12 characters and are bounded to 1024 bytes; password-reset tokens are locked and atomically consumed once. Production requires an independent rate-limit HMAC secret of at least 32 characters. Expired keys are removed after 24 hours; the table stores HMAC keys rather than raw addresses or account identifiers.
- Account deletion requires a CSRF-protected authenticated request and the current password. Platform and organization administrators must transfer or remove their duties first; organizations are preserved. The deletion flow removes the account's cascaded private records and scrubs linked contact and group-member location details.
- Settings provides an authenticated account-data JSON download, including the user's own journeys/location records, contacts, messages, notifications, memberships, roles, consent events, and validated avatar file. It excludes credentials, session records, and other users' private records. Current Terms/Privacy versions are recorded when a user explicitly accepts them; account deletion anonymizes retained consent events by clearing their user link.
- The current map camera and browser-provided location are stored in per-user `sessionStorage` for the current tab only. The map requests browser permission on first use; SafariTrak does not receive this camera/location cache. Map tile requests go to the configured map provider. Signed-in map pages preserve the shared camera and do not refit it for page-specific markers; zoom controls and gestures are disabled.
- `st_input_string()` rejects malformed structured values with a field error rather than allowing array-to-string/type errors. Unhandled API exceptions are logged server-side and returned with a support reference, not SQL credentials or stack traces.
- Public login/registration pages render without DB setup; authentication requests require the MySQL migration ledger and current additive migrations.

## Data and deployment warnings

- Do not use the root MySQL account in production.
- Keep uploads and database backups outside publicly executable paths or protect them with server configuration.
- Existing schema foreign keys often cascade on user deletion. Review retention/legal needs before extending or invoking deletion behavior.
- Email, maps, push, SMS, wearable, and emergency dispatch integrations require real provider configuration and verification; a UI or adapter is not proof of a live integration.
