# DUDHO AI Context

## Current objective

Phase 1 is formally closed. Plan Phase 2 scope and acceptance criteria as a separate task before starting implementation.

## Current state

- Laravel 13 application exists in `DUDHO-Laravel-WSL-Docker-Starter/backend`.
- Expo SDK 57, React Native 0.86, and TypeScript mobile application exist in `mobile`.
- Node 22.13.0 is the selected Windows runtime.
- Docker Engine and Compose run inside WSL2 Ubuntu.
- DUDHO Docker services build and start successfully.
- MySQL migrations have run.
- API health endpoint is available at `GET http://localhost:8082/api/v1/health`.
- Mobile startup screen checks the versioned Laravel health endpoint.
- Phase 1 identity, OTP, dairy membership, dairy settings, and configurable master-data migrations are applied to Docker MySQL; the master seeder is idempotent.
- `users.uuid` is database-unique through an additive corrective migration; the existing Docker database was upgraded in place. Fresh migration and full rollback are covered by isolated SQLite tests.
- API errors under `/api/*` use the contracted `{ success, message, errors, meta }` envelope for authentication, authorization, not-found, validation, conflict, throttling, and unexpected errors; unexpected exception details are not returned to clients.
- Authenticated profile retrieval/update and current-device logout endpoints are implemented. Profile changes are limited to name, English/Hindi preference, and a public default-dairy UUID backed by active membership; logout revokes only the presented Sanctum token.
- P1-R03 Docker validation passed: the full Laravel suite passed with 66 tests and 522 assertions, and the authenticated profile/logout routes were validated.
- Authenticated dairy detail and profile update endpoints are implemented with active-membership-scoped route binding, `DairyPolicy` checks, owner-only updates, strict profile-field validation, and service-owned transactional updates. Dairy detail returns the public owner summary and current persisted onboarding state; repeating identical profile updates does not touch the record.
- P1-R04 Docker validation passed with WSL2 Ubuntu and Docker Compose responsive: migrations are current; focused dairy tests passed (26 tests, 179 assertions); the full Laravel suite passed (66 tests, 522 assertions); the live health endpoint returned `status: ok`; and dairy routes use `auth:sanctum`.
- OTP send/verify endpoints are implemented with `+91` India-only validation, hashed challenges, phone/source rate limits, and encrypted token replay for client-UUID retries.
- Brand direction is approved as Concept 1: the blue D-shaped mark combining a milk drop and leaf. The master vector and production exports remain an asset-production step.
- Mobile foundation now uses Expo Router, shared blueprint-derived theme tokens, and typed English/Hindi translation keys.
- Mobile OTP sign-in is implemented against the Laravel API; native bearer sessions use Expo SecureStore and the web preview keeps session data in memory only.
- The non-sensitive English/Hindi selection is stored in SecureStore on Android/iOS and browser localStorage on web. Locale storage failures fall back to English and are surfaced in the login UI; web sessions and dairy-create drafts remain memory-only.
- The signed-in login state has a translated, accessible sign-out action. It posts to `/api/v1/auth/logout`; success and 401/403 clear local session data, while network, server, and malformed-response failures preserve the session and show translated retry guidance.
- Mobile dairy onboarding lists active dairies and creates a dairy through the authenticated API. Pending create payloads and `client_uuid` values stay in SecureStore until confirmed; the web preview keeps drafts in memory.
- Mobile owner dashboard and setup checklist are scoped to a dairy from the active-membership list. Metrics and operational checklist status remain explicitly unavailable until the API returns those values; only dairy registration is shown as confirmed.
- P1-R05 scope is documented in ADR 012 and the review follow-up: language, login/OTP, core dairy details, dashboard shell, and server-confirmed checklist are included; dedicated splash, welcome, account type, owner profile, setup selection, review, and success screens are deferred. SMS is the only mobile OTP option until a configured provider supports another channel.
- Laravel feature tests and TypeScript compilation pass.
- Expo web export passes.
- Root Git repository has an approved initial baseline commit.
- Master blueprint v4 includes the Phase 0 implementation record and has passed a 53-page visual inspection.
- P1-R06 final acceptance passed on 15 October 2026: host and Docker Laravel suites each passed (68 tests, 615 assertions); isolated fresh-migration/rollback and schema checks passed (2 tests/11 assertions and 7 tests/30 assertions); mobile TypeScript, unit/integration tests (12 Node tests and 17 Jest tests), lint, Expo Doctor (21/21), dependency tree, and web/Android exports passed. Route, security, migration, and live health checks passed. English/Hindi web visuals at 390×844 and physical Motorola Edge 50 Fusion validation passed. Production artwork remains a release limitation.
- Android Studio and SDK tools exist, but this Windows ARM64 host cannot run Google's supported Android emulator; the authorized Motorola Edge 50 Fusion was validated over USB on 15 October 2026.

