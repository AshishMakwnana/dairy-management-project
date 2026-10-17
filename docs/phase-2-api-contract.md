# DUDHO Phase 2 API Contract

## Purpose

This contract defines the Phase 2 owner-scoped API surface for the dairy operational setup layer. It covers dairy readiness, master data configuration, farmer management, and versioned pricing configuration. All routes are under `/api/v1`, require `auth:sanctum`, and resolve dairy resources only through active memberships.

The pricing section deliberately remains in a blocked state until the product owner approves the precise rules. Until then, draft and test flows may exist as non-activated design artifacts, but no pricing version may be activated and no financial calculation may be treated as authoritative.

## Base conventions

- Base path: `/api/v1`
- Authentication: Sanctum bearer token
- Resource ownership: all dairy-scoped endpoints resolve through the authenticated principal's active membership for that dairy
- Pagination: list endpoints use `page`, `per_page`, `total`, `last_page`, and `items` metadata
- UUIDs: resource IDs, `dairy_id`, `farmer_id`, `pricing_version_id`, and `client_uuid` are UUID strings
- Decimal values: quantity, quality inputs, threshold/rate values, and money are sent as decimal strings, never floats
- Time zone: effective-date rules are evaluated in the dairy's configured timezone
- Error envelope: `{ success, message, errors, meta }`
- Success envelope: `{ success, message, data, meta }`
- Idempotency: all harmful retryable mutations require a `client_uuid` or equivalent idempotency key

## Shared response schema

### Success response

```json
{
  "success": true,
  "message": "Dairy setup readiness retrieved.",
  "data": {
    "dairy_id": "dairy-uuid",
    "is_ready": true,
    "missing_steps": [],
    "pricing_ready": false
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
    "name": ["The name field is required."],
    "effective_date": ["The effective date must be a valid date."]
  },
  "meta": {
    "request_id": "req_01J9A7M4Y3FK9Q2QZ5F3A9ZQ4P",
    "timestamp": "2026-10-14T12:04:00Z"
  }
}
```

### Cross-dairy access response

```json
{
  "success": false,
  "message": "Not found.",
  "errors": {
    "dairy": ["The requested dairy was not found or is not accessible."]
  },
  "meta": {
    "request_id": "req_01J9A7M4Y3FK9Q2QZ5F3A9ZQ4P",
    "timestamp": "2026-10-14T12:04:00Z"
  }
}
```

## Dairy setup readiness

### GET `/api/v1/dairies/{dairy}/setup-readiness`

Returns the server-confirmed readiness state for the selected dairy.

Response fields:

```json
{
  "success": true,
  "message": "Dairy setup readiness retrieved.",
  "data": {
    "dairy_id": "dairy-uuid",
    "is_ready": true,
    "status": "ready",
    "completed_steps": ["profile", "milk_types", "collection_sessions", "quality_parameters"],
    "missing_steps": [],
    "pricing_ready": false,
    "pricing_missing_milk_type_ids": ["milk-type-uuid"],
    "onboarding": {
      "state": "draft",
      "can_complete": true,
      "completed_at": null
    },
    "updated_at": "2026-10-14T12:04:00Z"
  },
  "meta": {}
}
```

Rules:

- `dairy` must resolve via the authenticated owner's active membership
- cross-dairy access resolves to `404`
- readiness is derived from persisted server state, not client heuristics
- the milk types, quality parameters, and Morning/Evening sessions created atomically during Phase 1 dairy creation count as completed when they remain valid and enabled
- `is_ready` covers the non-financial profile, milk-type, collection-session, and quality-parameter configuration only
- `pricing_ready` is reported separately per enabled milk type and does not prevent the owner from completing basic onboarding
- this read endpoint never mutates `onboarding_completed_at`
- no configuration step is considered complete until the server validates its persisted state

### POST `/api/v1/dairies/{dairy}/setup/complete`

Explicitly completes basic dairy onboarding after the owner reviews the server-confirmed configuration.

Request body:

```json
{
  "client_uuid": "9d050031-acde-4217-8c41-27a0bb1dbd56"
}
```

Rules:

- owner-only and active-membership scoped
- requires `is_ready: true`; otherwise returns `409` with the current `missing_steps`
- sets `onboarding_completed_at` once inside the domain-service transaction
- retrying the same request is idempotent and returns the completed state
- a different payload using the same `client_uuid` returns `409`
- pricing readiness is independent and does not block basic onboarding completion

