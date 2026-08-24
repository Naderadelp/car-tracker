# Tasks: Logbook & Telemetry

**Input**: Design documents from `/specs/003-logbook-telemetry/`
**Branch**: `003-logbook-telemetry`
**Plan**: [plan.md](plan.md) | **Spec**: [spec.md](spec.md) | **Data model**: [data-model.md](data-model.md) | **Contracts**: [contracts/api-endpoints.md](contracts/api-endpoints.md)

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no blocking dependencies)
- **[Story]**: User story label (US1–US5) — foundational tasks carry no story label
- Exact file paths in every description

---

## Phase 1: Foundational (Blocking Prerequisites)

**Purpose**: All database, model, repository, policy, and permission scaffolding that every user story depends on. No story work can begin until this phase is complete.

**⚠️ CRITICAL**: Complete all tasks in this phase before moving to any user story phase.

- [X] T001 Create migration `database/migrations/2026_05_03_000003_create_fill_ups_table.php` — columns: id, car_id (FK→cars cascadeOnDelete), liters decimal(8,2), odometer integer, cost_egp decimal(10,2), fill_date date, timestamps
- [X] T002 Create migration `database/migrations/2026_05_03_000004_create_trips_table.php` — columns: id, car_id (FK→cars cascadeOnDelete), start_time datetime, end_time datetime, start_lat decimal(10,8), start_lng decimal(11,8), end_lat decimal(10,8), end_lng decimal(11,8), total_distance_km decimal(8,2), timestamps
- [X] T003 [P] Create `app/Models/FillUp.php` — table `fill_ups`, fillable: [car_id, liters, odometer, cost_egp, fill_date], casts: fill_date→date / liters→decimal:2 / cost_egp→decimal:2, `car(): BelongsTo` → Car, uses LogsActivity (getActivitylogOptions → logOnly ['*'])
- [X] T004 [P] Create `app/Models/Trip.php` — table `trips`, fillable: [car_id, start_time, end_time, start_lat, start_lng, end_lat, end_lng, total_distance_km], casts: start_time/end_time→datetime / lat/lng→decimal:8 / total_distance_km→decimal:2, `car(): BelongsTo` → Car, uses LogsActivity
- [X] T005 Update `app/Models/Traits/CarRelations.php` — add `fillUps(): HasMany` → FillUp and `trips(): HasMany` → Trip (import both models and HasMany)
- [X] T006 [P] Create `app/Repositories/Contracts/FillUpRepository.php` — extends RepositoryInterface, adds `statistics(int $carId): array` method signature
- [X] T007 [P] Create `app/Repositories/Contracts/TripRepository.php` — extends RepositoryInterface, no additional methods
- [X] T008 [P] Create `app/Repositories/Eloquent/FillUpRepositoryEloquent.php` — model(): FillUp::class, allowedFiltersExact: ['car_id'], allowedSorts: ['fill_date', 'cost_egp', 'liters', 'odometer'], allowedDefaultSorts: ['-fill_date'], scopeToUser(): whereHas('car', fn($q) => $q->where('user_id', auth()->id())), statistics(int $carId): aggregate query using `app($this->model())->newQuery()->where('car_id', $carId)->selectRaw('COUNT(*) as total_fill_ups, COALESCE(SUM(cost_egp),0) as total_cost_egp, COALESCE(SUM(liters),0) as total_liters, COALESCE(MAX(odometer),0) as max_odometer, COALESCE(MIN(odometer),0) as min_odometer')->first()` — returns array with total_fill_ups, total_cost_egp, average_consumption (= (max_odometer−min_odometer)/total_liters if total_liters > 0 else 0, rounded 2dp)
- [X] T009 [P] Create `app/Repositories/Eloquent/TripRepositoryEloquent.php` — model(): Trip::class, allowedFiltersExact: ['car_id'], allowedSorts: ['start_time', 'total_distance_km'], allowedDefaultSorts: ['-start_time'], scopeToUser(): whereHas('car', fn($q) => $q->where('user_id', auth()->id()))
- [X] T010 Update `app/Providers/RepositoryServiceProvider.php` — add use imports for FillUpRepository, FillUpRepositoryEloquent, TripRepository, TripRepositoryEloquent; add two bind() calls in register()
- [X] T011 [P] Create `app/Policies/FillUpPolicy.php` — viewAny(User, Car): bool ($user->id === $car->user_id || hasPermissionTo('index-fill-up')); create(User, Car): bool ($user->id === $car->user_id || hasPermissionTo('create-fill-up')); delete(User, FillUp): bool ($user->id === $fillUp->car->user_id || hasPermissionTo('destroy-fill-up'))
- [X] T012 [P] Create `app/Policies/TripPolicy.php` — viewAny(User, Car): bool ($user->id === $car->user_id || hasPermissionTo('index-trip')); create(User, Car): bool ($user->id === $car->user_id || hasPermissionTo('create-trip'))
- [X] T013 Update `app/Providers/AppServiceProvider.php` — add use imports for FillUp, Trip, FillUpPolicy, TripPolicy; add Gate::policy(FillUp::class, FillUpPolicy::class) and Gate::policy(Trip::class, TripPolicy::class) in boot()
- [X] T014 [P] Update `app/Repositories/Eloquent/CarRepositoryEloquent.php` — set allowedIncludes: ['brand', 'carModel', 'fillUps', 'trips'], allowedFiltersExact: ['brand_id', 'car_model_id', 'user_id', 'year'], allowedSorts: ['year', 'current_km', 'created_at'], allowedDefaultSorts: ['-id']
- [X] T015 [P] Update `app/Repositories/Eloquent/UserRepositoryEloquent.php` — add allowedIncludes: ['cars', 'documents'] and allowedDefaultSorts: ['-id']
- [X] T016 Update `database/seeders/RolePermissionsSeeder.php` — add 'fill-up' and 'trip' to the $models array (generates index/show/create/edit/destroy/force-delete/restore permissions for each)

