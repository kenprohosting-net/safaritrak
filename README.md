# SafariTrak

SafariTrak is a native PHP/MySQL web application for trips, location sharing, communication, and safety. The current codebase remains PHP-rendered; it is not being migrated to React, Laravel, or a mobile wrapper as part of Phase 1.

## Quick start on Windows

1. Install XAMPP with PHP 8.1+ and MySQL/MariaDB. Start Apache and MySQL.
2. Put this repository at `C:\xampp\htdocs\safaritrak`.
3. Create the `safaritrak` database in phpMyAdmin or MySQL Workbench and import `backend/sql/schema.sql`.
4. Create `backend/config/env.local.php` from the example below and use your local database values. Never commit this file.
5. Install Composer dependencies from PowerShell: `composer install`.
6. Initialise and baseline the imported database, then apply additive migrations: `php backend\scripts\migrate.php init`, `php backend\scripts\migrate.php baseline`, `php backend\scripts\migrate.php status`, and `php backend\scripts\migrate.php up`.
7. Open `http://localhost/safaritrak/` or `http://localhost/safaritrak/welcome.php`. Use the sign-up flow to create an account.

If Apache returns 404 at that URL, confirm the project folder is actually `C:\xampp\htdocs\safaritrak` (not only on the Desktop), then restart Apache and reload. If you keep the Git checkout elsewhere, configure an Apache alias or virtual host to point at that checkout instead.

Example `backend/config/env.local.php` for a local XAMPP database:

```php
<?php
return [
    'SAFARITRAK_DB_HOST' => '127.0.0.1',
    'SAFARITRAK_DB_NAME' => 'safaritrak',
    'SAFARITRAK_DB_USER' => 'root',
    'SAFARITRAK_DB_PASS' => '',
    'SAFARITRAK_RATE_LIMIT_SECRET' => 'replace-with-a-random-local-secret',
    'SAFARITRAK_APP_URL' => 'http://localhost/safaritrak',
    'SAFARITRAK_DEV_MODE' => '1',
    // Enable Google Maps JavaScript API and Places API (New), and restrict this browser key by API and HTTP referrer.
    'SAFARITRAK_GOOGLE_MAPS_API_KEY' => 'your-restricted-browser-key',
];
```

The optional local PHP configuration is loaded only when a process-level environment variable is not already set. This XAMPP development setup enables `SAFARITRAK_DEV_MODE=1`, so signup and resend responses show a local verification link when SMTP is unavailable; never enable this on a public deployment. To test email delivery, configure `SAFARITRAK_MAIL_HOST`, `SAFARITRAK_MAIL_PORT`, `SAFARITRAK_MAIL_USERNAME`, `SAFARITRAK_MAIL_PASSWORD`, and (optionally) `SAFARITRAK_MAIL_FROM` in the ignored local configuration. For Gmail, use an app password, not the account password. Production must set `SAFARITRAK_DEV_MODE=0` and a random `SAFARITRAK_RATE_LIMIT_SECRET`.

## Commands

- `composer install` — install the PHPMailer dependency.
- `composer test` — run database-independent route, CSRF, rate-limit, session-lifecycle, and map-privacy checks.
- `composer test:mysql-migrations` — on a local MySQL/MariaDB server, create a synthetic legacy schema in uniquely named disposable databases, dump and restore it, run migrations, exercise account deletion and role protections, verify schema/data, and remove the temporary databases and dump. Set `SAFARITRAK_TEST_DB_HOST=127.0.0.1`, `SAFARITRAK_TEST_DB_USER`, and (when needed) `SAFARITRAK_TEST_DB_PASS` first. The test rejects non-loopback hosts and never connects to the app's configured database.
- `composer test:mysql-live-backup` — explicitly back up the configured loopback XAMPP `safaritrak` database to a temporary file, restore it to a uniquely named disposable database, and compare table definitions and row counts. It does not modify the app database or apply migrations; the dump and disposable database are removed at the end. This is local validation, not an off-site production backup or retention plan.
- `php -l path\to\file.php` — syntax-check a PHP file.
- `php backend\scripts\migrate.php --help` — print safe migration commands.
- `php backend\scripts\migrate.php status` — show schema migration state (requires configured database).
- `php -S 127.0.0.1:8000` — run PHP's local server from the repository root for basic public/redirect smoke checks. Use XAMPP for database-backed pages.
- `git diff --check` — check the patch for whitespace errors.
- Database-backed pages and authentication require the MySQL service and imported schema; static welcome, login, and registration pages remain viewable before local DB setup.

For architecture, database migration safety, design tokens, the screen inventory, and the phased implementation plan, see [docs/architecture/overview.md](docs/architecture/overview.md), [docs/implementation/windows-setup-and-database.md](docs/implementation/windows-setup-and-database.md), [docs/design-system.md](docs/design-system.md), [docs/product/screen-catalog.md](docs/product/screen-catalog.md), and [docs/implementation/roadmap.md](docs/implementation/roadmap.md).

## Trip maps

The former `live-tracking.php` route is retained only as a compatibility redirect. Owners open an active route from **My trips**; authorized shared journeys open in **Watch journey**. Maps on other pages are loaded only when a map feature is used.

