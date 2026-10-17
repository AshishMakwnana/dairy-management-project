# DUDHO Shared Task Board

Agents should claim one task by changing its status to `IN PROGRESS` and adding their name or identifier. Avoid simultaneous edits to the same files.

| ID | Phase | Task | Status | Owner | Dependencies |
|---|---|---|---|---|---|
| P0-01 | 0 | Generate Laravel application | DONE | Codex | None |
| P0-02 | 0 | Generate Expo React Native TypeScript application | DONE | Codex | None |
| P0-03 | 0 | Configure and verify Docker services | DONE | Codex | P0-01 |
| P0-04 | 0 | Add API health contract and mobile connectivity screen | DONE | Codex | P0-01, P0-02 |
| P0-05 | 0 | Establish project documentation and agent context | DONE | Codex | P0-01 to P0-04 |
| P0-06 | 0 | Approve final brand direction and production asset plan | DONE | Codex | Product decision |
| P0-07 | 0 | Validate Android build on phone or Device Streaming | DONE | Codex | Supported Android target |
| P0-08 | 0 | Define Phase 1 API contract | DONE | Codex | P0-05 |
| P0-09 | 0 | Define Phase 1 authorization matrix | DONE | Codex | P0-08 |
| P0-10 | 0 | Add CI after the first baseline commit and remote are approved | TODO | Unassigned | P0-05 |
| P0-11 | 0 | Establish Laravel application architecture and service-binding conventions | DONE | Codex | P0-05 |
| P1-01 | 1 | Install/configure Sanctum and authentication foundation | DONE | Codex | P0-08, P0-09 |
| P1-02 | 1 | Implement Phase 1 migrations and seed data | DONE | Codex | P0-08 |
| P1-03 | 1 | Implement OTP provider abstraction and local driver | DONE | Codex | P1-01 |
| P1-04 | 1 | Implement send and verify OTP endpoints | DONE | Codex | P1-01, P1-03 |
| P1-05 | 1 | Create mobile theme, localization, and navigation foundation | DONE | Codex | P0-06 |
| P1-06 | 1 | Implement mobile login and OTP vertical slice | DONE | Codex | P1-04, P1-05 |
| P1-07 | 1 | Implement dairy onboarding API and transaction | DONE | Codex | P1-02, P0-09 |
| P1-08 | 1 | Implement mobile dairy onboarding screens | DONE | Codex | P1-05, P1-07 |
| P1-09 | 1 | Implement owner dashboard shell and setup checklist | DONE | Codex | P1-07, P1-08 |
| P1-10 | 1 | Run Phase 1 acceptance and security tests | DONE | Codex | P1-01 to P1-09 |
| P1-R01 | 1 | Repair public user UUID constraint and Phase 1 migration rollback | DONE | Copilot | P1-02, post-implementation review |
| P1-R02 | 1 | Standardize API authentication, authorization, not-found, and server error envelopes | DONE | Copilot | P0-08, post-implementation review |
| P1-R03 | 1 | Implement contracted authenticated profile and logout endpoints | DONE | Copilot | P1-01, P1-R02 |
| P1-R04 | 1 | Implement contracted dairy detail and update endpoints with scoped authorization | DONE | Copilot | P1-07, P1-R02 |
| P1-R05 | 1 | Reconcile the Phase 1 mobile flow with the approved blueprint and complete authorized scope | DONE | Copilot (GPT-6 Luna) | P1-R03, P1-R04, product scope decision |
| P1-R06 | 1 | Repeat Phase 1 acceptance, security, migration, contract, and visual checks | DONE | Copilot (GPT-6 Luna) | P1-R01 to P1-R05 |
| P1-R07 | 1 | Add WhatsApp OTP gateway delivery and restore the SMS/WhatsApp mobile selector | DONE | Codex | P1-R06, gateway contract |
| P2-00 | 2 | Define Phase 2 scope, product decisions, API boundaries, authorization, and acceptance criteria | DONE | Codex | P1-R07 |
| P2-01 | 2 | Approve pricing rules and publish the detailed Phase 2 API contract and authorization matrix | IN PROGRESS | Copilot | P2-00, product decisions |
| P2-02 | 2 | Implement dairy milk-type, collection-session, quality-parameter, and setup-readiness APIs | TODO | Unassigned | P2-01 contract boundary |
| P2-03 | 2 | Implement mobile Dairy Setup configuration and server-confirmed checklist | TODO | Unassigned | P2-02 |
| P2-04 | 2 | Implement farmer schema, services, policies, APIs, idempotency, and tests | TODO | Unassigned | P2-01 |
| P2-05 | 2 | Implement mobile farmer management flows | TODO | Unassigned | P2-04 |
| P2-06 | 2 | Implement versioned fixed-rate pricing, testing, activation, history, audit, and tests | TODO | Unassigned | P2-01, P2-02 |
| P2-07 | 2 | Implement mobile pricing overview and guided configuration | TODO | Unassigned | P2-06 |
| P2-08 | 2 | Implement the approved quality-based or manual rate-chart pricing method | TODO | Unassigned | P2-01, P2-06, product rules |
| P2-09 | 2 | Add Google-assisted dairy location with confirmed pin and manual fallback | TODO | Unassigned | P2-01 location contract |
| P2-10 | 2 | Run Phase 2 acceptance, security, migration, pricing-integrity, and device checks | TODO | Unassigned | P2-02 to P2-09 or approved deferrals |

## Safe parallel work

The following work can be parallelized after contracts are approved:

- Backend migrations and mobile design-system foundations.
- OTP backend services and static mobile authentication UI.
- Backend feature tests and mobile component tests when file ownership does not overlap.

Do not parallelize competing edits to API contracts, migrations, shared response formats, or the same navigation files.
