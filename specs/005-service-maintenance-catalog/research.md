# Research: Service & Maintenance Catalog

## Decision 1 — Reconcile field names with the existing schema
- **Decision**: `services.car_model_id` (NOT `model_id`) and `cars.current_km` (NOT `current_mileage`).
- **Rationale**: The existing `cars` table already uses `car_model_id` and `current_km`. The CarModel relation on `Car` is `carModel()`. Using `model_id` / `current_mileage` would either require a duplicate column rename across the project or break compatibility with `Car`, `Brand`, and `CarModel` consumers (Document, Trip, FillUp, ParkingRecord). The user's brief explicitly authorises rewriting earlier `create_*_table` migrations, but no rename is needed here — the existing names already fit.
- **Alternatives considered**: Add the new fields under the brief's names → rejected, churn across the codebase with no value.

## Decision 2 — Constitution-required corrections to the user's brief
The user's brief embeds three constitution violations:
1. URL prefix `/api/v1/` — forbidden by § "API Routing Convention" (no URL versioning).
2. Controller namespace `App\Http\Controllers\Api\V1\…` — forbidden by § IV (flat domain namespacing).
3. Form Requests at `App\Http\Requests\StoreItemRequest` (root) — § IV requires `App\Http\Requests\{Module}\…`.

- **Decision**: Routes register under `/api/brands/{brand}/service-centers`, `/api/cars/{car}/upcoming-services`, `/api/items`. Controllers live at `App\Http\Controllers\{ServiceCenter,UpcomingService,Item}Controller`. Form Requests live at `App\Http\Requests\Item\{Store,Update}ItemRequest`.
- **Rationale**: Constitution principles I, IV, V, and § "API Routing Convention" are NON-NEGOTIABLE. The brief's structure was carried over from a generic Laravel template that does not match this project's conventions.
- **Alternatives considered**: Add a one-off `Api\V1\` exemption → rejected, opens the door to inconsistent routing across modules.

## Decision 3 — Repository pattern (per constitution § I)
- **Decision**: Create three new repository pairs:
  - `ServiceCenterRepository` / `ServiceCenterRepositoryEloquent`
  - `ServiceRepository` / `ServiceRepositoryEloquent`
  - `ItemRepository` / `ItemRepositoryEloquent`
  Each binds in `RepositoryServiceProvider::register()`.
- **Rationale**: Constitution § I forbids direct Eloquent access from controllers. Three new entities = three new repository pairs. Each repository declares the full Spatie property set per the user's prompt (`make sure to add the needed filters and includes`).
- **Alternatives considered**: Skip repositories for the item CRUD ("it's just standard CRUD") → rejected, NON-NEGOTIABLE per the constitution.

## Decision 4 — `scopeToUser()` is NOT overridden for any of the three new repositories
- **Decision**: None of `ServiceCenterRepository`, `ServiceRepository`, `ItemRepository` override `scopeToUser()`.
- **Rationale**: Constitution § I carves this out: *"Repositories for system-wide resources (Role, Permission) do NOT override this."* Service centers, maintenance milestones, and parts are global catalogue data — every authenticated user reads the same rows. There is no `user_id` column on any of the four new tables.
- **Alternatives considered**: Scope `service_items` by car when `car_id` is set → rejected, the pivot row is not exposed as a top-level resource; ownership of a pivot row through a car is enforced at write time only (via the future write endpoint, out of scope for this feature).

## Decision 5 — Spatie includes / filters / sorts (per user prompt)
- **Decision**:
  - **`ServiceCenterRepositoryEloquent`**:
    ```php
    protected array $allowedIncludes      = ['brand'];
    protected array $allowedFilters       = ['name', 'address'];   // partial
    protected array $allowedFiltersExact  = ['brand_id'];
    protected array $allowedFilterScopes  = [];
    protected array $allowedSorts         = ['name', 'created_at'];
    protected array $allowedDefaultSorts  = ['-id'];
    ```
    *(The `index` action overrides ordering with the Haversine SQL — see Decision 7.)*
  - **`ServiceRepositoryEloquent`**:
    ```php
    protected array $allowedIncludes      = ['carModel', 'carModel.brand', 'items'];
    protected array $allowedFilters       = [];
    protected array $allowedFiltersExact  = ['car_model_id'];
    protected array $allowedFilterScopes  = [];
    protected array $allowedSorts         = ['km', 'price', 'created_at'];
    protected array $allowedDefaultSorts  = ['km'];                // ascending — upcoming-services UX
    ```
  - **`ItemRepositoryEloquent`**:
    ```php
    protected array $allowedIncludes      = ['services'];
    protected array $allowedFilters       = ['name'];              // partial
    protected array $allowedFiltersExact  = [];
    protected array $allowedFilterScopes  = [];
    protected array $allowedSorts         = ['name', 'price', 'created_at'];
    protected array $allowedDefaultSorts  = ['-id'];
    ```
- **Rationale**: Constitution § I requires every repository to declare a default sort and (for filtered/included queries) the relevant property arrays. The defaults reflect the dominant access pattern: service milestones are virtually always read newest-km-first as "upcoming," service centers are read distance-ordered (overridden at the controller), parts are admin-managed so `-id` (newest-first) is fine. Including `carModel.brand` lets a single GET on `/api/items` or `/api/services` walk the full ownership chain in one round trip.
- **Alternatives considered**: Provide partial-name filter on `services` → rejected, services are identified by `(car_model, km)` rather than name; `withCount('items')` exposed as a Spatie include — rejected, withCount is computed at query time inside the upcoming-services repo method, not via Spatie's include machinery.

## Decision 6 — Domain methods on the three repositories
Some queries are too specific to express through the generic `where(...)->spatie()->paginate()` chain. Each gets a named contract method.

- **Decision**:
  - `ServiceCenterRepository::nearby(int $brandId, float $lat, float $lng): \Illuminate\Support\Collection` — Haversine distance + ordering (see Decision 7).
  - `ServiceRepository::upcomingForCar(\App\Models\Car $car): \Illuminate\Support\Collection` — returns services where `car_model_id = $car->car_model_id AND km > $car->current_km`, sorted by `km ASC`, with `withCount('items')`.
  - `ItemRepository::nameExists(string $name, ?int $exceptId = null): bool` — used by `UpdateItemRequest` to check uniqueness while ignoring the current row. *(Optional — the standard `Rule::unique('items', 'name')->ignore(...)` already does this without touching the repository. This method is included only if a future caller wants to bypass Form Request validation.)*
- **Rationale**: Aggregate / spatial / cross-condition queries belong in the repository (Constitution § I forbids raw Eloquent in controllers). `upcomingForCar` and `nearby` mirror precedent (`FillUpRepository::statistics`, `ParkingRecordRepository::current`).
- **Alternatives considered**: Push Haversine into a service class → rejected, no service layer exists in this project (decision affirmed during the Trip-controller Haversine call). Push it into a model query scope → acceptable but couples model to query language; repository keeps it isolated.

## Decision 7 — Distance calculation: Haversine in SQL via `selectRaw` + `orderByRaw`
- **Decision**: `ServiceCenterRepositoryEloquent::nearby()` uses MySQL's trigonometric functions:
  ```sql
  6371 * 2 * ASIN(SQRT(
      POW(SIN(RADIANS((? - lat)/2)), 2) +
      COS(RADIANS(?)) * COS(RADIANS(lat)) *
      POW(SIN(RADIANS((? - lng)/2)), 2)
  )) AS distance_km
  ```
  bound via `selectRaw` and `orderByRaw`, with the user's `lat`/`lng` passed as positional bindings.
- **Rationale**: Computing distance row-by-row in PHP after fetching defeats the indexable ORDER BY and pulls every row of the brand into memory. SQL-side Haversine lets the DB sort and (eventually) limit. MySQL trigonometric functions are stable across 5.7 → 8.x. No extension required (unlike PostGIS / spatial indexes), which keeps the project on plain MySQL.
- **Alternatives considered**:
  - PHP-side Haversine on a `Collection::sortBy` → rejected, O(n) memory per request, no DB-side ordering.
  - MySQL `ST_Distance_Sphere` → cleaner SQL, but requires `POINT()` columns and a spatial index. Premature for v1.
  - Pre-compute geohash columns + range scan → premature; v1 traffic does not warrant it.

## Decision 8 — `is_open` accessor on ServiceCenter
- **Decision**: Add a virtual attribute via `protected $appends = ['is_open']` and a `getIsOpenAttribute()` accessor that compares `now()->format('H:i:s')` against `open_at` and `close_at`. Assumes `open_at < close_at` within the same day (overnight wrap is out of scope per spec).
- **Rationale**: Spec FR-009 requires open/closed status on every service-center record returned. An accessor keeps the logic on the model and serialises automatically through the API resource. Carbon's time-only comparison is exact for `time`-typed columns.
- **Alternatives considered**: Compute in the API resource → splits domain logic away from the model; compute in the repository → forces every call site to remember to ask for it. The accessor is the cleanest fit.

## Decision 9 — Many-to-many pivot with optional `car_id`
- **Decision**: `service_items` carries `service_id`, `item_id`, `car_id` (nullable), plus `id` and timestamps. The pivot is declared on `Service::items()` via `belongsToMany(Item::class, 'service_items')->withPivot('car_id')->withTimestamps()`. A mirroring `Item::services()` does the same. Both FK ends use `cascadeOnDelete`; `car_id` uses `nullOnDelete` (parent car is allowed to disappear without losing the catalogue link).
- **Rationale**: The user's brief calls for the pivot to optionally tie a row to a specific car so per-car maintenance history can be reconstructed later (e.g., "this Corolla actually replaced its spark plugs at 40 000 km service"). Cascade on `service_id` and `item_id` keeps the catalogue clean; `nullOnDelete` on `car_id` preserves the parts-per-milestone signal even when the source car is gone.
- **Alternatives considered**: Drop `car_id` from the pivot and store car-specific usage in a separate `service_logs` table → rejected, doubles the surface area for v1; explicit-pivot model class (`ServiceItem extends Pivot`) → rejected, no behaviour beyond cascade rules and `withPivot` covers the access need.

## Decision 10 — Authorization model
- **Decision**:
  - `ServiceCenterPolicy::viewAny` → any authenticated user.
  - `UpcomingServiceController::index` → owner of `$car` OR `index-service` permission (handled in controller via `$this->authorize('viewAny', [Service::class, $car])`). `ServicePolicy::viewAny(User, Car)` checks `$user->id === $car->user_id || hasPermissionTo('index-service')`.
  - `ItemPolicy`: `viewAny` and `view` are open to any authenticated user (catalogue browsing). `create` / `update` / `delete` require the corresponding `{action}-item` permission (admin/super-user).
  - Form Request `authorize()` for write operations uses `$this->user()->can(...)`.
  - All three policies registered in `AppServiceProvider::boot()` via `Gate::policy(…)`. None defines a `before()` method (global `Gate::before()` covers admin).
- **Rationale**: The catalogue side (centers, services, items) is read-mostly and globally shared, so reads stay open to any authenticated user. Writes funnel through the existing RBAC permission system (constitution § V). The `UpcomingService` endpoint is an exception — it returns *per-car* data, so it ties back to ownership through the parent `$car` route binding.
- **Alternatives considered**: Make item reads admin-only → rejected, end users see `items_count` and breakdowns; making the resource itself unreadable would force all denormalisation up to the milestone read path; an extra "viewer" role specifically for catalogue → rejected, three roles (admin/super-user/user) already cover the use case.

## Decision 11 — Permission seeding
- **Decision**: Append three slugs to `RolePermissionsSeeder::$models`: `'service-center'`, `'service'`, `'item'`. The seeder loop auto-generates `index-{slug}`, `show-{slug}`, `create-{slug}`, `edit-{slug}`, `destroy-{slug}`, `force-delete-{slug}`, `restore-{slug}` for each.
- **Rationale**: Matches the convention used by every other model. After deploy: `php artisan sync:permissions`. The unused permissions (`force-delete-*`, `restore-*` for these read-mostly entities) cost nothing and keep the permission table predictable.
- **Alternatives considered**: Whitelist only the three actions actually used per slug → rejected, special-cases the seeder loop and breaks symmetry with all existing entities.

## Decision 12 — Greenfield migration policy
- **Decision**: Following the user's brief, add four NEW migration files (one per new table). Do NOT alter or rewrite existing migrations — none of the new tables conflict with existing schema.
- **Rationale**: Although the user authorised rewriting `create_*_table` migrations, none of our four new tables touch existing columns. Four new migration files keep the deploy story minimal and keep existing data intact for any non-`migrate:fresh` environment.
- **Alternatives considered**: Bundle into a single migration file → rejected, breaks the project's one-migration-per-table convention.

## Decision 13 — Pre-existing technical debt: BrandController and CarModelController bypass repositories
- **Observation**: `BrandController` and `CarModelController` query Eloquent directly (e.g., `Brand::orderBy('name')->get()`) instead of going through a repository. This violates Constitution § I.
- **Decision**: **Out of scope for this feature.** No fix here. A separate constitutional-debt cleanup feature should add `BrandRepository` and `CarModelRepository` and migrate the existing controllers, plus expose `serviceCenters` / `services` as Spatie includes. The current feature delivers the new entities cleanly within the constitution; rolling in a refactor of two unrelated controllers would expand scope.
- **Rationale**: Mixing domain-feature work with cross-cutting cleanup makes both harder to review.
- **Alternatives considered**: Add the missing repositories now → rejected, scope creep; leave a `// TODO` comment → rejected, untracked debt; track it in this research log → ✅ done.

