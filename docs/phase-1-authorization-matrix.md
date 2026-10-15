# DUDHO Phase 1 Authorization Matrix

## Scope and identity model

This matrix covers Phase 1 mobile-owner authentication and dairy onboarding endpoints in the [Phase 1 API contract](./phase-1-api-contract.md).

- Authentication identifies a user; it does not imply dairy access.
- Dairy access is granted only by an active membership linking that user to that dairy.
- The initial membership role is `owner`, scoped to one dairy. Do not add a global `users.role`.
- The authenticated principal is always derived from the Sanctum token. Client-supplied user IDs, owner IDs, role names, or dairy IDs are never proof of permission.
- A user's default/selected dairy is a client context preference, not an authorization grant.
- Staff, operator, and web-administrator roles are outside Phase 1 and must be added with explicit dairy-scoped permissions before their endpoints ship.

## Role definitions

| Principal state | Meaning |
|---|---|
| Anonymous | No valid Sanctum token |
| Authenticated, no active dairy membership | Identity is valid but has no dairy access |
| Active dairy owner | Authenticated identity has an active `owner` membership for the requested dairy |
| Inactive/revoked membership | Membership is suspended, revoked, or otherwise not active; treat as no access |

## Endpoint matrix

| Endpoint/action | Anonymous | Authenticated without membership | Active owner of target dairy | Enforcement |
|---|---|---|---|---|
| `POST /auth/otp/send` | Allow, subject to rate limits | Allow, subject to rate limits | Allow, subject to rate limits | Public route; server-side throttling by phone and source |
| `POST /auth/otp/verify` | Allow valid one-time challenge, subject to rate limits | Allow valid one-time challenge, subject to rate limits | Allow valid one-time challenge, subject to rate limits | Public route; verify phone/code, consume challenge once, deduplicate by `client_uuid` |
| `GET /auth/me` | Deny `401` | Allow own profile | Allow own profile | `auth:sanctum` |
| `PATCH /auth/me` | Deny `401` | Allow own profile fields | Allow own profile fields | `auth:sanctum`; actor comes from authenticated principal; any default dairy must be within active membership scope |
| `POST /auth/logout` | Deny `401` | Allow current token revocation | Allow current token revocation | `auth:sanctum`; revoke only the current access token |
| `GET /dairies` | Deny `401` | Allow; return an empty list | Allow; return only dairies with the caller's active membership | `auth:sanctum`; membership-scoped query |
| `POST /dairies` | Deny `401` | Allow; create for the authenticated user as initial owner | Allow another dairy if product limits permit | `auth:sanctum`; derive owner identity from principal and create dairy + membership atomically |
| `GET /dairies/{dairy}` | Deny `401` | Deny as out-of-scope `404` | Allow | `auth:sanctum`, membership-scoped binding, dairy policy |
| `PATCH /dairies/{dairy}` | Deny `401` | Deny as out-of-scope `404` | Allow permitted profile fields | `auth:sanctum`, membership-scoped binding, dairy policy and validated request |

“Allow” on public OTP routes means only that authentication is not required; it does not bypass input validation, challenge checks, abuse controls, or delivery-provider availability.

## Denial and information-disclosure behavior

- Missing, invalid, or expired credentials return `401`.
- A dairy outside the caller's active-membership scope resolves as `404`, including a dairy ID that belongs to another user. This avoids disclosing whether an inaccessible dairy exists.
- `403` is reserved for a caller who can access the target resource but lacks the capability for the requested action. Phase 1 has no owner-denied operation in the listed endpoints; preserve this behavior for future scoped roles.
- Invalid request data returns `422`; throttled OTP requests return `429`; an idempotency key reused with a different request body returns `409`.
- Do not return or log OTP values, bearer tokens, token hashes, or unrelated membership/user records.

## Laravel enforcement checklist

1. Put authenticated endpoints behind `auth:sanctum`.
2. Use Form Requests for validation and request-level authorization; do not treat validation as authorization.
3. For dairy routes, resolve records through the authenticated user's active memberships (or enforce equivalent scoped route binding) before mutation/read.
4. Invoke the dairy policy at the endpoint boundary, including reads where access is restricted.
5. In the dairy creation service, derive the owner from the authenticated principal and transactionally create the dairy and owner membership. Ignore/reject caller-supplied owner or role fields.
6. Keep membership and identity separate in models, schema, API Resources, and tests.
7. Add feature tests for anonymous access, no-membership access, cross-dairy isolation, inactive membership, owner success, and OTP throttling/idempotency as applicable.

## Deferred decisions

- Additional role names and capabilities for staff/operators.
- Any web-administrator access and how it is provisioned.
- Whether product policy limits the number of dairies an owner can create.
- Owner onboarding steps and their persistence model.

These decisions must be resolved before the corresponding capabilities are implemented; they do not weaken the Phase 1 rules above.
