# Data Model: Logbook & Telemetry

## New Tables

### `fill_ups`

| Column      | Type            | Constraints                          | Notes                                     |
|-------------|-----------------|--------------------------------------|-------------------------------------------|
| id          | bigint unsigned | PK, auto-increment                   |                                           |
| car_id      | bigint unsigned | FK → cars.id, CASCADE on delete      |                                           |
| liters      | decimal(8,2)    | NOT NULL                             | Fuel volume added                         |
| odometer    | integer         | NOT NULL                             | Auto-snapshotted from car.current_km      |
| cost_egp    | decimal(10,2)   | NOT NULL                             | Total cost in Egyptian Pounds             |
| fill_date   | date            | NOT NULL                             | Date of refueling                         |
| created_at  | timestamp       |                                      |                                           |
| updated_at  | timestamp       |                                      |                                           |

### `trips`

| Column             | Type            | Constraints                     | Notes                                        |
|--------------------|-----------------|----------------------------------|----------------------------------------------|
| id                 | bigint unsigned | PK, auto-increment               |                                              |
| car_id             | bigint unsigned | FK → cars.id, CASCADE on delete  |                                              |
| start_time         | datetime        | NOT NULL                         |                                              |
| end_time           | datetime        | NOT NULL                         |                                              |
| start_lat          | decimal(10,8)   | NOT NULL                         | High-precision GPS                           |
| start_lng          | decimal(11,8)   | NOT NULL                         |                                              |
| end_lat            | decimal(10,8)   | NOT NULL                         |                                              |
| end_lng            | decimal(11,8)   | NOT NULL                         |                                              |
| total_distance_km  | decimal(8,2)    | NOT NULL                         | Calculated via Haversine over all waypoints  |
| created_at         | timestamp       |                                  |                                              |
| updated_at         | timestamp       |                                  |                                              |

## Modified Tables

### `cars` *(existing — no column changes)*
No schema changes. `current_km` (integer, default 0) already exists and is updated by:
- `TripObserver::created` → `current_km += round(trip.total_distance_km)`
- `FillUpController::store` (removed — see spec update: odometer is snapshot only, not a sync trigger)

## New Models

### `App\Models\FillUp`
- **Table**: `fill_ups`
- **Fillable**: `['car_id', 'liters', 'odometer', 'cost_egp', 'fill_date']`
- **Casts**: `fill_date => date`, `liters => decimal:2`, `cost_egp => decimal:2`
- **Relationships**: `car(): BelongsTo → Car`
- **Traits**: `LogsActivity` (getActivitylogOptions → logOnly ['*'])

### `App\Models\Trip`
- **Table**: `trips`
- **Fillable**: `['car_id', 'start_time', 'end_time', 'start_lat', 'start_lng', 'end_lat', 'end_lng', 'total_distance_km']`
- **Casts**: `start_time => datetime`, `end_time => datetime`, `start_lat/start_lng/end_lat/end_lng => decimal:8`, `total_distance_km => decimal:2`
- **Relationships**: `car(): BelongsTo → Car`
- **Traits**: `LogsActivity`

## Modified Models / Traits

### `App\Models\Traits\CarRelations` *(add HasMany relations)*
```
fillUps(): HasMany → FillUp
trips(): HasMany → Trip
```

## New Repository Layer

### `App\Repositories\Contracts\FillUpRepository`
Extends `RepositoryInterface`. Adds:
```
statistics(int $carId): array
// returns: total_fill_ups, total_cost_egp, total_liters, max_odometer, min_odometer
```

### `App\Repositories\Eloquent\FillUpRepositoryEloquent`
- `model()` → `FillUp::class`
- `allowedFiltersExact`: `['car_id']`
- `allowedSorts`: `['fill_date', 'cost_egp', 'liters', 'odometer']`
- `allowedDefaultSorts`: `['-fill_date']` *(domain sort: newest fill-up first)*
- `scopeToUser()`: `whereHas('car', fn($q) => $q->where('user_id', auth()->id()))`
- `statistics(int $carId)`: aggregate query via `app(FillUp::class)->newQuery()->where('car_id', $carId)->selectRaw(...)`

### `App\Repositories\Contracts\TripRepository`
Extends `RepositoryInterface`. No additional methods.

