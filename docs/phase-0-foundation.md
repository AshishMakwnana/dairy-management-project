# Phase 0 Foundation

## Purpose

Phase 0 establishes a working Laravel API and React Native mobile foundation before authentication and dairy onboarding begin. It records the runtime boundaries, development environment, API convention, verification results, and deferred work.

## Status

Status: In progress  
Started: 9 October 2026

## Confirmed architecture

- The React Native mobile application runs on the Windows host outside Docker.
- The Laravel API runs in Docker Engine inside WSL2 Ubuntu.
- Nginx exposes the API on port 8082 because port 8080 is already assigned to an unrelated local development container.
- MySQL, Redis, Mailpit, the Laravel queue worker, and the scheduler run as Docker services.
- The Android emulator accesses the API through `http://10.0.2.2:8082/api/v1`.
- Laravel is the authority for authentication, authorization, pricing, and financial calculations.
- Quality values are entered manually in the initial product. Physical analyzer, Bluetooth, USB, and vendor SDK integration are deferred.
- The mobile application is the initial product priority. Laravel web administration is required later and must reuse the same services and authorization rules.

## Created applications

### Backend

- Framework: Laravel 13
- PHP target: 8.3
- API prefix: `/api/v1`
- Initial endpoint: `GET /api/v1/health`
- Local infrastructure: Nginx, MySQL 8.4, Redis 7, Mailpit, queue worker, scheduler

### Mobile

- Framework: Expo with React Native and TypeScript
- Navigation: Expo Router with file-based routes under `src/app/`
- Application name: DUDHO
- Android application ID: `com.dudho.mobile`
- Default Android emulator API URL: `http://10.0.2.2:8082/api/v1`
- API URL override: `EXPO_PUBLIC_API_URL`
- Initial screen reports whether the Laravel API is reachable.

## API response convention

The initial health response establishes the Phase 0 envelope:

```json
{
  "success": true,
  "message": "DUDHO API is available.",
  "data": {
    "service": "dudho-api",
    "status": "ok",
    "version": "v1"
  },
  "meta": {}
}
```

The detailed success, validation, authorization, conflict, and server-error contracts will be completed before Phase 1 endpoints are implemented.

## Verification record

| Check | Result |
|---|---|
| WSL2 Ubuntu available | Passed |
| Docker Engine available in Ubuntu | Passed |
| Docker Compose available in Ubuntu | Passed |
| Laravel project generated | Passed |
| Laravel feature tests | Passed |
| Expo TypeScript project generated | Passed |
| TypeScript compilation | Passed |
| Node runtime compatibility | Passed after selecting Node 22.13.0 |
| Docker services | Passed |
| MySQL migrations in Docker | Passed |
| HTTP health check through Nginx | Passed |
| Expo web bundle | Passed |
| Master blueprint v4 render and 53-page visual inspection | Passed |
| Android emulator | Not supported by the current Windows ARM64 host |
| Physical Android device | Passed on Motorola Edge 50 Fusion over USB with Expo Go; Metro bundle loaded and the app's health request returned HTTP 200 |
| Mobile-to-Docker API check | Passed in browser preview and on the physical Android device through USB reverse on ports 8081 and 8082 |

## Dependency audit note

The generated Expo dependency tree currently reports npm advisories in transitive Expo, Metro, and React Native tooling. npm proposes incompatible framework downgrades as its automatic remediation. No forced downgrade has been applied. This must be reviewed against upstream Expo releases before production release hardening.

## Phase 0 remaining work

- Final brand decision: approved Concept 1, the blue D-shaped mark combining a milk drop and leaf. Do not use the cartoon farmer/cow asset as the primary identity.
- Production asset plan: use the approved master vector for the icon-only app mark and horizontal wordmark; export the Android adaptive foreground with safe padding, solid adaptive background, monochrome mark, launcher icon, splash artwork, and web favicon. Keep light, dark, and monochrome logo variants consistent with the blueprint. Final vector artwork and exports remain pending.
- Add CI after the first baseline commit and remote repository are approved.
- Phase 1 API contract and authorization matrix are documented in `phase-1-api-contract.md` and `phase-1-authorization-matrix.md`.
- Apply the conventions in `docs/backend-architecture.md` to every Laravel feature.

## Documentation and agent coordination

- Root Git repository: initialized with an approved baseline commit.
- Current blueprint: `projects assests/documents/DUDHO_Master_UI_UX_Development_Blueprint_v4.docx`.
- AI onboarding: `AGENTS.md` and `docs/AI_CONTEXT.md`.
- Work coordination: `docs/task-board.md`; each agent claims one task and avoids overlapping file ownership.
- Continuity log: `docs/agent-handoff.md`.
- Technical decisions: `docs/technical-decisions.md`.

## Phase 0 exit criteria

Phase 0 is complete when the Docker stack is healthy, database migrations pass, the versioned API is reachable through Nginx, the Android app runs on a supported Android target, the mobile app confirms API connectivity, and the remaining Phase 1 contracts are documented.
