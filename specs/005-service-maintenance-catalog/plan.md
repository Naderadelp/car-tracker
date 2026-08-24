# Implementation Plan: Service & Maintenance Catalog

**Branch**: `005-service-maintenance-catalog` | **Date**: 2026-05-04 (revised 2026-05-05) | **Spec**: [spec.md](spec.md)

## Summary

Add four new tables (`service_centers`, `services`, `items`, `service_items`) and the full repository → policy → form-request → resource → controller stack for three new entities (`ServiceCenter`, `Service`, `Item`). Endpoints exposed:

1. `GET /api/cars/{car}/upcoming-services` — predictive list of milestones for the car (catalogue + the user's own custom entries).
2. `GET /api/brands/{brand}/service-centers?lat=&lng=` — distance-ordered nearby centers (Haversine in SQL).
3. Full CRUD on `/api/items` — admin-managed master parts inventory.
4. **Full CRUD on `/api/brands/{brand}/service-centers/{serviceCenter}`** *(revised scope)* — admin/super-user manage the dealership directory.
5. **Full CRUD on `/api/services` and `/api/cars/{car}/services`** *(revised scope)* — admin/super-user manage catalogue milestones; any car owner creates / edits / deletes their own custom upcoming services for their car.

Per the user prompt, every new repository declares the full Spatie property set (`$allowedIncludes`, `$allowedFilters`, `$allowedFiltersExact`, `$allowedSorts`, `$allowedDefaultSorts`).

The user's brief embedded three constitution-violating shorthands (`api/v1/` URL prefix, `Api\V1\` namespace, root-level Form Requests) plus two stale field names (`model_id`, `current_mileage`); all five are corrected here. See research § Decisions 1–2.

## Scope additions (revised 2026-05-05)

The original plan shipped with `index`-only controllers for ServiceCenter and Service. The revised scope expands them, plus introduces user-owned services:

1. **User-created services** — A car owner can create their own custom upcoming-maintenance milestones for their own car (e.g. *"remind me at 45 000 km that I want to replace tires"*). These coexist with brand catalogue services in the upcoming-services list.
2. **All FKs nullable + nullOnDelete** — Per the user directive, every foreign key on the four new tables relaxes from `cascadeOnDelete` (or NOT NULL) to **nullable + nullOnDelete**. Deletes never destroy related rows; they de-attribute them. This applies to `services.car_model_id`, `service_centers.brand_id`, and the new `services.car_id` / `services.user_id` columns introduced for user-services. The pivot `service_items` follows the same rule on all three FKs (`service_id`, `item_id`, `car_id`).
3. **Full CRUD on ServiceCenter** — `store`, `show`, `update`, `destroy` added with admin/super-user permission gates and the existing Spatie include (`brand`).
4. **Full CRUD on Service** — `store`, `show`, `update`, `destroy` added. Authorization splits two ways: catalogue services (no `user_id`) require `*-service` permission; user services require ownership (`user_id` match) or admin bypass.

See research § Decisions 15–18 for the supporting rationale.

---

## Technical Context

**Language/Version**: PHP 8.4
**Primary Dependencies**: Laravel 13, Sanctum, spatie/laravel-permission, spatie/laravel-query-builder v7, spatie/laravel-activitylog
**Storage**: MySQL (Haversine via `selectRaw` — no spatial index in v1)
**Testing**: N/A (not in scope for this feature)
**Target Platform**: Linux server (REST API)
**Project Type**: Web service (REST API)
**Performance Goals**: Standard API response times; service centers per brand expected ≤ 100, services per car model ≤ 30, items inventory ≤ a few hundred
**Constraints**: No URL versioning; no service layer; all DB access via repositories; no `Api\V1\` namespacing
**Scale/Scope**: Per-user reads with shared catalogue tables; standard mobile-app load

---

## Constitution Check

| Principle | Status | Notes |
|-----------|--------|-------|
| I — Repository Pattern | ✅ | Three new contract+impl pairs (`ServiceCenter`, `Service`, `Item`), each bound in `RepositoryServiceProvider` |
| I — Full Spatie property set | ✅ | All five arrays declared on each repository (per user prompt) |
| I — `allowedDefaultSorts` declared | ✅ | `-id` for ServiceCenter and Item; `km` (asc) for Service (domain stronger) |
| I — `scopeToUser()` | ✅ | `ServiceRepositoryEloquent::scopeToUser` overrides for user-services (`WHERE user_id IS NULL OR user_id = auth()->id()` for non-admins). `ServiceCenter` and `Item` remain system-wide. |
| I — `makeModel()` uses `newQuery()` | ✅ | Inherited from `EloquentRepository` |
| I — Spatie variadic spread | ✅ | Inherited from `EloquentRepository::spatie()` |
| II — Form Request auth via `$this->user()->can()` | ✅ | `Store/UpdateItemRequest`, `NearbyServiceCentersRequest`, `Store/UpdateServiceCenterRequest`, `Store/UpdateServiceRequest` all delegate to Gate |
| II — No inline `$request->validate()` | ✅ | All validation in Form Requests (including the GPS query-param Form Request) |
| III — BaseController response helpers | ✅ | All controllers extend `BaseController`; only `success` / `paginated` / `error` |
| III — Resource for every payload | ✅ | `ServiceCenterResource`, `ServiceResource`, `ItemResource` |
| IV — Standard folder structure | ✅ | Flat namespace; Form Requests under `App\Http\Requests\Item\` and `App\Http\Requests\ServiceCenter\` |
| IV — No `Api\V1\` namespacing | ✅ | Corrected from user's brief (research § Decision 2) |
| V — Policy registered in `AppServiceProvider` | ✅ | `Gate::policy()` for all three new policies |
| V — No `before()` in policies | ✅ | Global `Gate::before()` covers admin |
| V — store/update auth in Form Request | ✅ | All other actions (index/show/destroy) authorize in controller |
| V — Permissions added to seeder | ✅ | `'service-center'`, `'service'`, `'item'` appended to `$models` |
| VI — DB transactions for side-effect writes | ✅ | `ItemController::store` and `update` wrapped in `DB::beginTransaction()` |
| VI — `LogsActivity` on new models | ✅ | `ServiceCenter`, `Service`, `Item` all use the trait |
| API Routing — No URL versioning | ✅ | All routes under `/api/...` — including the new `/api/services`, `/api/cars/{car}/services`, and full `/api/brands/{brand}/service-centers/{serviceCenter}` paths |

**No constitution violations.** Complexity Tracking section below is empty.

**Out-of-scope debt** (research § Decision 13): `BrandController` and `CarModelController` query Eloquent directly, violating Principle I. Addressing them is deferred to a dedicated cleanup feature.

---

## Project Structure

### Documentation (this feature)

```text
specs/005-service-maintenance-catalog/
├── plan.md              ← this file
├── research.md
├── data-model.md
├── contracts/
│   └── api-endpoints.md
├── quickstart.md
└── tasks.md             ← generated by /speckit-tasks
```

### Source Code Changes

```text
database/migrations/
├── 2026_05_05_000006_create_service_centers_table.php   [NEW — brand_id nullable nullOnDelete]
├── 2026_05_05_000007_create_services_table.php          [NEW — car_model_id, car_id, user_id all nullable nullOnDelete]
├── 2026_05_05_000008_create_items_table.php             [NEW]
└── 2026_05_05_000009_create_service_items_table.php     [NEW — service_id, item_id, car_id all nullable nullOnDelete]

app/Models/
├── ServiceCenter.php                                    [NEW]
├── Service.php                                          [NEW]
├── Item.php                                             [NEW]
├── Brand.php                                            [MODIFY — add serviceCenters()]
├── CarModel.php                                         [MODIFY — add services()]
└── Car.php                                              [MODIFY — add serviceItems() (forward-looking)]

app/Repositories/
├── Contracts/
│   ├── ServiceCenterRepository.php                      [NEW]
│   ├── ServiceRepository.php                            [NEW]
│   └── ItemRepository.php                               [NEW]
└── Eloquent/
    ├── ServiceCenterRepositoryEloquent.php              [NEW]
    ├── ServiceRepositoryEloquent.php                    [NEW]
    └── ItemRepositoryEloquent.php                       [NEW]

app/Policies/
├── ServiceCenterPolicy.php                              [NEW]
├── ServicePolicy.php                                    [NEW]
└── ItemPolicy.php                                       [NEW]

app/Http/
├── Requests/
│   ├── Item/
│   │   ├── StoreItemRequest.php                         [NEW]
│   │   └── UpdateItemRequest.php                        [NEW]
│   ├── Service/
│   │   ├── StoreServiceRequest.php                      [NEW — revised scope]
│   │   └── UpdateServiceRequest.php                     [NEW — revised scope]
│   └── ServiceCenter/
│       ├── NearbyServiceCentersRequest.php              [NEW]
│       ├── StoreServiceCenterRequest.php                [NEW — revised scope]
│       └── UpdateServiceCenterRequest.php               [NEW — revised scope]
├── Resources/
│   ├── ServiceCenterResource.php                        [NEW]
│   ├── ServiceResource.php                              [NEW]
│   └── ItemResource.php                                 [NEW]
└── Controllers/
    ├── ServiceCenterController.php                      [NEW — gains store/show/update/destroy in revised scope]
    ├── ServiceController.php                            [NEW — revised scope; full CRUD]
    ├── UpcomingServiceController.php                    [NEW]
    └── ItemController.php                               [NEW]

app/Providers/
├── AppServiceProvider.php                               [MODIFY — register 3 policies]
└── RepositoryServiceProvider.php                        [MODIFY — bind 3 repositories]

database/seeders/
└── RolePermissionsSeeder.php                            [MODIFY — add 3 model slugs]

routes/api.php                                           [MODIFY — add 7 routes inside auth:sanctum]
```

**Structure decision**: Standard Laravel REST-API layout. New code mirrors the layout used by Trip / FillUp (spec 003) and ParkingRecord (spec 004). No new top-level directories.

---

## Implementation Details

### Migrations *(revised — every FK nullable + nullOnDelete)*

```php
// 2026_05_05_000006_create_service_centers_table.php
Schema::create('service_centers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
    $table->string('name');
    $table->string('address', 500);
    $table->time('open_at');
    $table->time('close_at');
    $table->string('mobile', 50);
    $table->decimal('lat', 10, 8);
    $table->decimal('lng', 11, 8);
    $table->timestamps();
});

