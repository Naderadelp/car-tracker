# Research: Parking Tracker

## Decision 1 — Table & Module Naming
- **Decision**: Table `parking_records`, model `App\Models\ParkingRecord`, repository pair `ParkingRecordRepository` / `ParkingRecordRepositoryEloquent`, controller `ParkingRecordController`, requests under `App\Http\Requests\ParkingRecord\`, policy `ParkingRecordPolicy`, route segment `parking-records`, permission slug `parking-record` (auto-generates `index-parking-record`, `create-parking-record`, `destroy-parking-record`, …).
- **Rationale**: Spec entity is called "Parking Record." Matches the project convention used for `FillUp` / `Trip` (singular Eloquent model, plural snake-case table, kebab-case route segment, kebab-case permission slug). Constitution § IV mandates flat models under `app/Models/` and per-domain Request/Resource folders.
- **Alternatives considered**: `Parking` (singular) — rejected, ambiguous with the act of parking versus a recorded event; `ParkingLog` — rejected, "log" reads as audit trail, not user-facing data.

## Decision 2 — Schema for "GPS or descriptive" Location
- **Decision**: `parking_records` carries `name` (nullable string), `description` (nullable text), `latitude` (nullable `decimal(10,8)`), `longitude` (nullable `decimal(11,8)`), `parked_at` (datetime). At least one of (`name`, GPS pair) is enforced in the Form Request, NOT at the DB level (no DB-level CHECK constraint).
- **Rationale**: FR-001 / FR-002 require either descriptive text or GPS, with mixed records valid. Validation belongs in the Form Request per Principle II — DB CHECK constraints would duplicate the rule and make MySQL/MariaDB compatibility brittle. Lat/lng widths match the existing `trips` table (`decimal(10,8)` / `decimal(11,8)`) for consistency.
- **Alternatives considered**: Single JSON `location` column — rejected, blocks Spatie filters/sorts on coordinates and breaks the "include either name or coords" expectation in API responses; CHECK constraint at DB level — rejected, adds a parallel rule the Form Request must keep in sync.

## Decision 3 — Cross-field Validation: name OR coords
- **Decision**: In `StoreParkingRecordRequest`:
  ```
  name        => nullable | string | max:255 | required_without_all:latitude,longitude
  description => nullable | string | max:1000
  latitude    => nullable | numeric | between:-90,90  | required_with:longitude
  longitude   => nullable | numeric | between:-180,180 | required_with:latitude
  parked_at   => required | date | before_or_equal:now
  ```
- **Rationale**:
  - `required_without_all:latitude,longitude` — if BOTH coords are missing, `name` MUST be present (FR-002).
  - `required_with` pair — supplying only one of lat/lng triggers a validation error (edge case: incomplete coordinate pair is invalid GPS).
  - `before_or_equal:now` — accepts the boundary (FR-003 / edge case: `parked_at` equals current time exactly).
- **Alternatives considered**: Custom `Rule::when()` closure — rejected, more code for the same enforcement; `prohibited_unless` — rejected, expresses the inverse and is harder to read.

## Decision 4 — Includes / Filters / Sorts (per user prompt)
- **Decision**: `ParkingRecordRepositoryEloquent` declares the full Spatie property set:
  ```php
  protected array $allowedIncludes      = ['car'];
  protected array $allowedFiltersExact  = ['car_id'];
  protected array $allowedFilters       = ['name'];                    // partial match
  protected array $allowedFilterScopes  = [];
  protected array $allowedSorts         = ['parked_at', 'created_at'];
  protected array $allowedDefaultSorts  = ['-parked_at'];              // newest first
  ```
  `CarRepositoryEloquent::$allowedIncludes` is extended with `'parkingRecords'`.
- **Rationale**: Constitution § I requires every repository to declare these arrays and a `-id` (or domain-stronger) default sort. Newest-parked-first is the dominant access pattern (FR-006: history reverse-chronological), so `-parked_at` is the stronger natural sort and overrides `-id`. Partial filter on `name` lets the client narrow history (e.g. `?filter[name]=mall`). `car_id` exact filter is included for admin/cross-car queries; the route already constrains by `car_id`, but the filter is required for the Spatie pipeline. Adding `parkingRecords` to `Car`'s `$allowedIncludes` lets clients embed history in `GET /api/cars/{car}` responses on demand.
- **Alternatives considered**: Allow filtering on `parked_at` ranges (e.g. `AllowedFilter::scope('parkedBetween')`) — deferred, no spec requirement and YAGNI; auto-eager-load `car` via `$include` — rejected, the route already provides the car, no need to round-trip the relation on every read.

## Decision 5 — `scopeToUser()` Hook
- **Decision**: `ParkingRecordRepositoryEloquent::scopeToUser()` constrains to the current user's cars:
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
- **Rationale**: `parking_records.car_id` is the only ownership pointer (no direct `user_id`) — same shape as `trips` and `fill_ups`. Constitution § I marks this hook NON-NEGOTIABLE for any user-owned entity. Defense-in-depth: even if route-model binding is bypassed, non-admin reads cannot leak across users (SC-006).
- **Alternatives considered**: Skip `scopeToUser` and rely solely on policy + route binding — rejected, violates constitution.

## Decision 6 — "Current" Endpoint Strategy
- **Decision**: Add `current(int $carId): ?ParkingRecord` to `ParkingRecordRepository` contract. Implementation: `app($this->model())->newQuery()->where('car_id', $carId)->orderByDesc('parked_at')->first()`. Controller authorizes via `$this->authorize('viewAny', [ParkingRecord::class, $car])`, then `abort_if(is_null($record), 404, 'No parking history found.')`.
- **Rationale**: The "most recent" lookup is a domain query that benefits from explicit naming, matching the precedent set by `FillUpRepository::statistics()`. Authorization reuses the `viewAny` policy ability — no new ability is needed since the operation is "show the latest of the list." Returning 404 instead of `null` JSON satisfies FR-005 unambiguously.
- **Alternatives considered**: Inline `findWhereFirst(['car_id' => $car->id])` after an `orderBy('parked_at', 'desc')` chain — rejected, `EloquentRepository` does not expose a fluent `first()`; adding it would touch the base class for one consumer; raw `Eloquent::where(...)->first()` in the controller — rejected, violates Principle I.

## Decision 7 — Immutability (No Update Endpoint)
- **Decision**: Routes expose `index`, `store`, `current`, `destroy` only. No `update` / `PUT` route, no `update()` policy method, no Form Request for update.
- **Rationale**: Spec § Assumptions: "A parking record is immutable after creation." Adding an unused update path would create dead code and a permission (`edit-parking-record`) with no caller.
- **Alternatives considered**: Allow updates "for symmetry" — rejected, contradicts the spec.

## Decision 8 — Cascade Delete on Car Removal
- **Decision**: `parking_records.car_id` FK declared with `->constrained('cars')->cascadeOnDelete()`. The `cars` table uses `SoftDeletes` — Laravel soft-deletes do NOT trigger DB-level cascade, so for hard deletes (admin force-delete), the cascade kicks in; for soft deletes the records remain attached to the (soft-deleted) car and become inaccessible via `scopeToUser` because the soft-deleted car is excluded from `whereHas('car', …)` by default.
- **Rationale**: Matches `trips` and `fill_ups` migrations exactly. SC-007 requires zero orphans on car deletion — the FK guarantees this for hard deletes; soft-delete behavior is consistent with existing logbook/telemetry entities.
- **Alternatives considered**: `->nullOnDelete()` — rejected, an orphaned parking record with `car_id = NULL` violates the entity's invariant ("belongs to one Car"). Manual cleanup in a `Car` model `deleting` hook — rejected, FK cascade is simpler and atomic.

## Decision 9 — Permission Slug & Seeder
- **Decision**: Add `'parking-record'` to `RolePermissionsSeeder::$models`. Auto-generated permissions: `index-parking-record`, `show-parking-record`, `create-parking-record`, `edit-parking-record`, `destroy-parking-record`, `force-delete-parking-record`, `restore-parking-record`. After deploy, run `php artisan sync:permissions`.
- **Rationale**: Constitution § V CRUD pattern — `{action}-{model-slug}`. Even though `edit-parking-record` and the restore/force-delete trio have no controller routes (immutability + no soft delete), they are produced uniformly by the seeder loop. They cost nothing and keep the permission table predictable.
- **Alternatives considered**: Manually whitelist only the three actions used (`index`, `create`, `destroy`) — rejected, requires special-casing the seeder loop and breaks the convention used by every other model.

## Decision 10 — API Route Layout
- **Decision**: Routes nested under `cars/{car}` inside the existing `auth:sanctum` group:
  ```
  GET    /api/cars/{car}/parking-records          → index
  GET    /api/cars/{car}/parking-records/current  → current
  POST   /api/cars/{car}/parking-records          → store
  DELETE /api/cars/{car}/parking-records/{parkingRecord} → destroy
  ```
  No URL versioning. Controller lives at `app/Http/Controllers/ParkingRecordController.php` (flat namespace).
- **Rationale**: Constitution § "API Routing Convention" forbids `/v1/`, and Principle IV forbids `Api\V1\` namespacing. The nested-by-car shape matches `fill-ups` and `trips` precedent and makes ownership explicit at the URL level.
- **Alternatives considered**: Top-level `/api/parking-records` resource with `?car_id=` filter — rejected, breaks the per-car nesting convention already established for sub-entities of Car.
