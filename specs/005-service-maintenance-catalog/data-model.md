# Data Model: Service & Maintenance Catalog

## New Tables

### `service_centers` *(revised — brand_id now nullable + nullOnDelete)*

| Column      | Type            | Constraints                                              | Notes                                       |
|-------------|-----------------|----------------------------------------------------------|---------------------------------------------|
| id          | bigint unsigned | PK, auto-increment                                       |                                             |
| brand_id    | bigint unsigned | FK → brands.id, **NULLABLE, NULL on delete**             | Survives brand deletion                     |
| name        | varchar(255)    | NOT NULL                                   | e.g. "MG New Cairo"                         |
| address     | varchar(500)    | NOT NULL                                   |                                             |
| open_at     | time            | NOT NULL                                   | e.g. 09:00:00                               |
| close_at    | time            | NOT NULL                                   | e.g. 21:00:00; assumed `> open_at` (no overnight) |
| mobile      | varchar(50)     | NOT NULL                                   |                                             |
| lat         | decimal(10,8)   | NOT NULL                                   | Range −90 to +90                            |
| lng         | decimal(11,8)   | NOT NULL                                   | Range −180 to +180                          |
| created_at  | timestamp       |                                            |                                             |
| updated_at  | timestamp       |                                            |                                             |

**Indexes**: implicit FK index on `brand_id`. No spatial index in v1 (Haversine is computed in `selectRaw`).

**Migration file**: `database/migrations/2026_05_04_000006_create_service_centers_table.php`

### `services` *(revised scope — supports user-created services)*

| Column        | Type            | Constraints                                  | Notes                                                                |
|---------------|-----------------|----------------------------------------------|----------------------------------------------------------------------|
| id            | bigint unsigned | PK, auto-increment                           |                                                                      |
| car_model_id  | bigint unsigned | FK → car_models.id, **NULLABLE, NULL on delete** | NULL allowed for user-services tied to one specific car only         |
| car_id        | bigint unsigned | FK → cars.id, **NULLABLE, NULL on delete**   | NEW — set only on user-created services                              |
| user_id       | bigint unsigned | FK → users.id, **NULLABLE, NULL on delete**  | NEW — creator. NULL ⇒ catalogue (brand-defined); SET ⇒ user-owned    |
| km            | integer         | NOT NULL                                     | Milestone km, e.g. 40 000                                            |
| price         | decimal(10,2)   | NOT NULL                                     | Total cost in EGP                                                    |
| created_at    | timestamp       |                                              |                                                                      |
| updated_at    | timestamp       |                                              |                                                                      |

**Discriminator**: `user_id IS NULL` ⇒ catalogue row (admin-managed, applies to all cars of `car_model_id`); `user_id IS NOT NULL` ⇒ user-owned row (applies only to `car_id`).

**Indexes**: implicit FK index on `car_model_id`, `car_id`, `user_id`. Composite `(car_model_id, km)` for catalogue upcoming queries; composite `(car_id, km)` for per-car user-service queries.

**Migration file**: `database/migrations/2026_05_05_000007_create_services_table.php`

### `items`

| Column      | Type            | Constraints                                | Notes                                                  |
|-------------|-----------------|--------------------------------------------|--------------------------------------------------------|
| id          | bigint unsigned | PK, auto-increment                         |                                                        |
| name        | varchar(255)    | NOT NULL, **unique**                       | e.g. "Engine Oil", "Oil Filter"                        |
| price       | decimal(10,2)   | NOT NULL                                   | EGP                                                    |
| created_at  | timestamp       |                                            |                                                        |
| updated_at  | timestamp       |                                            |                                                        |

**Migration file**: `database/migrations/2026_05_04_000008_create_items_table.php`

### `service_items` *(pivot — revised: every FK nullable + nullOnDelete)*