## Dairy milk-type configuration

### GET `/api/v1/dairies/{dairy}/milk-types`

Returns all configured milk types for the dairy and whether each one is active.

Query parameters:

- `page` optional (default 1)
- `per_page` optional (default 20)
- `status` optional: `active`, `inactive`, `all`

Example response:

```json
{
  "success": true,
  "message": "Milk types retrieved.",
  "data": {
    "items": [
      {
        "id": "milk-type-uuid",
        "master_id": "milk-master-uuid",
        "name": "Cow",
        "code": "COW",
        "is_active": true,
        "sort_order": 1,
        "pricing_enabled": true,
        "updated_at": "2026-10-14T12:04:00Z"
      }
    ]
  },
  "meta": {
    "page": 1,
    "per_page": 20,
    "total": 1,
    "last_page": 1
  }
}
```

### POST `/api/v1/dairies/{dairy}/milk-types`

Creates a dairy-specific enabled milk type.

Request body:

```json
{
  "master_id": "milk-master-uuid",
  "is_active": true,
  "sort_order": 1,
  "pricing_enabled": true,
  "client_uuid": "2ef2f6a4-56a5-4e6d-9fd8-40b7537a762f"
}
```

Validation rules:

- `master_id` required and belongs to an active master catalog record
- `sort_order` integer, 0 or greater
- `pricing_enabled` boolean
- `client_uuid` required for retry-safe writes
- a dairy cannot enable a duplicate milk type for the same master record

### PATCH `/api/v1/dairies/{dairy}/milk-types/{milkType}`

Updates a dairy milk type; does not remove operational history.

Request body:

```json
{
  "is_active": true,
  "sort_order": 2,
  "pricing_enabled": true,
  "client_uuid": "2ef2f6a4-56a5-4e6d-9fd8-40b7537a762f"
}
```

Rules:

- source record must belong to the same dairy
- a configuration change that leaves a dairy without any enabled milk type is rejected
- if the milk type is referenced by an active or scheduled pricing version, the update must first be validated against the pricing version set

## Collection session configuration

### GET `/api/v1/dairies/{dairy}/collection-sessions`

Returns collection sessions configured for the dairy.

### POST `/api/v1/dairies/{dairy}/collection-sessions`

Request body:

```json
{
  "name": "Evening",
  "code": "EVN",
  "starts_at": "18:00:00",
  "ends_at": "20:00:00",
  "sort_order": 2,
  "is_active": true,
  "client_uuid": "0b3f1625-d0be-40d0-b6d9-cc59c1d0f5e5"
}
```

Validation rules:

- `name` required, 1-80 chars
- `code` required, unique within the dairy, uppercase alphanumeric
- `starts_at` and `ends_at` required valid times; end must be after start
- `sort_order` integer
- `is_active` boolean
- if a session is tied to active pricing logic or collection preparation, the update must not silently invalidate configuration

### PATCH `/api/v1/dairies/{dairy}/collection-sessions/{session}`

Updates a session's metadata or status.

Rules:

- no silent deletion of active sessions with live operational dependencies
- a dairy may not end up with zero enabled sessions unless the call explicitly rejects it

## Quality-parameter configuration

### GET `/api/v1/dairies/{dairy}/quality-parameters`

Returns enabled quality parameters and the usage mode configured for the dairy.

Example fields:

- `id`
- `master_id`
- `name`
- `code`
- `unit`
- `precision`
- `is_required`
- `is_active`
- `use_for_pricing`
- `sort_order`
- `min_value`
- `max_value`

### POST `/api/v1/dairies/{dairy}/quality-parameters`

Request body:

```json
{
  "master_id": "quality-master-uuid",
  "is_active": true,
  "is_required": false,
  "use_for_pricing": true,
  "precision": 2,
  "sort_order": 1,
  "client_uuid": "fca3452d-1f9e-405f-99af-377f3e2a8b4e"
}
```

Validation rules:

- `master_id` required and valid
- `precision` integer 0-6
- `use_for_pricing` must be explicit if a pricing method uses it
- required parameters cannot be disabled while referenced by an active or scheduled pricing version