**Checkpoint**: Run `php artisan migrate:fresh --seed` — all tables must be created cleanly. Run `php artisan sync:permissions` — fill-up and trip permissions must appear in the DB.

---

## Phase 2: User Story 1 — Record a Refueling Event (Priority: P1) 🎯 MVP

**Goal**: An authenticated car owner can submit a fill-up record (liters, cost_egp, fill_date). The system auto-snapshots car.current_km as the odometer value. Returns 201 with the saved record.

**Independent Test**: `POST /api/cars/{car}/fill-ups` with `{"liters":40.5,"cost_egp":283.50,"fill_date":"2026-05-01"}` → 201 response; DB row in fill_ups with `odometer = car.current_km`; validation rejects future dates and liters < 0.1.

- [X] T017 Create `app/Http/Requests/FillUp/StoreFillUpRequest.php` — authorize(): $this->user()->can('create', [FillUp::class, $this->route('car')]) ; rules: liters required numeric min:0.1 | cost_egp required numeric min:0 | fill_date required date before_or_equal:today
- [X] T018 [P] Create `app/Http/Resources/FillUpResource.php` — fields: id, car_id, liters, odometer, cost_egp, fill_date (→toDateString()), created_at (→toISOString()), updated_at (→toISOString())
- [X] T019 [US1] Create `app/Http/Controllers/FillUpController.php` — extends BaseController, inject FillUpRepository via constructor; implement `store(StoreFillUpRequest $request, Car $car): JsonResponse`: set odometer = $car->current_km, call repository->create([car_id, liters, odometer, cost_egp, fill_date]), return $this->success(new FillUpResource($fillUp), 201, 'Fill-up recorded successfully.')
- [X] T020 [US1] Add `Route::post('fill-ups', [FillUpController::class, 'store'])` inside `Route::prefix('cars/{car}')` group in `routes/api.php` — also add use import for FillUpController

**Checkpoint**: `POST /api/cars/1/fill-ups` returns 201; `fill_ups` row has correct odometer matching car.current_km.

---

## Phase 3: User Story 2 — View Refueling History & Statistics (Priority: P2)

**Goal**: Authenticated car owner calls `GET /api/cars/{car}/fill-ups` and receives a summary (total_fill_ups, total_cost_egp, average_consumption) plus the ordered fill-up list.

**Independent Test**: Seed 2+ fill-ups with different snapshotted odometers; `GET /api/cars/{car}/fill-ups` returns correct sums and average; list ordered fill_date descending; returns empty summary when no fill-ups exist.