Explore uses the Google Maps JavaScript API; selected trip maps use the local MapLibre/CARTO implementation. Location autocomplete and nearby Places use Places API (New). Configure `SAFARITRAK_GOOGLE_MAPS_API_KEY` in the ignored local config, enable billing plus the required Maps JavaScript and Places APIs in the key's Google Cloud project, and restrict the browser key to the app's HTTP referrers and required APIs. The dark style at [assets/google-maps-dark-style.json](assets/google-maps-dark-style.json) is also suitable for import into Google Maps Cloud-based Map Styling. The app uses inline map styles so the local style remains active when no Map ID is configured. Typed suggestions race Google Places against browser-side Open-Meteo and Photon geocoders; browser lookups are bounded to 12 seconds and stale searches are cancelled. An authenticated server geocoder remains the final fallback with bounded provider requests. Map camera and browser location are stored per user in `sessionStorage` for the current tab. Google receives map viewport requests, typed autocomplete text, and nearby-search coordinates directly from the browser; browser fallback geocoding sends the typed query directly to Open-Meteo and Photon.

Explore uses a full-canvas Google map by default, with Map and Satellite modes. A pulsing HTML overlay marks the user's location. POI icons and labels are hidden to keep the view clean; administrative, road, and water labels remain. Explore requests browser location permission when the user chooses the location action. Wheel zoom and map zoom controls are disabled; panning and mode selection remain available. Other people appear only when their active trip is explicitly shared with the signed-in user through a confirmed trusted-contact relationship; only their latest point is returned, and older readings are labelled as last known. Explore refreshes shared locations every 15 seconds. Narrow screens use a compact map-first layout and a five-item bottom navigation.

Google Maps cannot display production-quality tiles when the Google Cloud project has billing disabled or the key/API restrictions are incorrect. The browser may still render a map with Google's "For development purposes only" watermark in that state; enable billing and the listed APIs in Google Cloud Console to resolve it. This is a project configuration requirement and cannot be enabled by PHP or JavaScript code.

Most signed-in pages do not load a background map. MapLibre is loaded on demand when a trip route, location search, or map-specific view is selected; active shared trips retain their dedicated map. The dark palette uses `#090D16` for the app background, `#0B132B` for navigation, `#111827` for panels, green active navigation, and magenta active routes. The light palette uses `#F1F5F9`, white navigation/cards, and the same magenta route identity. All floating panels and forms retain a translucent glass surface in both themes. When panels are hidden for map focus, the top-left Back to panels control restores them. Login, registration, and recovery pages remain map-free; the dedicated public live-location page uses a map because the shared recipient needs to see the active trip. The standalone traveler Safety and platform SOS pages were removed; SOS tables and APIs remain, but there is no supported SOS UI flow.

## Account types, groups, and car hire

- Individual accounts can create persistent private groups, invite members with expiring links, and use member-only group chat. Persistent groups are separate from temporary group journeys; group-trip creation can invite confirmed contacts and members of selected persistent groups.
- Organization registration supports school and car-hire profiles. New profiles are pending platform review; existing organizations are retained as approved by migration `0007`.
- Approved school admins can create enrollment invitations for students, guardians, or staff. Enrollment requests stay pending until an admin approves them; only approved members are added to the organization’s traveler records.
- Approved car-hire organizations can publish vehicles and review booking requests. Customers see the daily-rate quote and dates; providers confirm availability. SafariTrak does not process rental payments, provide insurance guarantees, or dispatch drivers.
- Migration `0007_accounts_groups_and_rentals.php` is additive but must be applied explicitly. Back up the local XAMPP database and run `php backend\scripts\migrate.php status` before `php backend\scripts\migrate.php up`. The app code in the repository does not change the database automatically.
- School transport routing, guardian-to-student permissions, car-hire payments, document verification, and driver operations are not included in this first vertical slice.

On map-backed pages, map focus hides floating panels and the fixed top-left Back to panels control restores them. Explore route planning docks the From/To panel at the side and offers a matching restore action. Google Places suggests locations while typing, and selected results appear on the map. For Explore routes, selecting either autocomplete suggestion sets its coordinates and begins lookup; pressing Enter selects the highlighted suggestion or first result. Driving directions race the browser-accessible OSRM service against the authenticated server endpoint. Routes render as a magenta core with a wider glow and start/finish markers. Explore and Start Journey require a successful route preview before enabling car-trip actions. My trips requests and draws the selected trip's road route when no recorded GPS trace is available and shows route status, distance, and estimated duration. Directions are driving-only and not traffic-aware; route coordinates are sent to the routing provider. Migration `0008_planned_journeys.php` enables scheduled trips and must be applied explicitly after backing up the local XAMPP database. Google Maps traffic is not enabled, so the app does not display or imply live traffic.

Trusted-contact invitations are one-time links (seven-day expiry) shared by copy, email, or the device share sheet. No contact record is created from a phone-number match; sign-up and acceptance are required. The optional public journey link shows only the active trip's latest location, expires after one hour or when the trip ends, and can be revoked. Treat it as a bearer secret. The current public basemap endpoint is suitable for local development and evaluation, not a production availability guarantee. Before public deployment, select providers with suitable service plans, review usage terms and privacy implications, and configure endpoints without changing the trip authorization rules.

Settings provides an authenticated JSON export of account data and records explicit acceptance of the current Terms and Privacy versions. New registrations record acceptance transactionally; existing users must explicitly accept updated versions. Review these documents and synchronize their version constants before each policy release.

Phase 3 route-quality work is in progress. The shared UI status renderer now presents announced loading, empty, error, and offline states with retry actions for the notification dropdown and Messages. Conversation choices are keyboard-operable buttons, live messages are exposed as an accessible log, and the search/send controls have explicit labels. Map focus, autocomplete, trip-route preview, invitations, and short-lived location links are also implemented; remaining signed-in routes still need systematic responsive and keyboard testing before Phase 3 is complete.

Run `php backend\scripts\seed-demo-car-hire.php --apply` in local development to create a synthetic approved car-hire organization, admin, and sample vehicle. The script prints the generated password once; subsequent runs never reset or redisplay it. `backend\scripts\seed-demo-school.php --apply` creates the local school test accounts.
