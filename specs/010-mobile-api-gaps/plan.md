# Implementation Plan: Close the CarLog Mobile API Gaps

**Branch**: `010-mobile-api-gaps` | **Date**: 2026-08-24 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/010-mobile-api-gaps/spec.md`

## Summary

Close all 21 items in `docs/mobile-api-gaps.md` plus the 3 compliance items in
its section 5, so the CarLog Flutter app can be backed by this service instead of
by device-local storage.

The work divides into two new resources (a unified cost ledger and a fault log),
one missing controller-and-policy pair for cars, a set of column additions across
seven existing tables, three aggregate endpoints, and a one-method fix to
`BaseController` that normalises every response shape in the project at once.

Phase 0 research changed the sizing in both directions: three of the gap
report's items are near-trivial once you read the code, three are materially
bigger than described, and one item nobody listed — `GET /cars/{car}/documents`
throwing on Postgres — is **already broken in production** and is fixed here.

## Technical Context

**Language/Version**: PHP 8.4.24 / Laravel 13.26
**Primary Dependencies**: Laravel Sanctum, spatie/laravel-permission, spatie/laravel-query-builder v7, spatie/laravel-medialibrary, spatie/laravel-activitylog
**Storage**: PostgreSQL (production and local development); sqlite `:memory:` for the test suite
**Testing**: PHPUnit via `php artisan test` — `tests/Feature` and `tests/Unit`
**Target Platform**: Linux server behind Laravel Forge; consumed by a Flutter mobile client
**Project Type**: JSON API service (no server-rendered frontend beyond the Filament admin panel)
**Performance Goals**: insight endpoints must answer in a single request regardless of history length — no client-side pagination sweeps (SC-007)
**Constraints**: no URL versioning (constitution, non-negotiable); page size fixed at 15; the API guide `docs/mobile-api.md` is a deliverable of this feature, not a follow-up
**Scale/Scope**: one car per driver; 2 new tables, 9 altered tables, ~14 new endpoints, 10 user stories

## Constitution Check

*GATE: evaluated before Phase 0 and re-evaluated after Phase 1 design.*

| Principle | Status | How this feature complies |
|---|---|---|
| **I — Repository Pattern** | PASS | `CostRepository` and `IssueRepository` get contracts, Eloquent implementations, and bindings in `RepositoryServiceProvider`. Both models carry `user_id`, so both override `scopeToUser()`. Both declare `$allowedDefaultSorts` — `-spent_at` and `-occurred_at`, the domain's natural order, which the principle explicitly permits over `-id`. |
| **II — Form Request validation** | PASS | Every new write path gets a Form Request under `app/Http/Requests/{Domain}/`. `authorize()` delegates to `$this->user()->can()`. No inline `$request->validate()`. |
| **III — BaseController responses** | PASS, with intent | This feature *changes* `success()` — but toward the principle, not away from it. Today it discards `$message` and returns resources unwrapped; afterwards it honours both. Every response still goes through an API Resource. |
| **IV — Folder structure** | PASS | New code lands in the existing directories. `app/Observers/` is not named in the principle's tree but already exists (`TripObserver`) — see Complexity Tracking. |
| **V — Policies** | PASS | `CostPolicy` and `IssuePolicy` created; **`CarPolicy` created — it is missing today** (R1). All three registered via `Gate::policy()` in `AppServiceProvider`. No `before()` on any policy. Store/update authorize in the Form Request; index/show/destroy/custom in the controller. |
| **VI — Transactions & observability** | PASS | Every multi-step write is wrapped in a transaction — notably cost carry-across, which touches two tables. `Issue` implements `HasMedia` with a `->singleFile()->useDisk('local')` collection and a `StreamedResponse` download, mirroring `Document`. |
| **API Routing (no versioning)** | PASS | All new routes sit directly under the `api` prefix. No `/v1/`, no `Api\V1\` namespace. |

**Gate result: PASS.** Two deviations are recorded in Complexity Tracking below;
neither is a violation of a non-negotiable principle.

## Project Structure

### Documentation (this feature)

```text
specs/010-mobile-api-gaps/
├── plan.md              # This file
├── spec.md              # Requirements, 10 prioritised stories, decisions D1–D3
├── research.md          # Phase 0 — 12 findings, several resize the work
├── data-model.md        # Phase 1 — 2 new tables, 9 altered
├── quickstart.md        # Phase 1 — how to run and verify this feature
├── contracts/           # Phase 1 — endpoint contracts
│   └── endpoints.md
├── checklists/
│   └── requirements.md  # Spec quality checklist (all passing)
└── tasks.md             # Phase 2 — created by /speckit-tasks, NOT by this command
```

### Source code (repository root)

```text
app/
├── Http/
│   ├── Controllers/
│   │   ├── BaseController.php              # MODIFIED — success() wraps + honours $message; error() casts errors to object
│   │   ├── CarController.php               # NEW — show, update (US1)
│   │   ├── CostController.php              # NEW — index, store, show, update, destroy (US4)
│   │   ├── IssueController.php             # NEW — CRUD + photo download (US5)
│   │   ├── StatisticsController.php        # NEW — period report (US9)
│   │   ├── ValuationController.php         # NEW — derived present value (US9, D1)
│   │   ├── FillUpController.php            # MODIFIED — accept odometer/station/fuel type; unify quick() shape
│   │   ├── UpcomingServiceController.php   # MODIFIED — whole schedule, items included
│   │   ├── ServiceController.php           # MODIFIED — accept driver's own checklist lines
│   │   ├── CarLogController.php            # MODIFIED — title/workshop/category/notes
│   │   ├── TripController.php              # MODIFIED — timings and top speed
│   │   ├── ParkingRecordController.php     # MODIFIED — address + update action
│   │   ├── DocumentController.php          # MODIFIED — file optional, attach later
│   │   └── Auth/AuthController.php         # MODIFIED — me() returns car; destroy(); quiet forgot-password
│   ├── Requests/{Car,Cost,Issue,FillUp,Service,CarLog,Trip,ParkingRecord,Document,Auth}/
│   └── Resources/                          # CostResource, IssueResource + edits to existing
├── Models/
│   ├── Cost.php                            # NEW
│   ├── Issue.php                           # NEW — implements HasMedia
│   └── (Car, FillUp, CarLog, Trip, ParkingRecord, Item, ServiceCenter, User — fillable/casts updated)
├── Observers/
│   ├── FillUpObserver.php                  # NEW — carry fuel spend into the ledger (D2)
│   ├── CarLogObserver.php                  # NEW — carry service spend into the ledger (D2)
│   └── TripObserver.php                    # existing — precedent for the pattern
├── Policies/
│   ├── CarPolicy.php                       # NEW — does not exist today (R1)
│   ├── CostPolicy.php                      # NEW
│   └── IssuePolicy.php                     # NEW
├── Repositories/
│   ├── Contracts/{CostRepository,IssueRepository}.php
│   └── Eloquent/
│       ├── CostRepositoryEloquent.php      # NEW
│       ├── IssueRepositoryEloquent.php     # NEW
│       ├── DocumentRepositoryEloquent.php  # MODIFIED — ISNULL() → portable form (R11)
│       ├── ServiceRepositoryEloquent.php   # MODIFIED — whole schedule, items eager-loaded
│       └── FillUpRepositoryEloquent.php    # MODIFIED — per-record efficiency series
└── Providers/
    ├── AppServiceProvider.php              # MODIFIED — 3 Gate::policy() + 2 observer registrations
    └── RepositoryServiceProvider.php       # MODIFIED — 2 bindings

