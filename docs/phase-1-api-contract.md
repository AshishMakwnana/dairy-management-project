# DUDHO Phase 1 API Contract

## Purpose

This document defines the Phase 1 API contract for the mobile owner experience: authentication, language-aware onboarding, and the first dairy setup workflow. It is the source of truth for Laravel endpoint design and the React Native client until the feature implementation is complete.

## Base conventions

- Base path: `/api/v1`
- Authentication: Laravel Sanctum bearer tokens
- Laravel foundation: Sanctum is installed; bearer tokens are persisted in `personal_access_tokens`
- Content type: `application/json`
- Response envelope: `{ success, message, data, meta }`
- Error envelope: `{ success, message, errors, meta }`
- Dates and times: ISO 8601 UTC strings
- Resource IDs and `client_uuid` values are UUIDs; internal BIGINT database IDs are never exposed.
- All money and quantity values are decimal strings, not floats

## Phase 1 persistence model

Phase 1 persistence follows the database design in the current master blueprint. The Laravel schema uses BIGINT internal foreign keys and UUID public identifiers; clients must use the public UUIDs and must not infer authorization from an identifier.

- `users` stores a required, database-unique public `uuid`, identity fields, normalized `mobile_country_code` + `mobile`, mobile verification, preferred language, status, and optional `default_dairy_id`. The response's `full_name` maps to the stored `name`.
- The existing baseline `name`, `email`, and `password` columns are made nullable so OTP-first identities can be created without collecting a name or email before verification. `password` remains only for compatibility with existing baseline data and is not an authentication method for the mobile API. Legacy rows may have a null mobile; new OTP-authenticated identities must always use a normalized, unique phone pair.
- `otp_challenges` stores only `otp_hash`, a UUID challenge identifier, requested channel and locale, expiry/verification timestamps, attempt/resend counts, and security context. Raw OTP values are never persisted.
- `otp_login_requests` stores a client UUID, request HMAC, user reference, and encrypted successful verification response so safe retries can return the original token without storing it in plaintext.
- `dairies` owns its business profile and creator reference; the onboarding response's `state: "draft"` means `onboarding_completed_at` is null, not that the dairy lifecycle status is inactive.
- `dairy_users` stores dairy-scoped role and active membership state. Initial dairy creation inserts an active `owner` membership with `is_primary_owner: true`; no global role is stored on `users`.
- `dairy_settings`, `dairy_milk_types`, `collection_sessions`, and `dairy_quality_parameters` store per-dairy settings. `milk_types` and `quality_parameters` are shared master catalogs, seeded with active data by `MasterDataSeeder`.
- `dairies.creation_request_uuid` and `creation_request_hash` persist the authenticated creator's client UUID and canonical request hash so the onboarding service can make retries safe and reject key reuse with a different payload.
- `collection_sessions` are dairy-specific records rather than master catalog rows. The onboarding service creates the selected sessions (with Morning and Evening as initial defaults); future session names and codes remain data-driven.

The `dairies` table additionally stores `legal_name`, `city`, and `country_code` to support fields in the request examples alongside the blueprint's address, district, state, and country fields.

The identity migration is additive to the established Laravel baseline so existing installations can upgrade in place. New OTP identities have a normalized mobile pair; legacy baseline users may remain without a mobile until authenticated through the phone-verification flow. The original baseline `name`, `email`, and `password` columns remain nullable when this migration is rolled back rather than fabricating values for OTP-first identities.

The corrective `2026_10_15_000000_add_unique_uuid_to_users_table` migration adds the database unique index for `users.uuid` without rewriting existing user rows. It stops with an explicit error if duplicate UUID values already exist; resolve those records before retrying the migration. The already-applied identity migration's forward schema is unchanged. Its rollback checks that the UUID and normalized-mobile unique indexes exist before dropping them, allowing rollback both before and after the corrective migration. Apply the correction with the normal `php artisan migrate` upgrade; do not reset an existing database.

## Shared response envelope

### Success response

```json
{
  "success": true,
  "message": "OTP sent successfully.",
  "data": {
    "delivery_channel": "sms",
    "expires_at": "2026-10-14T12:05:00Z",
    "retry_after_seconds": 60
  },
  "meta": {
    "request_id": "req_01J9A7M4Y3FK9Q2QZ5F3A9ZQ4P",
    "timestamp": "2026-10-14T12:04:00Z"
  }
}
```

### Validation error response

```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": {
    "phone": ["The phone field is required."],
    "code": ["The code must contain 6 digits."]
  },
  "meta": {
    "request_id": "req_01J9A7M4Y3FK9Q2QZ5F3A9ZQ4P",
    "timestamp": "2026-10-14T12:04:00Z"
  }
}
```

