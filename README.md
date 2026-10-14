# DUDHO Development Workspace

DUDHO is an Android-first dairy management platform. The mobile application runs outside Docker, while the Laravel API and supporting services run in Docker Engine inside WSL2 Ubuntu.

## Workspace layout

```text
DUDHO-Laravel-WSL-Docker-Starter/
  backend/       Laravel API
  docker/        PHP and Nginx definitions
  compose.yaml   Local backend services
mobile/          Expo React Native TypeScript application
docs/            Phase records and technical decisions
projects assests/ Product blueprint and source assets
```

## Backend

From WSL2 Ubuntu in `DUDHO-Laravel-WSL-Docker-Starter`:

```bash
docker compose up -d --build
docker compose exec app php artisan migrate
docker compose exec app php artisan test
```

API health: `http://localhost:8082/api/v1/health`  
Mailpit: `http://localhost:8025`

## Mobile

From Windows PowerShell in `mobile`:

```powershell
npm install
npm run web
```

The Android emulator uses `http://10.0.2.2:8082/api/v1` by default. Copy `.env.example` to `.env` only when an override is required.

This development computer uses Windows on ARM64, which Google does not currently support for Android Studio/emulator execution. Use the Expo web preview for daily UI work and a physical Android phone or Android Device Streaming for Android validation.

## Current phase

Phase 0 foundation is in progress. See `docs/phase-0-foundation.md` for the verification record and remaining exit criteria.

Backend feature structure, service-container binding rules, observer guidance, and testing expectations are defined in `docs/backend-architecture.md`.