// 2026_05_05_000007_create_services_table.php
Schema::create('services', function (Blueprint $table) {
    $table->id();
    $table->foreignId('car_model_id')->nullable()->constrained('car_models')->nullOnDelete();
    $table->foreignId('car_id')->nullable()->constrained('cars')->nullOnDelete();   // user-services point to one car
    $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // creator (NULL = catalogue)
    $table->integer('km');
    $table->decimal('price', 10, 2);
    $table->timestamps();

    $table->index(['car_model_id', 'km']);
    $table->index(['car_id', 'km']);
    $table->index('user_id');
});

// 2026_05_05_000008_create_items_table.php
Schema::create('items', function (Blueprint $table) {
    $table->id();
    $table->string('name')->unique();
    $table->decimal('price', 10, 2);
    $table->timestamps();
});

// 2026_05_05_000009_create_service_items_table.php
Schema::create('service_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
    $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
    $table->foreignId('car_id')->nullable()->constrained('cars')->nullOnDelete();
    $table->timestamps();

    $table->index(['service_id', 'item_id']);
});
```

> **Migration delta vs. shipped 005**: `services.car_model_id`, `service_centers.brand_id`, `service_items.service_id`, `service_items.item_id` all flip from `cascadeOnDelete` (NOT NULL) to **nullable + nullOnDelete**. Two new columns on `services`: `car_id` and `user_id` (both nullable + nullOnDelete). Apply via `migrate:fresh` per the project's greenfield strategy.

### Repository Spatie property set (per user prompt)

```php
// ServiceCenterRepositoryEloquent
protected array $allowedIncludes      = ['brand'];
protected array $allowedFilters       = ['name', 'address'];
protected array $allowedFiltersExact  = ['brand_id'];
protected array $allowedSorts         = ['name', 'created_at'];
protected array $allowedDefaultSorts  = ['-id'];