### Authentication / authorization error response

```json
{
  "success": false,
  "message": "Unauthenticated.",
  "errors": {
    "token": ["The provided token is invalid or expired."]
  },
  "meta": {
    "request_id": "req_01J9A7M4Y3FK9Q2QZ5F3A9ZQ4P",
    "timestamp": "2026-10-14T12:04:00Z"
  }
}
```

## Authentication endpoints

### POST `/api/v1/auth/otp/send`

Request a one-time passcode to authenticate a mobile owner by phone number.

Request body:

```json
{
  "phone": "+919876543210",
  "channel": "sms",
  "locale": "en"
}
```

Validation rules:

- `phone` required, Indian mobile number in `+91` E.164 format for the initial rollout
- `channel` optional, enum: `sms`, `whatsapp`; it is a delivery preference, not an authorization signal
- `locale` optional, enum: `en`, `hi`

Success response:

```json
{
  "success": true,
  "message": "OTP sent successfully.",
  "data": {
    "delivery_channel": "sms",
    "expires_at": "2026-10-14T12:05:00Z",
    "retry_after_seconds": 60
  },
  "meta": {}
}
```

Notes:

- The response never exposes the actual OTP code or reveals whether the phone number was previously registered.
- A mock/local delivery driver is selected by server configuration and is never selectable by a production client.
- `delivery_channel` echoes the requested `sms` or `whatsapp` preference. In local/testing, the configured driver delivers the code to the Mailpit sink instead.
- The delivery boundary is the `OtpDeliveryProvider` contract; the OTP authentication service owns challenge creation and verification. Future SMS/WhatsApp providers can implement the delivery contract.
- Local and test environments can use `OTP_DELIVERY=local_mail`; codes are sent synchronously to `OTP_LOCAL_MAIL_TO` through the named SMTP mailer and can be inspected in the local Mailpit inbox. The phone number is masked in that email. Set `OTP_DELIVERY=local_mail_with_whatsapp` to keep SMS in Mailpit while sending WhatsApp requests to `WHATSAPP_GATEWAY_URL` plus `WHATSAPP_GATEWAY_ENDPOINT` (default `/api/send-otp`). The gateway request sends only JSON `{ "mobile": "+91..." }` with `X-Internal-Token`, `Content-Type: application/json`, and `Accept: application/json` headers. The gateway returns `{ "otp": "123456" }`; Laravel validates the six-digit value, stores only its hash, and never returns it to the mobile client. Set `OTP_DELIVERY=whatsapp_gateway` only when the client exposes WhatsApp alone. Gateway credentials and returned OTP values must never be logged or exposed through the public API.
- Codes expire after 5 minutes. Resends are blocked for 60 seconds, then limited to 5 sends per phone per hour and 30 sends per source IP per hour. Verification allows 5 attempts per challenge, 10 attempts per phone per 15 minutes, and 60 attempts per source IP per 15 minutes. Rate-limit responses use `429`, a `Retry-After` header, and `meta.retry_after_seconds`.

### POST `/api/v1/auth/otp/verify`

Verify the code and create a Sanctum session token.

Request body:

```json
{
  "phone": "+919876543210",
  "code": "123456",
  "device_name": "Pixel 8",
  "client_uuid": "7cb3f8ec-b62d-45c8-a7a1-487d9db1a1d3"
}
```

Validation rules:

- `phone` required, Indian mobile number in `+91` E.164 format for the initial rollout
- `code` required, exactly 6 digits
- `device_name` optional string
- `client_uuid` required to make repeated verification submissions safe

Success response:

```json
{
  "success": true,
  "message": "Signed in successfully.",
  "data": {
    "access_token": "Q2luZGF0aW9uIGNvbnN1bWVyIHNlc3Npb24=",
    "token_type": "Bearer",
    "expires_at": null,
    "user": {
      "id": "6ba7b810-9dad-11d1-80b4-00c04fd430c8",
      "phone": "+919876543210",
      "full_name": "Anita Verma",
      "preferred_language": "en",
      "status": "active"
    }
  },
  "meta": {}
}
```

Notes:

- A verified phone creates an identity only if one does not already exist; it does not grant any dairy membership.
- The token is stored as a Sanctum personal access token. `expires_at` is null when token expiry is not configured and otherwise reports the configured expiry.
- The same `client_uuid` and request body must not create a second identity or token. Any stored response needed for safe replay must be protected at rest; reject reuse of a key with a different body as `409`.
- An invalid, expired, already-consumed, or locked-out code returns `422` with a field error on `code`. Rate limits return `429`.
- A successful request stores its response payload encrypted at rest. Replaying the same `client_uuid` and request body returns the same token; reusing the UUID with a different body returns `409`.

