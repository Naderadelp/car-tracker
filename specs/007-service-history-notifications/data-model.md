# Data Model: Service History + Smart Notifications

**Feature**: 007-service-history-notifications | **Date**: 2026-05-25

---

## New Tables

### `car_logs`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint unsigned | PK, auto-increment |
| car_id | bigint unsigned | FK → cars, nullable, nullOnDelete |
| service_id | bigint unsigned | FK → services, nullable, nullOnDelete |
| odometer_at_service | int | NOT NULL |
| actual_cost | decimal(10,2) | NOT NULL |
| performed_at | date | NOT NULL |
| created_at | timestamp | |
| updated_at | timestamp | |

Indexes: `INDEX(car_id)`, `INDEX(service_id)`

---

### `device_tokens`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint unsigned | PK, auto-increment |
| user_id | bigint unsigned | FK → users, nullable, nullOnDelete |
| token | string | NOT NULL |
| device | enum('android','ios') | NOT NULL |
| created_at | timestamp | |
| updated_at | timestamp | |

Constraint: `UNIQUE(user_id, device)` — one token per user per device type. Overwritten on re-login.

---

### `fuel_prices`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint unsigned | PK, auto-increment |
| type | enum('92','95','electric') | NOT NULL |
| price_per_unit | decimal(10,2) | NOT NULL |
| effective_from | date | NOT NULL |
| created_at | timestamp | |
| updated_at | timestamp | |

Index: `INDEX(type, effective_from)` — supports the "latest price per type" lookup.

No unique constraint on `type` — multiple historical rows per type are preserved.

---

## Migration Delta: `fill_ups`

Additive migration (`alter_fill_ups_add_fuel_fields_table.php`):

| Column | Type | Change |
|--------|------|--------|
| fuel_type | enum('92','95','electric') | ADD COLUMN nullable |
| liters | decimal(8,2) | ALTER to nullable |
| station_lat | decimal(10,8) | ADD COLUMN nullable |
| station_lng | decimal(11,8) | ADD COLUMN nullable |

Existing rows: `fuel_type = null`, `liters` unchanged, `station_lat = null`, `station_lng = null`.

---

## Entity Relationships

```
User ─────────────────────┬─────────────────────┐
  │                       │                     │
  └── Car ──┬──── CarLog ──┘                     │
            │   (car_id, service_id?)            │
            │                                   │
            ├──── FillUp (extended)             DeviceToken
            │   (+ fuel_type, station_lat/lng)   (user_id, device, token)
            │
            └── [existing: FillUp, Trip, ParkingRecord, Document, Service]

Admin ──── FuelPrice
           (type, price_per_unit, effective_from)

Service ──── CarLog  (service_id FK — optional link)
```

---

## Model Summaries

### `CarLog`
- Uses `LogsActivity` trait
- Belongs to `Car`, optionally belongs to `Service`
- `$fillable`: `car_id`, `service_id`, `odometer_at_service`, `actual_cost`, `performed_at`
- `casts`: `odometer_at_service` → integer, `actual_cost` → decimal:2, `performed_at` → date

### `DeviceToken`
- No `LogsActivity` (infrastructure entity — no audit value)
- Belongs to `User`
- `$fillable`: `user_id`, `token`, `device`

### `FuelPrice`
- Uses `LogsActivity` trait
- No user ownership (system-wide)
- `$fillable`: `type`, `price_per_unit`, `effective_from`
- `casts`: `price_per_unit` → decimal:2, `effective_from` → date

### `FillUp` (modified)
- Add to `$fillable`: `fuel_type`, `station_lat`, `station_lng`
- Add to `casts`: `fuel_type` → string, `station_lat` → decimal:8, `station_lng` → decimal:8
- `liters` cast remains `decimal:2` — now nullable in DB
