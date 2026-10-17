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
  "message": "Dairy setup readiness updated.",
  "data": {
    "id": "dairy-uuid",
    "is_ready": false,
    "missing_steps": [
      "milk_types",
      "collection_sessions",
      "quality_parameters"
    ]
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
    "is_ready": false,
    "status": "incomplete",
    "completed_steps": ["profile"],
    "missing_steps": ["milk_types", "collection_sessions", "quality_parameters"],
    "updated_at": "2026-10-14T12:04:00Z"
  },
  "meta": {}
}
```

Rules:

- `dairy` must resolve via the authenticated owner's active membership
- cross-dairy access resolves to `404`
- readiness is derived from persisted server state, not client heuristics
- no configuration step is considered complete until the server validates its persisted state

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

Creates a farmer relationship for the dairy.

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

Important: the pricing method rules below are intentionally pending approval. The API contract reflects the gated state: pricing drafts and server-side test runs may exist, but pricing activation remains blocked until the product owner approves the rule set.

### GET `/api/v1/dairies/{dairy}/pricing/versions`

Returns all pricing versions for the dairy, including draft, test, active, and historical records.

### POST `/api/v1/dairies/{dairy}/pricing/versions`

Creates a new draft version for a given milk type.

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
- `method` required and approved by the current policy set
- `effective_date` required date; first moment of the selected date in the dairy timezone
- `rate` required decimal string with 4 decimal places for internal storage
- no overlapping active or scheduled pricing versions for the same milk type
- no silent backdating; if the effective date is in the past, the service requires explicit policy-controlled override
- `method` not equal to an unapproved quality-based formula until the product owner approves it

### POST `/api/v1/dairies/{dairy}/pricing/versions/{pricingVersion}/test`

Runs a server-side test calculation using the draft configuration, without persisting any collection or settlement record.

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
    "amount": "1054.5000",
    "final_amount": "1054.50",
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

Activates a draft or tested version. This endpoint is blocked unless the product-owner approval is recorded in the policy and contract state.

Request body:

```json
{
  "reason": "Approved seasonal milk pricing change",
  "client_uuid": "1368b17f-26d7-4d59-a9d8-6a1224edf0e0"
}
```

Rules:

- the version must be in a valid state for activation
- no active or overlapping versions remain for the same milk type in the same effective-date window
- activated pricing versions are immutable; future edits create a new version
- activation is auditable and must record actor, dairy, time, reason, and payload hash
- until the approval is recorded, the service rejects activation with a contract-blocking error

### GET `/api/v1/dairies/{dairy}/pricing/versions/{pricingVersion}`

Returns the full version details, including method, precision snapshot, effective date window, and immutable payload information.

### GET `/api/v1/dairies/{dairy}/pricing/versions/{pricingVersion}/history`

Returns the timeline of changes, activation events, and audit records for the version.

### GET `/api/v1/dairies/{dairy}/pricing/milk-types/{milkType}`

Returns the current active pricing configuration and latest status for one milk type.

## Optional Google-assisted location with manual fallback

### POST `/api/v1/dairies/{dairy}/location/search`

Performs Google-assisted place lookup when the user chooses to use it. The API may also accept a current-location lookup request and return suggestions.

Request body:

```json
{
  "query": "Bawana",
  "latitude": "28.7856",
  "longitude": "77.0559",
  "client_uuid": "13ac1d52-e3ea-4203-9dbf-f3c80564801a"
}
```

Response example:

```json
{
  "success": true,
  "message": "Location suggestions retrieved.",
  "data": {
    "items": [
      {
        "place_id": "google-place-id",
        "display_name": "Bawana, Delhi",
        "latitude": "28.7856",
        "longitude": "77.0559",
        "source": "google",
        "formatted_address": "Bawana, Delhi, India"
      }
    ]
  },
  "meta": {}
}
```

Rules:

- location lookup is optional and never required for core setup completion
- if Google is unavailable, the client may use manual entry without a server-side failure
- the service records `source` as `google` or `manual`
- manual fallback must support village, district, state, PIN, city, latitude, longitude, and place metadata if available

### PATCH `/api/v1/dairies/{dairy}/location`

Stores the confirmed dairy location.

Request body:

```json
{
  "city": "Delhi",
  "district": "North West Delhi",
  "state": "Delhi",
  "country": "IN",
  "pin_code": "110039",
  "latitude": "28.7856",
  "longitude": "77.0559",
  "source": "manual",
  "place_id": null,
  "client_uuid": "85d82f1f-6409-4875-b8b1-b5217ed8d1df"
}
```

Validation rules:

- at least one location identifier must be present: manual address or Google-provided place
- latitude and longitude are decimal strings
- if `source` is `google`, the place must be readable and known to the provider
- manual fallback remains available when Google fails

## Security and authorization contract

- All endpoints are under `auth:sanctum`
- Dairy mutation endpoints are owner-only
- Staff/operator roles remain deferred; no endpoint exposes or infers them
- A cross-dairy resource lookup resolves as `404`
- A `403` is reserved for an authenticated actor whose membership exists but whose action is not allowed by the active policy
- `409` covers idempotent-safe conflicts, rejected duplicate requests, and price-version overlap conflicts
- `422` covers validation failures
- `429` covers throttling

## Implementation notes

- Use scoped route binding and policy checks on the dairy, farmer, and pricing version records
- Keep identity separate from dairy membership and farmer relationships
- Store all measurable and monetary values as decimal strings or decimal database columns
- Do not silently overwrite active or historical pricing data
- Keep activated pricing versions immutable and create a new version instead of mutating in place
- Keep the pricing contract in a blocked state until product-owner approval is captured in the decision log
