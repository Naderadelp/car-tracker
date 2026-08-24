# Quickstart: Tank Size & Percentage-Based Fuel Usage

## Apply the schema

```bash
php artisan migrate
```

Runs the two additive migrations (`cars.tank_size`, `fill_ups.tank_percentage`).
No data backfill is required; existing rows get `NULL`.

No permission/seeder change is required — profile edit only touches the caller's
own records.

## Exercise the endpoints

Note: auth routes are prefixed `/api/auth`. Assume `$TOKEN` holds a Sanctum bearer
token and `$CAR` a car id owned by that user.

```bash
# 1. Register with a tank size (optional field)
curl -sX POST localhost:8000/api/auth/register \
  -H 'Content-Type: application/json' \
  -d '{"name":"A","email":"a@b.co","password":"password","brand_id":1,
       "car_model_name":"Corolla","model_year":2020,"current_km":1000,
       "has_warranty":false,"tank_size":60}'

# 2. Correct the tank size (and/or name) later via profile edit
curl -sX PUT localhost:8000/api/auth/user \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"name":"New Name","tank_size":55}'

# 3. Manual fill-up with after-fill percentage
curl -sX POST localhost:8000/api/cars/$CAR/fill-ups \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"liters":40,"cost_egp":1200,"fill_date":"2026-06-02","tank_percentage":90}'

# 4. Quick fill-up with after-fill percentage
curl -sX POST localhost:8000/api/cars/$CAR/fill-ups/quick \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"amount_paid":1000,"fuel_type":"95","tank_percentage":80}'

# 5. View refined statistics (look at the trailing "statistics" object)
curl -s localhost:8000/api/cars/$CAR/fill-ups -H "Authorization: Bearer $TOKEN"
```

## Manual verification checklist

- Register with and without `tank_size`; both succeed; `car.tank_size` reflects input.
- `PUT auth/user` updates the user's `name` and their car's `tank_size`; unauthenticated → `401`.
- Fill-ups save with and without `tank_percentage`; out-of-range (`-1`, `101`) → `422`.
- With ≥2 fills + tank data, `average_consumption` matches
  `Σ distance / Σ (liters_next + (f_prev − f_next)·tank_size)` within 0.01.
- With no tank data, `average_consumption` is non-zero, finite, and excludes the
  first fill's liters; never errors.
- One fill-up (or non-positive consumption) → `average_consumption = "0.00"`.
```