| Column      | Type            | Constraints                                              | Notes                                                  |
|-------------|-----------------|----------------------------------------------------------|--------------------------------------------------------|
| id          | bigint unsigned | PK, auto-increment                                       |                                                        |
| service_id  | bigint unsigned | FK → services.id, **NULLABLE, NULL on delete**           |                                                        |
| item_id     | bigint unsigned | FK → items.id, **NULLABLE, NULL on delete**              |                                                        |
| car_id      | bigint unsigned | FK → cars.id, NULLABLE, NULL on delete                   | Optional: per-car usage tracking                       |
| created_at  | timestamp       |                                            |                                                        |
| updated_at  | timestamp       |                                            |                                                        |

**Indexes**: composite `(service_id, item_id)` for fast pivot lookups; FK index on `car_id`.

**Migration file**: `database/migrations/2026_05_04_000009_create_service_items_table.php`

## Modified Tables / Models

### `cars` *(no schema change)*
No column modifications. The new entities relate via existing `cars.car_model_id` and `cars.current_km` fields — both already on the table.

### `App\Models\Brand` *(add `serviceCenters()` relation)*
```php
public function serviceCenters(): HasMany
{
    return $this->hasMany(ServiceCenter::class);
}
```

### `App\Models\CarModel` *(add `services()` relation)*
```php
public function services(): HasMany
{
    return $this->hasMany(Service::class);
}
```

### `App\Models\Car` *(add `serviceItems()` relation — for future per-car usage queries)*
```php
public function serviceItems(): HasMany
{
    return $this->hasMany(\App\Models\Pivot\ServiceItem::class);
}
```

> The `Car` relation is added so future per-car maintenance-history reports can join through this pivot. Not consumed by any v1 endpoint.

## New Models

### `App\Models\ServiceCenter`
- **Table**: `service_centers`
- **Fillable**: `['brand_id', 'name', 'address', 'open_at', 'close_at', 'mobile', 'lat', 'lng']`
- **Casts**:
  - `open_at  => datetime:H:i:s`
  - `close_at => datetime:H:i:s`
  - `lat      => decimal:8`
  - `lng      => decimal:8`
- **Appends**: `['is_open']`
- **Accessor**: `getIsOpenAttribute(): bool` — `true` iff `now()->format('H:i:s')` is between `open_at` and `close_at` (inclusive of `open_at`, exclusive of `close_at`).
- **Relationships**: `brand(): BelongsTo → Brand`
- **Traits**: `LogsActivity` with `getActivitylogOptions()` returning `LogOptions::defaults()->logOnly(['*'])`

### `App\Models\Service` *(revised)*
- **Table**: `services`
- **Fillable**: `['car_model_id', 'car_id', 'user_id', 'km', 'price']`
- **Casts**:
  - `km    => integer`
  - `price => decimal:2`
- **Relationships**:
  - `carModel(): BelongsTo → CarModel`
  - `car(): BelongsTo → Car`        *(NEW — only meaningful on user-services)*
  - `user(): BelongsTo → User`      *(NEW — only meaningful on user-services)*
  - `items(): BelongsToMany → Item` *(through `service_items`)* — `->withPivot('car_id')->withTimestamps()`
- **Traits**: `LogsActivity`
- **Helper attribute**: a virtual `is_catalogue` boolean — `getIsCatalogueAttribute(): bool { return is_null($this->user_id); }`. Helps clients render different UI affordances.

### `App\Models\Item`
- **Table**: `items`
- **Fillable**: `['name', 'price']`
- **Casts**: `price => decimal:2`
- **Relationships**: `services(): BelongsToMany → Service` *(through `service_items`)* — `->withPivot('car_id')->withTimestamps()`
- **Traits**: `LogsActivity`

