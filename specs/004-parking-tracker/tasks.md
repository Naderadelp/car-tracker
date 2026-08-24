---

description: "Task list for Parking Tracker (004-parking-tracker)"
---

# Tasks: Parking Tracker

**Input**: Design documents from `/specs/004-parking-tracker/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/api-endpoints.md, quickstart.md

**Tests**: Spec does not request automated tests; none are generated. Validation is manual via `quickstart.md`.

**Organization**: Tasks are grouped by user story (US1–US4) so each can be implemented and verified independently. Foundational tasks (DB / repository / policy / seeder / provider wiring) are completed once before any story phase begins.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Different file, no dependency on incomplete tasks → safe to run in parallel.
- **[Story]**: US1, US2, US3, US4 (omitted for Setup, Foundational, Polish).
- File paths are absolute-from-repo-root.

## Path Conventions

Standard Laravel layout:
- Models: `app/Models/`
- Repositories: `app/Repositories/{Contracts,Eloquent}/`
- Controllers: `app/Http/Controllers/`
- Form Requests: `app/Http/Requests/{Module}/`
- Resources: `app/Http/Resources/`
- Policies: `app/Policies/`
- Migrations: `database/migrations/`
- Seeders: `database/seeders/`
- Routes: `routes/api.php`

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Confirm prerequisites and create empty directories needed by this feature.

- [X] T001 Verify branch `004-parking-tracker` is checked out and prior migrations are applied (`php artisan migrate:status`)
- [X] T002 [P] Create directory `app/Http/Requests/ParkingRecord/` if it does not yet exist

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Database, model, repository skeleton, policy, permissions, and provider wiring. ALL user-story phases depend on this.

**⚠️ CRITICAL**: No User Story phase may begin until Phase 2 is complete.

- [X] T003 Create migration `database/migrations/2026_05_04_000005_create_parking_records_table.php` with columns `id`, `car_id` (FK cascadeOnDelete to `cars`), `name` (nullable string), `description` (nullable text), `latitude` (nullable decimal 10,8), `longitude` (nullable decimal 11,8), `parked_at` (datetime), timestamps, plus an index on `parked_at`
- [X] T004 [P] Create model `app/Models/ParkingRecord.php` with `$table = 'parking_records'`, `$fillable = ['car_id','name','description','latitude','longitude','parked_at']`, casts (`parked_at => datetime`, `latitude => decimal:8`, `longitude => decimal:8`), `belongsTo(Car::class)` relation, `LogsActivity` trait with `$logName = 'ParkingRecord'` and `getActivitylogOptions()` returning `LogOptions::defaults()->logOnly(['*'])`
- [X] T005 [P] Add `parkingRecords(): HasMany → ParkingRecord` to `app/Models/Traits/CarRelations.php`
- [X] T006 [P] Create repository contract `app/Repositories/Contracts/ParkingRecordRepository.php` extending `RepositoryInterface` and declaring `public function current(int $carId): ?\App\Models\ParkingRecord;`
- [X] T007 Create repository implementation `app/Repositories/Eloquent/ParkingRecordRepositoryEloquent.php` extending `EloquentRepository` and implementing `ParkingRecordRepository`. Declare `protected array $allowedIncludes = ['car']`, `protected array $allowedFilters = ['name']`, `protected array $allowedFiltersExact = ['car_id']`, `protected array $allowedSorts = ['parked_at', 'created_at']`, `protected array $allowedDefaultSorts = ['-parked_at']`. Implement `model()` returning `ParkingRecord::class`, `scopeToUser()` using `whereHas('car', fn($q) => $q->where('user_id', auth()->id()))` (gated on `auth()->check() && !auth()->user()->isAdmin()`), and `current(int $carId)` doing `app($this->model())->newQuery()->where('car_id', $carId)->orderByDesc('parked_at')->first()` (depends on T004, T006)
- [X] T008 Bind contract → implementation in `app/Providers/RepositoryServiceProvider.php::register()` via `$this->app->bind(ParkingRecordRepository::class, ParkingRecordRepositoryEloquent::class);` (depends on T007)
- [X] T009 [P] Append `'parkingRecords'` to the `$allowedIncludes` array in `app/Repositories/Eloquent/CarRepositoryEloquent.php` (depends on T005 — relation must exist for include to resolve)
- [X] T010 [P] Create `app/Policies/ParkingRecordPolicy.php` with three methods: `viewAny(User, Car): bool` (`$user->id === $car->user_id || $user->hasPermissionTo('index-parking-record')`), `create(User, Car): bool` (`… || hasPermissionTo('create-parking-record')`), `delete(User, ParkingRecord): bool` (`$user->id === $parkingRecord->car->user_id || hasPermissionTo('destroy-parking-record')`). NO `before()` method (depends on T004)
- [X] T011 Register policy binding `Gate::policy(ParkingRecord::class, ParkingRecordPolicy::class)` in `app/Providers/AppServiceProvider.php::boot()` and add the `use App\Models\ParkingRecord;` and `use App\Policies\ParkingRecordPolicy;` imports (depends on T010)
- [X] T012 Append `'parking-record'` to the `$models` array in `database/seeders/RolePermissionsSeeder.php`
- [X] T013 Run `php artisan migrate` to apply the new migration (depends on T003)
- [X] T014 Run `php artisan sync:permissions` (or `db:seed --class=RolePermissionsSeeder`) to provision the new CRUD permissions (depends on T012, T013)

**Checkpoint**: Schema, repository, policy, permissions, and includes all exist. User-story phases may now begin in parallel.

---

## Phase 3: User Story 1 — Record a Parking Location (Priority: P1) 🎯 MVP

**Goal**: Authenticated car owner can submit a parking record with GPS coordinates, descriptive text, or both, and receive a `201` with the persisted `ParkingRecordResource`. Invalid submissions (no location data, half coords, future timestamp, foreign car) are rejected.

**Independent Test**: `POST /api/cars/{CAR_ID}/parking-records` with the descriptive payload from `quickstart.md` § 2 → expect `201` and the record echoed. Re-run the validation smoke tests from `quickstart.md` § 7 → expect `422` for each. Re-run with another user's car → expect `403`.

### Implementation for User Story 1

- [X] T015 [P] [US1] Create Form Request `app/Http/Requests/ParkingRecord/StoreParkingRecordRequest.php`. `authorize()` returns `$this->user()->can('create', [ParkingRecord::class, $this->route('car')])`. `rules()` returns: `name` (`nullable|string|max:255|required_without_all:latitude,longitude`), `description` (`nullable|string|max:1000`), `latitude` (`nullable|numeric|between:-90,90|required_with:longitude`), `longitude` (`nullable|numeric|between:-180,180|required_with:latitude`), `parked_at` (`required|date|before_or_equal:now`)
- [X] T016 [P] [US1] Create API Resource `app/Http/Resources/ParkingRecordResource.php` returning `id`, `car_id`, `name`, `description`, `latitude`, `longitude`, `parked_at` (`?->toISOString()`), `car` (`new CarResource($this->whenLoaded('car'))`), `created_at` / `updated_at` (`?->toISOString()`)
- [X] T017 [US1] Create controller `app/Http/Controllers/ParkingRecordController.php` extending `BaseController`. Constructor injects `ParkingRecordRepository`. Implement `store(StoreParkingRecordRequest $request, Car $car): JsonResponse` wrapping the call in `DB::beginTransaction()` / `commit()` / `rollBack()`: build the array `['car_id' => $car->id, 'name' => …, 'description' => …, 'latitude' => …, 'longitude' => …, 'parked_at' => …]` from `$request->validated()`, call `$this->parkingRepository->create(...)`, and return `$this->success(new ParkingRecordResource($record), 201, 'Parking location recorded successfully.')` (depends on T015, T016, T007)
- [X] T018 [US1] Register `POST /api/cars/{car}/parking-records` route inside the `auth:sanctum` → `cars/{car}` group in `routes/api.php`, importing `App\Http\Controllers\ParkingRecordController` (depends on T017)
- [ ] T019 [US1] Smoke-test US1 manually with the curl recipes in `specs/004-parking-tracker/quickstart.md` §§ 2, 3, 7, 8 — confirm 201 / 422 / 403 outcomes match expectations (depends on T018)

**Checkpoint**: MVP — a user can record a parking location.

---

## Phase 4: User Story 2 — Find Current Parking Location (Priority: P2)

**Goal**: Authenticated car owner can fetch the most recently recorded parking location for their car as a single object, or receive `404` if no history exists.

**Independent Test**: After at least one record from US1, `GET /api/cars/{CAR_ID}/parking-records/current` returns the latest record (verified by `parked_at`). Against a car with no records → `404` with message "No parking history found." Against another user's car → `403`.

### Implementation for User Story 2

- [X] T020 [US2] Add `current(Request $request, Car $car): JsonResponse` action to `app/Http/Controllers/ParkingRecordController.php`: call `$this->authorize('viewAny', [ParkingRecord::class, $car])`, fetch `$record = $this->parkingRepository->current($car->id)`, `abort_if(is_null($record), 404, 'No parking history found.')`, return `$this->success(new ParkingRecordResource($record), 200)` (depends on T017, T007)
- [X] T021 [US2] Register `GET /api/cars/{car}/parking-records/current` route in `routes/api.php` **BEFORE** the parameterised `{parkingRecord}` routes inside the same `cars/{car}` group, so the literal `current` segment matches first (depends on T020)
- [ ] T022 [US2] Smoke-test US2 manually with `quickstart.md` § 4 — verify the latest record is returned, and confirm 404 against a car with no history (depends on T021)

**Checkpoint**: US1 + US2 both work independently.

---

## Phase 5: User Story 3 — View Full Parking History (Priority: P3)

**Goal**: Authenticated car owner can list all parking records for their car, ordered most-recent first, with optional filtering on `name`, embedding the parent `car`, and overriding sort order via Spatie query params.

**Independent Test**: After two or more records from US1, `GET /api/cars/{CAR_ID}/parking-records` returns them in descending `parked_at` order. `?filter[name]=mall` narrows the list. `?include=car` embeds the car resource. `?sort=parked_at` flips to ascending. Against another user's car → `403`. Against a car with zero records → empty `data` array.

### Implementation for User Story 3

- [X] T023 [US3] Add `index(Request $request, Car $car): JsonResponse` action to `app/Http/Controllers/ParkingRecordController.php`: call `$this->authorize('viewAny', [ParkingRecord::class, $car])`, run `$records = $this->parkingRepository->where('car_id', $car->id)->spatie()->paginate()`, return `$this->paginated($records, ParkingRecordResource::class)` (depends on T017, T007)
- [X] T024 [US3] Register `GET /api/cars/{car}/parking-records` route inside the same `cars/{car}` group in `routes/api.php` (depends on T023)
- [ ] T025 [US3] Smoke-test US3 manually with `quickstart.md` § 5 — verify default ordering (`-parked_at`), `filter[name]`, `include=car`, and `sort=parked_at` ascending (depends on T024)

**Checkpoint**: US1 + US2 + US3 all work independently.

---

## Phase 6: User Story 4 — Remove a Parking Record (Priority: P4)

**Goal**: Authenticated car owner can delete a parking record belonging to their car. Cross-car (correct user, wrong car) and cross-user attempts are rejected.

**Independent Test**: After creating a record under US1, `DELETE /api/cars/{CAR_ID}/parking-records/{ID}` succeeds with `200`. The record is gone from `GET …/parking-records`. Attempting `DELETE` for a record that belongs to a different car than the route's `{car}` → `404`. Attempting `DELETE` against another user's record → `403`.

### Implementation for User Story 4

- [X] T026 [US4] Add `destroy(Request $request, Car $car, ParkingRecord $parkingRecord): JsonResponse` action to `app/Http/Controllers/ParkingRecordController.php`: call `$this->authorize('delete', $parkingRecord)`, `abort_if($parkingRecord->car_id !== $car->id, 404)`, `$this->parkingRepository->delete($parkingRecord->id)`, return `$this->success([], 200, 'Parking record deleted successfully.')` (depends on T017, T010, T007)
- [X] T027 [US4] Register `DELETE /api/cars/{car}/parking-records/{parkingRecord}` route inside the same `cars/{car}` group in `routes/api.php`. Confirm Laravel implicit binding resolves the camelCase `{parkingRecord}` parameter to the `ParkingRecord` model (depends on T026)
- [ ] T028 [US4] Smoke-test US4 manually with `quickstart.md` § 6 — verify 200 on owner delete, 404 on wrong-car delete, 403 on cross-user delete (depends on T027)

**Checkpoint**: All four user stories independently functional.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Final verification, cascade-delete check, and constitution-compliance smoke pass.

- [X] T029 Verify cascade-on-car-delete: hard-delete a test car (or `$car->forceDelete()`) and confirm `parking_records` rows for that `car_id` are gone (covers FR-009 / SC-007)
- [X] T030 [P] Verify cross-user isolation end-to-end: log in as User B, hit each of the four endpoints against User A's car, confirm `403` from policy/route guard (covers FR-008 / SC-006)
- [X] T031 [P] Run `php artisan route:list | grep parking-records` and confirm exactly four routes are present and ordered with `current` ahead of `{parkingRecord}`
- [X] T032 [P] Run `php artisan permission:show` (or query the `permissions` table) and confirm `index-parking-record`, `create-parking-record`, `destroy-parking-record` exist
- [ ] T033 Re-run the full `specs/004-parking-tracker/quickstart.md` end-to-end as a final acceptance pass

---

## Dependencies & Execution Order

### Phase Dependencies

- Phase 1 (Setup): no dependencies.
- Phase 2 (Foundational): depends on Phase 1. **Blocks all user stories.**
- Phase 3–6 (User Stories): all depend on Phase 2. After Phase 2 completes, US1 / US2 / US3 / US4 can be developed in parallel by separate developers.
- Phase 7 (Polish): depends on all user-story phases the team chose to ship.

### User Story Dependencies

- US1 (P1): independent — only requires Phase 2.
- US2 (P2): independent — only requires Phase 2. Reads existing data created via US1 *for verification only*; does not import US1 code.
- US3 (P3): independent — only requires Phase 2. Same: reads US1's data for verification.
- US4 (P4): independent — only requires Phase 2. Same: reads US1's data for verification.

### Within Each User Story

- Models/relations exist from Phase 2 → controller actions can be added in any order.
- Form Request + Resource (US1) are parallelizable with each other; both must exist before the `store` action in T017.
- Routes are added per-action in each story; no cross-story file conflicts because the four routes are independent lines in the same `cars/{car}` group (sequential edits to `routes/api.php` are fine — these are not [P]).

### Parallel Opportunities

- Phase 1: T001 → T002 [P] (T002 has no dependency on T001).
- Phase 2: T004 [P], T005 [P], T006 [P], T009 [P], T010 [P] can all be authored before T007 (which consumes T004 + T006). T012 is independent of the migration/model and can run anywhere before T014.
- Phase 7: T030, T031, T032 [P] are all read-only checks against different surfaces.

---

## Parallel Example: Phase 2 Foundational

```bash
# After T003 (migration file created):
Task: "Create app/Models/ParkingRecord.php"                       # T004
Task: "Add parkingRecords() relation to CarRelations trait"       # T005
Task: "Create app/Repositories/Contracts/ParkingRecordRepository" # T006
Task: "Create app/Policies/ParkingRecordPolicy"                   # T010
Task: "Append 'parking-record' to RolePermissionsSeeder \$models"  # T012