## Decision 15 — User-created services *(revised scope, 2026-05-05)*

- **Decision**: Add two nullable columns to `services`: `car_id` and `user_id`. A row with **both NULL** is a catalogue (brand-defined) service; a row with **both set** is a user-created service for one specific car. Mixed states (only one set) are rejected at the Form Request level.
- **Rationale**: The user wants a car owner to define their own custom upcoming-maintenance milestones (e.g. *"replace tires at 45 000 km"*) without polluting the brand catalogue or affecting other owners of the same model. Tagging the row with the owning user + the specific car keeps the catalogue clean while letting `upcomingForCar` merge both sources in a single query.
- **Alternatives considered**:
  - Separate `user_services` table → rejected, doubles the model surface and forces every read of "upcoming services" to UNION two tables; the `services` table is the natural home.
  - Store user services as a JSON column on the car → rejected, breaks Spatie filters/sorts and complicates `withCount('items')`.
  - Use a separate `service_type` enum (`'catalogue'` / `'user'`) instead of `(user_id IS NULL)` discriminator → rejected, NULL-on-FK is already the project's discriminator pattern (matches `cars.brand_id` nullable).

## Decision 16 — All FKs in this feature: nullable + nullOnDelete *(revised scope)*

- **Decision**: Per the user's blanket directive, **every** FK on the four new tables uses `nullable()->constrained(...)->nullOnDelete()`. This applies retroactively: the migrations shipped under `2026_05_04_*` are renamed to `2026_05_05_*` and rewritten. A `migrate:fresh` (already permitted by the greenfield migration policy) replays them.

  | Column                       | Before                | After                |
  |------------------------------|-----------------------|----------------------|
  | `service_centers.brand_id`   | NOT NULL, cascade     | nullable, nullOnDelete |
  | `services.car_model_id`      | NOT NULL, cascade     | nullable, nullOnDelete |
  | `services.car_id`            | *(new)*               | nullable, nullOnDelete |
  | `services.user_id`           | *(new)*               | nullable, nullOnDelete |
  | `service_items.service_id`   | NOT NULL, cascade     | nullable, nullOnDelete |
  | `service_items.item_id`      | NOT NULL, cascade     | nullable, nullOnDelete |
  | `service_items.car_id`       | nullable, nullOnDelete (already) | unchanged |

