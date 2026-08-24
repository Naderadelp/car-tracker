# Data Model: Parking Tracker

## New Tables

### `parking_records`

| Column       | Type            | Constraints                          | Notes                                                |
|--------------|-----------------|--------------------------------------|------------------------------------------------------|
| id           | bigint unsigned | PK, auto-increment                   |                                                      |
| car_id       | bigint unsigned | FK → cars.id, CASCADE on delete      | Sole ownership pointer (no direct `user_id`)         |
| name         | varchar(255)    | NULLABLE                             | Descriptive location (e.g. "Mall basement, Level B2")|
| description  | text            | NULLABLE                             | Free-form additional context                         |
| latitude     | decimal(10,8)   | NULLABLE                             | Range −90 to +90; required together with longitude   |
| longitude    | decimal(11,8)   | NULLABLE                             | Range −180 to +180; required together with latitude  |
| parked_at    | datetime        | NOT NULL                             | Time car was parked; never in the future             |
| created_at   | timestamp       |                                      |                                                      |
| updated_at   | timestamp       |                                      |                                                      |

**Indexes**:
- Implicit FK index on `car_id`.
- `index('parked_at')` — dominant access pattern is "newest first" ordering and "current" lookup.

**Migration file**: `database/migrations/2026_05_04_000005_create_parking_records_table.php`

## Modified Tables

### `cars` *(no schema change)*
No column modifications. The new `parkingRecords()` HasMany relation lives in `App\Models\Traits\CarRelations`.

## New Models

### `App\Models\ParkingRecord`
- **Table**: `parking_records`
- **Fillable**: `['car_id', 'name', 'description', 'latitude', 'longitude', 'parked_at']`
- **Casts**:
  - `parked_at => datetime`
  - `latitude  => decimal:8`
  - `longitude => decimal:8`
- **Relationships**: `car(): BelongsTo → Car`
- **Traits**: `LogsActivity` with `getActivitylogOptions(): LogOptions { return LogOptions::defaults()->logOnly(['*']); }`
- **`$logName`**: `'ParkingRecord'`

## Modified Models / Traits

### `App\Models\Traits\CarRelations` *(add HasMany relation)*
```php
use App\Models\ParkingRecord;

public function parkingRecords(): HasMany
{
    return $this->hasMany(ParkingRecord::class);
}
```

## New Repository Layer

### `App\Repositories\Contracts\ParkingRecordRepository`
Extends `RepositoryInterface`. Adds one domain method:
```php
public function current(int $carId): ?\App\Models\ParkingRecord;
```

### `App\Repositories\Eloquent\ParkingRecordRepositoryEloquent`
- `model()` → `ParkingRecord::class`
- **Spatie property declarations** (constitution § I — full set):

  | Property                | Value                                |
  |-------------------------|--------------------------------------|
  | `$allowedIncludes`      | `['car']`                            |
  | `$allowedFilters`       | `['name']`              *(partial)*  |
  | `$allowedFiltersExact`  | `['car_id']`                         |
  | `$allowedFilterScopes`  | `[]`                                 |
  | `$allowedSorts`         | `['parked_at', 'created_at']`        |
  | `$allowedDefaultSorts`  | `['-parked_at']`        *(domain — newest first)* |

- **`scopeToUser()`**: scopes via the parent car (mirrors Trip / FillUp):
  ```php
  protected function scopeToUser(): void
  {
      if (auth()->check() && !auth()->user()->isAdmin()) {
          $this->model = $this->model->whereHas('car', function ($q) {
              $q->where('user_id', auth()->id());
          });
      }
  }
  ```
- **`current(int $carId)`**: most-recent lookup, bypassing Spatie:
  ```php
  public function current(int $carId): ?ParkingRecord
  {
      return app($this->model())->newQuery()
          ->where('car_id', $carId)
          ->orderByDesc('parked_at')
          ->first();
  }
  ```

## Updated Repositories