- [X] T021 [US2] Add `index(Request $request, Car $car): JsonResponse` to `app/Http/Controllers/FillUpController.php` — call $this->authorize('viewAny', [FillUp::class, $car]); fetch statistics via $this->fillUpRepository->statistics($car->id); fetch paginated list via ->where('car_id', $car->id)->spatie()->paginate(); return $this->paginated() wrapped inside $this->success() with summary in data (structure: {summary: {...}, fill_ups: paginated_collection})
- [X] T022 [US2] Add `Route::get('fill-ups', [FillUpController::class, 'index'])` inside the `cars/{car}` prefix group in `routes/api.php`

**Checkpoint**: `GET /api/cars/1/fill-ups` returns paginated fill-ups and a summary object with correct values.

---

## Phase 4: User Story 3 — Delete a Refueling Record (Priority: P3)

**Goal**: Authenticated car owner deletes a fill-up. Returns 200. Rejects requests where the fill-up does not belong to the given car (404) or user lacks permission (403).

**Independent Test**: Create a fill-up, call `DELETE /api/cars/{car}/fill-ups/{fillUp}` → 200; row gone from DB. Attempt delete of another user's fill-up → 403.

- [X] T023 [US3] Add `destroy(Request $request, Car $car, FillUp $fillUp): JsonResponse` to `app/Http/Controllers/FillUpController.php` — $this->authorize('delete', $fillUp); abort_if($fillUp->car_id !== $car->id, 404); $this->fillUpRepository->delete($fillUp->id); return $this->success([], 200, 'Fill-up deleted successfully.')
- [X] T024 [US3] Add `Route::delete('fill-ups/{fillUp}', [FillUpController::class, 'destroy'])` inside the `cars/{car}` prefix group in `routes/api.php`

**Checkpoint**: `DELETE /api/cars/1/fill-ups/1` returns 200 and record is removed; cross-car delete attempt returns 404.

---

## Phase 5: User Story 4 — Record a GPS Trip (Priority: P4)

**Goal**: Authenticated car owner submits a coordinate array (min 2 points). Server calculates Haversine distance, stores the trip, and the TripObserver increments car.current_km by round(total_distance_km). Returns 201.

**Independent Test**: `POST /api/cars/{car}/trips` with 3 GPS coordinates → 201 response; trip row in DB with correct start/end values and total_distance_km; car.current_km increased by round(distance).

- [X] T025 [P] Create `app/Http/Requests/Trip/StoreTripRequest.php` — authorize(): $this->user()->can('create', [Trip::class, $this->route('car')]) ; rules: coordinates required array min:2 | coordinates.*.lat required numeric between:-90,90 | coordinates.*.lng required numeric between:-180,180 | coordinates.*.timestamp required date
- [X] T026 [P] Create `app/Http/Resources/TripResource.php` — fields: id, car_id, start_time (→toISOString()), end_time (→toISOString()), start_lat, start_lng, end_lat, end_lng, total_distance_km, created_at (→toISOString()), updated_at (→toISOString())
- [X] T027 Create `app/Observers/TripObserver.php` — `created(Trip $trip): void` method: $car = $trip->car; $car->current_km += (int) round($trip->total_distance_km); $car->save();
- [X] T028 Update `app/Providers/AppServiceProvider.php` — add `use App\Models\Trip; use App\Observers\TripObserver;` and `Trip::observe(TripObserver::class)` in boot()
- [X] T029 [US4] Create `app/Http/Controllers/TripController.php` — extends BaseController, inject TripRepository via constructor; implement private `haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float` (Earth radius 6371 km, standard Haversine formula); implement `store(StoreTripRequest $request, Car $car): JsonResponse`: wrap in DB::beginTransaction/commit/rollBack; loop coords computing running distance sum; extract first/last coord for start/end; call repository->create([...]); return $this->success(new TripResource($trip), 201, 'Trip recorded successfully.')
- [X] T030 [US4] Add `Route::post('trips', [TripController::class, 'store'])` inside the `cars/{car}` prefix group in `routes/api.php` — also add use import for TripController

**Checkpoint**: `POST /api/cars/1/trips` with valid coordinates → 201; trip row present; car.current_km incremented; fewer than 2 coords → 422.

---

## Phase 6: User Story 5 — View Trip History (Priority: P5)

**Goal**: Authenticated car owner calls `GET /api/cars/{car}/trips` and receives all trips ordered by start_time descending.

**Independent Test**: Seed 2+ trips with different start_times; response list is ordered most-recent-first; trips from another user's car are not visible.