### PATCH `/api/v1/dairies/{dairy}/quality-parameters/{qualityParameter}`

Updates the parameter configuration.

Rules:

- quality configuration is server-owned and dairy-specific
- if a parameter is required for an active or scheduled pricing version, the API rejects disabling or narrowing it without a valid replacement pricing version

## Farmer management

### GET `/api/v1/dairies/{dairy}/farmers`

Returns a paginated list of farmers linked to the dairy.

Query parameters:

- `search`
- `status` (`active`, `inactive`, `all`)
- `page`, `per_page`

Example response:

```json
{
  "success": true,
  "message": "Farmers retrieved.",
  "data": {
    "items": [
      {
        "id": "farmer-uuid",
        "dairy_id": "dairy-uuid",
        "dairy_farmer_code": "D-0001",
        "name": "Ramesh Singh",
        "mobile": "+919876543210",
        "status": "active",
        "village_or_area": "Bawana",
        "milk_types": ["Cow", "Buffalo"],
        "created_at": "2026-10-14T12:04:00Z"
      }
    ]
  },
  "meta": {
    "page": 1,
    "per_page": 20,
    "total": 1,
    "last_page": 1
  }
}
```

### POST `/api/v1/dairies/{dairy}/farmers`

Creates a dairy-local farmer record. The Phase 2 farmer resource represents the relationship inside this dairy, not a globally shared person profile.

Request body:

```json
{
  "name": "Ramesh Singh",
  "mobile": "+919876543210",
  "village_or_area": "Bawana",
  "milk_types": ["milk-type-uuid-1", "milk-type-uuid-2"],
  "client_uuid": "c9f50f21-50af-4e73-93c6-9f7008f4ba18"
}
```

Validation rules:

- `name` required
- `mobile` required, normalized Indian mobile in `+91` E.164 format
- `village_or_area` optional string
- `milk_types` optional list of enabled dairy milk-type UUIDs
- server generates the dairy-specific farmer code
- duplicate farmer/mobile combinations within the same dairy are rejected by the domain service
- `name` and normalized `mobile` belong to the dairy-local farmer record; another dairy may keep its own record for the same person
- the service must not search, merge, return, or update another dairy's farmer record by mobile number
- a later optional link to an authenticated `user_id` does not move the display name or contact fields into the global user identity and does not grant dairy access

### GET `/api/v1/dairies/{dairy}/farmers/{farmer}`

Returns the farmer profile and relationship metadata for one farmer in that dairy.

### PATCH `/api/v1/dairies/{dairy}/farmers/{farmer}`

Updates farmer profile fields or active/inactive status.

Rules:

- do not delete historical operational records
- deactivation is a status change, not physical deletion
- ids and dairy relation must match the current dairy membership scope

### PATCH `/api/v1/dairies/{dairy}/farmers/{farmer}/milk-types`

Updates the set of milk types enabled for that farmer within the dairy.

## Pricing configuration, testing, activation, details, and history

Important: the pricing method rules below are intentionally pending approval. The recommended fixed-rate MVP is pending approval. The API contract reflects the gated state: pricing drafts and server-side test runs may exist, but pricing activation remains blocked until the product owner approves the rule set.

Six distinct operations are defined around `/api/v1/dairies/{dairy}/pricing/versions`:
1. **Listing all pricing versions**: `GET /api/v1/dairies/{dairy}/pricing/versions` (returns all versions for the dairy, supporting status and milk-type filters)
2. **Creating a draft version**: `POST /api/v1/dairies/{dairy}/pricing/versions` (creates a new version in `draft` status)
3. **Getting one version**: `GET /api/v1/dairies/{dairy}/pricing/versions/{pricingVersion}` (retrieves full configuration, precision snapshot, and payload hash)
4. **Editing a draft**: `PATCH /api/v1/dairies/{dairy}/pricing/versions/{pricingVersion}` (mutates draft configuration only; active, scheduled, expired, and historical versions are immutable; requires `client_uuid`)
5. **Testing a draft**: `POST /api/v1/dairies/{dairy}/pricing/versions/{pricingVersion}/test` (runs server-side calculation preview without creating collection or financial records)
6. **Activating a version**: `POST /api/v1/dairies/{dairy}/pricing/versions/{pricingVersion}/activate` (explicit, owner-only, retry-safe activation; blocked pending product approval)