### `App\Repositories\Eloquent\CarRepositoryEloquent`
Append `'parkingRecords'` to `$allowedIncludes` so clients can request `?include=parkingRecords` on Car endpoints.

| Property                | Before                                                  | After                                                                       |
|-------------------------|---------------------------------------------------------|-----------------------------------------------------------------------------|
| `$allowedIncludes`      | `['brand', 'carModel', 'fillUps', 'trips']`             | `['brand', 'carModel', 'fillUps', 'trips', 'parkingRecords']`               |
| `$allowedFiltersExact`  | `['brand_id', 'car_model_id', 'user_id']`               | unchanged                                                                   |
| `$allowedSorts`         | `['current_km', 'created_at']`                          | unchanged                                                                   |
| `$allowedDefaultSorts`  | `['-id']`                                               | unchanged                                                                   |

## New Policy

### `App\Policies\ParkingRecordPolicy`
Registered in `AppServiceProvider::boot()` via `Gate::policy(ParkingRecord::class, ParkingRecordPolicy::class)`. No `before()` method (global `Gate::before()` handles admin bypass).

| Method     | Signature                                       | Logic                                                                                     |
|------------|-------------------------------------------------|-------------------------------------------------------------------------------------------|
| `viewAny`  | `(User $user, Car $car): bool`                  | `$user->id === $car->user_id \|\| $user->hasPermissionTo('index-parking-record')`         |
| `create`   | `(User $user, Car $car): bool`                  | `$user->id === $car->user_id \|\| $user->hasPermissionTo('create-parking-record')`        |
| `delete`   | `(User $user, ParkingRecord $parkingRecord): bool` | `$user->id === $parkingRecord->car->user_id \|\| $user->hasPermissionTo('destroy-parking-record')` |

> No `view()` / `update()` methods. The "current" endpoint reuses `viewAny`. Records are immutable (Decision 7).

## New Form Request

### `App\Http\Requests\ParkingRecord\StoreParkingRecordRequest`
- **`authorize()`**:
  ```php
  return $this->user()->can('create', [ParkingRecord::class, $this->route('car')]);
  ```
- **`rules()`**:
  ```php
  return [
      'name'        => ['nullable', 'string', 'max:255', 'required_without_all:latitude,longitude'],
      'description' => ['nullable', 'string', 'max:1000'],
      'latitude'    => ['nullable', 'numeric', 'between:-90,90',  'required_with:longitude'],
      'longitude'   => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
      'parked_at'   => ['required', 'date', 'before_or_equal:now'],
  ];
  ```

**Rule coverage matrix**

| Submission                                       | Outcome | Rule firing                              |
|--------------------------------------------------|---------|------------------------------------------|
| `name="Mall"`, no coords                         | ✅ pass | name present satisfies `required_without_all` |
| `lat=30, lng=31`, no name                        | ✅ pass | coords present, name not required        |
| `name="Mall"`, `lat=30`, `lng=31`                | ✅ pass | hybrid record valid (edge case)          |
| `name=null`, no coords                           | ❌ 422  | `required_without_all:latitude,longitude` on name |
| `lat=30`, `lng=null`                             | ❌ 422  | `required_with:latitude` on longitude    |
| `parked_at` in the future                        | ❌ 422  | `before_or_equal:now`                    |
| `parked_at` exactly now                          | ✅ pass | boundary inclusive                       |
| `lat=999`                                        | ❌ 422  | `between:-90,90`                         |

## New API Resource

### `App\Http\Resources\ParkingRecordResource`
```php
return [
    'id'          => $this->id,
    'car_id'      => $this->car_id,
    'name'        => $this->name,
    'description' => $this->description,
    'latitude'    => $this->latitude,
    'longitude'   => $this->longitude,
    'parked_at'   => $this->parked_at?->toISOString(),
    'car'         => new CarResource($this->whenLoaded('car')),
    'created_at'  => $this->created_at?->toISOString(),
    'updated_at'  => $this->updated_at?->toISOString(),
];
```

