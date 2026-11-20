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

The optional local PHP configuration is loaded only when a process-level environment variable is not already set. This XAMPP development setup enables `SAFARITRAK_DEV_MODE=1`; local verification and password-reset links are returned only to loopback requests when the runtime environment is not production. Do not rely on this convenience for deployment email delivery. Configure Gmail API delivery with OAuth 2.0 by following the [Gmail API setup guide](docs/implementation/gmail-api-setup.md); do not use a Gmail app password for this integration. The `SAFARITRAK_MAIL_*` settings remain a compatibility fallback for other SMTP providers only when no Google OAuth mail settings are configured. Production must set `SAFARITRAK_ENV=production`, `SAFARITRAK_DEV_MODE=0`, and a random `SAFARITRAK_RATE_LIMIT_SECRET`.

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

All interactive road maps use the shared dark, multicolor CARTO/MapLibre style in [map-style.js](map-style.js), including legacy Leaflet map views whose markers and route controls are retained over the shared basemap. This keeps road colors, map labels, water, and land treatment consistent across Explore, trip details, group travel, and shared journeys. When no route or saved user-selected camera takes precedence, maps open to the Nairobi CBD reference view; cached device location is shown as a marker but does not silently replace the default camera. Explicit location controls and route fitting can still move the map. Google Maps is not used to render a basemap; the optional Google Maps JavaScript loader remains for Places autocomplete only. Configure `SAFARITRAK_GOOGLE_MAPS_API_KEY` in the ignored local config and enable/restrict the Places API as described in the setup section. Typed suggestions race Google Places against browser-side Open-Meteo and Photon geocoders; browser lookups are bounded to 12 seconds and stale searches are cancelled. An authenticated server geocoder remains the final fallback with bounded provider requests. Map camera and browser location are stored per user in `sessionStorage` for the current tab.

Explore uses the same full-canvas MapLibre road map as the other map-backed screens. The only map modes are the styled road map and its globe projection; satellite imagery is intentionally not offered. A pulsing HTML overlay marks the user's location. POI icons and labels are hidden to keep the view clean; administrative, road, and water labels remain. Explore requests browser location permission when the user chooses the location action. Wheel zoom and map zoom controls are disabled; panning and projection selection remain available. Other people appear only when their active trip is explicitly shared with the signed-in user through a confirmed trusted-contact relationship; only their latest point is returned, and older readings are labelled as last known. Explore refreshes shared locations every 15 seconds. Narrow screens use a compact map-first layout and a five-item bottom navigation.

MapLibre basemaps and CARTO tiles are external best-effort services. The Google Maps API key is only needed for Places autocomplete, not for rendering map tiles.

Authenticated pages with maps use the shared background map, with Messages and account-entry/recovery screens remaining intentionally map-free. The map stays dark and multicolor in both app themes: major roads are orange, secondary roads magenta, local roads slate-blue, and waterways cyan. Surrounding panels and controls continue to follow the selected light or dark theme. When panels are hidden for map focus, the top-left Back to panels control restores them. The dedicated public live-location page uses the same road style because the shared recipient needs to see the active trip. The standalone traveler Safety and platform SOS pages were removed; SOS tables and APIs remain, but there is no supported SOS UI flow.

The shared map and Explore open over Nairobi at city zoom on each page launch. Saved camera centers and cached device locations do not override that start position; users can explicitly locate themselves, select a location, or open a trip route to move the map.

Groups and Chats use full-screen map workspaces with centered navigation and floating glass sidebars. Chats can share a one-time location in a private conversation and show a contact's live trip pin only when that trip has been shared with the signed-in user; selecting shared pins fits them into the open center of the map.

## Account types, groups, and car hire