In addition:
- **Viewing audit/event history of one version**: `GET /api/v1/dairies/{dairy}/pricing/versions/{pricingVersion}/history` (returns lifecycle events and audit log for that specific version)
- **Current active pricing for a milk type**: `GET /api/v1/dairies/{dairy}/pricing/milk-types/{milkType}` (returns currently active version and readiness status)

### GET `/api/v1/dairies/{dairy}/pricing/versions`

Returns all pricing versions for the dairy, supporting pagination and filtering.

Query parameters:

- `milk_type` optional UUID: filter by specific dairy milk type
- `status` optional enum: `draft`, `active`, `scheduled`, `expired`, `archived`, `all` (default `all`)
- `page` optional integer (default 1)
- `per_page` optional integer (default 20)

### POST `/api/v1/dairies/{dairy}/pricing/versions`

Creates a new draft pricing version for a given milk type.

Request body:

```json
{
  "milk_type_id": "milk-type-uuid",
  "method": "fixed_rate",
  "effective_date": "2026-11-01",
  "rate": "42.5000",
  "currency": "INR",
  "description": "Winter fixed rate for Cow",
  "client_uuid": "bd82ea11-a5e1-4a7c-8f3d-8f9d383cd494"
}
```

Validation rules:

- `milk_type_id` required and belongs to the dairy
- `method` required and supported by the policy set (`fixed_rate`)
- `effective_date` required date; first moment of the selected date in the dairy timezone
- `rate` required decimal string with 4 decimal places for internal storage
- no overlapping active or scheduled pricing versions for the same milk type
- backdated effective dates are prohibited for the MVP; `effective_date` must be today or later in the dairy timezone
- `method` cannot use unapproved quality-based formulas until product-owner approval is recorded
- `client_uuid` required for retry-safe idempotency

### GET `/api/v1/dairies/{dairy}/pricing/versions/{pricingVersion}`

Returns the full version details, including method, precision snapshot, effective date window, immutable payload hash, and status.

### PATCH `/api/v1/dairies/{dairy}/pricing/versions/{pricingVersion}`

Updates an existing draft pricing version. This endpoint modifies drafts only; active, scheduled, expired, or historical versions are immutable.

Request body:

```json
{
  "rate": "44.0000",
  "effective_date": "2026-11-01",
  "description": "Updated winter fixed rate for Cow",
  "client_uuid": "e2d83ab1-5231-4cfa-85fe-4df3311f92e4"
}
```

Validation rules:

- version must belong to the active dairy membership scope; cross-dairy lookup returns `404`
- target version `status` must be `draft`; if the version is `active`, `scheduled`, `expired`, or `archived`, the request is rejected with `409 Conflict` (immutable version)
- `rate` optional decimal string with 4 decimal places
- `effective_date` optional date string; first moment of selected date in dairy timezone; cannot overlap another active or scheduled window
- when supplied, `effective_date` must be today or later in the dairy timezone; the MVP has no privileged backdating override
- `description` optional string
- `client_uuid` required for retry-safe idempotency
- records an audit event for the draft update

### POST `/api/v1/dairies/{dairy}/pricing/versions/{pricingVersion}/test`

Runs a server-side test calculation using the version configuration, without persisting any collection or settlement record.

Request body:

```json
{
  "quantity": "24.750",
  "quality_values": {
    "fat": "4.20",
    "snf": "8.50"
  },
  "client_uuid": "b1292b5d-8d57-46e2-8d76-b7f51f510d6b"
}
```

Example response:

```json
{
  "success": true,
  "message": "Pricing test completed.",
  "data": {
    "milk_type_id": "milk-type-uuid",
    "method": "fixed_rate",
    "quantity": "24.750",
    "rate": "42.5000",
    "amount": "1051.8750",
    "final_amount": "1051.88",
    "rounding_mode": "half_up_final_amount_only"
  },
  "meta": {}
}
```

Rules:

- test responses are advisory only
- no collection, settlement, or financial record is created
- if the method is unapproved or uses an unapproved formula, the endpoint rejects the request with a product-gate error

### POST `/api/v1/dairies/{dairy}/pricing/versions/{pricingVersion}/activate`

Activates a draft version (or schedules it if the effective date is in the future). This endpoint is blocked unless product-owner approval is recorded in the policy and contract state.