### `App\Models\Pivot\ServiceItem` *(optional explicit pivot model)*
- **Decision**: Use Laravel's default `Pivot` model unless a future feature needs per-row behaviour. The `Car::serviceItems()` HasMany uses a custom Pivot model only if/when it ships.
- For v1 we declare nothing under `App\Models\Pivot\` — the pivot is accessed through `Service::items()->withPivot('car_id')`.

## New Repository Layer

### `App\Repositories\Contracts\ServiceCenterRepository`
```php
interface ServiceCenterRepository extends RepositoryInterface
{
    public function nearby(int $brandId, float $lat, float $lng): \Illuminate\Support\Collection;
}
```

### `App\Repositories\Eloquent\ServiceCenterRepositoryEloquent`

| Property                | Value                                       |
|-------------------------|---------------------------------------------|
| `$allowedIncludes`      | `['brand']`                                 |
| `$allowedFilters`       | `['name', 'address']`         *(partial)*   |
| `$allowedFiltersExact`  | `['brand_id']`                              |
| `$allowedFilterScopes`  | `[]`                                        |
| `$allowedSorts`         | `['name', 'created_at']`                    |
| `$allowedDefaultSorts`  | `['-id']`                                   |

- `model() → ServiceCenter::class`
- `scopeToUser()`: NOT overridden (system-wide catalogue per Decision 4).
- **`nearby(int $brandId, float $lat, float $lng)`**:
  ```php
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
  ```

### `App\Repositories\Contracts\ServiceRepository`
```php
interface ServiceRepository extends RepositoryInterface
{
    public function upcomingForCar(\App\Models\Car $car): \Illuminate\Support\Collection;
}
```

### `App\Repositories\Eloquent\ServiceRepositoryEloquent` *(revised)*

| Property                | Value                                                                          |
|-------------------------|--------------------------------------------------------------------------------|
| `$allowedIncludes`      | `['carModel', 'carModel.brand', 'car', 'user', 'items']`                       |
| `$allowedFilters`       | `[]`                                                                           |
| `$allowedFiltersExact`  | `['car_model_id', 'car_id', 'user_id']`                                        |
| `$allowedFilterScopes`  | `[]`                                                                           |
| `$allowedSorts`         | `['km', 'price', 'created_at']`                                                |
| `$allowedDefaultSorts`  | `['km']`                                                                       |

- `model() → Service::class`
- **`scopeToUser()`** *(NEW override)*:
  ```php
  protected function scopeToUser(): void
  {
      if (auth()->check() && !auth()->user()->isAdmin()) {
          $this->model = $this->model->where(function ($q) {
              $q->whereNull('user_id')->orWhere('user_id', auth()->id());
          });
      }
  }
  ```
- **`upcomingForCar(Car $car)`** *(revised)*:
  ```php
  return app($this->model())->newQuery()
      ->where(function ($q) use ($car) {
          $q->where(function ($qq) use ($car) {
                $qq->where('car_model_id', $car->car_model_id)
                   ->whereNull('user_id')
                   ->whereNull('car_id');
            })
            ->orWhere(function ($qq) use ($car) {
                $qq->where('car_id', $car->id)
                   ->where('user_id', $car->user_id);
            });
      })
      ->where('km', '>', $car->current_km)
      ->withCount('items')
      ->orderBy('km')
      ->get();
  ```

### `App\Repositories\Contracts\ItemRepository`
```php
interface ItemRepository extends RepositoryInterface {}
```
*(No domain methods needed — `Rule::unique` on the Form Request handles uniqueness.)*

### `App\Repositories\Eloquent\ItemRepositoryEloquent`

| Property                | Value                                       |
|-------------------------|---------------------------------------------|
| `$allowedIncludes`      | `['services']`                              |
| `$allowedFilters`       | `['name']`                  *(partial)*     |
| `$allowedFiltersExact`  | `[]`                                        |
| `$allowedFilterScopes`  | `[]`                                        |
| `$allowedSorts`         | `['name', 'price', 'created_at']`           |
| `$allowedDefaultSorts`  | `['-id']`                                   |

- `model() → Item::class`
- `scopeToUser()`: NOT overridden (catalogue).

## New Policies

All registered in `AppServiceProvider::boot()` via `Gate::policy()`. No `before()` method (global `Gate::before()` handles admin). All methods explicitly return `bool`.

### `App\Policies\ServiceCenterPolicy`

| Method     | Signature                                | Logic                                                                       |
|------------|------------------------------------------|-----------------------------------------------------------------------------|
| `viewAny`  | `(User $user): bool`                     | `true` *(any authenticated user can browse the catalogue)*                  |
| `create`   | `(User $user): bool`                     | `$user->hasPermissionTo('create-service-center')`                           |
| `update`   | `(User $user, ServiceCenter $sc): bool`  | `$user->hasPermissionTo('edit-service-center')`                             |
| `delete`   | `(User $user, ServiceCenter $sc): bool`  | `$user->hasPermissionTo('destroy-service-center')`                          |

> v1 only wires `viewAny` (only the index action is exposed). The other methods are scaffolded for future write endpoints; the global admin bypass + RBAC keeps the permissions usable today.

### `App\Policies\ServicePolicy` *(revised — owner-vs-admin split)*

| Method                        | Signature                                | Logic                                                                                                                                            |
|-------------------------------|------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------|
| `viewAny` *(no Car arg)*      | `(User $user): bool`                     | `true` *(catalogue listing; repo `scopeToUser` filters user-rows)*                                                                                |
| `viewAny` *(in upcoming ctx)* | `(User $user, Car $car): bool`           | `$user->id === $car->user_id \|\| $user->hasPermissionTo('index-service')`                                                                       |
| `view`                        | `(User $user, Service $service): bool`   | `$service->user_id === null \|\| $service->user_id === $user->id \|\| $user->hasPermissionTo('show-service')`                                    |
| `create`                      | `(User $user, ?Car $car = null): bool`   | catalogue: `hasPermissionTo('create-service')`; user-svc: `$car && $user->id === $car->user_id`                                                  |
| `update`                      | `(User $user, Service $service): bool`   | user-svc: `$service->user_id === $user->id \|\| hasPermissionTo('edit-service')`. catalogue (`user_id` IS NULL): `hasPermissionTo('edit-service')` |
| `delete`                      | `(User $user, Service $service): bool`   | user-svc: `$service->user_id === $user->id \|\| hasPermissionTo('destroy-service')`. catalogue: `hasPermissionTo('destroy-service')`             |

### `App\Policies\ItemPolicy`

| Method     | Signature                       | Logic                                                                       |
|------------|---------------------------------|-----------------------------------------------------------------------------|
| `viewAny`  | `(User $user): bool`            | `true` *(any authenticated user)*                                           |
| `view`     | `(User $user, Item $item): bool`| `true`                                                                      |
| `create`   | `(User $user): bool`            | `$user->hasPermissionTo('create-item')`                                     |
| `update`   | `(User $user, Item $item): bool`| `$user->hasPermissionTo('edit-item')`                                       |
| `delete`   | `(User $user, Item $item): bool`| `$user->hasPermissionTo('destroy-item')`                                    |

## New Form Requests *(revised — added Service-CRUD and ServiceCenter-CRUD requests)*

### `App\Http\Requests\Item\StoreItemRequest`
- **`authorize()`**:
  ```php
  return $this->user()->can('create', Item::class);
  ```
- **`rules()`**:
  ```php
  return [
      'name'  => ['required', 'string', 'max:255', 'unique:items,name'],
      'price' => ['required', 'numeric', 'min:0'],
  ];
  ```

### `App\Http\Requests\Item\UpdateItemRequest`
- **`authorize()`**:
  ```php
  return $this->user()->can('update', $this->route('item'));
  ```
- **`rules()`**:
  ```php
  $itemId = $this->route('item')->id;
  return [
      'name'  => ['sometimes', 'string', 'max:255', Rule::unique('items', 'name')->ignore($itemId)],
      'price' => ['sometimes', 'numeric', 'min:0'],
  ];
  ```

> No Form Request for the `index` actions of ServiceCenter / UpcomingService — those validate query parameters inline via the controller (per Laravel idiom; the constitution's "no inline validate" rule applies to write operations). The lat/lng query parameters on the service-centers endpoint are validated through a tiny Form Request below.

### `App\Http\Requests\ServiceCenter\StoreServiceCenterRequest` *(NEW — revised scope)*
- **`authorize()`**: `return $this->user()->can('create', ServiceCenter::class);`
- **`rules()`**:
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

### `App\Http\Requests\ServiceCenter\UpdateServiceCenterRequest` *(NEW — revised scope)*
- **`authorize()`**: `return $this->user()->can('update', $this->route('serviceCenter'));`
- **`rules()`**: same shape as Store, all rules prefixed with `'sometimes'`.

### `App\Http\Requests\Service\StoreServiceRequest` *(NEW — revised scope; dual-mode)*
- **`authorize()`**:
  ```php
  $car = $this->route('car');
  if ($car) {
      return $this->user()->can('create', [Service::class, $car])
          && $this->user()->id === $car->user_id;
  }
  return $this->user()->can('create-service');
  ```
- **`rules()`**:
  ```php
  return [
      'km'           => ['required', 'integer', 'min:0'],
      'price'        => ['required', 'numeric', 'min:0'],
      'car_model_id' => [
          $this->route('car') ? 'nullable' : 'required',
          'exists:car_models,id',
      ],
  ];
  ```

### `App\Http\Requests\Service\UpdateServiceRequest` *(NEW — revised scope)*
- **`authorize()`**: `return $this->user()->can('update', $this->route('service'));`
- **`rules()`**: same shape as Store but every key prefixed with `'sometimes'`.

### `App\Http\Requests\ServiceCenter\NearbyServiceCentersRequest` *(query-param validation)*
- **`authorize()`**:
  ```php
  return $this->user()->can('viewAny', ServiceCenter::class);
  ```
- **`rules()`**:
  ```php
  return [
      'lat' => ['required', 'numeric', 'between:-90,90'],
      'lng' => ['required', 'numeric', 'between:-180,180'],
  ];
  ```
- **Notes**: Constitution § II forbids inline `$request->validate()`. Even though this validates query parameters (a read), wrapping it in a Form Request honours the rule and gives the lat/lng inputs a single declarative gate.

## New API Resources

### `App\Http\Resources\ServiceCenterResource`
```php
return [
    'id'         => $this->id,
    'brand_id'   => $this->brand_id,
    'name'       => $this->name,
    'address'    => $this->address,
    'open_at'    => $this->open_at?->format('H:i'),
    'close_at'   => $this->close_at?->format('H:i'),
    'mobile'     => $this->mobile,
    'lat'        => $this->lat,
    'lng'        => $this->lng,
    'is_open'    => $this->is_open,
    'distance_km'=> isset($this->distance_km) ? round((float) $this->distance_km, 2) : null,
    'brand'      => new BrandResource($this->whenLoaded('brand')),
    'created_at' => $this->created_at?->toISOString(),
    'updated_at' => $this->updated_at?->toISOString(),
];
```

### `App\Http\Resources\ServiceResource` *(used by both the future `services` index and the upcoming-services endpoint)*
```php
return [
    'id'           => $this->id,
    'car_model_id' => $this->car_model_id,
    'km'           => $this->km,
    'price'        => $this->price,
    'items_count'  => $this->whenCounted('items'),     // exposed when withCount('items') was called
    'remaining_km' => $this->when(
        $request->route('car') !== null,
        fn () => $this->km - $request->route('car')->current_km,
    ),
    'car_model'    => new CarModelResource($this->whenLoaded('carModel')),
    'items'        => ItemResource::collection($this->whenLoaded('items')),
    'created_at'   => $this->created_at?->toISOString(),
    'updated_at'   => $this->updated_at?->toISOString(),
];
```

### `App\Http\Resources\ItemResource`
```php
return [
    'id'         => $this->id,
    'name'       => $this->name,
    'price'      => $this->price,
    'services'   => ServiceResource::collection($this->whenLoaded('services')),
    'pivot'      => $this->when(isset($this->pivot), [
        'car_id' => $this->pivot->car_id ?? null,
    ]),
    'created_at' => $this->created_at?->toISOString(),
    'updated_at' => $this->updated_at?->toISOString(),
];
```

## New Controllers

### `App\Http\Controllers\ServiceCenterController` *(revised — full CRUD)*

| Action     | Authorization                                                    | Body                                                                                                                                                  |
|------------|------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------|
| `index`    | `NearbyServiceCentersRequest` (`authorize()` on policy)          | `repository->nearby($brand->id, $request->lat, $request->lng)` → `ServiceCenterResource::collection(...)` via `$this->success(...)`                  |
| `show`     | `$this->authorize('view', $serviceCenter)` + child-of-brand 404  | `success(new ServiceCenterResource($serviceCenter->load('brand')))`                                                                                  |
| `store`    | `StoreServiceCenterRequest::authorize()`                         | DB transaction → `repository->create([...$request->validated(), 'brand_id' => $brand->id])` → `success(new ServiceCenterResource($sc), 201, '...')` |
| `update`   | `UpdateServiceCenterRequest::authorize()` + child-of-brand 404   | DB transaction → `repository->update($request->validated(), $serviceCenter->id)` → `success(...)`                                                    |
| `destroy`  | `$this->authorize('delete', $serviceCenter)` + child-of-brand 404| `repository->delete($serviceCenter->id)` → `success([], 200, 'Service center deleted.')`                                                              |

### `App\Http\Controllers\ServiceController` *(NEW — revised scope; full CRUD)*

| Action            | Route                                  | Authorization                                                                | Body                                                                                                                                                                                |
|-------------------|----------------------------------------|------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `index`           | `GET /api/services`                    | `$this->authorize('viewAny', Service::class)`                                | `repository->spatie()->paginate()` → `paginated(..., ServiceResource::class)`. Repo's `scopeToUser` filters user-rows automatically                                                |
| `show`            | `GET /api/services/{service}`          | `$this->authorize('view', $service)`                                         | `success(new ServiceResource($service))`                                                                                                                                            |
| `store` *(user)*  | `POST /api/cars/{car}/services`        | `StoreServiceRequest::authorize()` *(car-owner branch)*                      | DB transaction → `repository->create([...$request->validated(), 'car_id' => $car->id, 'user_id' => auth()->id()])` → `success(new ServiceResource($svc), 201, 'Custom service created.')` |
| `storeCatalogue`  | `POST /api/services`                   | `StoreServiceRequest::authorize()` *(catalogue branch)*                      | DB transaction → `repository->create($request->validated())` → `success(new ServiceResource($svc), 201, 'Catalogue service created.')`                                            |
| `update`          | `PUT/PATCH /api/services/{service}`    | `UpdateServiceRequest::authorize()`                                          | DB transaction → `repository->update($request->validated(), $service->id)` → `success(...)`                                                                                         |
| `destroy`         | `DELETE /api/services/{service}`       | `$this->authorize('delete', $service)`                                       | `repository->delete($service->id)` → `success([], 200, 'Service deleted.')`                                                                                                        |

### `App\Http\Controllers\UpcomingServiceController`

| Action  | Authorization                                              | Body                                                                                                                                          |
|---------|------------------------------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------|
| `index` | `$this->authorize('viewAny', [Service::class, $car])`      | `repository->upcomingForCar($car)` → return via `$this->success(ServiceResource::collection(...))`. The resource pulls `current_km` from `$request->route('car')` for `remaining_km`. |

### `App\Http\Controllers\ItemController`

| Action     | Authorization                                                            | Body                                                                                                  |
|------------|--------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------|
| `index`    | `$this->authorize('viewAny', Item::class)`                               | `repository->spatie()->paginate()` → `paginated(..., ItemResource::class)`                            |
| `show`     | `$this->authorize('view', $item)`                                        | `success(new ItemResource($item))`                                                                    |
| `store`    | `StoreItemRequest::authorize()`                                          | Wrap in `DB::beginTransaction()` → `repository->create($request->validated())` → `success(...,201,'Item created successfully.')` |
| `update`   | `UpdateItemRequest::authorize()`                                         | Wrap in `DB::beginTransaction()` → `repository->update($request->validated(), $item->id)` → `success(...)` |
| `destroy`  | `$this->authorize('delete', $item)`                                      | `repository->delete($item->id)` → `success([], 200, 'Item deleted successfully.')`                    |

## Provider Registrations

### `App\Providers\RepositoryServiceProvider::register()`
```php
$this->app->bind(ServiceCenterRepository::class, ServiceCenterRepositoryEloquent::class);
$this->app->bind(ServiceRepository::class,       ServiceRepositoryEloquent::class);
$this->app->bind(ItemRepository::class,          ItemRepositoryEloquent::class);
```

### `App\Providers\AppServiceProvider::boot()`
```php
Gate::policy(ServiceCenter::class, ServiceCenterPolicy::class);
Gate::policy(Service::class,       ServicePolicy::class);
Gate::policy(Item::class,          ItemPolicy::class);
```

## Permissions (RolePermissionsSeeder)

Append to `$models`:
```php
'service-center', 'service', 'item',
```

Auto-generated CRUD permissions per slug (21 new permissions in total — 7 actions × 3 entities). After deploy: `php artisan sync:permissions`.

## Routing

### `routes/api.php` *(append inside `auth:sanctum` group)*

```php
// Items (master parts inventory)
Route::get   ('items',          [ItemController::class, 'index']);
Route::post  ('items',          [ItemController::class, 'store']);
Route::get   ('items/{item}',   [ItemController::class, 'show']);
Route::match (['put', 'patch'], 'items/{item}', [ItemController::class, 'update']);
Route::delete('items/{item}',   [ItemController::class, 'destroy']);