- **Rationale**: The user prefers data preservation over cascade destruction across the board. Deleting a brand de-attributes service centers but does not destroy them; deleting a car_model de-attributes catalogue services but keeps the records. This is consistent with how `cars` already treats its parent FKs (e.g., `cars.brand_id` is nullOnDelete). User-tolerant deletion is the dominant pattern for this codebase.
- **Trade-off**: `service_items` rows can become orphaned (`service_id IS NULL` / `item_id IS NULL`). These rows are unreachable through normal Eloquent relations and consume only a few bytes; a future housekeeping job can prune them. The trade is acceptable in exchange for never accidentally destroying historical maintenance data.
- **Alternatives considered**: Mixed strategy (cascade for pivot, null for parents) → rejected, simpler one-rule policy is easier to reason about; SET DEFAULT to a "deleted" tombstone row → rejected, requires a sentinel row and complicates seeders.

## Decision 17 — `scopeToUser` override on `ServiceRepository` *(revised scope)*

- **Decision**: `ServiceRepositoryEloquent::scopeToUser()` overrides to:
  ```php
  if (auth()->check() && !auth()->user()->isAdmin()) {
      $this->model = $this->model->where(function ($q) {
          $q->whereNull('user_id')->orWhere('user_id', auth()->id());
      });
  }
  ```
