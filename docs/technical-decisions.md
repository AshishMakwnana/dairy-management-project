# DUDHO Technical Decision Register

## ADR 001 Mobile runtime

Status: Accepted  
Decision: Run the Expo React Native application on Windows outside Docker.  
Reason: Android SDK, emulator, ADB, Metro, and native builds integrate directly with the host operating system.

## ADR 002 Backend runtime

Status: Accepted  
Decision: Run Laravel, Nginx, MySQL, Redis, queue workers, the scheduler, and Mailpit in Docker Engine inside WSL2 Ubuntu.  
Reason: This gives the backend a reproducible Linux environment without requiring Docker Desktop.

## ADR 003 Mobile framework

Status: Accepted for the current scope  
Decision: Use Expo with TypeScript and development builds.  
Reason: There is no current physical dairy-device integration, and development builds preserve a path to native modules later.

## ADR 004 Physical dairy devices

Status: Deferred  
Decision: Begin with manual quality entry. Do not integrate an analyzer, weighing scale, Bluetooth device, USB device, or vendor SDK in the initial phases.  
Consequence: Collection design must retain a future input-source/provider boundary.

## ADR 005 Product delivery priority

Status: Accepted  
Decision: Build the mobile owner workflows first. Design web administration as a later phase.  
Consequence: The web application must later reuse the Laravel domain services, policies, terminology, and financial rules rather than duplicating them.

## ADR 006 Financial authority

Status: Accepted  
Decision: Laravel performs final pricing and financial calculations.  
Consequence: Mobile calculations are never accepted as authoritative settlement or payment values.

## ADR 007 API versioning

Status: Accepted  
Decision: Version the REST API under `/api/v1` and use a consistent response envelope.

## ADR 008 Dependency remediation

Status: Accepted  
Decision: Do not run `npm audit fix --force` against the generated Expo project.  
Reason: The proposed remediation downgrades Expo and React Native across incompatible major versions. Upstream-compatible updates will be reviewed instead.

## ADR 009 Laravel application architecture

Status: Accepted
Decision: Use thin versioned API controllers, dedicated Form Requests for meaningful input, policies for authorization, application/domain services for use cases and transactions, API Resources for model-backed responses, and explicit container bindings for meaningful interfaces. Use observers only for behavior that genuinely belongs to an Eloquent lifecycle event.
Reason: This keeps HTTP, validation, authorization, domain behavior, persistence, and side effects independently testable without creating empty layers mechanically.
Reference: `docs/backend-architecture.md`.

## ADR 010 Mobile navigation

Status: Accepted
Decision: Use Expo Router with file-based routes under `mobile/src/app/` for the React Native application.
Reason: The mobile application targets Android first while retaining shared web preview and deep-linkable route structure.
Consequence: Route screens and layouts live under `src/app`; shared components, localization, and theme tokens remain in their respective `src/` modules.

## ADR 011 Mobile session storage

Status: Accepted
Decision: Store mobile bearer sessions in Expo SecureStore on Android and iOS; keep web-preview sessions in memory only.
Reason: Authentication tokens must not be persisted in unencrypted browser storage, while native sessions should survive app restarts using platform-protected storage.
Consequence: Do not log or expose access tokens; clear locally expired sessions; handle secure-storage failures explicitly. Persist a pending dairy-create payload with its `client_uuid` in SecureStore until the API confirms success, so retries reuse the same request; keep this draft in memory only in web preview. Persist the non-sensitive English/Hindi locale in SecureStore on native and browser localStorage on web; web bearer sessions and dairy-create drafts remain memory-only.

## ADR 012 Phase 1 mobile screen boundary

Status: Accepted
Decision: Phase 1 mobile is an owner-only, contract-supported vertical slice: language choice, phone/OTP authentication, core dairy profile creation, the owner dashboard shell, and the server-confirmed setup checklist are in scope. The dedicated splash, welcome, account-type selection, owner-profile form, basic setup selection, onboarding review, and dedicated onboarding-success screens are deferred. SMS is the only channel exposed in the mobile UI until an enabled provider can deliver through another channel.
Reason: The approved Phase 1 API supports identity, dairy membership, core dairy creation, and persisted registration state, but does not support the additional role-selection, profile-wizard, operational setup, review, or success-screen data flows.
Consequence: Do not infer dashboard metrics, setup completion, or operational success. The current app may show a server-confirmed registration notice and the checklist, but must retain the onboarding `client_uuid` and request payload across retries. Keep generated Expo launch artwork until approved production assets are available.
