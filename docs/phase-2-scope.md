# DUDHO Phase 2 Scope and Delivery Plan

## Purpose

Phase 2 turns a registered dairy into an operationally configurable dairy. It adds owner-managed milk, quality, and collection-session settings; farmer registration and dairy relationships; and versioned pricing configuration. Laravel remains authoritative for authorization, pricing, and final financial values.

This phase does not begin milk collection, settlement, or payment. Those workflows depend on the configuration produced here and remain in later phases.

## Product outcome

At the end of Phase 2, an active dairy owner can:

- configure which master milk types the dairy accepts;
- configure collection sessions without hard-coding Morning and Evening in the client;
- enable quality parameters and classify each as required, record only, rate calculation, or not used;
- register and manage farmers within the selected dairy;
- configure, test, explicitly activate, and review effective-dated pricing versions for each enabled milk type; and
- see server-confirmed setup readiness in the mobile checklist.

The app must continue to work when Google location services are unavailable. Google-assisted dairy location is an enhancement, not a prerequisite for pricing or farmer management.

## Accepted constraints

- Identity remains separate from dairy membership and farmer relationships. Do not add `users.role`.
- Every dairy-scoped route resolves through an active membership before applying a policy.
- Phase 2 mutations are owner-only. Staff and operator permissions remain deferred.
- Dairy milk types, sessions, and quality inputs are configuration-driven.
- Quantities, quality values, rates, thresholds, and money use decimal database columns and decimal-string API fields.
- Pricing configurations are versioned by effective date. Activated historical versions are immutable.
- Later collections will store the applied pricing version and calculated values; changing pricing must never recalculate history.
- Laravel performs authoritative pricing tests and calculations. Mobile previews display server results.
- Initial quality entry remains manual. Analyzer, Bluetooth, USB, and vendor SDK integration remains deferred.
- Harmful retried mutations use a client UUID or equivalent idempotency rule.

## Scope boundary

### Included

1. Dairy configuration
   - measurement unit;
   - enabled milk types;
   - enabled collection sessions, including name, time window, ordering, and status;
   - enabled quality parameters, usage, required state, ordering, and supported decimal metadata;
   - setup-readiness response derived from persisted server state.

2. Farmer management
   - dairy-scoped farmer list, search, and active/inactive filters;
   - farmer creation with name, normalized Indian mobile, optional village or area, and enabled milk types;
   - collision-safe, server-generated dairy-specific farmer code;
   - farmer profile and permitted updates;
   - inactive status without deleting operational history;
   - later authentication linkage without converting the farmer into a global role.

3. Pricing configuration
   - pricing readiness per enabled milk type;
   - draft configuration;
   - parameter usage captured within the pricing version;
   - effective date and version history;
   - server-side test calculation that creates no collection;
   - explicit activation;
   - immutable activated versions and a new-version update flow.

4. Mobile owner experience
   - Dairy Setup entry from the dashboard checklist;
   - Milk Types, Collection Sessions, and Quality Parameters screens;
   - farmer list, add, success, and profile screens;
   - pricing overview, guided configuration, test, review, activation, details, and history screens;
   - English and Hindi translation keys and accessible touch targets.

5. Optional Phase 2 enhancement
   - Google-assisted dairy location search or current-location lookup;
   - confirmed map pin plus structured village or city, district, state, PIN, latitude, longitude, place ID, and source;
   - manual fallback and explicit location permission only when requested by the user.

### Deferred

- Milk collection and local offline collection queue, which begin in Phase 3.
- Settlement and payment, which begin in Phase 4.
- Farmer-facing application screens.
- Staff and collection-operator permissions.
- File import for rate charts.
- Physical analyzer or device integration.
- Background location collection.
- Deleting farmers, pricing history, or other operational records.

## Product decisions required before pricing activation

The blueprint intentionally does not define a universal FAT, SNF, or density formula. Before a quality-based calculator is implemented, the product owner must approve:

1. The Phase 2 MVP method set. The recommended first complete vertical slice is Fixed Rate, followed by Manual Rate Chart. Formula-based methods should follow only after their exact rules are approved.
2. The input and output precision for quantity, each quality parameter, rate, and milk value.
3. The rounding mode and when rounding occurs.
4. Whether rate-chart matching is exact, range-based, stepped, or interpolated.
5. Boundary behavior for values below or above the configured chart.
6. Whether a future-dated version may overlap another version and how an already scheduled version is replaced or cancelled.
7. Whether backdated activation is forbidden or requires an explicit privileged correction workflow.
8. Whether one dairy-wide pricing method can differ by milk type. The current recommendation is independent pricing per enabled milk type.

