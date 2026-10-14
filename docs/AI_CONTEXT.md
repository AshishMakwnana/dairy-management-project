# DUDHO AI Context

## Current objective

Complete Phase 0, then implement Phase 1 Authentication and Dairy Onboarding as tested vertical slices across Laravel and React Native.

## Current state

- Laravel 13 application exists in `DUDHO-Laravel-WSL-Docker-Starter/backend`.
- Expo SDK 57, React Native 0.86, and TypeScript mobile application exist in `mobile`.
- Node 22.13.0 is the selected Windows runtime.
- Docker Engine and Compose run inside WSL2 Ubuntu.
- DUDHO Docker services build and start successfully.
- MySQL migrations have run.
- API health endpoint is available at `GET http://localhost:8082/api/v1/health`.
- Mobile startup screen checks the versioned Laravel health endpoint.
- Laravel feature tests and TypeScript compilation pass.
- Expo web export passes.
- Root Git repository has an approved initial baseline commit.
- Master blueprint v4 includes the Phase 0 implementation record and has passed a 53-page visual inspection.
- Android Studio and SDK tools exist, but this host is Windows ARM64 and cannot run Google's supported Android emulator.

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

1. Define the complete Phase 1 API contract.
2. Install/configure Laravel Sanctum for mobile token authentication.
3. Implement Phase 1 migrations from the blueprint instead of expanding the default users table ad hoc.
4. Define OTP provider abstraction and local fake/mail driver.
5. Define the Phase 1 authorization matrix.
6. Create the React Native feature, navigation, theme, localization, API, secure-storage, and form-validation foundations.
7. Implement OTP end to end with automated tests.

## Known risks and unresolved decisions

- Final brand choice is not approved: the blueprint selects a blue D-shaped mark, while a production-named asset uses a cartoon farmer/cow mark.
- SMS and WhatsApp providers are not selected.
- Detailed pricing formulas and rounding rules are not approved.
- Android validation needs a physical phone or Android Device Streaming because the Windows ARM64 host cannot run the supported emulator.
- npm reports transitive advisories in the generated Expo/Metro/React Native tree. Forced automatic remediation is unsafe; track upstream-compatible releases.
- The blueprint contains obsolete and revised screen-numbering sections that must be consolidated.

## Definition of done for an agent task

- Requested behavior is implemented.
- Relevant automated checks pass.
- No secrets are introduced.
- API/schema/decision documentation is updated if behavior changed.
- `docs/task-board.md` reflects the status.
- An unfinished task has a handoff entry.