### `App\Repositories\Eloquent\TripRepositoryEloquent`
- `model()` → `Trip::class`
- `allowedFiltersExact`: `['car_id']`
- `allowedSorts`: `['start_time', 'total_distance_km']`
- `allowedDefaultSorts`: `['-start_time']` *(domain sort: most recent trip first)*
- `scopeToUser()`: `whereHas('car', fn($q) => $q->where('user_id', auth()->id()))`

## Updated Repositories

### `App\Repositories\Eloquent\CarRepositoryEloquent`
Add:
- `allowedIncludes`: `['brand', 'carModel', 'fillUps', 'trips']`
- `allowedFiltersExact`: `['brand_id', 'car_model_id', 'user_id', 'year']`
- `allowedSorts`: `['year', 'current_km', 'created_at']`
- `allowedDefaultSorts`: `['-id']` *(constitutionally required)*

### `App\Repositories\Eloquent\UserRepositoryEloquent`
Add:
- `allowedIncludes`: `['cars', 'documents']`
- `allowedDefaultSorts`: `['-id']` *(constitutionally required)*

## New Observer

### `App\Observers\TripObserver`
- **Trigger**: `created`
- **Logic**:
  ```
  $car = $trip->car
  $car->current_km += (int) round($trip->total_distance_km)
  $car->save()
  ```
- **Registration**: `Car::observe(TripObserver::class)` in `AppServiceProvider::boot()`

## New Policies

### `App\Policies\FillUpPolicy`
| Method      | Signature                                  | Logic                                              |
|-------------|--------------------------------------------|----------------------------------------------------|
| `viewAny`   | `(User $user, Car $car): bool`             | `$user->id === $car->user_id \|\| hasPermissionTo('index-fill-up')` |
| `create`    | `(User $user, Car $car): bool`             | `$user->id === $car->user_id \|\| hasPermissionTo('create-fill-up')` |
| `delete`    | `(User $user, FillUp $fillUp): bool`       | `$user->id === $fillUp->car->user_id \|\| hasPermissionTo('destroy-fill-up')` |

### `App\Policies\TripPolicy`
| Method      | Signature                                  | Logic                                              |
|-------------|--------------------------------------------|----------------------------------------------------|
| `viewAny`   | `(User $user, Car $car): bool`             | `$user->id === $car->user_id \|\| hasPermissionTo('index-trip')` |
| `create`    | `(User $user, Car $car): bool`             | `$user->id === $car->user_id \|\| hasPermissionTo('create-trip')` |

## New Form Requests

### `App\Http\Requests\FillUp\StoreFillUpRequest`
- `authorize()`: `$this->user()->can('create', [FillUp::class, $this->route('car')])`
- **Rules**: `liters` required numeric min:0.1 | `cost_egp` required numeric min:0 | `fill_date` required date before_or_equal:today

### `App\Http\Requests\Trip\StoreTripRequest`
- `authorize()`: `$this->user()->can('create', [Trip::class, $this->route('car')])`
- **Rules**: `coordinates` required array min:2 | `coordinates.*.lat` required numeric between:-90,90 | `coordinates.*.lng` required numeric between:-180,180 | `coordinates.*.timestamp` required date

## New API Resources

### `App\Http\Resources\FillUpResource`
Fields: `id`, `car_id`, `liters`, `odometer`, `cost_egp`, `fill_date` (→ `toDateString()`), `created_at` (→ `toISOString()`), `updated_at` (→ `toISOString()`)

### `App\Http\Resources\TripResource`
Fields: `id`, `car_id`, `start_time` (→ `toISOString()`), `end_time` (→ `toISOString()`), `start_lat`, `start_lng`, `end_lat`, `end_lng`, `total_distance_km`, `created_at`, `updated_at`

## Permissions (RolePermissionsSeeder)
Add to `$models`: `'fill-up'`, `'trip'`

Auto-generated permissions:
- `index-fill-up`, `show-fill-up`, `create-fill-up`, `edit-fill-up`, `destroy-fill-up`, `force-delete-fill-up`, `restore-fill-up`
- `index-trip`, `show-trip`, `create-trip`, `edit-trip`, `destroy-trip`, `force-delete-trip`, `restore-trip`
