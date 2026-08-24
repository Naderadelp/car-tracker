# Quickstart: Parking Tracker

Quick reference for exercising the parking-tracker endpoints once the implementation lands. Replace `{TOKEN}` with a Sanctum bearer token and `{CAR_ID}` with a car ID owned by the authenticated user.

## 1. Run migrations + sync permissions

```bash
php artisan migrate
php artisan sync:permissions
```

The migration `2026_05_04_000005_create_parking_records_table.php` adds the `parking_records` table; `sync:permissions` adds `index-parking-record`, `create-parking-record`, `destroy-parking-record`, etc. and grants them all to the `admin` role.

## 2. Record a parking location (descriptive)

```bash
curl -X POST http://localhost:8000/api/cars/{CAR_ID}/parking-records \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Mall basement, Level B2",
    "description": "Near the elevator bank",
    "parked_at": "2026-05-04T17:30:00Z"
  }'
```

Expect `201` with the created `ParkingRecordResource`.

## 3. Record a parking location (GPS)

```bash
curl -X POST http://localhost:8000/api/cars/{CAR_ID}/parking-records \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "latitude": 30.062630,
    "longitude": 31.249670,
    "parked_at": "2026-05-04T17:30:00Z"
  }'
```

## 4. Retrieve the current parking location

```bash
curl http://localhost:8000/api/cars/{CAR_ID}/parking-records/current \
  -H "Authorization: Bearer {TOKEN}"
```

Expect `200` with the most recent record, or `404` if no history exists.

## 5. List the full history

```bash
# Newest first (default)
curl 'http://localhost:8000/api/cars/{CAR_ID}/parking-records' \
  -H "Authorization: Bearer {TOKEN}"

# Filter by location name
curl 'http://localhost:8000/api/cars/{CAR_ID}/parking-records?filter[name]=mall' \
  -H "Authorization: Bearer {TOKEN}"

# Embed the parent car
curl 'http://localhost:8000/api/cars/{CAR_ID}/parking-records?include=car' \
  -H "Authorization: Bearer {TOKEN}"

# Sort oldest first
curl 'http://localhost:8000/api/cars/{CAR_ID}/parking-records?sort=parked_at' \
  -H "Authorization: Bearer {TOKEN}"
```

## 6. Delete a record

```bash
curl -X DELETE http://localhost:8000/api/cars/{CAR_ID}/parking-records/{ID} \
  -H "Authorization: Bearer {TOKEN}"
```

## 7. Validation smoke tests (expect `422`)

```bash
# Empty submission
curl -X POST http://localhost:8000/api/cars/{CAR_ID}/parking-records \
  -H "Authorization: Bearer {TOKEN}" -H "Content-Type: application/json" \
  -d '{ "parked_at": "2026-05-04T17:30:00Z" }'

# Half-coords (latitude without longitude)
curl -X POST http://localhost:8000/api/cars/{CAR_ID}/parking-records \
  -H "Authorization: Bearer {TOKEN}" -H "Content-Type: application/json" \
  -d '{ "latitude": 30.06263, "parked_at": "2026-05-04T17:30:00Z" }'

# Future timestamp
curl -X POST http://localhost:8000/api/cars/{CAR_ID}/parking-records \
  -H "Authorization: Bearer {TOKEN}" -H "Content-Type: application/json" \
  -d '{ "name": "X", "parked_at": "2099-01-01T00:00:00Z" }'
```

## 8. Cross-user denial smoke test

Authenticate as User B (who owns Car B), then attempt to read Car A's parking history:

```bash
curl http://localhost:8000/api/cars/{CAR_A_ID}/parking-records \
  -H "Authorization: Bearer {TOKEN_B}"
```

Expect `403` (policy denial via `viewAny`). Repeat against `…/current` and `DELETE` — both should be denied.