bootstrap/app.php                           # MODIFIED — withExceptions() normalises the errors field (R5)
database/migrations/                        # 11 new migrations
database/seeders/RolePermissionsSeeder.php  # MODIFIED — + 'cost', 'issue'
routes/api.php                              # MODIFIED — car, costs, issues, statistics, valuation, parking update
docs/mobile-api.md                          # MODIFIED — re-issued; this feature changes documented shapes
tests/Feature/                              # new suites per story + a sweep for the response-shape change
```

**Structure Decision**: standard Laravel MVC under `app/`, exactly as Principle IV
requires. No new top-level directories. The only structural addition is two files
in `app/Observers/`, which already exists.

## Implementation Phases

Phases are ordered by **engineering dependency**, which is not the same as the
spec's priority order. The one place they disagree is called out immediately
below, because it matters.

### Phase A — Response contract and the live production bug

*Spec stories: US10 (P3) · Research: R3, R4, R5, R11*

**Why a P3 story runs first.** US10 changes the shape of every response in the
project. Doing it last would mean writing every test in phases B–J against the
old shape and then rewriting all of them. Doing it first costs nothing, because
the mobile client is not released yet and this service has no other consumer —
the spec records that as an explicit assumption. This is the safest moment this
change will ever have.

1. Fix `DocumentRepositoryEloquent::spatie()` — `ISNULL(...)` → `expiry_date IS NULL ASC, expiry_date ASC`. **This is a live production bug**; `GET /cars/{car}/documents` throws on Postgres today.
2. `BaseController::success()` — always wrap payloads in `data`; include `message` when one is passed.
3. `BaseController::error()` — cast `errors` to an object so it is never `[]`.
4. `bootstrap/app.php` — `withExceptions()` render callback so framework-thrown validation and HTTP exceptions match that shape.
5. Remove the hand-rolled `['message' => ..., 'data' => ...]` wrapper in `FillUpController::quick()`, now redundant.
6. `AuthController::me()` — return the car alongside the user, mirroring `updateProfile()`.
7. Sweep the existing test suite for assertions that depend on the old shapes and update them. **This is the bulk of the phase.**

**Demonstrable**: one decoder handles every collection; one error model handles
every failure; documents list without throwing on Postgres.

### Phase B — The car record

*Spec stories: US1 (P1) · Research: R1, R12*

8. Migration: `cars` gains `color`, `purchase_price_egp`, `purchased_at`.
9. **`CarPolicy`** — create it and register it. It does not exist today, which is why there has never been a car write endpoint.
10. `UpdateCarRequest` with `authorize()` delegating to `$this->user()->can('update', $this->route('car'))`.
11. `CarController@show` and `@update`; routes `GET /cars/{car}` and `PUT /cars/{car}`.
12. A manual `current_km` change fires `OdometerAdvanced`, the event trips already fire, so existing listeners keep working (R12).
13. Accept `color` at registration.

Per decision **D3**, a downward correction is accepted and nothing recalculates
history. No permission or seeder work — `'car'` is already in `$models` (R1).

**Demonstrable**: a driver corrects their odometer and the next fill-up records
the corrected value.

### Phase C — Records the app already collects

*Spec stories: US2 (P1) · Research: R2*

14. `StoreDocumentRequest` — `document_file` becomes `nullable`, `expiry_date` relaxes from `after:today` to `date`.
15. Confirm the expired state is derived from the date rather than gated by validation, and that attaching a file later works.
16. Migration: `fill_ups` gains `station_name`. `fuel_type` already exists (R2) — only `store()` fails to accept it.
17. `StoreFillUpRequest` accepts `odometer`, `station_name`, `fuel_type`, all optional; the controller prefers a supplied odometer over `$car->current_km` (FR-010).

**Demonstrable**: the two forms that are refused today both save.

### Phase D — Account lifecycle

*Spec stories: US3 (P1) · Research: R7*

18. Migration: soft deletes on `users`.
19. `DELETE /auth/user` — soft-delete the account, revoke every Sanctum token, cascade to the driver's records and stored media, inside a transaction.
20. `ForgotPasswordRequest` — drop `exists:users,email`; make `OtpService::send()` return quietly for an unknown address.
21. Assert the known-address and unknown-address responses are **byte-identical** (SC-005). This depends on Phase A, since today both are `[]` for the wrong reason (R4).

**Demonstrable**: in-app account deletion, which is an app-store review
requirement, and no account-enumeration oracle.

### Phase E — The unified cost ledger

*Spec stories: US4 (P2) · Research: R10 · Decision: D2*

**The largest phase in the feature.** Size it accordingly — decision D2 asked for
considerably more than the manual ledger the gap report describes.

22. Migration: `costs`, per `data-model.md`, including the unique `(source_type, source_id)`.
23. `Cost` model, `CostRepository` contract, `CostRepositoryEloquent` with `scopeToUser()`, binding, `CostPolicy`, registration.
24. Form Requests and `CostController` — full CRUD.
25. `FillUpObserver` and `CarLogObserver` — create, update and delete the carried-across row, **skipping any row whose `amount_overridden` is true** (FR-046).
26. Overriding a carried-across amount sets `amount_overridden` (FR-045).
27. Deleting a source record whose row was overridden clears `source_type`/`source_id` and keeps the row as a manual entry — the edge case named in the spec.
28. Category totals and shares for the Costs tab.
29. Seeder: `'cost'` into `$models`; run `sync:permissions`.

**Demonstrable**: a fill-up appears in the Costs tab without being typed twice;
the driver overrides its amount; editing the fill-up afterwards leaves the
override alone.

### Phase F — The fault log

*Spec stories: US5 (P2)*

30. Migration: `issues`, per `data-model.md`.
31. `Issue` model implementing `HasMedia` with a `photo` collection — `->singleFile()->useDisk('local')`.
32. Repository + `scopeToUser()` + binding + `IssuePolicy` + registration.
33. Form Requests, `IssueController` CRUD, resolve action, and a `StreamedResponse` photo download with its own `secureDownload` policy method and permission, mirroring `Document` (Principle VI).
34. Serious unresolved faults join the attention list alongside overdue services (FR-021).
35. Seeder: `'issue'` into `$models`; `sync:permissions`.

### Phase G — The whole service schedule

*Spec stories: US6 (P2) · Research: R8, R9*

36. Migration: `service_items` gains nullable `name` and `price` overrides (R8) — without this there is nowhere to put a driver's own line.
37. `ServiceRepositoryEloquent::upcomingForCar()` — `withCount('items')` → `with('items')`, and make the `km > current_km` filter conditional so passed intervals can be returned, each marked passed / current / upcoming.
38. `ServiceResource` resolves each line's name and price as override-then-catalogue.
39. Service create/update accept `items[] = [{name, price}]`.

### Phase H — History keeps its detail

*Spec stories: US7 (P2)*

40. Migration: `car_logs` gains `title`, `workshop`, `category`, `notes` — all nullable so existing rows survive.
41. Migration: `trips` gains `started_at`, `ended_at`, `duration_seconds`, `max_speed_kmh`.
42. Migration: `parking_records` gains `address`.
43. Form Requests and resources updated for all three.
44. `PUT /cars/{car}/parking-records/{parkingRecord}` — the resource is create/delete only today.

### Phase I — Arabic

*Spec stories: US8 (P3)*

45. Migration: `items.name_ar`; `service_centers.name_ar`, `address_ar`. `name_ar` is deliberately not unique.
46. Resources return both variants, falling back to the Latin value when the Arabic one is null (FR-030).
47. Admin write paths and the Filament resources accept the Arabic fields.

### Phase J — Insights

*Spec stories: US9 (P3) · Decision: D1*

48. Migration for `cars.purchase_price_egp` / `purchased_at` already landed in Phase B.
49. `StatisticsController` — period spend split by source, distance, fill-up count, average fuel price, cost per km, plus the previous period for comparison and weekly buckets (FR-031, FR-032). Aggregation lives in the repositories, not the controller.
50. Per-record efficiency on the fill-up index, reusing the existing tank-percentage-corrected arithmetic (FR-033). Computed, not stored — see `data-model.md`.
51. `ValuationController` — present value derived from purchase figure, age and mileage, **labelled an estimate**, with no external data source (decision D1). Comparable listings are out of scope.

### Phase K — Documentation and the verification sweep

52. Re-issue `docs/mobile-api.md`. Phase A changed shapes it currently documents, so leaving it is worse than not having it.
53. Update `docs/mobile-api-gaps.md` to record what closed.
54. Full suite green; every acceptance scenario in `spec.md` exercised; `sync:permissions` run.
55. Production checklist — see `quickstart.md`. `sync:permissions` has **still never been run on production**, and account deletion and the new permissions both depend on it.

## Complexity Tracking

| Violation | Why needed | Simpler alternative rejected because |
|---|---|---|
| Observers (`FillUpObserver`, `CarLogObserver`) write to a second table outside the repository layer | Decision D2 requires fuel and service spending to appear in the cost ledger without the driver re-entering it, and to stay in step as source records change | A read-time union of `fill_ups` and `car_logs` needs no observers and cannot drift — but it has nowhere to store a driver's override, which is exactly what FR-045 requires. `TripObserver` is the existing precedent for this pattern in this codebase (R10). |
| `BaseController::success()` is modified, changing the response shape of every endpoint at once | C1, C2 and C4 in the gap report are three symptoms of this one method; fixing them at the call sites would mean touching every controller and leaving the root cause | Per-endpoint patches keep the blast radius small per commit, but guarantee the inconsistency returns with the next controller anyone writes. The client is unreleased, so the blast radius is genuinely small right now (R3). |

## Risks

- **Phase A touches everything.** The test sweep in step 7 is the real cost of the phase, not steps 2–4. Budget for it.
- **The cost ledger can drift.** Two observers, an override flag and a unique constraint are three chances to end up with a ledger that disagrees with its sources. The unique `(source_type, source_id)` index is the backstop — it makes a duplicate row impossible at the database level rather than by convention.
- **`sync:permissions` has never run on production.** Two new permission sets land in this feature. If it is not run there, every new endpoint 403s for non-admins while working perfectly in development.
- **`php artisan config:cache` must never be run in this repository.** A stale `bootstrap/cache/config.php` overrides `phpunit.xml`, pointing `RefreshDatabase` at the real Postgres database. This has already destroyed the local database once in this project's history. Verify the file is absent before every test run.