- [X] T031 [US5] Add `index(Request $request, Car $car): JsonResponse` to `app/Http/Controllers/TripController.php` — $this->authorize('viewAny', [Trip::class, $car]); ->where('car_id', $car->id)->spatie()->paginate(); return $this->paginated($trips, TripResource::class)
- [X] T032 [US5] Add `Route::get('trips', [TripController::class, 'index'])` inside the `cars/{car}` prefix group in `routes/api.php`

**Checkpoint**: `GET /api/cars/1/trips` returns trips ordered by start_time DESC; pagination headers present.

---

## Phase 7: Polish & Verification

- [X] T033 Run `php artisan migrate:fresh --seed` — verify all migrations execute without FK errors and BrandSeeder populates brands/car_models
- [X] T034 Run `php artisan sync:permissions` — verify fill-up and trip permissions are created (14 new permissions: 7 per model)
- [X] T035 [P] Verify `routes/api.php` — confirm cars/{car} prefix group contains all 5 new routes (2 fill-up GET/POST, 1 fill-up DELETE, 2 trip GET/POST) and both controllers are imported

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Foundational)**: No dependencies — start immediately
- **Phase 2 (US1)**: Requires Phase 1 complete
- **Phase 3 (US2)**: Requires Phase 1 complete; benefits from Phase 2 (FillUpController exists)
- **Phase 4 (US3)**: Requires Phase 1 complete; benefits from Phase 2 (FillUpController exists)
- **Phase 5 (US4)**: Requires Phase 1 complete
- **Phase 6 (US5)**: Requires Phase 5 complete (TripController must exist)
- **Phase 7 (Polish)**: Requires all phases complete

### User Story Dependencies

- **US1 (P1)**: Needs Phase 1 only — independent
- **US2 (P2)**: Needs Phase 1 + FillUpController from US1 (adds method to same file)
- **US3 (P3)**: Needs Phase 1 + FillUpController from US1 (adds method to same file)
- **US4 (P4)**: Needs Phase 1 only — independent of fill-up stories
- **US5 (P5)**: Needs TripController from US4 (adds method to same file)

### Parallel Opportunities Within Phase 1

T003, T004, T005 can run in parallel (different files).
T006, T007, T008, T009 can run in parallel (different files, no cross-dependency).
T011, T012 can run in parallel (different policy files).
T014, T015 can run in parallel (different repository files).

### Parallel Opportunities Across Stories

US1 and US4 share no files and can be executed in parallel once Phase 1 is done.
US2 and US3 both add methods to FillUpController — must be sequential relative to each other but can be parallel with US4/US5.

---

## Parallel Example: Phase 1

```text
# Batch A (run together):
T003 — app/Models/FillUp.php
T004 — app/Models/Trip.php

# Batch B (run together, after T003/T004 for model imports):
T006 — app/Repositories/Contracts/FillUpRepository.php
T007 — app/Repositories/Contracts/TripRepository.php
T008 — app/Repositories/Eloquent/FillUpRepositoryEloquent.php
T009 — app/Repositories/Eloquent/TripRepositoryEloquent.php
T011 — app/Policies/FillUpPolicy.php
T012 — app/Policies/TripPolicy.php
T014 — app/Repositories/Eloquent/CarRepositoryEloquent.php
T015 — app/Repositories/Eloquent/UserRepositoryEloquent.php

# Sequential (affect shared files):
T005 — CarRelations.php (after T003, T004)
T010 — RepositoryServiceProvider.php (after T006–T009)
T013 — AppServiceProvider.php (after T011, T012)
T016 — RolePermissionsSeeder.php
```

---

## Implementation Strategy

### MVP (US1 only — Record a Fill-Up)

1. Complete Phase 1 (all 16 foundational tasks)
2. Complete Phase 2 (T017–T020)
3. **Validate**: `POST /api/cars/{car}/fill-ups` works end-to-end
4. Deploy/demo

### Full Incremental Delivery

1. Phase 1 → Foundation verified with migrate:fresh + sync:permissions
2. Phase 2 (US1) → Record fill-up
3. Phase 3 (US2) → View fill-up history with stats
4. Phase 4 (US3) → Delete fill-up
5. Phase 5 (US4) → Record trip + auto-odometer update
6. Phase 6 (US5) → View trip history
7. Phase 7 → Final verification