### GET `/api/v1/auth/me`

Return the authenticated user's current profile summary.

Headers:

```http
Authorization: Bearer <token>
```

Success response:

```json
{
  "success": true,
  "message": "Profile retrieved successfully.",
  "data": {
    "id": "6ba7b810-9dad-11d1-80b4-00c04fd430c8",
    "full_name": "Anita Verma",
    "phone": "+919876543210",
    "preferred_language": "en",
    "default_dairy_id": "91b9c71a-28f4-4c37-9b55-b26337e18220"
  },
  "meta": {}
}
```

### PATCH `/api/v1/auth/me`

Update only the authenticated user's own profile.

Request body:

```json
{
  "full_name": "Anita Verma",
  "preferred_language": "hi",
  "default_dairy_id": "91b9c71a-28f4-4c37-9b55-b26337e18220"
}
```

Validation rules:

- `full_name` optional, non-empty string up to 150 characters
- `preferred_language` optional, enum: `en`, `hi`
- `default_dairy_id` optional, public dairy UUID or `null` to clear the preference; a non-null value must resolve to a dairy with the user's active membership
- At least one supported profile field must be provided

The endpoint cannot change the user's phone number, identity ID, membership, or role. The selected dairy is only a preference; the API must still authorize each dairy request independently. Repeating the same request is safe.

Only the three listed request fields are accepted; unsupported fields are rejected. The response exposes exactly `id` (the public user UUID), `full_name`, normalized `phone`, `preferred_language`, and `default_dairy_id` (the public dairy UUID or `null`). The `default_dairy_id` preference is persisted as an internal foreign key but is never an authorization grant. A non-null selection is accepted only when the authenticated user has an active membership in the referenced dairy.

### POST `/api/v1/auth/logout`

Invalidate the active device session.

Headers:

```http
Authorization: Bearer <token>
```

Success response:

```json
{
  "success": true,
  "message": "Signed out successfully.",
  "data": null,
  "meta": {}
}
```

Logout revokes only the personal access token presented by the current request. Other tokens issued to the same user on other devices remain valid.

## Dairy onboarding endpoints

The current Phase 1 API exposes authenticated dairy listing and creation. It does not expose dashboard aggregates, collection records, or persisted per-step setup status. Until those contracts are added, mobile dashboard metrics must remain unavailable and setup checklist items must not be marked complete without server-confirmed state.

### GET `/api/v1/dairies`

List dairies where the authenticated user has an active membership.

Headers:

```http
Authorization: Bearer <token>
```

Success response:

```json
{
  "success": true,
  "message": "Dairies retrieved successfully.",
  "data": [
    {
      "id": "91b9c71a-28f4-4c37-9b55-b26337e18220",
      "name": "Green Valley Dairy",
      "state": "draft",
      "location": {
        "city": "Bhopal",
        "state": "Madhya Pradesh",
        "country": "IN"
      }
    }
  ],
  "meta": {
    "total": 1
  }
}
```

### POST `/api/v1/dairies`

Create a new dairy and establish the authenticated owner relationship.

Request body:

```json
{
  "name": "Green Valley Dairy",
  "legal_name": "Green Valley Dairy Private Limited",
  "location": {
    "city": "Bhopal",
    "state": "Madhya Pradesh",
    "country": "IN",
    "postal_code": "462001"
  },
  "client_uuid": "212f48a4-8cba-48e4-b0a6-5a1c9f4bd3d0"
}
```

Validation notes:

- `name` required, non-empty string up to 191 characters.
- `legal_name` optional string up to 191 characters.
- `location.city`, `location.state`, and `location.postal_code` must fit their corresponding dairy fields; `location.country` is an ISO 3166-1 alpha-2 country code.
- Initial dairy onboarding accepts `location.country: "IN"` only, matching the India-first Phase 1 rollout.
- `client_uuid` is required for duplicate protection. A retry with the same key and body returns the original result; reusing the key with a different body returns `409`.
- The authenticated user is always the creator and initial owner; the request cannot provide or override the owner identity.
- The initial `state` is `draft` and onboarding steps are tracked separately.
- Create the dairy, active owner membership, dairy settings, enabled master milk/quality selections, and Morning/Evening collection sessions atomically.

Success response:

```json
{
  "success": true,
  "message": "Dairy created successfully.",
  "data": {
    "id": "91b9c71a-28f4-4c37-9b55-b26337e18220",
    "name": "Green Valley Dairy",
    "state": "draft",
    "location": {
      "city": "Bhopal",
      "state": "Madhya Pradesh",
      "country": "IN"
    }
  },
  "meta": {}
}
```