Until these decisions are recorded, dairy configuration and farmer management may proceed, and pricing can support drafts, but no unapproved quality-based formula may be activated.

## Proposed data model

The final migration names and columns belong to the implementation task, but the model must preserve these boundaries.

### Existing tables to extend through services

- `dairy_settings`: stable dairy-wide defaults and a settings version.
- `dairy_milk_types`: enabled master milk types per dairy.
- `collection_sessions`: configurable dairy session records.
- `dairy_quality_parameters`: dairy-wide enablement, usage, requirement, and ordering.

Configuration updates must be transactional. A change that would leave no enabled milk type or no enabled collection session is rejected. A quality parameter required by an active or scheduled pricing version cannot be disabled without first creating a valid replacement pricing version.

### Farmer tables

- `farmers`: farmer business profile with UUID, name, normalized contact mobile, and optional linked `user_id` when the same identity later authenticates.
- `dairy_farmers`: dairy relationship with UUID, dairy-specific farmer code, status, village or area, joined date, creator, and client creation UUID/hash.
- `dairy_farmer_milk_types`: enabled milk types supplied by that farmer within that dairy relationship.

The same authenticated user may later have farmer relationships with more than one dairy. Dairy owners only see farmer relationships belonging to dairies where they have active access.

### Pricing tables

- `pricing_configurations`: UUID, dairy, milk type, version, method, status, effective range, precision/rounding snapshot, creator, activation metadata, and immutable activated payload hash.
- `pricing_parameter_usages`: the quality parameters and usage required by one pricing version.
- `pricing_rules` or method-specific child records: decimal inputs, ranges, and outputs for the approved method. Do not store executable expressions supplied by clients.
- `pricing_activation_requests`: idempotency and audit data where a separate record is useful.
- `audit_events`: actor, dairy, action, resource, timestamp, and safe before/after metadata for activation and later correction workflows.

Draft records may be replaced before activation. Activated records are never edited in place; updating pricing creates a new version.

## API boundary

All routes use `/api/v1`, `auth:sanctum`, active-membership-scoped dairy binding, policy checks, Form Requests, services for transactions, and API Resources for model output.

### Dairy configuration

- `GET /dairies/{dairy}/setup`
- `PATCH /dairies/{dairy}/settings`
- `GET /dairies/{dairy}/milk-types`
- `PUT /dairies/{dairy}/milk-types`
- `GET /dairies/{dairy}/collection-sessions`
- `PUT /dairies/{dairy}/collection-sessions`
- `GET /dairies/{dairy}/quality-parameters`
- `PUT /dairies/{dairy}/quality-parameters`

### Farmers

- `GET /dairies/{dairy}/farmers`
- `POST /dairies/{dairy}/farmers`
- `GET /dairies/{dairy}/farmers/{farmer}`
- `PATCH /dairies/{dairy}/farmers/{farmer}`

Farmer creation uses `client_uuid`. List responses are paginated and searchable by name, normalized mobile, or farmer code. Nested farmer binding must never resolve a relationship from another dairy.

### Pricing

- `GET /dairies/{dairy}/pricing`
- `POST /dairies/{dairy}/pricing/drafts`
- `GET /dairies/{dairy}/pricing/{pricingConfiguration}`
- `PATCH /dairies/{dairy}/pricing/{pricingConfiguration}` for drafts only
- `POST /dairies/{dairy}/pricing/{pricingConfiguration}/test`
- `POST /dairies/{dairy}/pricing/{pricingConfiguration}/activate`
- `GET /dairies/{dairy}/pricing/history`

Activation is explicit, idempotent, locked against concurrent activation, and rejects incomplete rules, invalid effective dates, disabled milk types, or missing required parameters.

### Assisted dairy location

- `GET /location/suggestions` through an authenticated, rate-limited Laravel proxy when Places search is selected;
- `POST /location/reverse-geocode` for user-approved coordinates; and
- the existing dairy update endpoint accepts confirmed structured fields plus decimal latitude and longitude after its contract is updated.

Google credentials remain server-side, restricted, uncommitted, and protected by quotas. The client must not relay arbitrary provider requests through the proxy.

## Authorization matrix

