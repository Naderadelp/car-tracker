# Design: tank_size + percentage-based fuel-usage refinement

**Date**: 2026-06-02
**Status**: Superseded in part — see amendment
**Area**: Cars, Fill-ups, fuel statistics

> **Amendment (implementation)**: The "editable later" mechanism changed during
> implementation. Instead of a dedicated `PATCH cars/{car}` endpoint + `CarPolicy`
> + `edit-car` permission, tank capacity is edited through a **profile-edit**
> endpoint `PUT auth/user` (`AuthController::updateProfile`) that updates the
> authenticated user's `name` and their car's `tank_size`. No new policy or
> permission is introduced. The authoritative spec is
> `specs/008-tank-size-fuel-usage/`. Everything else below still holds.

## Summary

Add a per-car `tank_size` (full fuel capacity, in liters) and an after-fill
`tank_percentage` (0–100 gauge reading) on each fill-up. Use both, together with
the existing `liters`/`amount_paid` data, to compute a more accurate average fuel
usage reported as **km/L**.

`tank_size` is collected (optionally) at signup and editable later via a new
focused car-edit endpoint. `tank_percentage` is optional on every fill-up. Both
the existing liters/amount inputs are kept unchanged — the percentage is a
supplementary signal used only to correct for partial fills, because the gauge
reading is not 100% accurate.

## Decisions (from brainstorming)

- **Percentage meaning**: tank level reached **after** filling.
- **Inputs**: keep `liters`/`amount_paid` as the accurate base; percentage is layered on top.
- **Metric**: average usage reported as **km/L**.
- **tank_size**: optional at signup, editable later (new car-edit endpoint).
- **tank_percentage**: optional (nullable) on all fill-up endpoints.

## Data model

Two additive migrations (no data backfill needed):

| Table | Column | Type | Notes |
|-------|--------|------|-------|
| `cars` | `tank_size` | `DECIMAL(6,2)` NULL | Full capacity in liters |
| `fill_ups` | `tank_percentage` | `DECIMAL(5,2)` NULL | Gauge level after filling, 0–100 |

- `Car`: add `tank_size` to `$fillable`, cast `decimal:2`.
- `FillUp`: add `tank_percentage` to `$fillable`, cast `decimal:2`.

## Signup

- `RegisterUserRequest`: add `tank_size => ['nullable','numeric','min:0.1','max:999']`.
- `AuthController::register`: pass `tank_size` into the `carRepository->create([...])` array.

## Editable later — new car-edit endpoint

No car-update path exists today; add a minimal one scoped to `tank_size`
(extensible to other mutable car fields later):

- Route: `PATCH cars/{car}` → `CarController::update`.
- `UpdateCarRequest`: authorize via `$this->user()->can('update', $this->route('car'))`;
  rule `tank_size => ['required','numeric','min:0.1','max:999']`.
- `CarPolicy::update(User $user, Car $car)`: `$user->id === $car->user_id || $user->hasPermissionTo('update-car')`.
- Register `Gate::policy(Car::class, CarPolicy::class)` in `AppServiceProvider`
  (admin already covered by global `Gate::before`).
- Add `update-car` permission to `RolePermissionsSeeder`.
- Returns `CarResource`.

## Fill-up percentage capture

Both endpoints gain the optional field; liters/amount handling is unchanged.

- `StoreFillUpRequest` & `QuickFillUpRequest`: add
  `tank_percentage => ['nullable','numeric','min:0','max:100']`.
- `FillUpController::store` & `::quick`: persist `tank_percentage` on create.

## Refined consumption math

Replace the single-SQL aggregate in `FillUpRepositoryEloquent::statistics()`
with an ordered walk over the car's fill-ups (per-car counts are tiny).

Order fill-ups by `odometer` ascending. For each consecutive pair *(i, i+1)*:

```
f      = tank_percentage / 100            (fraction, when known)
consumed_i = liters_added_{i+1} + (f_i − f_{i+1}) × tank_size

  The (f_i − f_{i+1}) × tank_size correction term is included ONLY when
  both percentages and tank_size are known; otherwise it is 0.

distance_i = odometer_{i+1} − odometer_i
```

Then:

```
average_consumption (km/L) = Σ distance_i / Σ consumed_i   (over all pairs)
```

Properties:

- Excludes the **first** fill's liters from consumption (today's naive method
  wrongly counts them — this is the bug being fixed).
- Corrects for partial fills wherever percentage data exists.
- **Degrades gracefully**: with no percentages / no tank_size, it reduces to
  `distance ÷ (total_liters − first_fill_liters)` — still more correct than today.

Guards:

- Fewer than 2 fill-ups, or `Σ consumed ≤ 0` → `average_consumption = "0.00"`.

Payload: keep existing keys (`total_fill_ups`, `total_cost_egp`,
`average_consumption`); add `total_distance_km`.

## Output (resources)

- `CarResource`: add `tank_size`.
- `FillUpResource`: add `tank_percentage`.

## Scope / non-goals

- **Electric** (`fuel_type=electric`): `tank_size`/`tank_percentage` remain
  storable, but km/L labeling is liquid-fuel oriented; not special-cased now.
  A null `tank_size` simply skips the correction term.
- No automated tests (project `Testing: N/A`).
- Per project convention: **no per-task commits** — a single final commit is
  made by the user.

## Touched files

```
database/migrations/2026_06_02_*_add_tank_size_to_cars_table.php          [NEW]
database/migrations/2026_06_02_*_add_tank_percentage_to_fill_ups_table.php [NEW]
app/Models/Car.php                                  [MODIFY fillable + cast]
app/Models/FillUp.php                               [MODIFY fillable + cast]
app/Http/Requests/Auth/RegisterUserRequest.php      [MODIFY +tank_size]
app/Http/Controllers/Auth/AuthController.php        [MODIFY pass tank_size]
app/Http/Controllers/CarController.php              [NEW update()]
app/Http/Requests/Car/UpdateCarRequest.php          [NEW]
app/Policies/CarPolicy.php                           [NEW update()]
app/Providers/AppServiceProvider.php                [MODIFY register CarPolicy]
database/seeders/RolePermissionsSeeder.php          [MODIFY +update-car]
routes/api.php                                       [MODIFY +PATCH cars/{car}]
app/Http/Requests/FillUp/StoreFillUpRequest.php     [MODIFY +tank_percentage]
app/Http/Requests/FillUp/QuickFillUpRequest.php     [MODIFY +tank_percentage]
app/Http/Controllers/FillUpController.php           [MODIFY persist tank_percentage]
app/Repositories/Eloquent/FillUpRepositoryEloquent.php [MODIFY statistics()]
app/Http/Resources/CarResource.php                  [MODIFY +tank_size]
app/Http/Resources/FillUpResource.php               [MODIFY +tank_percentage]
```