- **Rationale**: With `services.user_id` introduced, the table now contains user-owned rows alongside catalogue rows. Constitution § I marks `scopeToUser` NON-NEGOTIABLE for any table with `user_id`. The carve-out we relied on in the original Decision 4 (system-wide catalogue) no longer applies as written — it now applies only to `user_id IS NULL` rows. Adding `OR user_id = auth()->id()` preserves catalogue visibility while restricting user-rows to their owner. Admins bypass via `isAdmin()`.
- **Alternatives considered**:
  - Skip the scope and rely on policies → rejected, defense-in-depth requirement (constitution § I).
  - Two separate repositories (`CatalogueServiceRepository` + `UserServiceRepository`) → rejected, doubles boilerplate; one repo with a discriminator scope is cleaner.

## Decision 18 — Full CRUD on `Service` and `ServiceCenter` *(revised scope)*

- **Decision**: Add `store`, `show`, `update`, `destroy` to both `ServiceCenterController` and `ServiceController`. Authorization model:

  | Action                                | Subject                              | Authorization                                                                 |
  |---------------------------------------|--------------------------------------|-------------------------------------------------------------------------------|
  | ServiceCenter store/update/destroy    | catalogue (always admin-managed)     | Form Request `authorize()` → `can('create'\|'update'\|'delete', ...)` → policy permission check |
  | ServiceCenter show                    | catalogue                            | controller `$this->authorize('view', $serviceCenter)` (always allows authenticated)             |
  | Service show / index                  | catalogue OR user-owned              | controller authorize; repo `scopeToUser` filters user-rows automatically                        |
  | Service `storeCatalogue`              | catalogue (no `car_id`/`user_id`)    | `can('create-service')` permission                                                              |
  | Service `store` *(per-car user form)* | user-owned                           | car-ownership check inside Form Request `authorize()` (`$this->user()->id === $this->route('car')->user_id`) |
  | Service update                        | depends on subject                   | policy `update(User, Service)`: catalogue → permission; user-owned → owner OR permission         |
  | Service destroy                       | depends on subject                   | policy `delete(User, Service)`: same shape as update                                            |

  Policies fan out:
  ```php
  // ServicePolicy
  public function view(User $user, Service $service): bool
  {
      return $service->user_id === null
          || $service->user_id === $user->id
          || $user->hasPermissionTo('show-service');
  }

  public function update(User $user, Service $service): bool
  {
      if ($service->user_id !== null) {
          return $service->user_id === $user->id || $user->hasPermissionTo('edit-service');
      }
      return $user->hasPermissionTo('edit-service');
  }

  public function delete(User $user, Service $service): bool
  {
      if ($service->user_id !== null) {
          return $service->user_id === $user->id || $user->hasPermissionTo('destroy-service');
      }
      return $user->hasPermissionTo('destroy-service');
  }
  ```
