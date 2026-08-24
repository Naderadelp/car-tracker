---

description: "Revised-scope task list for Service & Maintenance Catalog (005-service-maintenance-catalog)"
---

# Tasks: Service & Maintenance Catalog *(Revised Scope — 2026-05-05)*

**Input**: Design documents from `/specs/005-service-maintenance-catalog/`
**Prerequisites**: plan.md (revised), spec.md, research.md (Decisions 15–20), data-model.md, contracts/api-endpoints.md

**State**: The original tasks file (50 tasks) was completed in the prior `/speckit-implement` run. Migrations, models, repositories, policies, controllers (ServiceCenter:index, UpcomingService, Item full CRUD), and routes for the original scope are live. **This regenerated task list covers the delta work** introduced by the revised plan:

1. Relax every FK on the four feature tables to **nullable + nullOnDelete**.
2. Add `services.car_id` and `services.user_id` columns; teach `Service` to be either catalogue (`user_id IS NULL`) or user-owned (`user_id` set).
3. Override `ServiceRepository::scopeToUser` and rewrite `upcomingForCar` to merge catalogue + per-user services.
4. Rewrite `ServicePolicy` for owner-vs-permission split.
5. Add full CRUD controllers/requests for **ServiceCenter** (`store`, `show`, `update`, `destroy`) and **Service** (`index`, `show`, `store`, `storeCatalogue`, `update`, `destroy`), plus the `POST /api/cars/{car}/services` user-creation route.

**Tests**: Spec does not request automated tests; none are generated. Validation is manual via tinker smoke checks (the `quickstart.md` recipes still apply for read paths).

**Organization**: User-story labels:
- **US1** — *View upcoming maintenance* — original story; the revised `upcomingForCar` query lands here.
- **US4** *(new)* — *User creates / edits / deletes their own upcoming services*.
- **US5** *(new)* — *Admin / super-user manage the brand catalogue (services + service centers)*.

US2 (nearby service centers) and US3 (items CRUD) ship unchanged from the original implementation; no new tasks for them.

## Format: `[ID] [P?] [Story?] Description`

- **[P]**: Different file, no dependency on incomplete tasks → safe to run in parallel.
- **[Story]**: US1 / US4 / US5 (omitted for Setup, Foundational, Polish).
- File paths are absolute-from-repo-root.

## Path Conventions

Standard Laravel layout (unchanged from original tasks.md).

---

## Phase 1: Setup

**Purpose**: Confirm branch + acknowledge that `migrate:fresh` will wipe development data.

- [X] T001 Verify branch `005-service-maintenance-catalog` is checked out (`git branch --show-current`)
- [X] T002 If you have valuable seed data in MySQL, dump it (`mysqldump …`). Phase 2 runs `php artisan migrate:fresh` which destroys all tables — greenfield strategy permits this per spec § Assumptions

---

## Phase 2: Foundational delta (Blocking Prerequisites)

**Purpose**: Reshape the schema, model, repository, and policy to support user-owned services + nullable FKs. ALL story phases depend on this.

**⚠️ CRITICAL**: No User Story phase may begin until Phase 2 is complete and `migrate:fresh` has succeeded.

### Migration rewrites

- [X] T003 [P] Rewrite `database/migrations/2026_05_05_000006_create_service_centers_table.php` so `brand_id` is `nullable()->constrained('brands')->nullOnDelete()`
- [X] T004 [P] Rewrite `database/migrations/2026_05_05_000007_create_services_table.php`: flip `car_model_id` to `nullable()->constrained('car_models')->nullOnDelete()`; add `foreignId('car_id')->nullable()->constrained('cars')->nullOnDelete()`; add `foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()`; keep the `(car_model_id, km)` index and add `(car_id, km)` and `(user_id)` indexes
- [X] T005 [P] Rewrite `database/migrations/2026_05_05_000009_create_service_items_table.php`: flip `service_id` and `item_id` to `nullable()->constrained(...)->nullOnDelete()` (the existing `car_id` already uses this pattern)

