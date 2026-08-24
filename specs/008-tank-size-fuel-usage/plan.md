# Implementation Plan: Tank Size & Percentage-Based Fuel Usage

**Branch**: `008-tank-size-fuel-usage` | **Date**: 2026-06-02 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/008-tank-size-fuel-usage/spec.md`

> **⚠️ RECONSTRUCTED DOCUMENT — 2026-08-18**
>
> The original `plan.md` was destroyed on 2026-08-18 when `setup-plan.sh` was run
> while `.specify/feature.json` still pinned this feature directory; it wrote the
> blank plan template over this file. `/specs/` was gitignored at the time, so no
> version-controlled copy existed.
>
> This document has been rebuilt from the surviving Phase 0/1 artifacts in this
> directory (`spec.md`, `research.md`, `data-model.md`, `contracts/`, `quickstart.md`,
> `tasks.md`) and from the shipped implementation in commit `4c9eec9`. The decisions
> and file list are accurate; the prose is not the original author's.
>
> `/specs/` is no longer gitignored, so this cannot recur.

## Summary

Add an optional per-car fuel tank capacity (`tank_size`, liters) and an optional
after-fill gauge reading per fill-up (`tank_percentage`, 0–100), then use both to
correct the average fuel-efficiency statistic for partial fills.

The existing statistic divided total distance by total liters, which over-counted
consumption on two fronts: it included the first fill-up's liters (no distance
precedes it) and the last fill-up's unburned fuel. The refined calculation walks
consecutive fill-ups ordered by odometer and computes consumption as
`liters_added + (level_prev − level_next) × tank_size`, applying the tank-level
correction only where the data supports it and degrading cleanly where it does not.

Capacity is made editable through a new profile-edit endpoint (`PUT /api/auth/user`)
rather than a car resource, per product decision — see Research Decision 5.

## Technical Context

**Language/Version**: PHP 8.4 / Laravel 13
**Primary Dependencies**: Laravel Sanctum (token auth), spatie/laravel-permission
(RBAC, `api` guard), spatie/laravel-query-builder (filtering/sorting),
spatie/laravel-activitylog (audit; both new columns covered automatically by the
existing `logOnly(['*'])`)
**Storage**: PostgreSQL. Two additive nullable columns, no backfill.
**Testing**: N/A — no automated tests per project convention at the time (spec
Assumptions). Verification is manual via `quickstart.md`.
**Target Platform**: Linux server (Laravel Forge), API-only backend consumed by a
Flutter mobile client
**Project Type**: REST API, no versioning, JSON responses via API Resources
**Performance Goals**: Statistics computed in-process over one car's fill-ups;
no pagination or streaming concerns at expected volumes (tens to low hundreds of
rows per car)
**Constraints**: Full backward compatibility (SC-006) — every request that
succeeded before must succeed unchanged. Both new fields are optional everywhere.
**Scale/Scope**: 2 migrations, 2 models, 4 Form Requests, 2 controllers, 2 API
Resources, 1 repository method, 1 new route.

## Constitution Check

*GATE: passed before Phase 0 and re-checked after Phase 1.*

| Principle | Compliance |
|---|---|
| **I. Repository Pattern (non-negotiable)** | ✅ The statistic stays in `FillUpRepositoryEloquent::statistics()`. `AuthController::updateProfile` reads and writes through `UserRepository` / `CarRepository` — no Eloquent queries in controllers. |
| **II. Form Request validation & authorization** | ✅ No inline `$request->validate()`. New `UpdateProfileRequest`; `tank_size` / `tank_percentage` rules added to the existing `RegisterUserRequest`, `StoreFillUpRequest`, `QuickFillUpRequest`. |
| **III. BaseController response methods** | ✅ `updateProfile` returns through `success()`; car and user are serialized by `CarResource` / `UserResource`. No raw `response()->json()`. |
| **IV. Standard Laravel folder structure** | ✅ No new directories. Models gain only `$fillable` entries and casts — no business logic. |
| **V. Authorization via Policies** | ⚠️ Deliberate exception, justified below. |

**Constitution deviation — no policy for profile edit.** Principle V requires
authorization via registered policies. `PUT /api/auth/user` introduces none:
`UpdateProfileRequest::authorize()` requires only an authenticated user. This is
sound because the endpoint has no route-model binding and can only ever act on
`$request->user()` and that user's own car — there is no object to authorize
against and no way to target another user's record. Adding a `CarPolicy` and a
per-car endpoint was explicitly rejected as product scope (Research Decision 5).

## Project Structure

### Documentation (this feature)

```text
specs/008-tank-size-fuel-usage/
├── plan.md              # This file
├── research.md          # Phase 0 — 6 decisions (formula, degradation, guards,
│                        #   column types, profile-edit endpoint, percentage role)
├── data-model.md        # Phase 1 — column definitions and computation rules
├── quickstart.md        # Phase 1 — manual verification walkthrough
├── contracts/
│   └── api-endpoints.md # Phase 1 — request/response deltas for 4 endpoints
├── checklists/
└── tasks.md             # Phase 2 — /speckit-tasks output
```

A supporting design document exists at
`docs/superpowers/specs/2026-06-02-tank-size-fuel-usage-design.md`.

### Source Code (repository root)

Standard Laravel layout; this feature adds no new directories.

```text
database/migrations/
├── 2026_06_02_000002_add_tank_size_to_cars_table.php          # NEW
└── 2026_06_02_000003_add_tank_percentage_to_fill_ups_table.php # NEW