// ServiceRepositoryEloquent
protected array $allowedIncludes      = ['carModel', 'carModel.brand', 'car', 'user', 'items'];
protected array $allowedFiltersExact  = ['car_model_id', 'car_id', 'user_id'];
protected array $allowedSorts         = ['km', 'price', 'created_at'];
protected array $allowedDefaultSorts  = ['km'];

protected function scopeToUser(): void
{
    if (auth()->check() && !auth()->user()->isAdmin()) {
        // Catalogue rows (user_id IS NULL) are visible to everyone;
        // user-owned rows only to their owner.
        $this->model = $this->model->where(function ($q) {
            $q->whereNull('user_id')->orWhere('user_id', auth()->id());
        });
    }
}

// ItemRepositoryEloquent
protected array $allowedIncludes      = ['services'];
protected array $allowedFilters       = ['name'];
protected array $allowedSorts         = ['name', 'price', 'created_at'];
protected array $allowedDefaultSorts  = ['-id'];
```

### Repository domain methods

```php
// ServiceCenterRepositoryEloquent::nearby
public function nearby(int $brandId, float $lat, float $lng): \Illuminate\Support\Collection
{
    return app($this->model())->newQuery()
        ->where('brand_id', $brandId)
        ->selectRaw('
            *,
            (6371 * 2 * ASIN(SQRT(
                POW(SIN(RADIANS((? - lat)/2)), 2)
                + COS(RADIANS(?)) * COS(RADIANS(lat)) *
                  POW(SIN(RADIANS((? - lng)/2)), 2)
            ))) AS distance_km
        ', [$lat, $lat, $lng])
        ->orderBy('distance_km')
        ->get();
}

// ServiceRepositoryEloquent::upcomingForCar — revised to merge catalogue + user-owned services
public function upcomingForCar(\App\Models\Car $car): \Illuminate\Support\Collection
{
    return app($this->model())->newQuery()
        ->where(function ($q) use ($car) {
            // Catalogue services for this car's model
            $q->where(function ($qq) use ($car) {
                $qq->where('car_model_id', $car->car_model_id)
                   ->whereNull('user_id')
                   ->whereNull('car_id');
            })
            // User-owned services for THIS car specifically
            ->orWhere(function ($qq) use ($car) {
                $qq->where('car_id', $car->id)
                   ->where('user_id', $car->user_id);
            });
        })
        ->where('km', '>', $car->current_km)
        ->withCount('items')
        ->orderBy('km')
        ->get();
}
```

### `is_open` accessor on `ServiceCenter`

```php
protected $appends = ['is_open'];

public function getIsOpenAttribute(): bool
{
    if (! $this->open_at || ! $this->close_at) {
        return false;
    }
    $now = now()->format('H:i:s');
    return $now >= $this->open_at->format('H:i:s')
        && $now <  $this->close_at->format('H:i:s');
}
```

### Controllers (skeletons — revised)

#### `ServiceCenterController` *(full CRUD; index keeps Haversine ordering)*
```php
public function index(NearbyServiceCentersRequest $request, Brand $brand): JsonResponse
{
    $centers = $this->serviceCenterRepository->nearby(
        $brand->id,
        (float) $request->lat,
        (float) $request->lng,
    );
    return $this->success(ServiceCenterResource::collection($centers));
}

public function show(Brand $brand, ServiceCenter $serviceCenter): JsonResponse
{
    $this->authorize('view', $serviceCenter);
    abort_if($serviceCenter->brand_id !== $brand->id, 404);
    $serviceCenter->load('brand');
    return $this->success(new ServiceCenterResource($serviceCenter));
}

public function store(StoreServiceCenterRequest $request, Brand $brand): JsonResponse
{
    try { DB::beginTransaction();
        $sc = $this->serviceCenterRepository->create([...$request->validated(), 'brand_id' => $brand->id]);
        DB::commit();
        return $this->success(new ServiceCenterResource($sc), 201, 'Service center created.');
    } catch (\Exception $e) { DB::rollBack(); return $this->error($e->getMessage(), 422); }
}

public function update(UpdateServiceCenterRequest $request, Brand $brand, ServiceCenter $serviceCenter): JsonResponse
{
    abort_if($serviceCenter->brand_id !== $brand->id, 404);
    try { DB::beginTransaction();
        $updated = $this->serviceCenterRepository->update($request->validated(), $serviceCenter->id);
        DB::commit();
        return $this->success(new ServiceCenterResource($updated));
    } catch (\Exception $e) { DB::rollBack(); return $this->error($e->getMessage(), 422); }
}

public function destroy(Brand $brand, ServiceCenter $serviceCenter): JsonResponse
{
    $this->authorize('delete', $serviceCenter);
    abort_if($serviceCenter->brand_id !== $brand->id, 404);
    $this->serviceCenterRepository->delete($serviceCenter->id);
    return $this->success([], 200, 'Service center deleted.');
}
```

#### `ServiceController` *(NEW — full CRUD; mounts under `/api/cars/{car}/services` for user-services and `/api/services/{service}` for direct access)*
```php
public function index(Request $request): JsonResponse
{
    $this->authorize('viewAny', Service::class);
    $services = $this->serviceRepository->spatie()->paginate();
    return $this->paginated($services, ServiceResource::class);
}

public function show(Service $service): JsonResponse
{
    $this->authorize('view', $service);
    return $this->success(new ServiceResource($service));
}

public function store(StoreServiceRequest $request, Car $car): JsonResponse
{
    // user-service path: car is route-bound; user_id auto-stamped from auth()->id()
    try { DB::beginTransaction();
        $svc = $this->serviceRepository->create([
            ...$request->validated(),
            'car_id'  => $car->id,
            'user_id' => auth()->id(),
        ]);
        DB::commit();
        return $this->success(new ServiceResource($svc), 201, 'Custom service created.');
    } catch (\Exception $e) { DB::rollBack(); return $this->error($e->getMessage(), 422); }
}

// Optional second store — admin-only catalogue creation under /api/services
public function storeCatalogue(StoreServiceRequest $request): JsonResponse
{
    try { DB::beginTransaction();
        $svc = $this->serviceRepository->create($request->validated()); // no car_id, no user_id
        DB::commit();
        return $this->success(new ServiceResource($svc), 201, 'Catalogue service created.');
    } catch (\Exception $e) { DB::rollBack(); return $this->error($e->getMessage(), 422); }
}

public function update(UpdateServiceRequest $request, Service $service): JsonResponse
{
    try { DB::beginTransaction();
        $updated = $this->serviceRepository->update($request->validated(), $service->id);
        DB::commit();
        return $this->success(new ServiceResource($updated));
    } catch (\Exception $e) { DB::rollBack(); return $this->error($e->getMessage(), 422); }
}

public function destroy(Service $service): JsonResponse
{
    $this->authorize('delete', $service);
    $this->serviceRepository->delete($service->id);
    return $this->success([], 200, 'Service deleted.');
}
```

#### `UpcomingServiceController` *(unchanged — index now naturally returns merged catalogue + user services via the revised `upcomingForCar`)*
```php
public function index(Request $request, Car $car): JsonResponse
{
    $this->authorize('viewAny', [Service::class, $car]);
    $services = $this->serviceRepository->upcomingForCar($car);
    return $this->success(ServiceResource::collection($services));
}
```

#### `ItemController` — unchanged from original plan (full CRUD, see data-model.md).

### Routes (append inside `auth:sanctum` group — revised)

```php
// Items
Route::get   ('items',          [ItemController::class, 'index']);
Route::post  ('items',          [ItemController::class, 'store']);
Route::get   ('items/{item}',   [ItemController::class, 'show']);
Route::match (['put', 'patch'], 'items/{item}', [ItemController::class, 'update']);
Route::delete('items/{item}',   [ItemController::class, 'destroy']);

// Service centers — full CRUD nested by brand
Route::get   ('brands/{brand}/service-centers',                   [ServiceCenterController::class, 'index']);
Route::post  ('brands/{brand}/service-centers',                   [ServiceCenterController::class, 'store']);
Route::get   ('brands/{brand}/service-centers/{serviceCenter}',   [ServiceCenterController::class, 'show']);
Route::match (['put', 'patch'], 'brands/{brand}/service-centers/{serviceCenter}', [ServiceCenterController::class, 'update']);
Route::delete('brands/{brand}/service-centers/{serviceCenter}',   [ServiceCenterController::class, 'destroy']);

// Catalogue services (admin) — flat, no parent route binding
Route::get   ('services',           [ServiceController::class, 'index']);
Route::post  ('services',           [ServiceController::class, 'storeCatalogue']);
Route::get   ('services/{service}', [ServiceController::class, 'show']);
Route::match (['put', 'patch'], 'services/{service}', [ServiceController::class, 'update']);
Route::delete('services/{service}', [ServiceController::class, 'destroy']);

// User-services nested by car (creator implied by auth()->id())
Route::post('cars/{car}/services', [ServiceController::class, 'store']);

// Upcoming maintenance (catalogue + this user's services for the car)
Route::get('cars/{car}/upcoming-services', [UpcomingServiceController::class, 'index']);
```

> **Route count delta vs. shipped 005**: +4 service-center actions, +5 service-CRUD actions, +1 user-service create. The shipped `index` and `upcoming-services` routes remain.

### Provider wiring

```php
// AppServiceProvider::boot()
Gate::policy(ServiceCenter::class, ServiceCenterPolicy::class);
Gate::policy(Service::class,       ServicePolicy::class);
Gate::policy(Item::class,          ItemPolicy::class);

// RepositoryServiceProvider::register()
$this->app->bind(ServiceCenterRepository::class, ServiceCenterRepositoryEloquent::class);
$this->app->bind(ServiceRepository::class,       ServiceRepositoryEloquent::class);
$this->app->bind(ItemRepository::class,          ItemRepositoryEloquent::class);
```

### Permissions seeder change

```php
private array $models = [
    'car', 'fill-up', 'trip', 'parking-record',
    'service-center', 'service', 'item',
    'brand', 'car-model', 'document', 'user', 'role', 'permission',
];
```

After deploy: `php artisan sync:permissions`.

---

## Phase 0 Output

Resolved unknowns and decisions captured in [research.md](research.md) (14 decisions covering naming, constitution corrections, repository pattern, scoping, includes/filters/sorts, domain methods, Haversine, `is_open`, pivot, authorization, permissions, migrations, deferred debt, response shape).

## Phase 1 Output

- Entity definition: [data-model.md](data-model.md)
- API contract: [contracts/api-endpoints.md](contracts/api-endpoints.md)
- Smoke-test recipes: [quickstart.md](quickstart.md)
- Agent context: `CLAUDE.md` plan reference updated to point at this file.

---

## Complexity Tracking

> **No constitution violations.**

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| *(none)*  |            |                                     |
