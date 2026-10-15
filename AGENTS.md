# DUDHO Agent Instructions

## Mission

Build DUDHO as an Android-first dairy management platform with a Laravel REST API, an Expo React Native mobile app, and a later Laravel web administration surface. Work phase by phase and keep implementation, tests, and documentation synchronized.

## Read first

Before changing code, read:

1. `docs/AI_CONTEXT.md`
2. `docs/task-board.md`
3. `docs/technical-decisions.md`
4. The relevant phase document under `docs/`

For Phase 1 remediation, also read `docs/phase-1-review-follow-up.md` before claiming any `P1-R*` task.

The current product blueprint is `projects assests/documents/DUDHO_Master_UI_UX_Development_Blueprint_v4.docx`. Version 3 is retained as the pre-implementation source. The blueprint contains historical and revised sections; where they conflict, the technical decision register and current phase documents take precedence until the master blueprint is consolidated.

## Runtime boundaries

- Mobile runs on Windows outside Docker from `mobile/`.
- Laravel and supporting services run in Docker Engine inside WSL2 Ubuntu from `DUDHO-Laravel-WSL-Docker-Starter/`.
- Do not add the mobile app to Docker Compose.
- Do not install a global Laravel installer or global React Native CLI.
- API base path is `/api/v1`.
- Local API port is `8082`; port `8080` belongs to an unrelated local project.
- Mailpit is on `8025`; MySQL host access is on `3307`.

## Product rules that must not be weakened

- Laravel is authoritative for authentication, authorization, pricing, settlement, payment, and final financial values.
- Use decimal database types for quantity, quality, rates, and money. Never use floating point for stored financial values.
- Keep identity separate from dairy membership and role context. Do not add a global `users.role` column.
- Treat client-provided dairy identifiers as requested context, not proof of authorization.
- Retried mutations must use idempotency/client UUID rules where duplication would be harmful.
- Historical pricing and financial records must not be silently recalculated or overwritten.
- Financial corrections and voids require reasons and audit history.
- Initial quality entry is manual. Physical analyzer/Bluetooth/USB/vendor SDK integration is deferred, but future provider boundaries must remain possible.
- Initial UI languages are English and Hindi. Use translation keys rather than embedded business text when Phase 1 UI implementation starts.
- Web administration is required later, after the mobile core, and must reuse backend services and policies.

## Development workflow

- Work on one task from `docs/task-board.md` at a time unless tasks have explicitly non-overlapping ownership.
- Update the task status and handoff notes when stopping.
- Each feature change must include proportionate automated tests.
- Run the smallest relevant checks while developing and the complete phase checks before marking a task complete.
- Update the relevant phase document whenever an API contract, schema, architectural decision, or deferred requirement changes.
- Never commit `.env`, `.env.docker`, credentials, tokens, signing keys, or provider secrets.
- Do not run `npm audit fix --force`; current automatic remediation proposes incompatible Expo/React Native downgrades.

## Verification commands

### Backend host-level checks

```powershell
cd "DUDHO-Laravel-WSL-Docker-Starter\backend"
php artisan test
```

### Backend Docker checks

From WSL2 Ubuntu inside `DUDHO-Laravel-WSL-Docker-Starter`:

```bash
docker compose up -d
docker compose exec app php artisan migrate
docker compose exec app php artisan test
docker compose ps
```

Health endpoint: `http://localhost:8082/api/v1/health`

### Mobile checks

```powershell
cd mobile
npx tsc --noEmit
npx expo export --platform web --output-dir dist
```

This host is Windows ARM64. Google does not currently support the Android emulator on this host. Use the Expo web preview for routine UI work and a physical Android phone or Android Device Streaming for Android validation.

## Code organization

### Laravel

- Controllers: `backend/app/Http/Controllers/Api/V1/`
- Form Requests: `backend/app/Http/Requests/`
- API Resources: `backend/app/Http/Resources/`
- Domain services: `backend/app/Services/`
- Policies: `backend/app/Policies/`
- API routes: `backend/routes/api.php`
- Keep controllers thin and transactions inside the appropriate application/domain service.

### Mobile

- Application/navigation providers: `mobile/src/app/`
- Feature modules: `mobile/src/features/`
- Reusable visual components: `mobile/src/components/`
- API/storage/sync services: `mobile/src/services/`
- Local database: `mobile/src/database/`
- Localization: `mobile/src/i18n/`
- Shared types: `mobile/src/types/`
- Design tokens: `mobile/src/theme/`

Do not place all screens in one global screens directory and do not introduce arbitrary colors or spacing when a token exists.

## Handoff requirement

Before ending an unfinished task, add a short entry to `docs/agent-handoff.md` containing:

- task ID;
- files changed;
- checks run and results;
- open issue or next exact action;
- assumptions that another agent must preserve.
