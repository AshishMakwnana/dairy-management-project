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
