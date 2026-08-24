# Phase 1 Data Model: Close the CarLog Mobile API Gaps

**Feature**: `010-mobile-api-gaps` · **Date**: 2026-08-24

Two new tables, nine altered ones. Every column below is justified by a numbered
requirement in `spec.md`.

---

## New tables

### `costs` — the unified cost ledger (US4, FR-014 – FR-017, FR-042 – FR-046)

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | bigint PK | no | |
| `car_id` | FK → `cars` | no | cascade on delete |
| `user_id` | FK → `users` | no | denormalised for `scopeToUser()` (Principle I) |
| `spent_at` | date | no | |
| `title` | string(255) | no | |
| `amount_egp` | decimal(10,2) | no | matches `fill_ups.cost_egp` and `car_logs.actual_cost` |
| `category` | enum | no | `fuel`, `service`, `insurance`, `tyres`, `warranty`, `other` |
| `source_type` | string(64) | **yes** | null = typed in by the driver; otherwise the source model |
| `source_id` | bigint | **yes** | the source record's id |
| `amount_overridden` | boolean | no | default `false`; set when a driver edits a carried-across amount |
| `timestamps` | | | |

**Indexes**: `(car_id, spent_at)` for the ledger read; a unique
`(source_type, source_id)` so a source record can never produce two rows.

**The `source_*` columns are the whole of decision D2.** A row with
`source_type = null` is a manual entry and behaves like any other record. A row
with a source is owned by a `FillUp` or a `CarLog` until the driver overwrites
its amount, at which point `amount_overridden` flips and the observers leave it
alone (FR-046).

**State transitions**

```
                    driver types an entry
                             │
                             ▼
                    manual  (source_type = null)  ──── driver deletes ───▶ gone
                             
  fill-up / car-log filed
             │
             ▼
    carried-across  (source_type set, amount_overridden = false)
             │                                    │
   source edited → row follows                    │ driver edits amount
             │                                    ▼
   source deleted → row deleted        overridden (amount_overridden = true)
                                                  │
                                        source edited → row UNCHANGED
                                        source deleted → row survives as manual
```

The last transition is the one worth arguing about, and it is called out as an
edge case in the spec: when a source record is deleted after the driver has
overridden its amount, the driver's figure is deliberate data and is kept, with
`source_type` and `source_id` cleared so it becomes an ordinary manual entry.

### `issues` — the fault log (US5, FR-018 – FR-021)

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | bigint PK | no | |
| `car_id` | FK → `cars` | no | cascade on delete |
| `user_id` | FK → `users` | no | for `scopeToUser()` |
| `occurred_at` | date | no | |
| `title` | string(255) | no | |
| `severity` | enum | no | `low`, `medium`, `high` — ordered; `high` is "serious" for FR-021 |
| `summary` | text | yes | what was wrong |
| `solution` | text | yes | what was done |
| `note` | text | yes | |
| `resolved_at` | timestamp | **yes** | null = unresolved; presence is the resolved flag |
| `timestamps` | | | |

**Indexes**: `(car_id, resolved_at)` — the attention list reads exactly this.

Implements `HasMedia` with a `photo` collection declared `->singleFile()` and
`->useDisk('local')`, matching `Document` (Principle VI). One photo per fault,
per the spec's assumptions.

---

## Altered tables

### `cars` (US1, US9)

| Column | Type | Null | Requirement |
|---|---|---|---|
| `color` | string(32) | yes | FR-003 |
| `purchase_price_egp` | decimal(12,2) | yes | FR-034 |
| `purchased_at` | date | yes | FR-034 |

`current_km`, `has_warranty`, `warranty_limit_km` and `warranty_expiry_date`
already exist — US1 makes them writable, it does not add them.

### `fill_ups` (US2, US9)

| Column | Type | Null | Requirement |
|---|---|---|---|
| `station_name` | string(255) | yes | FR-009 |

`fuel_type`, `station_lat`, `station_lng` and `tank_percentage` already exist
(R2). `odometer` stays `NOT NULL`; FR-010's fallback is controller logic.
`km_per_liter` (FR-033) is **computed in the resource, not stored** — it depends
on the preceding fill-up, so a stored value would go stale the moment a record
between two others is inserted or deleted.

### `car_logs` (US7)

| Column | Type | Null | Requirement |
|---|---|---|---|
| `title` | string(255) | yes | FR-025 |
| `workshop` | string(255) | yes | FR-025 |
| `category` | string(64) | yes | FR-025 |
| `notes` | text | yes | FR-025 |

All nullable — existing rows have none of this and must keep working.

### `trips` (US7)

| Column | Type | Null | Requirement |
|---|---|---|---|
| `started_at` | timestamp | yes | FR-026 |
| `ended_at` | timestamp | yes | FR-026 |
| `duration_seconds` | unsigned int | yes | FR-026 |
| `max_speed_kmh` | decimal(6,2) | yes | FR-026 |

`duration_seconds` is stored rather than derived from the two timestamps because
the client measures it directly and a trip may be filed with a duration but no
wall-clock times.

### `parking_records` (US7)

| Column | Type | Null | Requirement |
|---|---|---|---|
| `address` | string(500) | yes | FR-027 |

Matches `service_centers.address`, which is already `string(500)`.

### `service_items` (US6)

| Column | Type | Null | Requirement |
|---|---|---|---|
| `name` | string(255) | yes | FR-024 — driver's own label |
| `price` | decimal(10,2) | yes | FR-024 — driver's own price |

Both nullable overrides (R8). Resolution order is override, then the linked
`Item`, so existing rows are untouched.

### `items` and `service_centers` (US8)

| Table | Column | Type | Null | Requirement |
|---|---|---|---|---|
| `items` | `name_ar` | string(255) | yes | FR-029 |
| `service_centers` | `name_ar` | string(255) | yes | FR-029 |
| `service_centers` | `address_ar` | string(500) | yes | FR-029 |

`items.name` is `unique`; `name_ar` is deliberately **not** unique — two
catalogue entries may legitimately share an Arabic name. FR-030's fallback is
resource logic: return the Latin value when the Arabic one is null.

### `users` (US3)

`softDeletes()` on `users`, plus the same on `cars` (already present).
FR-012 requires the account to become inaccessible and sessions to end; a soft
delete plus token revocation achieves that without destroying records that other
tables still reference by foreign key.

---

## Repositories and policies to create

Per Principle I and V, each new entity needs a contract, an Eloquent
implementation, a binding, and a policy:

| Entity | Contract | Implementation | Policy | `scopeToUser()` |
|---|---|---|---|---|
| `Cost` | `CostRepository` | `CostRepositoryEloquent` | `CostPolicy` | **required** (`user_id`) |
| `Issue` | `IssueRepository` | `IssueRepositoryEloquent` | `IssuePolicy` | **required** (`user_id`) |
| `Car` | exists | exists | **`CarPolicy` — missing, must be created** | required (`user_id`) |

Every one declares `protected array $allowedDefaultSorts = ['-id']` unless the
domain has a stronger natural order — `costs` uses `-spent_at` and `issues` uses
`-occurred_at`, both of which are what the app displays.

## Permissions

`RolePermissionsSeeder::$models` gains `'cost'` and `'issue'`, generating the
standard CRUD set. `sync:permissions` must be run after — locally **and on
production**, where it has still never been run at all.

`'car'` is already in `$models`, so US1 needs no permission work (R1).
