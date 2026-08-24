# Research: Logbook & Telemetry

## Decision 1 — API Route Prefix
- **Decision**: No version prefix. Routes registered as `/api/cars/{car}/fill-ups` and `/api/cars/{car}/trips`.
- **Rationale**: Constitution § API Routing Convention explicitly forbids `/v1/` prefix segments. The feature spec input contained `api/v1/` — corrected here.
- **Alternatives considered**: Keep `v1` prefix → rejected, violates constitution.

## Decision 2 — Controller Namespace
- **Decision**: `App\Http\Controllers\FillUpController` and `App\Http\Controllers\TripController`.
- **Rationale**: Constitution § IV forbids `Api\V1\` namespacing. The spec input referenced this namespace — corrected.
- **Alternatives considered**: Domain subfolder (e.g., `Controllers\Logbook\`) → allowed by constitution but unnecessary complexity for two controllers.

## Decision 3 — Odometer Field Name
- **Decision**: Observer increments `$car->current_km` (not `current_mileage` as written in spec input).
- **Rationale**: The `cars` table column is `current_km` (integer). `current_mileage` does not exist.
- **Alternatives considered**: Rename column to `current_mileage` → rejected, requires changes to existing Car model, migrations, and resources with no added value.

## Decision 4 — Odometer Rounding in Observer
- **Decision**: `$car->current_km += (int) round($trip->total_distance_km)` — round to nearest whole km.
- **Rationale**: `current_km` is an integer column. Adding a decimal without rounding would truncate silently. Rounding to nearest km matches real odometer display behavior.
- **Alternatives considered**: Change `current_km` to decimal → requires migration change; overkill for an odometer that displays whole numbers.

## Decision 5 — Odometer Auto-Snapshot on Fill-Up
- **Decision**: `FillUpController::store` reads `$car->current_km` and sets `fill_up.odometer` before calling `repository->create()`. The user does not provide the odometer value.
- **Rationale**: Spec design: user enters litres/cost/date only; the car's master odometer is the source of truth for consumption calculations.
- **Alternatives considered**: Trigger via model observer → harder to test and less explicit.

## Decision 6 — Statistics Query
- **Decision**: Add `statistics(int $carId): array` to `FillUpRepository` contract and implement in `FillUpRepositoryEloquent`. Returns `total_fill_ups`, `total_cost_egp`, `total_liters`, `max_odometer`, `min_odometer`.
- **Rationale**: Constitution forbids direct Eloquent queries in controllers. Aggregate queries (COUNT/SUM/MAX/MIN) must go through the repository layer.
- **Alternatives considered**: Raw `DB::table()` in controller → violates constitution.

## Decision 7 — scopeToUser for FillUp and Trip
- **Decision**: Both repositories override `scopeToUser()` using `whereHas('car', fn($q) => $q->where('user_id', auth()->id()))`.
- **Rationale**: FillUp and Trip have `car_id`, not `user_id` directly. The constitution requires scoping for user-owned entities. Defense-in-depth: even if a bad actor bypasses route-model binding, non-admin users cannot read another user's fill-ups or trips.
- **Alternatives considered**: Skip scopeToUser since ownership is enforced at route/policy level → rejected, constitution mandates scoping for all user-owned data.

## Decision 8 — Haversine Calculation Location
- **Decision**: Private method `haversineKm()` inside `TripController`.
- **Rationale**: No service layer exists in this project. Pure math function with no side effects. Constitution does not mandate a service class.
- **Alternatives considered**: Dedicated service class → premature abstraction for a single formula.

## Decision 9 — DB Transaction Scope
- **Decision**: Both `FillUpController::store` and `TripController::store` wrap the entire operation (create + side effect) in `DB::beginTransaction() / commit() / rollBack()`.
- **Rationale**: Constitution § VI requires transactions for any write with side effects. Fill-up store updates `car.current_km` (conditional). Trip store triggers the observer which updates `car.current_km`.
- **Alternatives considered**: No transaction → observer update and trip create could diverge on failure.

## Decision 10 — CarRepository & UserRepository Updates
- **Decision**: Expand `CarRepositoryEloquent` with `allowedIncludes = ['brand', 'carModel', 'fillUps', 'trips']`, `allowedFiltersExact = ['brand_id', 'car_model_id', 'user_id', 'year']`, `allowedSorts = ['year', 'current_km', 'created_at']`, `allowedDefaultSorts = ['-id']`. Expand `UserRepositoryEloquent` with `allowedDefaultSorts = ['-id']` and `allowedIncludes = ['cars', 'documents']`.
- **Rationale**: User explicitly requested these additions. `allowedDefaultSorts = ['-id']` is constitutionally required on every repository.
- **Alternatives considered**: Leave unchanged → violates the constitution's mandatory default sort rule.
