# Phase 1 Data Model: Tank Size & Percentage-Based Fuel Usage

## Modified entities

### Car (`cars` table)

| New column | Type | Null | Default | Notes |
|------------|------|------|---------|-------|
| `tank_size` | `DECIMAL(6,2)` | YES | `NULL` | Full fuel capacity in liters |

- Model: add `tank_size` to `$fillable`; cast `'tank_size' => 'decimal:2'`.
- Activity log: already `logOnly(['*'])` → covered automatically.
- Validation (register: `nullable`; profile-edit `PUT auth/user`: `sometimes|nullable`), `numeric`, `min:0.1`, `max:999`.

### FillUp (`fill_ups` table)

| New column | Type | Null | Default | Notes |
|------------|------|------|---------|-------|
| `tank_percentage` | `DECIMAL(5,2)` | YES | `NULL` | Fuel gauge level (%) reached after filling, 0–100 |

- Model: add `tank_percentage` to `$fillable`; cast `'tank_percentage' => 'decimal:2'`.
- Activity log: already `logOnly(['*'])` → covered automatically.
- Validation (store & quick): `nullable`, `numeric`, `min:0`, `max:100`.

## Migrations

Both are additive and require no backfill.

1. `add_tank_size_to_cars_table` — `$table->decimal('tank_size', 6, 2)->nullable()->after('current_km');`
2. `add_tank_percentage_to_fill_ups_table` — `$table->decimal('tank_percentage', 5, 2)->nullable()->after('liters');`

## Derived: fuel-usage statistics

Computed by `FillUpRepositoryEloquent::statistics(int $carId): array` from the car's
fill-ups (ordered by `odometer` asc) and the car's `tank_size`.

| Key | Type | Meaning |
|-----|------|---------|
| `total_fill_ups` | int | Count of fill-ups (unchanged) |
| `total_cost_egp` | string (2dp) | Sum of `cost_egp` (unchanged) |
| `average_consumption` | string (2dp) | **km/L**, refined per the consumption formula |
| `total_distance_km` | int | `max(odometer) − min(odometer)` (new) |

### Computation rules (see research.md Decision 1–3)

- Order fills by `odometer` ascending.
- For each consecutive pair *(i, i+1)*:
  `consumed_i = liters_{i+1} + (f_i − f_{i+1}) × tank_size` (correction term only when
  both `tank_percentage` values and `tank_size` are present, else 0);
  `distance_i = odometer_{i+1} − odometer_i`.
- `average_consumption = round(Σ distance_i / Σ consumed_i, 2)`.
- Guards: `< 2` fills OR `Σ consumed ≤ 0` → `average_consumption = "0.00"`.

## Relationships

No relationship changes. `FillUp belongsTo Car`; statistics read a single car's fills
plus that car's `tank_size`.