Request body:

```json
{
  "reason": "Approved seasonal milk pricing change",
  "client_uuid": "1368b17f-26d7-4d59-a9d8-6a1224edf0e0"
}
```

Rules:

- activation is fail-closed behind backend configuration `pricing.activation_enabled`, sourced from `PRICING_ACTIVATION_ENABLED` and defaulting to `false`
- while the gate is disabled, the endpoint returns `409 Conflict` with stable error code `pricing_activation_disabled` and performs no write
- the version must be in a valid draft state for activation
- no active or overlapping versions remain for the same milk type in the same effective-date window
- activated pricing versions are immutable; future changes create a new version
- activation is auditable and records actor, dairy, time, reason, and payload hash
- until the approval is recorded, the service rejects activation with a contract-blocking error

### GET `/api/v1/dairies/{dairy}/pricing/versions/{pricingVersion}/history`

Returns the timeline of changes, test calculations, activation events, and audit records for the specific pricing version.

### GET `/api/v1/dairies/{dairy}/pricing/milk-types/{milkType}`

Returns the current active pricing configuration and latest version status for one milk type.

## Assisted dairy location with manual fallback

Location lookup uses dairy-scoped, authenticated Laravel proxy endpoints. Google credentials remain strictly server-side in backend environment configuration (`.env`), protected by IP/quota restrictions, and are never exposed or returned to clients. Saving the confirmed location reuses the existing dairy update endpoint `PATCH /api/v1/dairies/{dairy}`.

### GET `/api/v1/dairies/{dairy}/location/suggestions`

Performs Google-assisted place autocomplete search via the authenticated Laravel proxy when the user chooses to search. Read-only search does not mutate state and does not require `client_uuid`.

Query parameters:

- `query` required string (minimum 3 characters)
- `latitude` optional decimal string (for location bias)
- `longitude` optional decimal string (for location bias)

Response example:

```json
{
  "success": true,
  "message": "Location suggestions retrieved.",
  "data": {
    "items": [
      {
        "place_id": "ChIJL_h_P8QBDTkRw9Yq4Yg_Zf8",
        "display_name": "Bawana, Delhi",
        "formatted_address": "Bawana, Delhi, 110039, India",
        "source": "google"
      }
    ]
  },
  "meta": {}
}
```

Rules:

- read-only proxy; rate-limited and protected by server-side quotas
- does not require `client_uuid`
- Google credentials remain server-side only

### POST `/api/v1/dairies/{dairy}/location/reverse-geocode`

Resolves latitude and longitude coordinates to structured address components via the authenticated Laravel proxy.

Request body:

```json
{
  "latitude": "28.7856000",
  "longitude": "77.0559000"
}
```

Validation rules:

- `latitude` required decimal string between `-90.0000000` and `90.0000000`
- `longitude` required decimal string between `-180.0000000` and `180.0000000`
- read-only lookup; does not mutate dairy records and does not require `client_uuid`

Response example:

```json
{
  "success": true,
  "message": "Coordinates resolved successfully.",
  "data": {
    "place_id": "ChIJL_h_P8QBDTkRw9Yq4Yg_Zf8",
    "formatted_address": "Bawana, Delhi, 110039, India",
    "village_or_area": "Bawana",
    "city": "Delhi",
    "district": "North West Delhi",
    "state": "Delhi",
    "postal_code": "110039",
    "country": "IN",
    "source": "google"
  },
  "meta": {}
}
```

### PATCH `/api/v1/dairies/{dairy}` (Saving confirmed location and profile updates)

Reuses the existing Phase 1 dairy update endpoint to save user-confirmed structured location fields, map coordinates, and source provenance, while preserving backward compatibility for legacy partial profile and location updates.

#### Backward Compatibility for Legacy Updates
Existing Phase 1 partial updates continue to work without modification. An authenticated owner may update `name` and/or partial location fields (`city`, `state`, `country`, `postal_code`) without providing `source` or coordinate fields.

#### Conditional Enhanced Location Validation
When `location.source` is submitted, it signifies confirmed enhanced location data and triggers strict validation:
- `location.source`: optional for legacy partial updates; when present, must be an enum: `"google"` or `"manual"`
- **Enhanced confirmation required fields**: When `location.source` is provided, the request must include:
  - `location.state`: required string up to 100 characters
  - `location.country`: required string, restricted to `"IN"`
  - `location.postal_code`: required valid six-digit Indian PIN code
