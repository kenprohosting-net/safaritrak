# Implementation roadmap

This roadmap is based on the [Phase A application audit](./application-audit.md) and the supplied product brief. It preserves the existing PHP/MySQL application and its URLs. Each phase is a reviewed vertical slice; do not start a later phase until the current checkpoint is reviewed.

## Cross-cutting foundation — transactional email

- Implemented Gmail API OAuth 2.0 transport, a loopback-only CLI authorization flow, branded HTML/plain-text templates, database-backed outbound rate limits, and verification/reset/security/trusted-contact/persistent-group invitation wiring.
- Local Google OAuth configuration is in place, and Gmail API accepted one synthetic test message on 2026-11-12. Inbox receipt and end-to-end testing of the remaining transactional flows are not yet confirmed; see the [Gmail API guide](./gmail-api-setup.md).

## Phase A — Audit and stability baseline (complete; awaiting review)

- Inventory the current architecture and feature state; separate implemented, partial, missing, and unverified behavior.
- Record the verified runtime, test baseline, configuration limits, and highest-priority defects in the application audit.
- Reconcile the roadmap and screen catalog with live routes and implemented functionality.
- No schema, runtime feature, or dependency changes in this audit checkpoint.

## Phase B — Responsive UI and route coverage (in progress; reviewed routes have remaining external-service and state coverage)

- Keep the existing shared shell, dark/light themes, public entry routes, and accessible navigation; finish route-by-route responsive, keyboard, and loading/empty/error/offline-state review. The shared mobile navigation now has explicit close, hidden/inert closed state, focus containment, Escape close, and focus restoration. Public account forms now label all fields independently of placeholders. The signed-in route review covered the principal trip, group, messaging, contact, settings, notification, and Places views at narrow and desktop sizes; further flows and external-provider success states remain unverified. A duplicate auth-guard include found in Group trips was fixed and regression-tested.
- Treat the screen catalog as a target and coverage checklist, not evidence that a feature is implemented.
- Verify map-provider fallback and map-dependent flows with and without optional Google Maps credentials. Keep a usable, accessible 2D experience as the default; a 3D globe remains optional.
- Exit when every existing data-driven route has documented owners, verified behavior, privacy boundaries, and reviewed narrow-screen and keyboard states.

## Phase C — Identity, privacy, and data foundation (in progress)

- Build on the existing session, CSRF/origin, rate-limit, migration, consent, account export/deletion, and backup/restore foundations; do not replace them wholesale.
- Local verification and password-reset links are now guarded by both a loopback client address and a non-production environment, and production does not receive development rate-limit overrides. Tests cover production and non-loopback requests.
- Reconcile deployment documentation with actual configuration and ensure local-only development conveniences cannot be mistaken for production email delivery.
- Close any discovered authorization, validation, retention, or migration gaps with additive migrations and isolated regression tests.
- Production backup retention, disaster recovery, HTTPS, and secret management still require deployment-specific verification.

## Phase D — Groups and memberships

- Preserve the existing persistent groups, member-only group chat, invitation links, organization groups, and distinct temporary group trips.
- Add role editing, safe archive/leave flows, membership lifecycle visibility, and relevant audit events where product policy is agreed.
- Keep group membership separate from trip participation and location sharing; each remains explicitly scoped and revocable.

## Phase E — Journeys, maps, and live tracking

- Fix the confirmed tracking-page endpoint-path defect and validate latitude/longitude as finite numeric values within geographic bounds in both personal and group update APIs.
- Reconcile the legacy journey-position reader with the migrated schema and establish whether any client still depends on it before removing or replacing it.
- Test trip planning, participant authorization, activation/end/revocation, position freshness, and public-link expiry end to end. Preserve the existing best-effort route preview without describing it as live traffic.
- Show accurate location freshness/quality; do not claim continuous background tracking. A native Android client and background GPS require a separate, explicitly scoped phase.

## Phase F — Trip communications and events

- Keep existing persistent group chat distinct from trip-scoped communication.
- Add trip chat, participant/system events, delivery/error states, and moderation controls only after membership, retention, and authorization rules are defined.
- SOS must remain independent of chat.

## Phase G — Safety workflow and saved places

- Implement a clear SOS user workflow over the retained backend only after confirming activation, cancellation/resolution, notification recipients, history, and operational expectations.
- Do not claim SMS, push, emergency-service dispatch, or response monitoring unless a real integration is configured, tested, and documented.
- Add private saved-place persistence and management; distinguish user-created places from provider search results and label provider coverage as unverified unless independently sourced.

## Phase H — Organization, school, and fleet workflows

- Preserve existing organization review, school enrollment, traveler, vehicle-catalog, and booking-request foundations.
- Define and verify guardian-child relationships, school routes/bus tracking, assignment-scoped access, driver operations, and retention before adding child or fleet location views.
- Payments, telematics, and rental fulfillment require separately selected and tested service integrations; do not imply they are present.

## Phase I — Native mobile and device management

- Decide on Android only after the browser API and privacy rules are stable.
- Specify per-device authentication/revocation, location permissions, foreground/background behavior, push, offline states, and supported-device testing before implementation.
- Do not describe browser geolocation as native background tracking or as a connected-device manager.

## Phase J — Release readiness

- Complete end-to-end authorization/privacy review, accessibility and performance testing, deployment configuration, provider terms/limits, backup/restore and disaster-recovery rehearsals, and incident procedures.
- Verify production email and any later safety notification integrations with explicitly configured credentials and test recipients.
- Publish current Terms/Privacy versions and verify the retention, export, and deletion behavior against them.

## Phase exit criteria

Implement one vertical slice at a time (schema/migration, API/business rule, UI, privacy rules, tests, and directly related documentation). Run targeted tests and exercise the real flow locally. Report implemented behavior separately from placeholders and external-service dependencies. Stop for review at each phase boundary unless the user explicitly authorizes proceeding.
