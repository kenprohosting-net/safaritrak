# Phase 1 test plan

## Checks established now

- `php tests/smoke.php` — database-independent checks for the planned-screen route metadata and shared app-shell files.
- `php -l path\to\file.php` — PHP syntax check; use on changed PHP files and escalate to all first-party PHP files when preparing a release.
- `node --check dashboard.js` — JavaScript syntax check.
- `git diff --check` — whitespace check.
- Local route smoke checks via XAMPP or `php -S`: root redirects to welcome; welcome/login/registration return 200 without a working database; unauthenticated planned screen redirects to login; a missing planned-screen ID returns 404 after authenticated routing is available.

## Acceptance checks for this checkpoint

1. An unauthenticated visitor can open the site root, welcome, login, and registration routes without a working database.
2. Authenticated route guards remain server-side; the overview still queries the user's active/recent journeys and confirmed contacts.
3. Shell links lead to existing PHP routes or an explicit roadmap placeholder, never a dead `#` link.
4. Theme selection changes dark/light palette and works if browser storage is disabled.
5. On narrow screens the navigation opens/closes with an accessible control and backdrop.
6. Unknown placeholder IDs return 404; future routes state clearly that no feature is active there.
7. Development configuration secrets are not tracked; environment variables override local fallback values.

## Phase 2 checks established

- `composer test` runs the route smoke, security-foundation, database-backed session lifecycle, and map-privacy checks without using the application database.
- `composer test:mysql-migrations` uses uniquely named loopback-only disposable databases to exercise backup, restore, migrations, and account deletion (including role protections and retained PII scrubbing). It cleans up the databases and dump after the run.
- `composer test:mysql-live-backup` verifies a temporary backup of the configured local XAMPP `safaritrak` database by restoring into a uniquely named disposable database and comparing every base table's definition and row count. It cleans up the dump and restore database and does not apply migrations or modify the source database.
- Browser checks cover map-panel interaction outlines, themes, reduced-motion behavior, map camera continuity between signed-in pages, first-use location permission, and disabled zoom controls/gestures.
- Account export and policy-consent acceptance are authenticated, versioned workflows covered by the migration/restore and security-foundation checks.

## Phase 3 checks in progress

- `composer test` asserts the shared status-state renderer, conversation keyboard controls, message live-log semantics, labeled search/send controls, and offline notification messaging.
- Browser-test Messages in dark/light themes at desktop and narrow-mobile widths; confirm loading, empty, failure/retry, and offline states are announced and usable by keyboard/touch.
- Browser-test map-focus mode on non-Explore pages, return-to-panels behavior, location-marker visibility around floating panels, autocomplete keyboard selection, selected-location map markers, and red trip-route previews. Confirm walking/bus/motorbike trips are not presented as supported route profiles by the driving-only preview.
- Test trusted-contact invitations by copy, email, and message/share sheet; ensure a phone match alone does not add a contact and that signup plus explicit acceptance is required. Test optional live links against an active journey, expiry, revoke, journey end, and missing location data.
- Validate both auth illustration aspect ratios in dark/light themes. Confirm Google Maps/Places suggestions and nearby results with an API key restricted to the local app referrer and required APIs. Confirm traffic is not displayed as a feature while no traffic provider is configured.
- Continue the signed-in route inventory for heading hierarchy, accessible control names, focus visibility, responsive overflow, and meaningful empty/error states; phase completion requires every data-driven route to have reviewed states and no known keyboard-only barriers.

## Remaining quality gates

Production readiness still requires end-to-end checks for registration/verification/login, trip sharing/revocation, messages, organisation isolation, mobile navigation, keyboard, and both themes. The live-backup test validates the local XAMPP database only: configure and rehearse encrypted off-site backups, retention, access control, and disaster recovery for any deployment. Also validate HTTPS, production secrets, tile-provider terms/limits, and the published Terms/Privacy versions before release.