## Important environment facts

| Item | Value |
|---|---|
| Backend stack directory | `DUDHO-Laravel-WSL-Docker-Starter` |
| Laravel directory | `DUDHO-Laravel-WSL-Docker-Starter/backend` |
| Mobile directory | `mobile` |
| Current master blueprint | `projects assests/documents/DUDHO_Master_UI_UX_Development_Blueprint_v4.docx` |
| API URL from Windows/browser | `http://localhost:8082/api/v1` |
| API URL from Android emulator on a supported host | `http://10.0.2.2:8082/api/v1` |
| Nginx host port | `8082` |
| Mailpit | `http://localhost:8025` |
| MySQL host port | `3307` |
| MySQL service host | `mysql:3306` |
| Redis service host | `redis:6379` |

Port 8080 must not be claimed or stopped; it belongs to the unrelated `client-development-web` container.

## Agreed scope

- Mobile owner workflows first.
- Web administration later, but plan its authorization and service reuse during architecture work.
- No physical dairy measurement device integration now.
- Manual Fat, SNF, Density, and other enabled quality values initially.
- Farmer and operator experiences follow later phases.

## Phase order

1. Phase 0: architecture, tooling, environment, conventions, contracts.
2. Phase 1: authentication, language, owner onboarding, dairy setup, dashboard shell.
3. Phase 2: farmer management and pricing configuration.
4. Phase 3: milk collection, quality entry, calculation, history, initial offline queue.
5. Phase 4: settlement and payment.
6. Later: farmer app, disputes, complete offline/sync UI, reports, notifications, staff/operator permissions, web administration, release hardening.

## Immediate next technical work

1. P1-R01 through P1-R06 are complete; Phase 1 is formally closed.
2. Plan Phase 2 scope and acceptance criteria as a separate task. No Phase 2 implementation has started.

## Known risks and unresolved decisions

- Production vector artwork and platform icon exports for the approved blue D-shaped mark are not yet available.
- SMS and WhatsApp providers are not selected.
- The Phase 1 database stores legacy baseline users with nullable mobile numbers; newly OTP-authenticated identities must have a normalized unique phone pair.
- Detailed pricing formulas and rounding rules are not approved.
- Emulator validation is unavailable on this Windows ARM64 host; the authorized Motorola Edge 50 Fusion was used for physical-device validation over USB.
- npm reports transitive advisories in the generated Expo/Metro/React Native tree. Forced automatic remediation is unsafe; track upstream-compatible releases.
- The approved Phase 1 mobile boundary is consolidated in ADR 012 and `docs/phase-1-review-follow-up.md`; the master blueprint still contains historical sections that require later document cleanup.

## Definition of done for an agent task

- Requested behavior is implemented.
- Relevant automated checks pass.
- No secrets are introduced.
- API/schema/decision documentation is updated if behavior changed.
- `docs/task-board.md` reflects the status.
- An unfinished task has a handoff entry.
