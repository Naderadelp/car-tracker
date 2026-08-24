---
description: "Task list for Tank Size & Percentage-Based Fuel Usage"
---

# Tasks: Tank Size & Percentage-Based Fuel Usage

**Input**: Design documents from `/specs/008-tank-size-fuel-usage/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/api-endpoints.md

**Tests**: None. This project has no automated test suite (`Testing: N/A`); verification is manual per `quickstart.md`.

**Organization**: Tasks are grouped by user story (P1 → P2 → P3). US1 and US2 are fully independent; US3 (refined statistics) depends on the columns/casts introduced in US1 and US2.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependency on an incomplete task)
- **[Story]**: US1 / US2 / US3

## Path Conventions

Flat Laravel layout at repo root (`app/`, `database/`, `routes/`) per plan.md.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Confirm baseline; no new dependencies.

- [X] T001 Confirm work is on branch `008-tank-size-fuel-usage` and that no Composer dependencies are required (no change to `composer.json`); run `git branch --show-current`.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: None. There is no cross-cutting blocking work — each story carries its own migration and model change. US3 depends on US1 + US2 (documented below), not on a separate foundational phase.

**Checkpoint**: Proceed directly to user stories.

---

## Phase 3: User Story 1 - Record and update tank capacity (Priority: P1) 🎯 MVP

**Goal**: A car can carry an optional full-tank capacity (liters), provided at signup and editable later through profile edit (`PUT auth/user`, which also updates the user's name); the value is visible in the car payload.

**Independent Test**: Register a car with `tank_size` (and one without) → both succeed and `car.tank_size` reflects input; `PUT auth/user` with `name`/`tank_size` updates the user's name and their car's capacity; an unauthenticated call → `401`.

### Implementation for User Story 1

- [X] T002 [P] [US1] Create migration `database/migrations/2026_06_02_000002_add_tank_size_to_cars_table.php` adding `$table->decimal('tank_size', 6, 2)->nullable()->after('current_km');` with a matching `down()` that drops the column.
- [X] T003 [US1] In `app/Models/Car.php`, add `'tank_size'` to `$fillable` and `'tank_size' => 'decimal:2'` to the `casts()` array.
- [X] T004 [P] [US1] In `app/Http/Resources/CarResource.php`, add `'tank_size' => $this->tank_size,` to the returned array.
- [X] T005 [P] [US1] In `app/Http/Requests/Auth/RegisterUserRequest.php`, add rule `'tank_size' => ['nullable', 'numeric', 'min:0.1', 'max:999']`.
- [X] T006 [US1] In `app/Http/Controllers/Auth/AuthController.php` `register()`, add `'tank_size' => $request->tank_size,` to the `carRepository->create([...])` array. (Depends on T003, T005.)
- [X] T007 [P] [US1] Create `app/Http/Requests/Auth/UpdateProfileRequest.php`: `authorize()` returns `$this->user() !== null`; `rules()` returns `['name' => ['sometimes','required','string','max:255'], 'tank_size' => ['sometimes','nullable','numeric','min:0.1','max:999']]`.
- [X] T008 [US1] In `app/Http/Controllers/Auth/AuthController.php`, add `updateProfile(UpdateProfileRequest $request): JsonResponse`: in a `DB::beginTransaction()/commit()` (rollback + `$this->error(...)` on exception), update the user's `name` when `filled('name')`, and when `has('tank_size')` resolve the user's car via `carRepository->findWhereFirst(['user_id' => $user->id])` and update its `tank_size`; then return `$this->success(['user' => new UserResource($user), 'car' => $car ? new CarResource($car) : null], 200, 'Profile updated successfully.')`. Import `UpdateProfileRequest`. (Depends on T003, T004.)
- [X] T009 [US1] In `routes/api.php`, add `Route::put('user', [AuthController::class, 'updateProfile']);` inside the `auth` → `auth:sanctum` group, right after the `GET user` (`me`) route. (Depends on T008.)

**Checkpoint**: Tank capacity can be set at signup, edited via profile edit, and is returned in car payloads. US1 is independently testable.

---

## Phase 4: User Story 2 - Record tank level after a fill-up (Priority: P2)

**Goal**: Both manual and quick fill-ups accept an optional after-fill `tank_percentage` (0–100); it is stored and returned. Existing liters/amount handling is untouched.

**Independent Test**: Create a fill-up with `tank_percentage` (and one without) → both succeed and the value is returned; `-1` or `101` → `422`.

### Implementation for User Story 2

- [X] T010 [P] [US2] Create migration `database/migrations/2026_06_02_000003_add_tank_percentage_to_fill_ups_table.php` adding `$table->decimal('tank_percentage', 5, 2)->nullable()->after('liters');` with a matching `down()` that drops the column.
- [X] T011 [US2] In `app/Models/FillUp.php`, add `'tank_percentage'` to `$fillable` and `'tank_percentage' => 'decimal:2'` to the `casts()` array.
- [X] T012 [P] [US2] In `app/Http/Resources/FillUpResource.php`, add `'tank_percentage' => $this->tank_percentage,` to the returned array.
- [X] T013 [P] [US2] In `app/Http/Requests/FillUp/StoreFillUpRequest.php`, add rule `'tank_percentage' => ['nullable', 'numeric', 'min:0', 'max:100']`.
- [X] T014 [P] [US2] In `app/Http/Requests/FillUp/QuickFillUpRequest.php`, add rule `'tank_percentage' => ['nullable', 'numeric', 'min:0', 'max:100']`.
- [X] T015 [US2] In `app/Http/Controllers/FillUpController.php`, add `'tank_percentage' => $request->tank_percentage,` to the `fillUpRepository->create([...])` arrays in BOTH `store()` and `quick()`. (Depends on T011; T013/T014 for validated input.)

**Checkpoint**: After-fill percentage is captured on both fill-up paths and returned. US2 is independently testable.

---

## Phase 5: User Story 3 - See accurate average fuel efficiency (Priority: P3)

**Goal**: `statistics()` reports a partial-fill-corrected average in km/L plus total distance, degrading gracefully when tank data is absent.

**Independent Test**: With ≥2 fills + tank data, `average_consumption` matches `Σ distance / Σ (liters_next + (f_prev − f_next)·tank_size)` within 0.01; with no tank data it returns a finite non-zero value excluding the first fill's liters; with <2 fills or non-positive consumption it returns `"0.00"`.

**Depends on**: T002+T003 (tank_size) and T010+T011 (tank_percentage) — both columns and casts must exist.

### Implementation for User Story 3

- [X] T016 [US3] Rewrite `FillUpRepositoryEloquent::statistics(int $carId): array` in `app/Repositories/Eloquent/FillUpRepositoryEloquent.php`: fetch the car's `tank_size`; load the car's fill-ups ordered by `odometer` asc (columns `odometer, liters, tank_percentage`, plus aggregate `total_fill_ups`/`total_cost_egp`); walk consecutive pairs computing `consumed_i = liters_{i+1} + (f_i − f_{i+1}) × tank_size` (correction term only when both `tank_percentage` values and `tank_size` are non-null, else 0) and `distance_i = odo_{i+1} − odo_i`; set `average_consumption = round(Σdistance / Σconsumed, 2)` formatted to 2dp, guarding `<2` fills or `Σconsumed ≤ 0` → `"0.00"`. Return existing keys (`total_fill_ups`, `total_cost_egp`, `average_consumption`) plus new `total_distance_km` (int, `max_odometer − min_odometer`). Keep the explicit `app($this->model())->newQuery()->where('car_id', $carId)` access pattern (constitution).

**Checkpoint**: All three stories functional. Refined statistics visible on `GET cars/{car}/fill-ups`.

---

## Phase 6: Polish & Cross-Cutting Concerns

- [X] T017 Run `php artisan migrate` and confirm both new columns exist (`cars.tank_size`, `fill_ups.tank_percentage`). Done — both columns present; `php -l` clean on all changed files; `PUT api/auth/user` route registered.
- [ ] T018 [P] Execute the `quickstart.md` manual verification checklist against a running server (register ±tank_size, `PUT auth/user` name/tank_size, fill-ups ±percentage + range validation, statistics across the data/no-data cases). Pending live run.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: none.
- **Foundational (Phase 2)**: empty (no blocking cross-cutting work).
- **US1 (Phase 3)** and **US2 (Phase 4)**: independent of each other; either can be the first slice. US1 is the MVP.
- **US3 (Phase 5)**: depends on T002+T003 (US1) and T010+T011 (US2).
- **Polish (Phase 6)**: after the desired stories are complete.

### Within US1

- T002 (migration), T004 (resource), T005 (register rule), T007 (UpdateProfileRequest) are independent ([P]).
- T006 depends on T003 + T005. T008 depends on T003 + T004 (+T007 request). T009 depends on T008.

### Within US2

- T010, T012, T013, T014 are independent ([P]). T011 (model) before T015 (controller persist).

### Parallel Opportunities

- US1: T002, T004, T005, T007 can run together.
- US2: T010, T012, T013, T014 can run together.
- US1 and US2 can be developed fully in parallel by different people.

---

## Parallel Example: User Story 1

```text
# Independent US1 tasks (different files, no incomplete dependency):
Task: T002 migration add tank_size to cars
Task: T004 add tank_size to CarResource
Task: T005 add tank_size rule to RegisterUserRequest
Task: T007 create UpdateProfileRequest
```

---

## Implementation Strategy

### MVP First (User Story 1)

1. Phase 1 Setup → 2. US1 (Phase 3) → 3. **STOP & VALIDATE** tank capacity set/edit/visible.

### Incremental Delivery

US1 (capacity) → US2 (percentage capture) → US3 (refined km/L). Each adds value without breaking the previous.

---

## Notes

- **No per-task commits** — per project convention a single final commit is made by the user at the end of the feature.
- `[P]` = different files, no dependency on an incomplete task.
- No new policy or permission — profile edit (`PUT auth/user`) only touches the caller's own user + car.
- Migrations are additive + nullable → full backward compatibility (SC-006).
- Verify manually per `quickstart.md`; there is no automated test suite.
