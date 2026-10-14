# DUDHO Shared Task Board

Agents should claim one task by changing its status to `IN PROGRESS` and adding their name or identifier. Avoid simultaneous edits to the same files.

| ID | Phase | Task | Status | Owner | Dependencies |
|---|---|---|---|---|---|
| P0-01 | 0 | Generate Laravel application | DONE | Codex | None |
| P0-02 | 0 | Generate Expo React Native TypeScript application | DONE | Codex | None |
| P0-03 | 0 | Configure and verify Docker services | DONE | Codex | P0-01 |
| P0-04 | 0 | Add API health contract and mobile connectivity screen | DONE | Codex | P0-01, P0-02 |
| P0-05 | 0 | Establish project documentation and agent context | DONE | Codex | P0-01 to P0-04 |
| P0-06 | 0 | Approve final brand direction and production asset plan | TODO | Unassigned | Product decision |
| P0-07 | 0 | Validate Android build on phone or Device Streaming | TODO | Unassigned | Supported Android target |
| P0-08 | 0 | Define Phase 1 API contract | TODO | Unassigned | P0-05 |
| P0-09 | 0 | Define Phase 1 authorization matrix | TODO | Unassigned | P0-08 |
| P0-10 | 0 | Add CI after the first baseline commit and remote are approved | TODO | Unassigned | P0-05 |
| P0-11 | 0 | Establish Laravel application architecture and service-binding conventions | DONE | Codex | P0-05 |
| P1-01 | 1 | Install/configure Sanctum and authentication foundation | TODO | Unassigned | P0-08, P0-09 |
| P1-02 | 1 | Implement Phase 1 migrations and seed data | TODO | Unassigned | P0-08 |
| P1-03 | 1 | Implement OTP provider abstraction and local driver | TODO | Unassigned | P1-01 |
| P1-04 | 1 | Implement send and verify OTP endpoints | TODO | Unassigned | P1-01, P1-03 |
| P1-05 | 1 | Create mobile theme, localization, and navigation foundation | TODO | Unassigned | P0-06 |
| P1-06 | 1 | Implement mobile login and OTP vertical slice | TODO | Unassigned | P1-04, P1-05 |
| P1-07 | 1 | Implement dairy onboarding API and transaction | TODO | Unassigned | P1-02, P0-09 |
| P1-08 | 1 | Implement mobile dairy onboarding screens | TODO | Unassigned | P1-05, P1-07 |
| P1-09 | 1 | Implement owner dashboard shell and setup checklist | TODO | Unassigned | P1-07, P1-08 |
| P1-10 | 1 | Run Phase 1 acceptance and security tests | TODO | Unassigned | P1-01 to P1-09 |

## Safe parallel work

The following work can be parallelized after contracts are approved:

- Backend migrations and mobile design-system foundations.
- OTP backend services and static mobile authentication UI.
- Backend feature tests and mobile component tests when file ownership does not overlap.

Do not parallelize competing edits to API contracts, migrations, shared response formats, or the same navigation files.