- **Paired coordinate rule**: `location.latitude` and `location.longitude` must appear together (if either is submitted, both are required decimal strings: `-90.0000000` to `90.0000000` and `-180.0000000` to `180.0000000`).
- **Provider place ID rules**:
  - when `location.source` is `"google"`, `location.place_id` is **required** (non-empty Google Place ID string).
  - when `location.source` is `"manual"`, `location.place_id` **must be null** (or explicitly omitted).
- **Column Mapping and Reused Fields**:
  - The API field `location.village_or_area` maps directly to the existing `dairies.village_area` database column (optional string up to 150 characters).
  - The API field `location.district` maps directly to the existing `dairies.district` database column (optional string up to 150 characters).
  - The API field `location.city` maps to the existing `dairies.city` database column.
- **Optional structured fields**:
  - `location.village_or_area`: optional string up to 150 characters
  - `location.city`: optional string up to 150 characters
  - `location.district`: optional string up to 150 characters
- **Manual fallback**: If Google services fail, are unavailable, or the user opts out of assisted lookup, the mobile client submits manual entries with `location.source: "manual"` and `location.place_id: null`. Manual entry requires `state`, `country: "IN"`, and a valid 6-digit `postal_code`; `village_or_area`, `city`, and `district` are captured from structured text inputs; coordinates are optional.
- **P2-09 Implementation Scope**: Task `P2-09` reuses existing `dairies.village_area` and `dairies.district` columns (it does not add new columns for them). P2-09 adds a new additive forward migration introducing only the missing coordinate and provenance columns: `latitude` (decimal, e.g. `decimal(10, 7)`, nullable), `longitude` (decimal, e.g. `decimal(10, 7)`, nullable), `location_source` (string/enum: `"manual"`, `"google"`, nullable), and `provider_place_id` (string, nullable). Do not rewrite the existing `create_dairies` migration; use a new forward migration. In addition, `P2-09` must update the `Dairy` model fillable attributes and casts as needed, `UpdateDairyRequest` conditional validation, `DairyProfileService` transaction and persistence mapping, `DairyDetailResource` API output, and comprehensive automated feature tests covering both legacy partial updates and enhanced location confirmation.

Request body example (enhanced location confirmation):

```json
{
  "name": "Green Valley Dairy",
  "location": {
    "village_or_area": "Bawana Industrial Area",
    "city": "Delhi",
    "district": "North West Delhi",
    "state": "Delhi",
    "country": "IN",
    "postal_code": "110039",
    "latitude": "28.7856000",
    "longitude": "77.0559000",
    "source": "google",
    "place_id": "ChIJL_h_P8QBDTkRw9Yq4Yg_Zf8"
  }
}
```

## Security and authorization contract

- All endpoints require `auth:sanctum` bearer authentication.
- Missing or invalid authentication returns `401 Unauthorized`.
- Dairies outside the caller's active membership scope, inactive memberships, nested cross-dairy resources, or membership mismatches return `404 Not Found` (never disclosing resource existence).
- An authenticated active member lacking required permission (including all active non-owner members accessing Phase 2 endpoints) returns `403 Forbidden`.
- Current Phase 2 endpoints (both read and mutation) remain strictly owner-only. Staff and operator roles remain deferred.
- Validation failures return `422 Unprocessable Entity`.
- Idempotency collisions, duplicate records, or price-version window overlaps return `409 Conflict`.
- Throttled proxy or calculation requests return `429 Too Many Requests`.
- The recommended fixed-rate MVP is pending approval, so pricing activation endpoints remain locked and reject activation requests until approval is recorded.

## Implementation notes

- Use scoped route binding and policy checks on the dairy, farmer, and pricing version records
- Keep identity separate from dairy membership and farmer relationships
- Store all measurable and monetary values as decimal strings or decimal database columns
- Do not silently overwrite active or historical pricing data
- Keep activated pricing versions immutable and create a new version instead of mutating in place
- Keep the pricing contract in a blocked state until product-owner approval is captured in the decision log
- Implement `PRICING_ACTIVATION_ENABLED=false` as the default and test both fail-closed activation and the explicitly enabled path; documentation approval alone must never enable activation
