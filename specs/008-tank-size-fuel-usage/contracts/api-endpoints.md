# Phase 1 Contracts: API Endpoint Deltas

All endpoints are under `/api`, no versioning. Auth via Sanctum bearer token
except `POST register`.

## 1. `POST /api/auth/register` (modified)

Adds one optional field; everything else unchanged.

**Request (new field only)**:
```jsonc
{
  "tank_size": 60.0   // optional, numeric, 0.1–999 (liters)
}
```

**Response**: `201` — `data.car` now includes `"tank_size"` (number or `null`).

## 2. `PUT /api/auth/user` (new — profile edit)

Update the authenticated user's profile: their `name` and their car's `tank_size`.
The tank size targets the user's car from registration. Requires authentication
(Sanctum); acts only on the caller's own records.

**Request** (both fields optional; send what you want to change):
```jsonc
{
  "name": "New Name",   // optional: sometimes|string|max:255
  "tank_size": 55.0     // optional: nullable|numeric|0.1–999 (null clears it)
}
```

**Responses**:
- `200` — `{ "data": { "user": {...UserResource...}, "car": {...CarResource with updated tank_size...} | null }, "message": "Profile updated successfully." }`
- `401` — unauthenticated.
- `422` — validation error (e.g. invalid `tank_size` or empty `name`).

## 3. `POST /api/cars/{car}/fill-ups` (modified — manual)

**Request (new field)**:
```jsonc
{
  "liters": 40.0,            // unchanged (required)
  "cost_egp": 1200.0,        // unchanged (required)
  "fill_date": "2026-06-02", // unchanged (required)
  "tank_percentage": 90.0    // NEW: optional, numeric, 0–100
}
```

**Response**: `201` — `data` (FillUpResource) includes `"tank_percentage"`.

## 4. `POST /api/cars/{car}/fill-ups/quick` (modified)

**Request (new field)**:
```jsonc
{
  "amount_paid": 1000.0,     // unchanged (required)
  "fuel_type": "95",         // unchanged (required: 92|95|electric)
  "liters": null,            // unchanged (optional)
  "station_lat": null,       // unchanged (optional)
  "station_lng": null,       // unchanged (optional)
  "tank_percentage": 80.0    // NEW: optional, numeric, 0–100
}
```

**Response**: `201` — `data.data` (FillUpResource) includes `"tank_percentage"`.

## 5. `GET /api/cars/{car}/fill-ups` (modified statistics)

List payload unchanged except each item now carries `tank_percentage`. The
appended `statistics` object changes:

```jsonc
{
  "data": [ /* FillUpResource[] each with tank_percentage */ ],
  "links": { /* pagination */ },
  "meta":  { /* pagination */ },
  "statistics": {
    "total_fill_ups": 5,
    "total_cost_egp": "6000.00",
    "average_consumption": "12.34",  // km/L (refined)
    "total_distance_km": 1500         // NEW
  }
}
```

## Resource field additions

- `CarResource`: `"tank_size"` (number|null).
- `FillUpResource`: `"tank_percentage"` (number|null).