// Service centers — full CRUD nested by brand (revised scope)
Route::get   ('brands/{brand}/service-centers',                   [ServiceCenterController::class, 'index']);
Route::post  ('brands/{brand}/service-centers',                   [ServiceCenterController::class, 'store']);
Route::get   ('brands/{brand}/service-centers/{serviceCenter}',   [ServiceCenterController::class, 'show']);
Route::match (['put', 'patch'], 'brands/{brand}/service-centers/{serviceCenter}', [ServiceCenterController::class, 'update']);
Route::delete('brands/{brand}/service-centers/{serviceCenter}',   [ServiceCenterController::class, 'destroy']);

// Catalogue services (admin) — flat (revised scope)
Route::get   ('services',           [ServiceController::class, 'index']);
Route::post  ('services',           [ServiceController::class, 'storeCatalogue']);
Route::get   ('services/{service}', [ServiceController::class, 'show']);
Route::match (['put', 'patch'], 'services/{service}', [ServiceController::class, 'update']);
Route::delete('services/{service}', [ServiceController::class, 'destroy']);

// User-services nested by car (revised scope) — creator implied by auth()->id()
Route::post('cars/{car}/services', [ServiceController::class, 'store']);

// Upcoming maintenance (catalogue + this user's own services for the car)
Route::get('cars/{car}/upcoming-services', [UpcomingServiceController::class, 'index']);
```

> Routes are flat (no `/api/v1/` prefix) — corrects the user's brief per Decision 2.

## Functional-Requirement Coverage

| Requirement | Mechanism                                                                      |
|-------------|--------------------------------------------------------------------------------|
| FR-001      | `UpcomingServiceController::index` + `ServiceRepository::upcomingForCar`       |
| FR-002      | Repository `WHERE km > current_km` filter                                       |
| FR-003      | `ServiceResource` exposes `km`, `remaining_km`, `price`, `items_count`         |
| FR-004      | `->orderBy('km')` in `upcomingForCar`                                          |
| FR-005      | `ServicePolicy::viewAny(User, Car)` ownership/permission check                  |
| FR-006      | `ServiceCenterController::index` over `brands/{brand}/service-centers`          |
| FR-007      | `ServiceCenterRepository::nearby` Haversine + `orderBy('distance_km')`         |
| FR-008      | `selectRaw('… AS distance_km')` projected through `ServiceCenterResource`      |
| FR-009      | `ServiceCenter::is_open` accessor + `$appends`                                  |
| FR-010      | `ItemController` (index/show/store/update/destroy)                              |
| FR-011      | `unique:items,name` rule in `StoreItemRequest`                                  |
| FR-012      | `Rule::unique('items','name')->ignore($itemId)` in `UpdateItemRequest`         |
| FR-013      | All routes inside `auth:sanctum` group                                          |
| FR-014      | Migrations declare `cascadeOnDelete` (and `nullOnDelete` for pivot.car_id)      |
| FR-015      | `NearbyServiceCentersRequest` rules `between:-90,90` and `between:-180,180`     |
| FR-016      | `min:0` on price in `StoreItemRequest` / `UpdateItemRequest`                    |
