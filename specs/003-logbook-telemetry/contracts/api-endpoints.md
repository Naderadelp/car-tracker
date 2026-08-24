# API Contracts: Logbook & Telemetry

**Auth**: All endpoints require `Authorization: Bearer {token}` (Sanctum).
**Base path**: `/api/cars/{car}/`

---

## Fill-Ups

### GET `/api/cars/{car}/fill-ups`
List all fill-ups for a car with consumption statistics.

**Authorization**: Car owner or `index-fill-up` permission.

**Response 200**:
```json
{
  "data": {
    "summary": {
      "total_fill_ups": 5,
      "total_cost_egp": "1250.00",
      "average_consumption": "12.50"
    },
    "fill_ups": [
      {
        "id": 3,
        "car_id": 1,
        "liters": "40.00",
        "odometer": 15200,
        "cost_egp": "280.00",
        "fill_date": "2026-05-01",
        "created_at": "2026-05-01T10:00:00.000000Z",
        "updated_at": "2026-05-01T10:00:00.000000Z"
      }
    ]
  }
}
```

**Notes**:
- `average_consumption` = (max_odometer − min_odometer) / total_liters, rounded to 2 decimal places. Returns `"0.00"` if insufficient data.
- List ordered by `fill_date` descending.

---

### POST `/api/cars/{car}/fill-ups`
Record a new refueling event.

**Authorization**: Car owner or `create-fill-up` permission (handled in `StoreFillUpRequest`).

**Request body**:
```json
{
  "liters": 40.5,
  "cost_egp": 283.50,
  "fill_date": "2026-05-01"
}
```

**Validation**:
| Field       | Rules                                      |
|-------------|--------------------------------------------|
| `liters`    | required, numeric, min:0.1                 |
| `cost_egp`  | required, numeric, min:0                   |
| `fill_date` | required, date, before_or_equal:today      |

**Side effect**: `odometer` is auto-populated from `car.current_km` at time of creation. Not accepted from client.

**Response 201**:
```json
{
  "data": {
    "id": 4,
    "car_id": 1,
    "liters": "40.50",
    "odometer": 15200,
    "cost_egp": "283.50",
    "fill_date": "2026-05-01",
    "created_at": "2026-05-01T10:05:00.000000Z",
    "updated_at": "2026-05-01T10:05:00.000000Z"
  },
  "message": "Fill-up recorded successfully."
}
```

---

### DELETE `/api/cars/{car}/fill-ups/{fillUp}`
Delete a fill-up record.

**Authorization**: Car owner or `destroy-fill-up` permission.

**Validation**: `fillUp.car_id` must match `car.id` → 404 if not.

**Response 200**:
```json
{
  "data": [],
  "message": "Fill-up deleted successfully."
}
```

---

## Trips

### GET `/api/cars/{car}/trips`
List all trips for a car.

**Authorization**: Car owner or `index-trip` permission.

**Response 200**:
```json
{
  "data": [
    {
      "id": 2,
      "car_id": 1,
      "start_time": "2026-05-01T08:00:00.000000Z",
      "end_time": "2026-05-01T08:45:00.000000Z",
      "start_lat": "30.06263000",
      "start_lng": "31.24967000",
      "end_lat": "30.04442000",
      "end_lng": "31.23572000",
      "total_distance_km": "12.34",
      "created_at": "2026-05-01T08:45:05.000000Z",
      "updated_at": "2026-05-01T08:45:05.000000Z"
    }
  ]
}
```

**Notes**: Ordered by `start_time` descending.

---

### POST `/api/cars/{car}/trips`
Submit a GPS trip from a coordinate sequence.

**Authorization**: Car owner or `create-trip` permission (handled in `StoreTripRequest`).

**Request body**:
```json
{
  "coordinates": [
    { "lat": 30.062630, "lng": 31.249670, "timestamp": "2026-05-01T08:00:00Z" },
    { "lat": 30.055000, "lng": 31.245000, "timestamp": "2026-05-01T08:20:00Z" },
    { "lat": 30.044420, "lng": 31.235720, "timestamp": "2026-05-01T08:45:00Z" }
  ]
}
```

**Validation**:
| Field                      | Rules                                         |
|----------------------------|-----------------------------------------------|
| `coordinates`              | required, array, min:2                        |
| `coordinates.*.lat`        | required, numeric, between:-90,90             |
| `coordinates.*.lng`        | required, numeric, between:-180,180           |
| `coordinates.*.timestamp`  | required, date                                |

**Server-side processing**:
1. Calculate cumulative Haversine distance across consecutive waypoint pairs (Earth radius = 6371 km).
2. Extract `start_time`/`start_lat`/`start_lng` from first coordinate.
3. Extract `end_time`/`end_lat`/`end_lng` from last coordinate.
4. Create trip record.
5. Observer increments `car.current_km` by `round(total_distance_km)`.

**Response 201**:
```json
{
  "data": {
    "id": 3,
    "car_id": 1,
    "start_time": "2026-05-01T08:00:00.000000Z",
    "end_time": "2026-05-01T08:45:00.000000Z",
    "start_lat": "30.06263000",
    "start_lng": "31.24967000",
    "end_lat": "30.04442000",
    "end_lng": "31.23572000",
    "total_distance_km": "12.34",
    "created_at": "2026-05-01T08:45:05.000000Z",
    "updated_at": "2026-05-01T08:45:05.000000Z"
  },
  "message": "Trip recorded successfully."
}
```
