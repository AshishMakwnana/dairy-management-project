# Phase 1 Review Follow Up

## Purpose

This guide records the remediation work identified after the Phase 1 implementation review. Green test, lint, and export results do not close these items because the current suites do not cover every approved API contract, migration rollback, response envelope, or blueprint requirement.

Agents must claim and complete one task from `docs/task-board.md` at a time. Do not combine the remediation tasks unless ownership is explicitly changed and the affected files do not overlap.

## Source precedence

Use these sources in order:

1. `docs/technical-decisions.md`
2. `docs/phase-1-api-contract.md`
3. `docs/phase-1-authorization-matrix.md`
4. This remediation guide
5. `projects assests/documents/DUDHO_Master_UI_UX_Development_Blueprint_v4.docx`

The master blueprint still contains historical flows. Do not silently implement or delete a conflicting flow. Record product scope decisions in the technical decision register and this phase document; update the API contract only when its endpoint or data behavior changes. Capture the decision in the blueprint consolidation notes before consolidating the master blueprint.

## Required remediation order

### P1-R01 Repair public UUID constraints and migration rollback

Priority: Critical

- Add a database-enforced unique constraint for `users.uuid`.
- Correct the Phase 1 user migration rollback so it removes only constraints that actually exist.
- Verify both a fresh migration and a full rollback on an isolated database.
- Add automated coverage proving duplicate public UUIDs are rejected and Phase 1 migrations can roll back.
- Preserve nullable legacy mobile fields and do not reintroduce a global user role.

Do not alter a migration already applied to shared or production-like databases without deciding whether the repository is still allowed to rewrite pre-release migrations. If it is not, add a corrective migration and document the upgrade path.

### P1-R02 Standardize API error envelopes

Priority: High

- Return `{ success, message, errors, meta }` for API authentication, authorization, not-found, throttling, validation, conflict, and unexpected-error responses.
- Do not expose exception messages, SQL details, secrets, OTPs, tokens, or token hashes.
- Keep non-API Laravel behavior unchanged.
- Add feature tests for representative `401`, `403`, `404`, `409`, `422`, and `429` envelopes.

### P1-R03 Complete authenticated profile and logout APIs

Priority: High

Implement the contracted endpoints:

- `GET /api/v1/auth/me`
- `PATCH /api/v1/auth/me`
- `POST /api/v1/auth/logout`

Requirements:

- Use Sanctum authentication.
- A user may update only approved profile fields.
- A non-null default dairy must belong to an active membership.
- Logout revokes only the current access token.
- Use Form Requests, API Resources where model-backed, thin controllers, policies or scoped membership checks, and proportionate feature tests.

### P1-R04 Complete dairy detail and update APIs

Priority: High

Implement the contracted endpoints:

- `GET /api/v1/dairies/{dairy}`
- `PATCH /api/v1/dairies/{dairy}`

Requirements:

- Resolve access through active membership and return `404` for out-of-scope dairies.
- Invoke `DairyPolicy` at the endpoint boundary.
- Keep mutation logic and transactions in the service layer.
- Do not accept client identity, owner, role, or authorization claims.
- Add tests for anonymous access, no membership, inactive membership, cross-dairy isolation, owner success, validation, and repeated safe updates.

### P1-R05 Reconcile and finish the Phase 1 mobile experience

Priority: High after P1-R03 and P1-R04

#### Approved screen boundary

Phase 1 is an owner-only mobile flow. The listed screen scope is approved by ADR 012 in `docs/technical-decisions.md`; the master blueprint's role-selection and full setup wizard are future-phase concepts where they exceed the current API.

