# SafariTrak test plan

## Routine checks

- `php tests/smoke.php` — database-independent checks for the planned-screen route metadata and shared app-shell files.
- `php -l path\to\file.php` — PHP syntax check; use on changed PHP files and escalate to all first-party PHP files when preparing a release.
- `node --check dashboard.js` — JavaScript syntax check.
- `git diff --check` — whitespace check.
- Local route smoke checks via XAMPP or `php -S`: root redirects to welcome; welcome/login/registration return 200 without a working database; unauthenticated planned screen redirects to login; a missing planned-screen ID returns 404 after authenticated routing is available.

## UI and route coverage acceptance checks

1. An unauthenticated visitor can open the site root, welcome, login, and registration routes without a working database.
2. Authenticated route guards remain server-side; the overview still queries the user's active/recent journeys and confirmed contacts.
3. Shell links lead to existing PHP routes or an explicit roadmap placeholder, never a dead `#` link.
4. Public login, registration, recovery, password-reset, and organization-registration fields have explicit labels independent of placeholder text.
5. Theme selection changes dark/light palette and works if browser storage is disabled.
6. On narrow screens closed navigation is absent from keyboard and accessibility navigation; opening moves focus into the drawer, Tab stays within it, and close button, Escape, and backdrop close it and restore focus to the opener.
7. On desktop resize, the mobile drawer and backdrop close and the full navigation returns to the accessibility tree.
8. Unknown placeholder IDs return 404; future routes state clearly that no feature is active there.
9. Development configuration secrets are not tracked; environment variables override local fallback values.

## Identity, session, migration, and privacy regression checks

- `composer test` runs the route smoke, security-foundation, database-backed session lifecycle, and map-privacy checks without using the application database.
- Security-foundation tests verify that development verification/reset links and relaxed rate limits are available only to loopback requests in development/testing, and remain disabled in production and for remote clients.
- `composer test:mysql-migrations` uses uniquely named loopback-only disposable databases to exercise backup, restore, migrations, and account deletion (including role protections and retained PII scrubbing). It cleans up the databases and dump after the run.
- `composer test:mysql-live-backup` verifies a temporary backup of the configured local XAMPP `safaritrak` database by restoring into a uniquely named disposable database and comparing every base table's definition and row count. It cleans up the dump and restore database and does not apply migrations or modify the source database.
- Browser checks cover map-panel interaction outlines, themes, reduced-motion behavior, map camera continuity between signed-in pages, first-use location permission, and disabled zoom controls/gestures.
- Account export and policy-consent acceptance are authenticated, versioned workflows covered by the migration/restore and security-foundation checks.

## Current browser acceptance checks

- `composer test` asserts the shared status-state renderer, conversation keyboard controls, message live-log semantics, labeled search/send controls, and offline notification messaging.
- On 2026-11-12, a synthetic signed-in account on an isolated database exercised the Explore, trips, trip planning, messages, groups, group trips, Places, trusted contacts, notifications, settings, and planned-screen views. Tested at 320px and 1280px; core pages were additionally checked at 390px, 768px, and 1280px. No horizontal overflow or PHP fatal errors remained after fixing the duplicate auth-guard include in the Groups → Group trips view.
- The signed-in navigation opened with focus on its close control, Escape restored focus to the opener, and resizing back to desktop restored the full navigation. The Explore MapLibre fallback loaded without a Google Maps key. Places displayed a bounded, actionable unavailable state because Google Places credentials were not configured; verify its successful result flow when a restricted key is available.
- Browser-test Messages in dark/light themes at desktop and narrow-mobile widths; confirm loading, empty, failure/retry, and offline states are announced and usable by keyboard/touch.
- Browser-test map-focus mode on non-Explore pages, return-to-panels behavior, location-marker visibility around floating panels, autocomplete keyboard selection, selected-location map markers, and red trip-route previews. Confirm walking/bus/motorbike trips are not presented as supported route profiles by the driving-only preview.
- The shared MapLibre background renders without a JavaScript visibility gate and starts loading immediately on authenticated app pages. Verify saved-camera continuity, per-user selected-journey route persistence/red rendering across the shared map and Explore, and current-location centering on the Account profile (including denied permission).
- The Account page presents a social-profile header and journey/trusted-contact counts while preserving profile editing, privacy, notification, consent, and account controls.
- The latest authenticated browser pass checked the Trusted Contacts split-panel layout at desktop and mobile sizes, Settings panels and all edit tabs, the group member-details dialog, and direct/group trip-photo preview/cancel. No photo was uploaded or sent and no membership action was submitted; owner-only membership actions still require an owner-role test account.
- Test trusted-contact lookup by SafariTrak username, no-details sign-up invitations, and copyable sign-up and temporary-journey links. Ensure a phone match alone does not add a contact and that signup plus explicit acceptance is required. Test optional live links against active/scheduled journeys, expiry, revoke, journey end, and missing location data.
- Validate both auth illustration aspect ratios in dark/light themes. Confirm Google Maps/Places suggestions and nearby results with an API key restricted to the local app referrer and required APIs. Confirm traffic is not displayed as a feature while no traffic provider is configured.
- Continue the signed-in route inventory for heading hierarchy, accessible control names, focus visibility, responsive overflow, and meaningful empty/error states; phase completion requires every data-driven route to have reviewed states and no known keyboard-only barriers.

## Open tracking acceptance checks

- Correct the position sender URL in `tracking.php` and verify that the active tracking page successfully posts to the implemented journey-position API.
- Validate finite numeric latitude/longitude values and geographic bounds in both personal and group update APIs; cover malformed input, boundary values, and out-of-range values.
- Confirm whether any client uses the legacy journey-position reader before reconciling its query with the migrated database schema.

## Gmail API email checks

- `composer test` builds a base64url multipart MIME message and checks the OAuth/Gmail endpoint wiring and local callback state validation without network access or live credentials.
- A synthetic test message was accepted by the configured Gmail API sender on 2026-11-12. Inbox receipt was not independently confirmed; verification, password, trusted-contact, and group-invitation flows remain to be tested with controlled recipients.
- With a dedicated test mailbox configured, run the local OAuth CLI and confirm it saves the refresh token only in ignored backend configuration and never in its browser response or console output. Group invitations are additionally limited to 10 attempts per account per hour.
- Send verification, password-reset/password-changed (both reset and settings), trusted-contact, and persistent-group invitation emails to controlled test recipients. Confirm Gmail delivery, HTML/plain-text alternatives, branded responsive layout, link expiry, and that API failures do not reveal account existence or credentials.
- Confirm mail rate limits reject bursts without database schema changes; check sanitized logs for status/error categories only.

## Remaining quality gates

Production readiness still requires end-to-end checks for registration/verification/login, trip sharing/revocation, messages, organisation isolation, mobile navigation, keyboard, and both themes. The live-backup test validates the local XAMPP database only: configure and rehearse encrypted off-site backups, retention, access control, and disaster recovery for any deployment. Also validate HTTPS, production secrets, tile-provider terms/limits, and the published Terms/Privacy versions before release.
