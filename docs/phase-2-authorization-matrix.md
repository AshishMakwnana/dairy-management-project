# DUDHO Phase 2 Authorization Matrix

## Scope

This matrix covers the Phase 2 API contract for dairy setup readiness, master-data configuration, farmer management, versioned pricing configuration, and optional location enhancement. It assumes the identity model from Phase 1 remains intact:

- identity is separate from dairy membership
- dairy access is granted only by an active membership
- no global `users.role` exists
- every dairy-scoped route must resolve through an active membership before policy evaluation
- Phase 2 mutations remain owner-only until staff/operator roles are introduced in a later contract

## Role and state definitions

| Principal state | Meaning |
|---|---|
| Anonymous | No valid Sanctum token |
| Authenticated, no active dairy membership | identity valid but no access to the target dairy |
| Active dairy owner | Authenticated user has an active `owner` membership for the target dairy |
| Inactive or revoked membership | membership is disabled or not active; treat as no access |
| Deferred roles | staff/operator/web admin remain outside the current contract |

## Endpoint matrix

| Endpoint | Anonymous | Authenticated without membership | Active dairy owner | Notes |
|---|---|---|---|---|
| `GET /api/v1/dairies/{dairy}/setup-readiness` | Deny `401` | Deny as `404` | Allow | Reads only the target dairy; no cross-dairy disclosure |
| `GET /api/v1/dairies/{dairy}/milk-types` | Deny `401` | Deny as `404` | Allow | List only for active membership |
| `POST /api/v1/dairies/{dairy}/milk-types` | Deny `401` | Deny as `404` | Allow owner-only | Requires `client_uuid` and duplicate validation |
| `PATCH /api/v1/dairies/{dairy}/milk-types/{milkType}` | Deny `401` | Deny as `404` | Allow owner-only | Reject configuration that invalidates required setup or pricing references |
| `GET /api/v1/dairies/{dairy}/collection-sessions` | Deny `401` | Deny as `404` | Allow | Ordered sessions only for this dairy |
| `POST /api/v1/dairies/{dairy}/collection-sessions` | Deny `401` | Deny as `404` | Allow owner-only | Validates time windows, code uniqueness, and ordering |
| `PATCH /api/v1/dairies/{dairy}/collection-sessions/{session}` | Deny `401` | Deny as `404` | Allow owner-only | Reject removal or invalidation of live configuration |
| `GET /api/v1/dairies/{dairy}/quality-parameters` | Deny `401` | Deny as `404` | Allow | The dairy-specific configuration view only |
| `POST /api/v1/dairies/{dairy}/quality-parameters` | Deny `401` | Deny as `404` | Allow owner-only | Parameter configuration is owner-only |
| `PATCH /api/v1/dairies/{dairy}/quality-parameters/{qualityParameter}` | Deny `401` | Deny as `404` | Allow owner-only | Reject disabling required pricing inputs |
| `GET /api/v1/dairies/{dairy}/farmers` | Deny `401` | Deny as `404` | Allow | Filtered to active dairy membership |
| `POST /api/v1/dairies/{dairy}/farmers` | Deny `401` | Deny as `404` | Allow owner-only | Generates dairy-specific farmer code; rejects duplicates |
| `GET /api/v1/dairies/{dairy}/farmers/{farmer}` | Deny `401` | Deny as `404` | Allow | Only for this dairy |
| `PATCH /api/v1/dairies/{dairy}/farmers/{farmer}` | Deny `401` | Deny as `404` | Allow owner-only | Status changes and profile updates are owner-only |
| `PATCH /api/v1/dairies/{dairy}/farmers/{farmer}/milk-types` | Deny `401` | Deny as `404` | Allow owner-only | Updates farmer-specific supply configuration |
| `GET /api/v1/dairies/{dairy}/pricing/versions` | Deny `401` | Deny as `404` | Allow | Readable only within the dairy scope |
| `POST /api/v1/dairies/{dairy}/pricing/versions` | Deny `401` | Deny as `404` | Allow owner-only | Draft creation only; activation remains blocked until approval |
| `POST /api/v1/dairies/{dairy}/pricing/versions/{pricingVersion}/test` | Deny `401` | Deny as `404` | Allow owner-only | Server-side calculation preview; no persistence |
| `POST /api/v1/dairies/{dairy}/pricing/versions/{pricingVersion}/activate` | Deny `401` | Deny as `404` | Allow owner-only only after approval | Activation is blocked pending product approval |
| `GET /api/v1/dairies/{dairy}/pricing/versions/{pricingVersion}` | Deny `401` | Deny as `404` | Allow | Read-only details of a version |
| `GET /api/v1/dairies/{dairy}/pricing/versions/{pricingVersion}/history` | Deny `401` | Deny as `404` | Allow | Immutable version history and audit records |
| `GET /api/v1/dairies/{dairy}/pricing/milk-types/{milkType}` | Deny `401` | Deny as `404` | Allow | Current active pricing for one milk type |
| `POST /api/v1/dairies/{dairy}/location/search` | Deny `401` | Deny as `404` | Allow owner-only | Optional assistive lookup, not required for setup |
| `PATCH /api/v1/dairies/{dairy}/location` | Deny `401` | Deny as `404` | Allow owner-only | Stores confirmed Google or manual location |

## Denial and disclosure behavior

- Missing or invalid credentials return `401`.
- Cross-dairy access or non-membership records resolve to `404` to avoid leaking whether the dairy exists.
- A valid but unauthorized actor with a membership mismatch or insufficient permission gets `403` only when the resource exists and the action is also within the current policy scope.
- Validation failures return `422`; throttling returns `429`; idempotency collisions or duplicate rules return `409`.
- The API never exposes raw OTP codes, internal pricing formulas, or unrelated membership records.

## Policy enforcement checklist

1. Put all Phase 2 dairy routes behind `auth:sanctum`.
2. Resolve target resources through the authenticated principal's active membership before evaluating policy.
3. Enforce policy on the dairy, farmer, and pricing version at the endpoint boundary.
4. Reject any caller-provided `user_id`, `owner_id`, `dairy_id`, or role override that does not come from the authenticated membership context.
5. Ensure all updates are owner-only and use a transaction owned by the domain service.
6. Use `client_uuid` or equivalent idempotency for writes that could be retried or duplicated.
7. Treat pricing activation as a regulated operation: no prior approval, no activation; no silent re-calculation of historical data.
8. Keep pricing version records immutable; a new version is created instead of editing the active one in place.

## Deferred policy decisions

- Staff and operator roles remain deferred.
- Web-admin or super-admin access remains outside the Phase 2 contract.
- Any future pricing method beyond the approved fixed-rate method requires explicit policy review, calibration, and audit rules.
- The approved fixed-rate MVP is pending signoff, so the authorization matrix still blocks activation until the product owner records the rule set.
