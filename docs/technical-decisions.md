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
Decision: Phase 1 mobile is an owner-only, contract-supported vertical slice: language choice, phone/OTP authentication, core dairy profile creation, the owner dashboard shell, and the server-confirmed setup checklist are in scope. The dedicated splash, welcome, account-type selection, owner-profile form, basic setup selection, onboarding review, and dedicated onboarding-success screens are deferred. The mobile login exposes the blueprint's WhatsApp/SMS selector. WhatsApp delivery uses the configured internal gateway; local/testing can route SMS to Mailpit while routing WhatsApp to that gateway. A production SMS option must not be enabled until a production SMS provider is configured.
Reason: The approved Phase 1 API supports identity, dairy membership, core dairy creation, and persisted registration state, but does not support the additional role-selection, profile-wizard, operational setup, review, or success-screen data flows.
Consequence: Do not infer dashboard metrics, setup completion, or operational success. The current app may show a server-confirmed registration notice and the checklist, but must retain the onboarding `client_uuid` and request payload across retries. The WhatsApp gateway generates and returns the delivery OTP to Laravel; Laravel validates and hashes it, remains authoritative for challenge expiry, attempts and verification, and never exposes the OTP through its public API. Gateway credentials stay in environment configuration and are never committed or returned to clients. Keep generated Expo launch artwork until approved production assets are available.

## ADR 013 Phase 2 pricing governance and MVP pricing method (pending approval)

Status: Pending product-owner approval
Decision: Use the recommended MVP pricing method as the only allowed pricing activation path until explicit product-owner approval is recorded. The recommended fixed-rate MVP is pending approval and requires explicit agreement on all of the following rules:

1. fixed rate per enabled milk type;
2. Cow and Buffalo configured independently;
3. quantity stored to 3 decimal places;
4. rate stored to 4 decimal places;
5. final amount rounded to 2 decimal places;
6. half-up rounding only on the final amount (`Amount = quantity × active rate`);
7. effective from the start of the selected date in the dairy timezone;
8. backdated effective dates are prohibited for the MVP; there is no privileged override in this phase;
9. no overlapping effective versions;
10. activated versions are immutable.

Reason: The blueprint and Phase 2 scope explicitly require that pricing precision, effective-date behavior, rounding, method set, and activation rules be approved before any pricing version is activated. The contract must reject unapproved pricing formulas and preserve immutability for activated versions.
Consequence: Draft and test endpoints remain available for review, but pricing activation is blocked until the product owner approves the complete rule set. The backend gate `pricing.activation_enabled`, sourced from `PRICING_ACTIVATION_ENABLED`, defaults to `false` and must be tested fail-closed; accepting this ADR does not itself change deployment configuration. Activated pricing history remains immutable. Any future quality-based or manual rate-chart method must define exact matching, boundary, rounding, and audit behavior before it can be enabled. The current API contract and authorization matrix reflect this pending state rather than treating the rules as accepted.

## ADR 014 Dairy-local farmer profiles

Status: Accepted
Decision: In Phase 2, the farmer API resource is a dairy-local `dairy_farmers` record containing the dairy-specific display name and normalized contact mobile. Do not create or reuse a global farmer business-profile record keyed by mobile. An optional future `user_id` link connects authentication identity without moving dairy-local profile data or granting membership.
Reason: Different dairies may know the same person by different names or contact details. Global mobile matching could leak another dairy's record or create cross-dairy update conflicts.
Consequence: Farmer uniqueness, search, route binding, updates, and API output are always scoped by `dairy_id`. The same person may have independent records in multiple dairies, and cross-dairy matching never returns or mutates another dairy's profile.