| Blueprint screen | Phase 1 status | Current mobile experience |
|---|---|---|
| 01 Splash | Dedicated screen deferred | Expo's native launch splash remains; its generated artwork is retained until approved production assets exist. |
| 02 Language Selection | Included | English/Hindi switcher is available before authentication and persists by platform. It is not a separate route. |
| 03 Welcome / Introduction | Deferred | Login is the entry experience. |
| 04 Mobile Login | Included | Owner phone sign-in offers WhatsApp and SMS delivery choices. WhatsApp calls the configured internal gateway; SMS remains the local/test Mailpit path until a production SMS provider is configured. |
| 05 OTP Verification | Included | OTP verification is the second state of the login route and resends use the selected delivery channel. |
| 06 New User – Account Type | Deferred | Phase 1 mobile serves the owner flow only; no farmer/operator branch is exposed. |
| 07 Dairy Owner Personal Details | Deferred | Identity is established by verified phone; no owner-profile wizard is shown. |
| 08 Dairy Details | Included, contract-supported subset | Core dairy profile fields are submitted directly to the idempotent dairy-create API. |
| 09 Basic Dairy Setup / selection | Deferred | Setup selection is not collected in mobile; backend defaults and server state remain authoritative. |
| 10 Review & Create Dairy | Deferred | The core dairy form submits directly; retries preserve the same request UUID and payload. |
| 11 Dairy Setup Success | Dedicated screen deferred | A server-confirmed registration notice and the accessible setup checklist provide current confirmation. |
| 12 Dairy Owner Dashboard | Included as a shell | Dashboard metrics and collection summaries remain unavailable until returned by the API. |
| 13 Dairy Setup Checklist | Included, server-confirmed subset | Only dairy registration is complete; operational items remain unverified until the API persists their status. |

Do not expand the flow or reinterpret deferred screens as completed. Keep decisions consistent with ADR 012 and the API contract's existing limits.

At minimum address:

- Persist pre-authentication English/Hindi selection locally.
- Add logout backed by the server endpoint, then clear local storage.
- The documented SMS/WhatsApp selector is enabled by P1-R07. WhatsApp is backed by the configured internal gateway; do not enable an unconfigured delivery channel in production.
- Decide whether splash, welcome, account type, owner profile, setup selection, review, and success screens are required now or formally deferred.
- Do not invent dashboard metrics or checklist completion without server-confirmed data.
- Extract repeated buttons, inputs, headers, status badges, cards, and loading/error states into reusable components where behavior is shared.
- Replace the generated Expo artwork only when the approved D-shaped milk-drop/leaf production assets are available.
- Keep all customer-facing business text behind English/Hindi translation keys.

Add component or integration tests for login states, session restoration, logout, locale persistence, dairy navigation, API error mapping, and onboarding retry/idempotency behavior. P1-R05 is not complete until the provider and user-visible logout flows are exercised, transient logout failures are verified not to discard a usable local session, and English/Hindi web preview checks are performed at a narrow mobile viewport.

### P1-R06 Repeat Phase 1 acceptance

Priority: Final gate

Run after P1-R01 through P1-R05 are complete or explicitly deferred in approved documentation:

```powershell
cd "DUDHO-Laravel-WSL-Docker-Starter\backend"
vendor\bin\pint --test
php artisan test

cd "..\..\mobile"
npx tsc --noEmit
npm run test:unit
npx expo lint --no-cache
npx expo-doctor
npx expo export --platform web --output-dir dist
npx expo export --platform android --output-dir dist-android
```

Also verify:

- fresh migrations and rollback on an isolated database;
- Docker migrations and full backend tests;
- route list matches the Phase 1 API contract;
- API error envelopes match the contract;
- no generated export directories are staged;
- no secrets or environment files are staged;
- phone validation, OTP privacy, rate limiting, idempotency, token revocation, and dairy isolation;
- English and Hindi layout on the web preview and a physical Android device when available;
- app icon, adaptive icon, splash, monochrome icon, and favicon against approved production artwork.

## Review evidence recorded on 15 October 2026

- Laravel suite passed: 34 tests and 241 assertions.
- Laravel Pint passed.
- TypeScript, Expo lint, mobile unit tests, and Expo web export passed.
- Mobile unit tests passed as 10 individual tests; the sandbox blocked the parallel Node test runner with `spawn EPERM`.
- `git diff --check` passed.
- Fresh migrations passed on an isolated SQLite database.
- Full rollback failed because `users_uuid_unique` does not exist.
- The Laravel route list exposed only health, OTP send/verify, and dairy list/create.
- An unauthenticated dairy request returned Laravel's default `{"message":"Unauthenticated."}` instead of the approved error envelope.
- The configured launcher and adaptive foreground images were visually confirmed as generated Expo artwork rather than the approved DUDHO mark.
- A live web-preview screenshot could not be captured because no browser surface was available to the reviewing session; repeat visual QA during P1-R06.

## Generated artifacts

`mobile/dist-android/` is generated output and should not be committed. Remove it after local verification or add an appropriate ignore rule if that output directory remains the standard command target. Do not remove another agent's output while they are actively using it.