## New Controller

### `App\Http\Controllers\ParkingRecordController`
Extends `BaseController`. Constructor injects `ParkingRecordRepository`. Actions:

| Action     | Authorization                                           | Body                                                                                          |
|------------|----------------------------------------------------------|-----------------------------------------------------------------------------------------------|
| `index`    | `$this->authorize('viewAny', [ParkingRecord::class, $car])` | `repository->where('car_id', $car->id)->spatie()->paginate()` → `paginated(..., ParkingRecordResource::class)` |
| `current`  | `$this->authorize('viewAny', [ParkingRecord::class, $car])` | `repository->current($car->id)`; `abort_if(is_null(...), 404, 'No parking history found.')`; return `success(new ParkingRecordResource(...), 200)` |
| `store`    | Form Request `authorize()`                                | Wrap in `DB::beginTransaction()` → `repository->create([...$request->validated(), 'car_id' => $car->id])` → `success(new ParkingRecordResource($record), 201, 'Parking location recorded successfully.')` |
| `destroy`  | `$this->authorize('delete', $parkingRecord)`              | `abort_if($parkingRecord->car_id !== $car->id, 404)`; `repository->delete($parkingRecord->id)`; `success([], 200, 'Parking record deleted successfully.')` |

## Provider Registrations

### `App\Providers\RepositoryServiceProvider::register()`
Add binding:
```php
$this->app->bind(ParkingRecordRepository::class, ParkingRecordRepositoryEloquent::class);
```

### `App\Providers\AppServiceProvider::boot()`
Add policy binding:
```php
Gate::policy(ParkingRecord::class, ParkingRecordPolicy::class);
```

## Permissions (RolePermissionsSeeder)

Add to `$models`: `'parking-record'`.

Auto-generated CRUD permissions:
- `index-parking-record`
- `show-parking-record`         *(unused — no `show` action)*
- `create-parking-record`
- `edit-parking-record`         *(unused — immutable)*
- `destroy-parking-record`
- `force-delete-parking-record` *(unused)*
- `restore-parking-record`      *(unused)*

After deploy: `php artisan sync:permissions`.

## Routing

### `routes/api.php` *(append inside `cars/{car}` group)*
```php
// Parking Records
Route::get   ('parking-records',                   [ParkingRecordController::class, 'index']);
Route::get   ('parking-records/current',           [ParkingRecordController::class, 'current']);
Route::post  ('parking-records',                   [ParkingRecordController::class, 'store']);
Route::delete('parking-records/{parkingRecord}',   [ParkingRecordController::class, 'destroy']);
```

> The `current` route is registered BEFORE `{parkingRecord}` so the literal segment matches first; otherwise Laravel would attempt to bind `current` as a `ParkingRecord` ID.

## Functional-Requirement Coverage

| Requirement | Mechanism |
|-------------|-----------|
| FR-001 — Either GPS or descriptive | `StoreParkingRecordRequest` rules (`required_without_all` + `required_with`) |
| FR-002 — Reject blank entries | `required_without_all:latitude,longitude` on `name` |
| FR-003 — `parked_at` required, no future | `required` + `before_or_equal:now` on `parked_at` |
| FR-004 — Retrieve current location | `GET …/parking-records/current` → `repository->current()` |
| FR-005 — 404 on no history | `abort_if(is_null(...), 404)` in `current` action |
| FR-006 — Full history reverse-chronological | `$allowedDefaultSorts = ['-parked_at']` |
| FR-007 — Owner can delete | `destroy` action + `ParkingRecordPolicy::delete` |
| FR-008 — Cross-user denial | `scopeToUser()` + policy ownership checks |
| FR-009 — Cascade on car delete | FK `cascadeOnDelete()` |
| FR-010 — Lat/lng range validation | `between:-90,90` and `between:-180,180` |