- **Rationale**: Two creation paths (`store` per-car for user-services, `storeCatalogue` for admin-side) split cleanly because the validation rules and the authorization model differ. A single overloaded route would conflate ownership checks with permission checks. Two named methods stay testable and keep policies minimal.
- **Alternatives considered**:
  - Single `store` that infers user-vs-catalogue from request body → rejected, breaks the constitution's "Form Request authorize()" pattern (one Form Request, one rule set).
  - Use `Route::resource` with `apiResource` → rejected, the project does not use Laravel resource routes (every existing module declares each verb explicitly).

## Decision 19 — Form Requests for Service & ServiceCenter writes *(revised scope)*

- **Decision**:
  - `App\Http\Requests\ServiceCenter\StoreServiceCenterRequest` — `authorize() => $this->user()->can('create', ServiceCenter::class)`. Rules: `name|address|open_at (time)|close_at (time)|mobile|lat (between -90,90)|lng (between -180,180)`.
  - `App\Http\Requests\ServiceCenter\UpdateServiceCenterRequest` — `authorize() => $this->user()->can('update', $this->route('serviceCenter'))`. All rules `sometimes`.
  - `App\Http\Requests\Service\StoreServiceRequest` — dual-mode `authorize()`:
    ```php
    public function authorize(): bool
    {
        $car = $this->route('car');
        if ($car) {
            // user-service path
            return $this->user()->can('create', [Service::class, $car])
                && $this->user()->id === $car->user_id;
        }
        // catalogue path
        return $this->user()->can('create-service');
    }
    ```
    Rules:
    ```php
    return [
        'km'           => ['required', 'integer', 'min:0'],
        'price'        => ['required', 'numeric', 'min:0'],
        'car_model_id' => [
            $this->route('car') ? 'nullable' : 'required',  // catalogue path needs a model
            'exists:car_models,id',
        ],
    ];
    ```
  - `App\Http\Requests\Service\UpdateServiceRequest` — `authorize() => $this->user()->can('update', $this->route('service'))`. Rules same shape but all `sometimes`.
