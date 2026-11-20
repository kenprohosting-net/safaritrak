# SafariTrak application audit — Phase A

## Purpose and scope

This audit establishes a verified baseline against the supplied product brief before further feature work. It examines the repository architecture, documented flows, schema/migrations, current runtime, test results, and selected high-risk tracking paths. It is not a full penetration test, exhaustive review of every endpoint, production deployment review, or evidence that unavailable third-party integrations work.

The existing PHP/MySQL application and its URLs are retained. The audit makes no application-code, dependency, schema, or database changes.

## Current application

| Area | Verified state |
| --- | --- |
| Server/runtime | PHP-rendered pages and JSON APIs, PDO/MySQL, Apache/XAMPP on Windows; local runtime uses PHP 8.2 and MariaDB 10.4. |
| Repository shape | Approximately 45 root PHP routes, 86 backend API PHP files, 15 shared includes, 4 views, 41 JavaScript files, 9 versioned migrations, and 8 test PHP files including fixtures. |
| Data model | The checked-in schema defines 29 application tables; the configured migrated local database has 32 tables. The baseline and migrations through `0009` are applied locally. |
| Authentication and privacy foundation | Database-backed sessions, CSRF/origin checks, authentication rate limits, consent, user-scoped export, account deletion protections, and migration/restore coverage are present. This does not establish production deployment readiness. |
| Maps and journeys | Browser geolocation and journey/group tracking are present. Explore has a Google Maps path with a MapLibre fallback; route preview uses best-effort public routing. Personal position records store latitude, longitude, and speed; verified GPS accuracy and heading are not recorded. Position updates and views use polling rather than a verified native/background tracking service. |
| Groups and messaging | Persistent groups, member list, invitation flow, and member-only group chat exist. Persistent groups remain distinct from temporary group trips. Full role management, archive lifecycle, and trip-scoped chat/events are incomplete. |
| Organizations | Organization review and school enrollment, traveler, vehicle catalog, and booking-request foundations exist. Guardian-child access, school-bus routing, driver operations, rental payments, and fleet telematics are not established. |
| Safety and places | SOS data and backend endpoints remain, but the standalone Safety pages were removed and no supported SOS UI flow currently exists. No saved-place persistence was found. Provider-search results are not a saved-place feature. |
| Native/device features | No native Android app, verified background GPS, push/SMS integration, or device manager was found. Browser location permission and page-level geolocation do not provide those capabilities. |

## Confirmed defects and follow-up

| Priority | Finding | Evidence and impact | Roadmap |
| --- | --- | --- | --- |
| P1 | The inline sender in `tracking.php` posts to `backend/api/journey/update-position.php` (singular), while the implemented endpoint is `backend/api/journeys/update-position.php` (plural). The route file is absent; the separate `tracking.js` uses the plural path. | The inline tracking flow can fail to persist location updates. | Phase E: correct the sender and add a regression check for the URL and behavior. |
| P1 | Personal and group position-update APIs cast inputs directly to floats and only check for missing values. | Malformed input can become a numeric value, and coordinates outside latitude/longitude bounds are not rejected before storage or distance calculations. This is a tracking-data integrity issue. | Phase E: require finite numeric coordinates in range and test malformed, boundary, and out-of-range values. |
| P2 | Legacy `backend/api/journeys/position.php` joins `group_member_positions`, which does not appear in the checked-in schema or migrations. A repository search found no client reference to this route. | If the legacy endpoint is invoked against the current migrated schema, its query may fail. Its ownership and compatibility should be confirmed before removal or replacement. | Phase E: verify consumers and reconcile with current schema. |

## Product and operational gaps

- At the time of this Phase A audit, Google Maps and SMTP credentials were absent locally. Since then, Gmail API OAuth has been configured locally and Gmail accepted one synthetic test message on 2026-11-12. Inbox receipt and the remaining transactional workflows have not been independently verified; follow the [Gmail API setup guide](./gmail-api-setup.md) before relying on production delivery. The map fallback remains available.
- OSRM/CARTO and other map resources are external best-effort services. They are not an uptime commitment, live-traffic service, or offline-map capability.
- There is no verified SMS, push, emergency-service dispatch, trip chat, saved-place persistence, optional 3D globe, native Android/background-tracking flow, device manager, school-bus/guardian-child tracking, or connected live fleet/vehicle telematics.
- The SOS backend is not equivalent to a supported emergency workflow. Any later UI must clearly describe recipients and delivery outcomes, and must not imply emergency-service dispatch or monitoring without a tested integration.
- `docs/product/screen-catalog.md` is an aspirational coverage checklist. Its route and status labels previously overstated placeholders for persistent groups/chat and described SOS as a permanently removed decision. Those entries have been reconciled with current routes and the later planned SOS workflow; remaining rows are not proof that listed behavior is implemented.
- The current tests establish meaningful local foundations but do not prove production security, third-party availability, deployment configuration, or every browser/device flow.
- Local development verification/reset links and rate-limit overrides are now restricted to loopback requests in a non-production runtime; this guard has regression coverage. It does not replace production deployment configuration or a review of every endpoint authorization boundary.

## Validation evidence

- `composer test`: 442 checks passed (368 smoke, 50 security-foundation, 21 session, 3 map-privacy).
- `composer test:mysql-migrations`: passed using disposable databases.
- `composer test:mysql-live-backup`: backup/restore verified across all 32 tables in the configured local database; the source database was not migrated or modified by this check.
- PHP syntax: 170 first-party PHP files checked, no failures.
- JavaScript syntax: 41 first-party JavaScript files checked, no failures.
- Local HTTP smoke: `welcome.php`, `login.php`, and `signup.php` each returned HTTP 200.
- `git diff --check`: passed.
- An isolated browser flow previously exercised signup, verification, login, Explore, route preview, trip scheduling, activation, and history, then removed its test account/database. The primary local application database was preserved.

## Phase A exit and next checkpoint

The audit baseline and documentation reconciliation are complete. The confirmed P1 tracking defects remain open; no code was changed to avoid beginning the next implementation phase before review. Review this report, the [implementation roadmap](./roadmap.md), and the [test plan](./test-plan.md) before authorizing Phase B or any feature work. The roadmap prioritizes tracking-flow repair and coordinate validation in Phase E; if a production release is planned before then, treat those findings as release blockers.
