# Research: Identity & Authentication

**Feature**: 001-identity-auth
**Date**: 2026-04-30
**Status**: Complete — no unknowns; all decisions derived from carlog_auth_spec.md

---

## Decision Log

### Language & Runtime

**Decision**: PHP 8.4
**Rationale**: Explicitly specified in carlog_auth_spec.md. PHP 8.4 brings readonly properties,
property hooks, and further JIT improvements; no conflicts with Laravel 13.
**Alternatives considered**: None — prescribed.

---

### Framework

**Decision**: Laravel 13
**Rationale**: Explicitly specified. Laravel 13 maintains the same DDD-friendly structure as
Laravel 10–12 with continued support for Domain-organized source layouts under `src/`.
**Alternatives considered**: None — prescribed.

---

### Authentication Mechanism

**Decision**: Laravel Sanctum — stateless API tokens (not SPA cookies, not Passport OAuth2)
**Rationale**: Mobile-first application; Sanctum's token-based auth is the standard for
mobile API clients. Tokens are issued per device (`device_name`), independently revocable,
and do not require a cookie-capable browser client.
**Alternatives considered**:
- Laravel Passport (OAuth2): Heavier, suited to third-party token delegation; unnecessary here.
- Session-based auth: Requires stateful cookies; incompatible with mobile-first headless API.

---

### Database

**Decision**: MySQL
**Rationale**: Explicitly specified. Standard relational database; appropriate for the
structured user/vehicle data model with foreign key constraints and soft deletes.
**Alternatives considered**: None — prescribed.

---

### Password Reset Flow

**Decision**: Laravel built-in `Password::sendResetLink()` using `password_reset_tokens` table
**Rationale**: The spec explicitly references Laravel's built-in password reset. No custom
flow needed. The `password_reset_tokens` migration is included in the standard Laravel build.
**Alternatives considered**: Custom token table — adds complexity with no benefit over the
framework default.

---

### Transaction Strategy for Registration

**Decision**: Single `DB::beginTransaction()` wrapping both User and Vehicle creation
**Rationale**: Spec requires atomic registration — if vehicle creation fails, the user record
must not persist. The constitution (Principle VI) mandates transactions for multi-table writes.
**Alternatives considered**: Two-phase save without transaction — rejected; leaves orphaned
User records on Vehicle creation failure.

---

### Authorization Pattern for Auth Endpoints

**Decision**: Middleware-based Sanctum auth (`auth:sanctum`) for protected endpoints; no
Policy-based `$this->authorize()` calls on auth endpoints.
**Rationale**: Auth endpoints are either public (register, login, forgot-password) or operate
solely on the authenticated user's own data (profile, logout). There is no third-party resource
being accessed, so Policy authorization adds no value. Constitution Principle V applies to
resource controllers, not auth controllers — this deviation is documented in the plan's
Complexity Tracking table.
**Alternatives considered**: UserPolicy with `view` / `delete` gates — overcomplicated for
self-referential auth operations; rejected.

---

### Domain Module Layout

**Decision**: Split across three domain modules:
- `src/Domain/Auth/` — controllers, form requests (registration & login logic)
- `src/Domain/User/` — User entity, repository, resource
- `src/Domain/Vehicle/` — Vehicle entity, repository, resource

**Rationale**: Follows constitution Principle IV (DDD folder structure). Auth is a cross-cutting
concern that orchestrates User and Vehicle creation but owns no persistent entity of its own.
Separating the domains keeps each module independently maintainable.
**Alternatives considered**: Single `src/Domain/Auth/` for all — conflates unrelated
responsibilities (user identity vs. vehicle ownership) into one module.

---

### Response Standardization

**Decision**: All responses use the `Responder` trait; API Resources for every data payload
**Rationale**: Constitution Principle III mandates `Responder` on every controller.
Spec section 4 explicitly requires it.
**Alternatives considered**: None — prescribed by constitution and spec.