- Individual accounts can create persistent private groups, invite members with expiring links, and use member-only group chat. Persistent groups are separate from temporary group journeys; group-trip creation can invite confirmed contacts and members of selected persistent groups.
- The Groups page is a full-screen map workspace with a searchable circle directory, unread badges, member-only chat, and explicit location sharing. Migration `0015_persistent_group_reads.php` adds per-member read tracking; back up the local database and apply pending migrations before deploying this UI.
- Organization registration supports school and car-hire profiles. New profiles are pending platform review; existing organizations are retained as approved by migration `0007`.
- Approved school admins can create enrollment invitations for students, guardians, or staff. Enrollment requests stay pending until an admin approves them; only approved members are added to the organization’s traveler records.
- The Car Hire marketplace uses the full-screen Nairobi map with verified-provider listings, pickup price markers, date and category filters, and booking requests. Approved car-hire organizations use a fleet portal to manage listings, coordinates, pricing, availability, and booking approvals. Approvals hold vehicle dates; payments and final rental terms are handled directly with the provider.
- Migration `0016_rental_vehicle_pickup_coordinates.php` adds optional vehicle pickup coordinates; listings without a vehicle-specific point use their organization’s default pickup location. Apply it only after backing up the local database with `php backend\scripts\migrate.php status` followed by `php backend\scripts\migrate.php up`.
- SafariTrak does not process rental payments, provide insurance guarantees, or dispatch drivers.
- Migration `0007_accounts_groups_and_rentals.php` is additive but must be applied explicitly. Back up the local XAMPP database and run `php backend\scripts\migrate.php status` before `php backend\scripts\migrate.php up`. The app code in the repository does not change the database automatically.
- School transport routing, guardian-to-student permissions, car-hire payments, document verification, and driver operations are not included in this first vertical slice.

On map-backed pages, explicit map gestures and selected route/location actions move the map while floating panels remain usable. Explore route planning docks the From/To panel at the side and offers a matching restore action. My Trips focuses the selected route by collapsing filter/action panels, docking trip details at the lower left, and shifting the map camera into the unobstructed area; a trip-panels control restores the full layout. Google Places suggests locations while typing, and selected results appear on the map. For Explore routes, selecting either autocomplete suggestion sets its coordinates and begins lookup; pressing Enter selects the highlighted suggestion or first result. Driving directions race the browser-accessible OSRM service against the authenticated server endpoint. Routes render with a bright-red core and glow while the dark, multicolor basemap remains unchanged. Buttons use the map's arterial-road orange, with cyan and magenta accents across navigation and glass panels. Explore and Start Journey require a successful route preview before enabling car-trip actions. My trips requests and draws the selected trip's road route when no recorded GPS trace is available and shows route status, distance, and estimated duration. Directions are driving-only and not traffic-aware; route coordinates are sent to the routing provider. Migration `0008_planned_journeys.php` enables scheduled trips and must be applied explicitly after backing up the local XAMPP database. Google Maps traffic is not enabled, so the app does not display or imply live traffic.

Trusted-contact invitations are one-time links (seven-day expiry) shared by copy, email, or the device share sheet. No contact record is created from a phone-number match; sign-up and acceptance are required. The optional public journey link shows only the active trip's latest location, expires after one hour or when the trip ends, and can be revoked. Treat it as a bearer secret. The current public basemap endpoint is suitable for local development and evaluation, not a production availability guarantee. Before public deployment, select providers with suitable service plans, review usage terms and privacy implications, and configure endpoints without changing the trip authorization rules.

Settings provides an authenticated JSON export of account data and records explicit acceptance of the current Terms and Privacy versions. New registrations record acceptance transactionally; existing users must explicitly accept updated versions. Review these documents and synchronize their version constants before each policy release.

Phase 3 route-quality work is in progress. The shared UI status renderer now presents announced loading, empty, error, and offline states with retry actions for the notification dropdown and Messages. Conversation choices are keyboard-operable buttons, live messages are exposed as an accessible log, and the search/send controls have explicit labels. Map focus, autocomplete, trip-route preview, invitations, and short-lived location links are also implemented; remaining signed-in routes still need systematic responsive and keyboard testing before Phase 3 is complete.

Run `php backend\scripts\seed-demo-car-hire.php --apply` in local development to create a synthetic approved car-hire organization, admin, and sample vehicle. The script prints the generated password once; subsequent runs never reset or redisplay it. `backend\scripts\seed-demo-school.php --apply` creates the local school test accounts.
