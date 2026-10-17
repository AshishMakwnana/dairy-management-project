# DUDHO Laravel Backend Architecture

## Purpose

This document defines the default structure for Laravel backend features. It is a guardrail, not a requirement to create every class for every endpoint. Add a layer when it owns a real responsibility, and keep business rules out of HTTP controllers.

## Request flow

For a typical write endpoint, use this flow:

```text
Route
  -> Controller
    -> Form Request validation and authorization
      -> Application/domain service
        -> Models, domain rules, transaction, events
    -> API Resource
      -> Versioned JSON response
```

The responsibility of each layer is:

| Layer | Responsibility |
|---|---|
| Route | Version prefix, middleware, route model binding, and endpoint name |
| Controller | Coordinate the HTTP request and response only |
| Form Request | Validate input and perform request-level authorization |
| Policy | Enforce model and dairy-membership permissions |
| Service/action | Execute a use case, enforce invariants, and own its database transaction |
| Model | Define persistence, casts, relationships, scopes, and model-local behavior |
| API Resource | Produce the public response shape without leaking model internals |
| Observer | React to Eloquent lifecycle events when the behavior genuinely belongs to that lifecycle |
| Event/listener or job | Perform decoupled or asynchronous side effects |

## Directory conventions

```text
app/
  Contracts/                         Interfaces at meaningful boundaries
  Http/
    Controllers/Api/V1/              Thin versioned API controllers
    Requests/                        Form Requests grouped by feature when useful
    Resources/                       Versioned public response transformers
  Models/                            Eloquent models
  Observers/                         Eloquent lifecycle observers
  Policies/                          Authorization policies
  Providers/DomainServiceProvider.php
  Services/                          Application and domain services
```

Feature code may be grouped below these established directories as the codebase grows. Do not introduce repositories, interfaces, DTOs, or actions mechanically. Add them when they create a real boundary, make a use case independently testable, or remove meaningful duplication.

## Controllers and Form Requests

- Controllers must not contain database transactions, pricing calculations, settlement logic, or multi-model persistence.
- Mutation endpoints and endpoints with meaningful input rules use a dedicated Form Request.
- Controllers pass only validated input (`validated()` or `safe()`) to the service.
- A Form Request's `authorize()` method or an explicit controller authorization call must enforce the applicable policy. Validation is not authorization.
- Use implicit and scoped route model binding where it prevents unrelated or cross-dairy records from being resolved.
- Use one controller per resource or focused operation. Prefer conventional `index`, `show`, `store`, `update`, and `destroy` methods.

## Services and container bindings

Services own complete use cases. A service may coordinate models, policies already invoked at the boundary, events, locks, and external provider contracts. Transactions belong inside the service so every caller receives the same atomic behavior.

Concrete services with only concrete dependencies are resolved automatically by Laravel and do not need a manual binding. Use an interface when the dependency is a meaningful boundary, including OTP delivery, payments, storage providers, external APIs, or a replaceable implementation used in tests.

Bindings live in `app/Providers/DomainServiceProvider.php`:

```php
public function register(): void
{
    $this->app->bind(HealthService::class, SystemHealthService::class);
}
```

Consumers request the contract through constructor injection:

```php
public function __construct(private readonly HealthService $healthService) {}
```

Binding lifetime rules:

- `bind`: a fresh instance whenever resolved; the default for stateless domain/provider services.
- `singleton`: one instance for the whole application process; use only when shared state is intentional and safe for queue workers.
- `scoped`: one instance per request or queue-job lifecycle; use for request-specific context.

Do not call `app()` or `resolve()` from business code when constructor or method injection can expose the dependency.

## Models and persistence

- Keep identity separate from dairy membership and role context; do not add a global `users.role` field.
- Define explicit casts, typed relationships, focused query scopes, and guarded or fillable attributes.
- Store quantities, quality readings, rates, and money in decimal columns, never floating-point columns.
- Laravel remains authoritative for pricing, settlement, payment, and final financial values.
- Use database constraints and transactions for invariants that validation alone cannot protect.
- Historical pricing and financial records are immutable except through explicit correction or void workflows with reasons and audit records.
- Retried harmful mutations require the documented idempotency or client-UUID mechanism.

## Observers, events, and jobs

Use an observer only when behavior must consistently follow an Eloquent lifecycle event regardless of the caller. Good candidates include audit metadata, derived identifiers, or dispatching a domain event after persistence.

Do not hide a primary use case, authorization decision, financial calculation, or multi-model transaction in an observer. Those belong in an explicit service. Avoid observers when bulk database updates must trigger the behavior, because mass update/delete queries do not dispatch individual model events.

Register an observer close to its model with Laravel's `ObservedBy` attribute when that relationship is stable, or register it in a provider when environment/configuration controls the relationship. Side effects that require committed data must run after commit, and slow or retryable work belongs in a queued listener or job.

## API resources and response contracts

- Model-backed API responses use API Resources rather than returning Eloquent models directly.
- Resources expose only approved public fields and preserve the `/api/v1` contract.
- Non-model system responses, such as the health endpoint, may return a typed `JsonResponse` directly.
- Success and error envelopes must follow the approved Phase 1 API contract; P0-08 is complete.
- Never expose exception messages, SQL details, secrets, or unapproved model attributes.

## Testing expectations

- Every endpoint has a feature test for its observable contract.
- Test validation, authentication, authorization, dairy isolation, successful persistence, and applicable failure paths.
- Policy tests own the complete permission matrix; one HTTP denial test proves the endpoint invokes authorization.
- Service tests cover domain branches and transactions when feature tests cannot identify failures precisely.
- Use factories for model data and Laravel fakes for events, queues, notifications, mail, storage, and HTTP.
- Run the narrowest relevant test during development, then the complete phase checks before completion.

## Reference implementation

`GET /api/v1/health` demonstrates the dependency direction:

1. `routes/api.php` maps the route to `HealthController`.
2. `HealthController` depends on the `HealthService` contract.
3. `DomainServiceProvider` binds that contract to `SystemHealthService`.
4. `SystemHealthService` supplies the application status data.
5. `HealthCheckTest` verifies the public HTTP contract and therefore the complete container wiring.

Future Phase 1 features must follow these conventions while avoiding empty or speculative layers.