### GET `/api/v1/dairies/{dairy}`

Fetch a single dairy and the current onboarding status.

Success response:

```json
{
  "success": true,
  "message": "Dairy retrieved successfully.",
  "data": {
    "id": "91b9c71a-28f4-4c37-9b55-b26337e18220",
    "name": "Green Valley Dairy",
    "state": "draft",
    "onboarding_step": "basic_profile",
    "location": {
      "city": "Bhopal",
      "state": "Madhya Pradesh",
      "country": "IN",
      "postal_code": "462001"
    },
    "owner": {
      "id": "6ba7b810-9dad-11d1-80b4-00c04fd430c8",
      "full_name": "Anita Verma"
    }
  },
  "meta": {}
}
```

### PATCH `/api/v1/dairies/{dairy}`

Update dairy profile information within the authenticated owner's dairy context.
The authenticated user must have an active owner membership. Active members
without update capability receive `403`; dairies outside the caller's active
membership scope, including inactive dairies, return `404`.

Request body:

```json
{
  "name": "Green Valley Dairy",
  "location": {
    "city": "Bhopal",
    "state": "Madhya Pradesh",
    "country": "IN",
    "postal_code": "462001"
  }
}
```

Notes:

- Only `name` and the `location` fields shown above (`city`, `state`, `country`,
  and `postal_code`) may be updated. `country` is currently restricted to `IN`.
- Owner, creator, membership, user, role, status, UUID, and unknown fields are
  rejected; the server derives ownership and authorization from the authenticated
  principal and active membership.
- Use a proper policy check before mutation.
- Repeating the same profile update is safe. If an update later triggers a non-idempotent side effect, require `client_uuid` deduplication for that side effect.

For dairy detail, `state` is derived from `onboarding_completed_at`; the
`onboarding_step` is `basic_profile` while the dairy is a draft and `null` once
onboarding is complete. No unpersisted checklist state is inferred.

## Authorization model

- Users have an identity record independent from dairy membership.
- Memberships determine whether a user can access a dairy or its onboarding state.
- Phase 1 supports the dairy-scoped `owner` membership only; do not add a global `users.role` column.
- `default_dairy_id` is a client convenience/context preference only and never proves authorization.
- Every dairy endpoint must derive the actor from Sanctum authentication and enforce active membership through a policy or membership-scoped query.
- See [phase-1-authorization-matrix.md](./phase-1-authorization-matrix.md) for endpoint permissions and denial behavior.

## Error handling expectations

| HTTP Status | Meaning |
|---|---|
| 200 | Successful read or mutation |
| 201 | Resource created |
| 400 | Bad request payload or malformed data |
| 401 | Missing or invalid authentication |
| 403 | Authenticated member lacks the capability for an action |
| 404 | Dairy/resource does not exist or is outside the authenticated user's active membership scope |
| 409 | Conflict such as duplicate onboarding or duplicate active membership |
| 422 | Validation failure |
| 429 | Request or OTP rate limit; include `Retry-After` and `meta.retry_after_seconds` when available |
| 500 | Unexpected server error |

API error responses use `{ success: false, message, errors, meta }`. Authentication, authorization, not-found, conflict, validation, rate-limit, and unexpected server errors use stable, sanitized messages. Unexpected errors must not expose exception messages, SQL details, secrets, OTPs, bearer tokens, or token hashes. This API formatting applies only under `/api/*`; non-API Laravel exception behavior remains unchanged.

## Implementation rules for the Laravel codebase

- Keep controllers thin; services own the transaction and business rules.
- Validate requests in dedicated Form Requests.
- Protect mutation use cases with an explicit policy and feature service.
- Use decimal columns for financial and quantity values.
- Keep historical records immutable unless a correction workflow explicitly creates an audit trail.
- Prefer `Transaction` + `lockForUpdate()` or a comparable pattern for writes that depend on single-source-of-truth dairy state.
- Do not expose OTPs, token hashes, exception details, or unapproved identity fields in API responses or logs.

## Open questions to resolve before Phase 1 implementation

- Select and configure a production SMS provider before enabling the SMS option in a production build. WhatsApp delivery is implemented through the internal gateway, subject to live contract validation with a rotated gateway token.
- Confirm final product language and in-app translation keys for all customer-facing messages.
- Confirm the owner onboarding step sequence and milestone names before implementing onboarding progress.

This contract is intentionally designed to support the first mobile owner flow while leaving room for later staff/operator permissions and future web-admin reuse.