### Model + repository + policy updates

- [X] T006 [P] Update `app/Models/Service.php`: add `'car_id'` and `'user_id'` to `$fillable`; add `belongsTo(Car::class)` relation as `car()`; add `belongsTo(User::class)` relation as `user()`; add `protected $appends = ['is_catalogue']` and `getIsCatalogueAttribute(): bool { return is_null($this->user_id); }`; add `use App\Models\User;` and `use Illuminate\Database\Eloquent\Relations\BelongsTo;` imports as needed
- [X] T007 Update `app/Repositories/Eloquent/ServiceRepositoryEloquent.php`: expand `$allowedIncludes` to `['carModel', 'carModel.brand', 'car', 'user', 'items']`; expand `$allowedFiltersExact` to `['car_model_id', 'car_id', 'user_id']`; add `scopeToUser()` override that gates `whereNull('user_id')->orWhere('user_id', auth()->id())` for non-admins (no scope when `auth()->user()->isAdmin()`); rewrite `upcomingForCar(Car $car)` to merge catalogue rows (`car_model_id = $car->car_model_id AND user_id IS NULL AND car_id IS NULL`) with this user's own rows (`car_id = $car->id AND user_id = $car->user_id`), then `WHERE km > $car->current_km`, `withCount('items')`, `orderBy('km')` (depends on T006)
- [X] T008 [P] Rewrite `app/Policies/ServicePolicy.php` per data-model.md table:
  - `viewAny(User $user, ?Car $car = null): bool` — keep ownership-or-`index-service`-permission check when `$car` provided; return `true` when no car (catalogue listing — `scopeToUser` enforces row-level visibility)
  - `view(User $user, Service $service): bool` — `$service->user_id === null || $service->user_id === $user->id || $user->hasPermissionTo('show-service')`
  - `create(User $user, ?Car $car = null): bool` — when `$car` is provided, `$user->id === $car->user_id`; otherwise `$user->hasPermissionTo('create-service')`
  - `update(User $user, Service $service): bool` — user-svc: owner or `edit-service`; catalogue: `edit-service`
  - `delete(User $user, Service $service): bool` — user-svc: owner or `destroy-service`; catalogue: `destroy-service`
- [X] T009 [P] Verify `app/Policies/ServiceCenterPolicy.php` exposes `view(User, ServiceCenter): bool` returning `true` (browsing the dealership directory is open to authenticated users). Add the method if missing — the original implementation only declared `viewAny`, `create`, `update`, `delete`

### Apply schema + reset caches

- [X] T010 Run `php artisan migrate:fresh` to drop all tables and replay migrations with the new constraints (depends on T003, T004, T005). **This wipes development data.**
- [X] T011 Run `php artisan db:seed --class=Database\\Seeders\\RolePermissionsSeeder` (or `php artisan sync:permissions`) to repopulate roles + permissions (depends on T010)

**Checkpoint**: Schema, model, repo, policies all reshaped. User-story phases may now begin.

---

## Phase 3: User Story 1 *(revised)* — Upcoming Services merges catalogue + user-owned (Priority: P1)

**Goal**: `GET /api/cars/{car}/upcoming-services` now returns BOTH the brand catalogue rows AND the requesting user's own custom services for THIS car, all filtered to `km > current_km` and ordered ascending.

**Independent Test**: Seed a car at 35 000 km, one catalogue service for the model at 40 000 km, and one user-created service for the same car at 45 000 km. Hit the endpoint as the car's owner → expect both rows in order. As another user → 403.

### Implementation for User Story 1 (revised)

