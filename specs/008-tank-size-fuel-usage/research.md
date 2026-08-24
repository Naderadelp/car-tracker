# Phase 0 Research: Tank Size & Percentage-Based Fuel Usage

No `NEEDS CLARIFICATION` markers remained after brainstorming. This document
records the design decisions that drive the implementation.

## Decision 1 — Consumption formula (partial-fill correction)

**Decision**: Order a car's fill-ups by `odometer` ascending. For each consecutive
pair *(i, i+1)*:

```
f = tank_percentage / 100                       (fraction, when recorded)
consumed_i = liters_{i+1} + (f_i − f_{i+1}) × tank_size
distance_i = odometer_{i+1} − odometer_i
```

The `(f_i − f_{i+1}) × tank_size` correction term is applied **only** when both
fill-ups have a recorded `tank_percentage` and the car has a `tank_size`; otherwise
it is `0`. Then `average_km_per_l = Σ distance_i / Σ consumed_i`.

**Rationale**: Between two fills, fuel consumed = (fuel right after the earlier fill)
− (fuel right before the later fill). The "fuel right before the later fill" equals
its after-fill amount minus the liters just added. Substituting after-fill levels
`f×tank_size` yields `consumed = liters_added + (f_prev − f_next) × tank_size`. This
correctly handles partial fills (after-level < 100%) instead of assuming every fill
returns the tank to the same level.

**Alternatives considered**:
- *Naive `distance / total_liters`* (today's method): over-counts because it includes
  the first fill's liters (no distance precedes it) and the last fill's unburned fuel.
  Rejected — it is exactly the inaccuracy this feature fixes.
- *Full-tank method (require fill-to-100%)*: simpler but forces users to always top up
  and discards partial-fill data. Rejected — the percentage signal exists precisely to
  support partial fills.

## Decision 2 — Graceful degradation

**Decision**: When `tank_size` or a pair's percentages are missing, drop the
correction term (treat it as 0). With no tank data at all, the formula reduces to
`distance_total / (total_liters − first_fill_liters)`.

**Rationale**: Excluding only the first fill's liters is still strictly more correct
than today's naive sum, and the endpoint never errors regardless of data coverage
(SC-004). The feature improves accuracy progressively as users supply more data.

## Decision 3 — Guards

**Decision**: Return `average = 0` when there are fewer than 2 fill-ups, or when
`Σ consumed ≤ 0`.

**Rationale**: Distance between fills is undefined with <2 fills; non-positive
consumption (sparse/inconsistent data) would produce negative or infinite km/L.
Zero is the existing sentinel already returned by `statistics()`.

## Decision 4 — Column types

**Decision**: `cars.tank_size` = `DECIMAL(6,2) NULL`; `fill_ups.tank_percentage` =
`DECIMAL(5,2) NULL`. Both nullable, no backfill.

**Rationale**: `DECIMAL(6,2)` covers tank sizes up to 9999.99 L (far beyond any real
tank) with two-decimal precision matching the existing `liters` column
(`decimal:2`). `DECIMAL(5,2)` covers 0.00–100.00 for a percentage. Nullable +
additive keeps full backward compatibility (SC-006) with zero data migration.

## Decision 5 — Editability via a profile-edit endpoint

**Decision**: Add `PUT auth/user` → `AuthController::updateProfile`, backed by a new
`UpdateProfileRequest`. It updates the authenticated user's `name` and their car's
`tank_size`. The target car is the user's car from registration, resolved via
`CarRepository::findWhereFirst(['user_id' => $user->id])`; each user is treated as
having a single car. `authorize()` simply requires an authenticated user (self-edit;
no model binding to authorize against).

**Rationale**: The product treats tank capacity as part of the user's profile, not a
standalone car resource. The project has no profile-edit endpoint yet, so this adds
one on the existing `AuthController` (next to `me`). No new policy or permission is
needed because the action only ever touches the caller's own records.

**Alternatives considered**:
- *Dedicated `PATCH cars/{car}` + `CarPolicy`*: rejected per product decision — editing
  belongs in profile edit, and a per-car endpoint adds a policy/permission surface that
  isn't wanted.
- *Move `tank_size` onto the User*: rejected — capacity is inherently per-car and would
  break the fuel math if a user ever has multiple cars.
- *Require a `car_id` in the request*: deferred — the single-car assumption keeps the
  profile-edit payload simple; revisit if multi-car support lands.

## Decision 6 — Percentage is supplementary, not a fuel-quantity input

**Decision**: `liters`/`amount_paid` handling in both fill-up endpoints is unchanged;
`tank_percentage` is an additional optional field only.

**Rationale**: The user noted the gauge reading is not 100% accurate, so liters
remains authoritative; the percentage only refines the between-fills correction.
