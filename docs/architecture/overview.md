# SafariTrak architecture (Phase 1)

## Decision record

**Keep native PHP and MySQL.** The repository is already a PHP-rendered application with a MySQL schema and JSON endpoints. Replacing it with a JavaScript SPA, Laravel, or an Android shell before stabilising its flows would add migration risk without advancing the current product. Existing journeys, contacts, messages, safety, organisation, and admin routes remain in place.

The Phase 1 shell is shared by the overview and roadmap screens through PHP view partials. Feature pages can migrate incrementally; they are not bulk-moved in this phase. The public entry, login, registration, and reset screens continue to be PHP routes. The app shell is responsive HTML/CSS/JavaScript; business rules remain on the PHP server.

## Current and target responsibility boundaries

| Responsibility | Current/Phase 1 location | Direction |
| --- | --- | --- |
| PHP page routes | Repository root (`index.php`, `start-journey.php`, etc.) | Preserve URLs; migrate by vertical slice only |
| Reusable authenticated shell | `backend/views/app-shell-start.php`, `backend/views/app-shell-end.php` | Share navigation, header, theme, and footer |
| Authentication/session | `backend/includes/session.php`, `backend/includes/auth-guard.php`, `backend/api/` | Centralise session validation, CSRF, expiry, revocation, and rate limits |
| Database connection | `backend/config/database.php` | Keep PDO and prepared statements; move schema evolution to versioned migrations |
| Environment configuration | `backend/config/env.php`, ignored `backend/config/env.local.php`, `.env.example` | Process environment takes precedence; local values stay untracked |
| Domain/API | `backend/api/` | Keep JSON conventions; extract business services as features are touched |
| Database schema | `backend/sql/schema.sql` and existing SQL patches | Treat as legacy baseline until a migration ledger is introduced |
| Tests | `tests/` | Keep database-independent smoke tests and isolated SQLite session lifecycle coverage; exercise MySQL migrations/restores on disposable databases |
| Design direction | `dashboard.css`, `dashboard.js`, `docs/design-system.md` | Dark/light violet shell; accessible controls and reduced motion |

## Request and client architecture

The website uses same-origin PHP sessions and HTML pages. Existing API calls are JSON over same-origin `fetch`; keep same-origin as the default and do not enable wildcard CORS. Mutating API methods require a session-bound CSRF token and matching origin when a browser Origin/Referer is supplied. The shared UI fetch wrapper obtains the token from `backend/api/csrf-token.php`. Enforce authorisation server-side for every user, trip, group, child, and organisation record.

Before a future Android client is built, define a versioned JSON API with a mobile-appropriate revocable token/session strategy, per-device expiry, refresh/revocation, rate limits, and audit records. Do not reuse browser session cookies as a native authentication design. The current same-origin browser API intentionally has no wildcard CORS. Document allowed origins explicitly; CORS is not an authorisation control.

For now, requests are handled by route scripts, shared includes, PDO, and JSON response helpers (`backend/includes/response.php`). Unhandled API errors are logged server-side with a request reference and return a generic JSON error. Authentication endpoints use database-backed pseudonymous rate-limit keys; configure a random `SAFARITRAK_RATE_LIMIT_SECRET` in production. Extract controllers/services only when a coherent feature is implemented; a speculative framework conversion is out of scope.

## Product and privacy boundaries

- A permanent group and a trip are different domain objects. Existing `group_journeys` are temporary group trips, not persistent groups.
- Membership must not silently enable continuous location access. Trip-scoped sharing needs visible scope, consent, revocation, and expiry.
- Stale or disagreeing locations are data-quality states, not proof of danger.
- SOS must be available independently of chat and must use the best available location without waiting for GPS corroboration. Do not claim emergency-service dispatch without a verified integration.
- School/guardian access must be scoped to authorised children and trips at the record level.
- Keep sensitive location history limited by purpose and role; define retention and deletion before adding new history tables.

See the [roadmap](../implementation/roadmap.md) and [screen catalog](../product/screen-catalog.md) for implementation boundaries.