- [X] T012 [US1] Smoke-test `ServiceRepository::upcomingForCar` via tinker:
  ```bash
  php artisan tinker --execute="
  \$user  = \App\Models\User::firstOrCreate(['email' => 'us1-revised@example.com'], ['name' => 'X', 'password' => bcrypt('p')]);
  \$brand = \App\Models\Brand::firstOrCreate(['name' => 'TestBrandUS1']);
  \$model = \App\Models\CarModel::firstOrCreate(['brand_id' => \$brand->id, 'name' => 'TestModelUS1']);
  \$car   = \App\Models\Car::firstOrCreate(['user_id' => \$user->id, 'brand_id' => \$brand->id, 'car_model_id' => \$model->id], ['current_km' => 35000, 'has_warranty' => false]);
  \App\Models\Service::create(['car_model_id' => \$model->id, 'km' => 40000, 'price' => 1250]);                                              // catalogue
  \App\Models\Service::create(['car_id' => \$car->id, 'user_id' => \$user->id, 'km' => 45000, 'price' => 600]);                              // user-owned
  auth()->login(\$user);
  \$repo = app(\App\Repositories\Contracts\ServiceRepository::class);
  \$rows = \$repo->upcomingForCar(\$car);
  echo 'count=' . \$rows->count() . PHP_EOL;
  foreach (\$rows as \$r) { echo '  km=' . \$r->km . ' user_id=' . (\$r->user_id ?? 'NULL') . ' is_catalogue=' . (\$r->is_catalogue ? 'true':'false') . PHP_EOL; }
  \$brand->forceDelete();
  \App\Models\User::where('email', 'us1-revised@example.com')->forceDelete();
  "
  ```
  Expect `count=2`, with the catalogue (40 000) listed before the user-owned (45 000) and `is_catalogue` correctly toggling per row (depends on T007)

**Checkpoint**: Revised upcoming-services merge confirmed.

---

## Phase 4: User Story 4 *(new)* — User-Owned Service CRUD (Priority: P2)

**Goal**: A car owner can `POST /api/cars/{car}/services` to add a personal upcoming-maintenance milestone, then `GET`, `PATCH`, and `DELETE` it via `/api/services/{service}`. Cross-user attempts are denied; admins can override.

**Independent Test**: As user A, owner of car X: create a user-service for car X (`POST /api/cars/X/services`) → 201. As user B (no relation to car X): repeat → 403. As user A: PATCH that service's `km` → 200. As user A: DELETE → 200. The deleted row is gone from `GET /api/cars/X/upcoming-services`.

### Implementation for User Story 4

- [X] T013 [P] [US4] Create `app/Http/Requests/Service/StoreServiceRequest.php`. Dual-mode `authorize()`:
  ```php
  $car = $this->route('car');
  if ($car) {
      return $this->user()->id === $car->user_id
          && $this->user()->can('create', [\App\Models\Service::class, $car]);
  }
  return $this->user()->can('create-service');
  ```
  Dual-mode `rules()`:
  ```php
  return [
      'km'           => ['required', 'integer', 'min:0'],
      'price'        => ['required', 'numeric', 'min:0'],
      'car_model_id' => [$this->route('car') ? 'nullable' : 'required', 'exists:car_models,id'],
  ];
  ```