| Principal | Read dairy configuration | Change dairy configuration | Read farmers | Manage farmers | Read pricing | Draft test or activate pricing |
|---|---:|---:|---:|---:|---:|---:|
| Anonymous | Deny 401 | Deny 401 | Deny 401 | Deny 401 | Deny 401 | Deny 401 |
| Authenticated without active target-dairy membership | Hide as 404 | Hide as 404 | Hide as 404 | Hide as 404 | Hide as 404 | Hide as 404 |
| Active non-owner member | Allow when a later role contract authorizes it; otherwise deny 403 | Deny 403 | Allow only after a later role contract | Deny 403 | Allow only after a later role contract | Deny 403 |
| Active owner | Allow | Allow | Allow | Allow | Allow | Allow |
| Inactive user, dairy, or membership | Deny | Deny | Deny | Deny | Deny | Deny |

Phase 2 exposes owner flows only, so no new staff/operator capability is implied by the table.

## Validation and history rules

- Master milk and quality identifiers must be active and server-recognized.
- At least one milk type and one collection session remain enabled.
- Session codes are unique within a dairy; start/end-time behavior is explicitly validated.
- Quality values and rate-rule values are decimal strings within approved ranges and scales.
- Farmer phone numbers are normalized; duplicate handling does not disclose another dairy's records.
- Farmer codes are server-generated and unique within the dairy.
- Pricing method and rule payloads use a server-owned schema; arbitrary formulas or code are rejected.
- Only drafts are mutable.
- Activation records actor, time, effective date, version, and safe audit metadata.
- A future collection must reference the applied pricing version and store its calculated rate and value snapshot.

## Delivery tasks

1. `P2-00` Define Phase 2 scope, decisions, boundaries, and acceptance criteria.
2. `P2-01` Approve pricing precision, rounding, effective-date, and MVP method rules; publish the Phase 2 API contract and detailed authorization matrix.
3. `P2-02` Implement dairy milk-type, collection-session, quality-parameter, and setup-readiness APIs.
4. `P2-03` Implement the mobile Dairy Setup configuration screens and server-confirmed checklist.
5. `P2-04` Implement farmer schema, services, policies, APIs, idempotency, and tests.
6. `P2-05` Implement mobile farmer list, registration, success, and profile flows.
7. `P2-06` Implement versioned pricing schema, fixed-rate calculator, testing, activation, history, audit, and tests.
8. `P2-07` Implement the mobile pricing overview and guided configuration flow.
9. `P2-08` Add the approved quality-based pricing method or manual rate-chart method after its rules are accepted.
10. `P2-09` Add Google-assisted dairy location with manual fallback; this may proceed independently after its API/privacy contract is approved.
11. `P2-10` Run Phase 2 acceptance, security, migration, pricing-integrity, and physical-device checks.

Only one task is active at a time unless ownership and touched files are explicitly non-overlapping.

## Acceptance criteria

### Dairy configuration

- Owner can read and update only their active dairy's settings.
- Cross-dairy UUIDs do not disclose records.
- Milk, session, and quality configuration is data-driven and returned in stable order.
- Invalid updates roll back atomically and cannot leave the dairy unusable.
- Mobile checklist status comes from persisted server state.

### Farmers

- Owner can create, list, search, view, update, and deactivate dairy farmers.
- Retrying the same create request cannot create a duplicate relationship.
- The farmer identity model supports later app authentication without a global role.
- One dairy cannot read or mutate another dairy's farmer relationship.
- Farmer removal does not erase operational history.

### Pricing

- Each enabled milk type reports configured or not-configured readiness.
- Server rejects unsupported methods and incomplete or invalid rule payloads.
- A test returns decimal-string rate and milk value without creating a collection.
- Activation is explicit, idempotent, authorized, audited, and concurrency-safe.
- Updating active pricing creates a new version and never changes the prior version.
- Effective-date resolution selects at most one applicable version.
- Automated tests cover decimal precision, rounding, boundaries, version selection, dairy isolation, and immutability.

### Mobile and release quality

- English and Hindi screens pass narrow-width visual checks.
- TypeScript, lint, unit/integration tests, Expo Doctor, and Android/web exports pass.
- Laravel focused and complete suites pass on host and Docker.
- Fresh migration, rollback, upgrade, route, policy, API-envelope, and secret checks pass.
- Physical Android validation covers configuration, farmer registration, pricing test, activation, and history.

## Immediate next action

Complete `P2-01` by recording the pricing product decisions and publishing the detailed Phase 2 API contract and authorization matrix. If those decisions are not yet available, begin `P2-02` only for dairy configuration endpoints that do not perform pricing calculations, while keeping pricing activation blocked.