- **Rationale**: Two-route shape (catalogue at `/api/services`, user at `/api/cars/{car}/services`) means the same Form Request can pivot on `$this->route('car')`. Keeps the file count down without compromising the Form Request authorization rule.
- **Alternatives considered**: Two separate Form Requests (`StoreCatalogueServiceRequest`, `StoreUserServiceRequest`) → marginally cleaner but doubles the Form Request count for marginal value. The dual-mode form keeps the public API smaller.

## Decision 20 — Spatie includes / filters / sorts updates *(revised scope)*

- **Decision**:
  - `ServiceRepositoryEloquent::$allowedIncludes` expands to `['carModel', 'carModel.brand', 'car', 'user', 'items']` — adds `car` and `user` so the API can reveal which car/user a personal service belongs to.
  - `ServiceRepositoryEloquent::$allowedFiltersExact` expands to `['car_model_id', 'car_id', 'user_id']` — clients can list user-services for a particular car (`?filter[car_id]=123`) or scope to catalogue-only (`?filter[user_id]=null` is not directly supported by Spatie's exact filter; instead use a dedicated scope `mine` / `catalogue` if needed in a follow-up).
  - `ServiceCenterRepositoryEloquent` is unchanged from the original Decision 5.
- **Rationale**: User mentioned "needed includes" for the third time — keeping the discipline that every new relation/column ships with the corresponding Spatie array entry. New `Service::car()` and `Service::user()` relations are no use unless they're includable.
- **Alternatives considered**: Auto-eager-load `user` via `$include` → rejected, adds a join to every list read; client-driven inclusion is more efficient.

---

## Decision 14 — `withCount('items')` shape on the upcoming-services response
- **Decision**: The repository call chains `->withCount('items')` so each Service model instance carries an `items_count` attribute. The API resource exposes it directly as `items_count`. `remaining_km` is computed in the resource (`$this->km - $request->route('car')->current_km`) — no extra DB column.
- **Rationale**: `items_count` is a single denormalised SQL aggregate per row (`COUNT(*) FROM service_items WHERE service_id = …`). `remaining_km` is a simple integer subtraction; computing it in the resource keeps the DB query clean and the resource's purpose explicit.
- **Alternatives considered**: Eager-load `items` and call `count()` in PHP → rejected, fetches every pivot row per request just to count them; expose `remaining_km` as a model accessor → rejected, accessor would have to know the request's car context, which is brittle.