- [X] T014 [P] [US4] Create `app/Http/Requests/Service/UpdateServiceRequest.php`. `authorize()` returns `$this->user()->can('update', $this->route('service'))`. `rules()` returns the same fields prefixed with `'sometimes'` — drop the catalogue-vs-user discriminator on update (the row's existing `user_id` already determines write rules via the policy)
- [X] T015 [US4] Create `app/Http/Controllers/ServiceController.php` extending `BaseController`. Constructor injects `ServiceRepository`. Implement actions per data-model.md:
  - `index(Request $request): JsonResponse` — `$this->authorize('viewAny', Service::class)`; `$this->serviceRepository->spatie()->paginate()`; return `paginated(..., ServiceResource::class)`
  - `show(Request $request, Service $service): JsonResponse` — `$this->authorize('view', $service)`; return `success(new ServiceResource($service))`
  - `store(StoreServiceRequest $request, Car $car): JsonResponse` — DB transaction; `$service = $this->serviceRepository->create([...$request->validated(), 'car_id' => $car->id, 'user_id' => auth()->id()])`; return `success(new ServiceResource($service), 201, 'Custom service created.')`
  - `storeCatalogue(StoreServiceRequest $request): JsonResponse` — DB transaction; `$service = $this->serviceRepository->create($request->validated())`; return `success(new ServiceResource($service), 201, 'Catalogue service created.')`
  - `update(UpdateServiceRequest $request, Service $service): JsonResponse` — DB transaction; `$updated = $this->serviceRepository->update($request->validated(), $service->id)`; return `success(new ServiceResource($updated))`
  - `destroy(Request $request, Service $service): JsonResponse` — `$this->authorize('delete', $service)`; `$this->serviceRepository->delete($service->id)`; return `success([], 200, 'Service deleted.')`. Imports: `Service`, `Car`, `Request`, `JsonResponse`, `DB`, `ServiceRepository`, `ServiceResource`, `StoreServiceRequest`, `UpdateServiceRequest` (depends on T007, T008, T013, T014)
- [X] T016 [US4] Add to `routes/api.php` inside the `auth:sanctum` group:
  ```php
  Route::post  ('cars/{car}/services',         [ServiceController::class, 'store']);     // user create
  Route::get   ('services/{service}',          [ServiceController::class, 'show']);
  Route::match (['put', 'patch'], 'services/{service}', [ServiceController::class, 'update']);
  Route::delete('services/{service}',          [ServiceController::class, 'destroy']);
  ```
  (depends on T015)
- [X] T017 [US4] Smoke-test US4 via tinker — POST as owner (201), POST as non-owner (403), PATCH (200), DELETE (200), confirm row is gone from upcomingForCar (depends on T016)

**Checkpoint**: User-owned service CRUD live.

---

## Phase 5: User Story 5 *(new)* — Admin Catalogue + Service Center CRUD (Priority: P3)

**Goal**: Users with `*-service` and `*-service-center` permissions (admin / super-user / explicitly-granted) can CRUD the brand catalogue and dealership directory.

**Independent Test**: As admin: create a service center for a brand → 201; show → 200; update → 200; delete → 200. Repeat for catalogue services via `/api/services` — including create-without-`car_id` (`storeCatalogue` path). As a regular user without `*-service-center` permission: every write returns 403.

### Implementation for User Story 5

- [X] T018 [P] [US5] Create `app/Http/Requests/ServiceCenter/StoreServiceCenterRequest.php`. `authorize() => $this->user()->can('create', \App\Models\ServiceCenter::class)`. `rules()`:
  ```php
  return [
      'name'    => ['required', 'string', 'max:255'],
      'address' => ['required', 'string', 'max:500'],
      'open_at' => ['required', 'date_format:H:i,H:i:s'],
      'close_at'=> ['required', 'date_format:H:i,H:i:s', 'after:open_at'],
      'mobile'  => ['required', 'string', 'max:50'],
      'lat'     => ['required', 'numeric', 'between:-90,90'],
      'lng'     => ['required', 'numeric', 'between:-180,180'],
  ];
  ```
- [X] T019 [P] [US5] Create `app/Http/Requests/ServiceCenter/UpdateServiceCenterRequest.php`. `authorize() => $this->user()->can('update', $this->route('serviceCenter'))`. Rules same as Store but prefixed `'sometimes'`
- [X] T020 [US5] Add `show`, `store`, `update`, `destroy` actions to `app/Http/Controllers/ServiceCenterController.php`:
  - `show(Brand $brand, ServiceCenter $serviceCenter)` — `$this->authorize('view', $serviceCenter)`; `abort_if($serviceCenter->brand_id !== $brand->id, 404)`; `$serviceCenter->load('brand')`; return `success(new ServiceCenterResource($serviceCenter))`
  - `store(StoreServiceCenterRequest $request, Brand $brand)` — DB transaction; `$sc = $this->serviceCenterRepository->create([...$request->validated(), 'brand_id' => $brand->id])`; return `success(new ServiceCenterResource($sc), 201, 'Service center created.')`
  - `update(UpdateServiceCenterRequest $request, Brand $brand, ServiceCenter $serviceCenter)` — `abort_if($serviceCenter->brand_id !== $brand->id, 404)`; DB transaction; `$updated = $this->serviceCenterRepository->update($request->validated(), $serviceCenter->id)`; return `success(new ServiceCenterResource($updated))`
  - `destroy(Brand $brand, ServiceCenter $serviceCenter)` — `$this->authorize('delete', $serviceCenter)`; `abort_if($serviceCenter->brand_id !== $brand->id, 404)`; `$this->serviceCenterRepository->delete($serviceCenter->id)`; return `success([], 200, 'Service center deleted.')`. Imports for `StoreServiceCenterRequest`, `UpdateServiceCenterRequest`, `ServiceCenter`, `DB` (depends on T009, T018, T019)
- [X] T021 [US5] Add to `routes/api.php` inside the `auth:sanctum` group, beneath the existing `brands/{brand}/service-centers` index:
  ```php
  Route::post  ('brands/{brand}/service-centers',                   [ServiceCenterController::class, 'store']);
  Route::get   ('brands/{brand}/service-centers/{serviceCenter}',   [ServiceCenterController::class, 'show']);
  Route::match (['put','patch'], 'brands/{brand}/service-centers/{serviceCenter}', [ServiceCenterController::class, 'update']);
  Route::delete('brands/{brand}/service-centers/{serviceCenter}',   [ServiceCenterController::class, 'destroy']);
  ```
- [X] T022 [US5] Add catalogue-service routes to `routes/api.php`:
  ```php
  Route::get   ('services',         [ServiceController::class, 'index']);
  Route::post  ('services',         [ServiceController::class, 'storeCatalogue']);
  ```
  (`show` / `update` / `destroy` were already registered in T016; the `index` and catalogue `store` close the set)
- [X] T023 [US5] Smoke-test US5 via tinker as admin user: create+show+update+delete a service center (verify 201/200/200/200); create+update+delete a catalogue service via `/api/services` (verify 201/200/200). Repeat as a regular user → expect 403 on every write (depends on T021, T022)

**Checkpoint**: Admin catalogue + service-center CRUD live.

---

## Phase 6: Polish

**Purpose**: Verify the new constraints, scopes, and route map.

- [X] T024 Verify nullable+nullOnDelete on brand: hard-delete a test brand, confirm its `service_centers` rows survive with `brand_id = NULL` (NOT cascade-deleted). Repeat for `car_models`: deleting a model leaves catalogue services with `car_model_id = NULL`. Repeat for cars: deleting a car leaves user-services with `car_id = NULL`
- [X] T025 [P] Verify `ServiceRepository::scopeToUser`: as user A, `services` table has both A's user-services AND B's user-services AND catalogue rows. The repo's `spatie()->paginate()` should return only catalogue + A's rows (NOT B's). As admin, all three groups appear
- [X] T026 [P] Run `php artisan route:list | grep -E 'services|service-centers'` and confirm exactly **12 routes** related to this feature: 1 upcoming-services + 5 service-center CRUD + 5 catalogue-service CRUD + 1 user-service create
- [X] T027 [P] Run `php -l` against every file touched in this delta: 3 migrations, `Service.php`, `ServiceRepositoryEloquent.php`, `ServicePolicy.php`, `ServiceCenterPolicy.php` (if T009 added a method), 4 form requests (`Store/UpdateServiceCenterRequest`, `Store/UpdateServiceRequest`), `ServiceController.php`, `ServiceCenterController.php`, `routes/api.php` — expect all clean
- [X] T028 [P] Run `php artisan tinker --execute="echo \App\Models\Permission::where('name','like','%-service')->orWhere('name','like','%-service-center')->pluck('name')->implode(',');"` and confirm the original 14 service permissions are still present (no schema changes for permissions; `migrate:fresh` + reseed should restore them via T011)
- [ ] T029 Spot-check the policy fork: as a regular non-admin user, attempt `PATCH /api/services/{some-other-user-service-id}` → 403; attempt to PATCH a catalogue service without `edit-service` permission → 403; admin attempts both → 200

---

## Dependencies & Execution Order

### Phase Dependencies

- Phase 1 → Phase 2: Setup before foundational delta.
- Phase 2 (T003–T011): blocks every story phase. T003/T004/T005 [P] in parallel; T006/T007/T008/T009 [P] in parallel; T010/T011 sequential at the end.
- Phase 3 (US1 verify): only depends on T007.
- Phase 4 (US4): depends on T007 + T008 + T013 + T014.
- Phase 5 (US5): depends on T009 + T018 + T019 + T020 + T015 (T015 lives in US4 because it builds the controller used by US5 catalogue routes too — the controller is shared).
- Phase 6: depends on every preceding phase that ships.

### Cross-story coupling

- `ServiceController` is built once in T015 (US4 phase) and consumed by both US4 (user routes) and US5 (catalogue routes via the `index`/`storeCatalogue` actions). Build it in US4; US5 just adds new routes pointing at existing methods.
- `routes/api.php` is touched by both US4 (T016) and US5 (T021, T022). Sequence the route blocks so `services/{service}` appears AFTER `services/current` if any literal-segment routes are added (none in this feature).

### Parallel Opportunities

- Phase 2 migrations: T003, T004, T005 [P].
- Phase 2 model/repo/policy: T006, T008, T009 [P]; T007 follows T006.
- Phase 4: T013, T014 [P]; both block T015.
- Phase 5: T018, T019 [P]; both block T020.
- Phase 6: T025, T026, T027, T028 [P].

---

## Parallel Example: Phase 2 Foundational Delta

```bash
# Migrations (all [P]):
Task: "Rewrite create_service_centers_table migration"   # T003
Task: "Rewrite create_services_table migration"          # T004
Task: "Rewrite create_service_items_table migration"     # T005

# Model + policy (all [P]):
Task: "Update Service model with car/user/is_catalogue"  # T006
Task: "Rewrite ServicePolicy with owner-vs-permission"   # T008
Task: "Add ServiceCenterPolicy::view if missing"         # T009

# Then T007 (depends on T006), T010 (migrate:fresh), T011 (sync permissions).
```

## Parallel Example: User Story 4

```bash
# Form Requests in parallel:
Task: "Create StoreServiceRequest"   # T013
Task: "Create UpdateServiceRequest"  # T014

# Then T015 (controller), T016 (routes), T017 (smoke).
```

---

## Implementation Strategy

### MVP (Revised Scope)

For this delta, the MVP is **US4** — the headline new value is "user creates their own next service." Ship in this order:

1. Phase 1 + Phase 2 (foundational delta).
2. Phase 3 (verify upcoming merge).
3. Phase 4 (user-service CRUD).
4. **STOP and demo** — a car owner can now plan their own custom maintenance.

### Incremental Delivery

5. Phase 5 (admin catalogue CRUD).
6. Phase 6 (polish + verification).

### Single-Dev Critical Path

T003–T011 (foundational, sequential at the apply step) → T012 (US1 verify) → T013–T017 (US4) → T018–T023 (US5) → T024–T029 (polish). Roughly half a day for a focused single-dev pass.

---

## Notes

- [P] = different file, no dependency on incomplete tasks.
- [Story] label maps task to user-story phase — US1 (revised), US4 (new), US5 (new). US2 / US3 are unchanged from the original implementation.
- The original tasks.md is preserved here in regenerated form; the prior 50-task list is now superseded by the 29 delta tasks above. Prior `[X]` markers on the originals do not transfer — those tasks are no longer in scope.
- `php artisan migrate:fresh` (T010) DESTROYS development data. Back up first if the dev DB carries seed work.
- The two new `services` columns (`car_id`, `user_id`) ride on the same migration file (`2026_05_05_000007_create_services_table.php`) — there is no separate `alter_*` migration, per the project's greenfield strategy.
- No automated tests are generated (spec did not request them); rely on tinker smoke checks. The `quickstart.md` recipes still apply for read paths and item CRUD (US2 / US3 unchanged).
