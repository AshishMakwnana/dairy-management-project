# DUDHO Phase 2 Authorization Matrix

## Scope

This matrix covers the Phase 2 API contract for dairy setup readiness, master-data configuration, farmer management, versioned pricing configuration, and optional location enhancement. It assumes the identity model from Phase 1 remains intact:

- identity is separate from dairy membership
- dairy access is granted only by an active membership
- no global `users.role` exists
- every dairy-scoped route must resolve through an active membership before policy evaluation
- all Phase 2 endpoints (both read and mutation) remain owner-only until staff/operator roles are introduced in a later contract

## Role and state definitions

| Principal state | Meaning |
|---|---|
| Anonymous | No valid Sanctum token (Deny `401`) |
| No active target-dairy membership, inactive membership, nested cross-dairy resource, or membership mismatch | Caller has no active membership in target dairy, or resource belongs to another dairy (Deny as `404`) |
| Active in-scope non-owner member | Caller has active membership in target dairy, but lacks required role (Deny `403` on all Phase 2 endpoints) |
| Active dairy owner | Authenticated user has an active `owner` membership for the target dairy |
| Deferred roles | Staff, collection operator, and web admin remain outside the current contract |

## Endpoint matrix

| Endpoint | Anonymous | Authenticated without active membership | Active non-owner member | Active dairy owner | Notes |
|---|---|---|---|---|---|
| `GET /api/v1/dairies/{dairy}/setup-readiness` | Deny `401` | Deny as `404` | Deny `403` | Allow | Owner-only; reads only target dairy; no cross-dairy disclosure |
| `POST /api/v1/dairies/{dairy}/setup/complete` | Deny `401` | Deny as `404` | Deny `403` | Allow | Owner-only, readiness-validated, and idempotent; sets onboarding completion once |
| `GET /api/v1/dairies/{dairy}/milk-types` | Deny `401` | Deny as `404` | Deny `403` | Allow | Owner-only; list enabled milk types for target dairy |
| `POST /api/v1/dairies/{dairy}/milk-types` | Deny `401` | Deny as `404` | Deny `403` | Allow | Owner-only; requires `client_uuid` and duplicate validation |
| `PATCH /api/v1/dairies/{dairy}/milk-types/{milkType}` | Deny `401` | Deny as `404` | Deny `403` | Allow | Owner-only; reject updates that invalidate required setup or pricing |
| `GET /api/v1/dairies/{dairy}/collection-sessions` | Deny `401` | Deny as `404` | Deny `403` | Allow | Owner-only; ordered sessions for this dairy only |
| `POST /api/v1/dairies/{dairy}/collection-sessions` | Deny `401` | Deny as `404` | Deny `403` | Allow | Owner-only; validates time windows, unique code, ordering; `client_uuid` |
| `PATCH /api/v1/dairies/{dairy}/collection-sessions/{session}` | Deny `401` | Deny as `404` | Deny `403` | Allow | Owner-only; reject removal or invalidation of live sessions |
| `GET /api/v1/dairies/{dairy}/quality-parameters` | Deny `401` | Deny as `404` | Deny `403` | Allow | Owner-only; dairy-specific quality parameter view |
| `POST /api/v1/dairies/{dairy}/quality-parameters` | Deny `401` | Deny as `404` | Deny `403` | Allow | Owner-only; parameter configuration; `client_uuid` |
| `PATCH /api/v1/dairies/{dairy}/quality-parameters/{qualityParameter}` | Deny `401` | Deny as `404` | Deny `403` | Allow | Owner-only; reject disabling parameters required by pricing versions |
| `GET /api/v1/dairies/{dairy}/farmers` | Deny `401` | Deny as `404` | Deny `403` | Allow | Owner-only; filtered to active dairy membership |
| `POST /api/v1/dairies/{dairy}/farmers` | Deny `401` | Deny as `404` | Deny `403` | Allow | Owner-only; generates dairy farmer code; rejects duplicates; `client_uuid` |
| `GET /api/v1/dairies/{dairy}/farmers/{farmer}` | Deny `401` | Deny as `404` | Deny `403` | Allow | Owner-only; nested lookup scoped to target dairy; cross-dairy is `404` |
| `PATCH /api/v1/dairies/{dairy}/farmers/{farmer}` | Deny `401` | Deny as `404` | Deny `403` | Allow | Owner-only; profile updates and deactivation |
| `PATCH /api/v1/dairies/{dairy}/farmers/{farmer}/milk-types` | Deny `401` | Deny as `404` | Deny `403` | Allow | Owner-only; updates farmer-specific supply configuration |
| `GET /api/v1/dairies/{dairy}/pricing/versions` | Deny `401` | Deny as `404` | Deny `403` | Allow | Owner-only; lists pricing versions within dairy scope |
| `POST /api/v1/dairies/{dairy}/pricing/versions` | Deny `401` | Deny as `404` | Deny `403` | Allow | Owner-only; draft creation only; requires `client_uuid` |
| `GET /api/v1/dairies/{dairy}/pricing/versions/{pricingVersion}` | Deny `401` | Deny as `404` | Deny `403` | Allow | Owner-only; read-only details of one specific version |
| `PATCH /api/v1/dairies/{dairy}/pricing/versions/{pricingVersion}` | Deny `401` | Deny as `404` | Deny `403` | Allow | Owner-only; updates drafts only; active/historical are immutable; `client_uuid` |
| `POST /api/v1/dairies/{dairy}/pricing/versions/{pricingVersion}/test` | Deny `401` | Deny as `404` | Deny `403` | Allow | Owner-only; server-side calculation preview; creates no collection records |
| `POST /api/v1/dairies/{dairy}/pricing/versions/{pricingVersion}/activate` | Deny `401` | Deny as `404` | Deny `403` | Blocked | Blocked pending explicit product-owner approval |
| `GET /api/v1/dairies/{dairy}/pricing/versions/{pricingVersion}/history` | Deny `401` | Deny as `404` | Deny `403` | Allow | Owner-only; immutable lifecycle events and audit records for one version |
| `GET /api/v1/dairies/{dairy}/pricing/milk-types/{milkType}` | Deny `401` | Deny as `404` | Deny `403` | Allow | Owner-only; current active pricing configuration for one milk type |
| `GET /api/v1/dairies/{dairy}/location/suggestions` | Deny `401` | Deny as `404` | Deny `403` | Allow | Owner-only; read-only proxy to Google Places Autocomplete; no `client_uuid` |
| `POST /api/v1/dairies/{dairy}/location/reverse-geocode` | Deny `401` | Deny as `404` | Deny `403` | Allow | Owner-only; read-only proxy to reverse-geocode coordinates; no `client_uuid` |
| `PATCH /api/v1/dairies/{dairy}` | Deny `401` | Deny as `404` | Deny `403` | Allow | Owner-only; reuses existing dairy update endpoint to save confirmed location |