app/
├── Models/
│   ├── Car.php                                 # + tank_size fillable & cast
│   └── FillUp.php                              # + tank_percentage fillable & cast
├── Http/
│   ├── Requests/
│   │   ├── Auth/RegisterUserRequest.php        # + tank_size rule
│   │   ├── Auth/UpdateProfileRequest.php       # NEW
│   │   ├── FillUp/StoreFillUpRequest.php       # + tank_percentage rule
│   │   └── FillUp/QuickFillUpRequest.php       # + tank_percentage rule
│   ├── Controllers/
│   │   ├── Auth/AuthController.php             # + updateProfile()
│   │   └── FillUpController.php                # pass tank_percentage through
│   └── Resources/
│       ├── CarResource.php                     # + tank_size
│       └── FillUpResource.php                  # + tank_percentage
└── Repositories/Eloquent/
    └── FillUpRepositoryEloquent.php            # statistics() rewritten

routes/api.php                                  # + PUT auth/user
```

**Structure Decision**: Single Laravel application, no new architectural layer. The
feature is a set of additive column, validation, serialization and calculation
changes threaded through the existing request → Form Request → controller →
repository → resource pipeline.

### Implementation sequence

1. **Migrations** — both additive and nullable; safe to deploy ahead of code.
2. **Models** — `$fillable` + `decimal:2` casts. Activity logging is inherited.
3. **Validation** — `tank_size` (`nullable|numeric|min:0.1|max:999`) on register and
   profile edit; `tank_percentage` (`nullable|numeric|min:0|max:100`) on both fill-up
   paths.
4. **Profile-edit endpoint** — `UpdateProfileRequest`, `AuthController::updateProfile`,
   route. Resolves the caller's car via `CarRepository::findWhereFirst(['user_id' => …])`
   under the single-car-per-user assumption.
5. **Serialization** — expose both fields.
6. **Statistics** — rewrite `statistics()` per the formula, including the new
   `total_distance_km` key.
7. **Manual verification** — follow `quickstart.md`.

Steps 1–3 are prerequisites for the rest; 4, 5 and 6 are independent of one another
and map to User Stories 1, 2 and 3 respectively.

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| No policy for `PUT auth/user` (Principle V) | The endpoint has no route-model binding and acts only on `$request->user()` and that user's own car; there is no object to authorize against. | A dedicated `PATCH cars/{car}` with a `CarPolicy` was rejected as product scope — capacity is edited as part of the profile, and a per-car endpoint would add a policy and permission surface that was not wanted (Research Decision 5). |
| Single-car-per-user assumption in `updateProfile` | Keeps the profile-edit payload free of a `car_id`, matching the product model where a car is created at registration. | Requiring `car_id` was deferred rather than rejected; revisit if multi-car support lands. Storing `tank_size` on the User instead was rejected outright — capacity is inherently per-car and would break the fuel math. |