# Then T007 (consumes T004 + T006), T009 (consumes T005),
# T011 (consumes T010), T008 (consumes T007).
# Finally T013 (migrate) → T014 (sync permissions).
```

## Parallel Example: User Story 1

```bash
# T015 and T016 don't share a file and have no order dependency:
Task: "Create app/Http/Requests/ParkingRecord/StoreParkingRecordRequest.php"  # T015
Task: "Create app/Http/Resources/ParkingRecordResource.php"                   # T016

# Then T017 (controller store action), T018 (route), T019 (smoke).
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Phase 1: Setup (T001–T002).
2. Phase 2: Foundational (T003–T014). **Blocks everything else.**
3. Phase 3: User Story 1 (T015–T019).
4. **STOP and validate** — run `quickstart.md` §§ 2, 3, 7, 8.
5. Demo / merge-able MVP.

### Incremental Delivery

1. Foundational ready → ship US1 (record a location) → demo.
2. Add US2 (current) → demo.
3. Add US3 (history list) → demo.
4. Add US4 (delete) → demo.
5. Run Phase 7 polish before final merge to `main`.

### Parallel Team Strategy

Once Phase 2 is complete:

- Dev A: US1 (T015–T019)
- Dev B: US2 (T020–T022)
- Dev C: US3 (T023–T025)
- Dev D: US4 (T026–T028)

All four touch the same `routes/api.php` and the same `ParkingRecordController.php`, so route registrations and controller-action additions need to be sequenced (e.g. one merge at a time, or each developer rebases before adding their action). No other file conflicts.

---

## Notes

- [P] = different file, no dependency on incomplete tasks.
- [Story] label maps task to its user-story phase for traceability (US1 / US2 / US3 / US4).
- The `routes/api.php` and `ParkingRecordController.php` files are touched by US1–US4; do NOT mark route or controller tasks `[P]` across stories.
- The `current` literal route MUST be registered before `{parkingRecord}` (T021 before T027 if added in the same edit).
- `php artisan sync:permissions` is idempotent; running it more than once is safe.
- Validation smoke checks live in `quickstart.md` § 7; cross-user denial smoke check in § 8.
- No automated tests are generated (spec did not request them); rely on `quickstart.md` recipes for verification.