## Denial and disclosure behavior

- Missing or invalid authentication returns `401 Unauthorized`.
- No active target-dairy membership, inactive membership, nested cross-dairy resource, or membership mismatch resolves to `404 Not Found` to avoid leaking whether the dairy or resource exists.
- An authenticated active member lacking required permission (including all active non-owner members accessing Phase 2 endpoints) receives `403 Forbidden`.
- Current Phase 2 endpoints (both read and mutation) remain strictly owner-only. Staff and operator roles remain deferred.
- Validation failures return `422 Unprocessable Entity`; throttling returns `429 Too Many Requests`; idempotency collisions, duplicate records, or price-version overlap conflicts return `409 Conflict`.
- The API never exposes raw OTP codes, internal pricing formulas, or unrelated membership records.

## Policy enforcement checklist

1. Put all Phase 2 dairy routes behind `auth:sanctum`.
2. Resolve target resources through the authenticated principal's active membership before evaluating policy.
3. Enforce policy on the dairy, farmer, and pricing version at the endpoint boundary.
4. Reject any caller-provided `user_id`, `owner_id`, `dairy_id`, or role override that does not come from the authenticated membership context.
5. Ensure all updates are owner-only and use a transaction owned by the domain service.
6. Use `client_uuid` or equivalent idempotency for writes that could be retried or duplicated.
7. Treat pricing activation as a regulated operation: no prior approval, no activation; no silent recalculation of historical data.
8. Keep pricing version records immutable; a new version is created instead of editing the active one in place.

## Deferred policy decisions

- Staff and operator roles remain deferred.
- Web-admin or super-admin access remains outside the Phase 2 contract.
- Any future pricing method beyond the recommended fixed-rate method requires explicit policy review, calibration, and audit rules.
- The recommended fixed-rate MVP is pending approval, so the authorization matrix still blocks activation until the product owner records the rule set.
