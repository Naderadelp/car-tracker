# Implementation Plan: Identity & Authentication

**Branch**: `001-identity-auth` | **Date**: 2026-04-30 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `specs/001-identity-auth/spec.md`

## Summary

Implement the Phase 1 Identity & Onboarding module for CarLog — a mobile-first, API-only
Laravel application. The module covers user registration (atomic with first vehicle onboarding),
login, profile retrieval, logout, and forgot-password. Authentication is stateless via Laravel
Sanctum tokens. All validation is handled by dedicated Form Request classes; all responses use
the `Responder` trait via API Resource classes. The registration endpoint wraps user + vehicle
creation in a single database transaction.

## Technical Context

**Language/Version**: PHP 8.4
**Primary Dependencies**: Laravel 13, Laravel Sanctum, Spatie Laravel-Activity-Log
**Storage**: MySQL — `users`, `vehicles`, `personal_access_tokens`, `password_reset_tokens`
**Testing**: PHPUnit (Laravel default test suite)
**Target Platform**: Linux server — headless JSON API consumed by a mobile app
**Project Type**: Web service (API-only backend)
**Performance Goals**: Sub-second response for all auth endpoints under normal mobile load
**Constraints**: Stateless API (no sessions/cookies); atomic registration (no orphaned records)
**Scale/Scope**: Single-tenant; Phase 1 scoped to auth + one vehicle per registration

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-checked after Phase 1 design — all pass.*

| Principle | Status | Notes |
|-----------|--------|-------|
| I. Repository Pattern | ✅ PASS | `UserRepository` and `VehicleRepository` contracts + Eloquent implementations required |
| II. Form Request Validation | ✅ PASS | `RegisterUserRequest` and `LoginUserRequest` required; no inline validation |
| III. Responder Trait | ✅ PASS | All controllers use `Responder`; `UserResource` and `VehicleResource` required |
| IV. Domain-Driven Folder Structure | ✅ PASS | `src/Domain/Auth/`, `src/Domain/User/`, `src/Domain/Vehicle/` per constitution layout |
| V. Authorization via Policies | ⚠️ JUSTIFIED DEVIATION | Auth endpoints are either public or self-referential (acting on the current user's own token/data). Policy-based `authorize()` does not apply; `auth:sanctum` middleware is the correct guard. See Complexity Tracking. |
| VI. Transactional Writes & Observability | ✅ PASS | Registration wraps both inserts in `DB::beginTransaction()`; `LogsActivity` on User and Vehicle |

## Project Structure

### Documentation (this feature)

```text
specs/001-identity-auth/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/
│   └── auth-api.md      # Phase 1 output — all 5 endpoint contracts
└── tasks.md             # Phase 2 output (/speckit-tasks — NOT created here)
```

### Source Code (repository root)

```text
src/
├── Domain/
│   ├── Auth/
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   │   └── AuthController.php
│   │   │   └── Requests/
│   │   │       ├── RegisterUserRequest.php
│   │   │       └── LoginUserRequest.php
│   │   └── Providers/
│   │       └── AuthServiceProvider.php
│   │
│   ├── User/
│   │   ├── Entities/
│   │   │   ├── User.php
│   │   │   └── Traits/
│   │   │       ├── UserRelations.php
│   │   │       └── UserAttributes.php
│   │   ├── Http/
│   │   │   └── Resources/
│   │   │       └── UserResource.php
│   │   ├── Repositories/
│   │   │   ├── Contracts/
│   │   │   │   └── UserRepository.php
│   │   │   └── Eloquent/
│   │   │       └── UserRepositoryEloquent.php
│   │   └── Providers/
│   │       └── UserServiceProvider.php
│   │
│   └── Vehicle/
│       ├── Entities/
│       │   ├── Vehicle.php
│       │   └── Traits/
│       │       ├── VehicleRelations.php
│       │       └── VehicleAttributes.php
│       ├── Http/
│       │   └── Resources/
│       │       └── VehicleResource.php
│       ├── Repositories/
│       │   ├── Contracts/
│       │   │   └── VehicleRepository.php
│       │   └── Eloquent/
│       │       └── VehicleRepositoryEloquent.php
│       └── Providers/
│           └── VehicleServiceProvider.php
│
└── Infrastructure/
    └── AbstractRepositories/
        └── EloquentRepository.php   # Base class (pre-existing)

database/
└── migrations/
    ├── xxxx_xx_xx_create_users_table.php           # Standard Laravel
    ├── xxxx_xx_xx_create_vehicles_table.php        # New for this feature
    ├── xxxx_xx_xx_create_personal_access_tokens_table.php  # Sanctum
    └── xxxx_xx_xx_create_password_reset_tokens_table.php   # Standard Laravel

routes/
└── api.php    # Explicit route definitions (no apiResource)
```

**Structure Decision**: Three-domain split (Auth / User / Vehicle). Auth owns the HTTP layer
for identity operations; User and Vehicle own their respective entities, repositories, and
resources. This mirrors the constitution's DDD layout exactly.

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| Principle V — no `$this->authorize()` on auth endpoints | Auth endpoints are either public (no authenticated user exists to check) or operate on `$request->user()` (self-referential — no third-party resource). Policy gates require an authenticated actor checking access to another model. | Adding a trivial UserPolicy `view` gate that always returns `true` for the current user adds noise with no security value and is misleading to future developers. |

