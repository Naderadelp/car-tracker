# API Contracts: Parking Tracker

**Auth**: All endpoints require `Authorization: Bearer {token}` (Sanctum).
**Base path**: `/api/cars/{car}/parking-records`
**No URL versioning** — constitution § "API Routing Convention" forbids `/v1/`.

---

## GET `/api/cars/{car}/parking-records`

List the full parking history for a car, ordered newest-first.

**Authorization**: Car owner OR `index-parking-record` permission.

**Query parameters** (Spatie QueryBuilder):

| Param                  | Effect                                                          |
|------------------------|-----------------------------------------------------------------|
| `?include=car`         | Embed the parent `CarResource` on each record                   |
| `?filter[name]=mall`   | Partial (LIKE `%mall%`) match on `name`                         |
| `?filter[car_id]=…`    | Exact match (admin / cross-car); route already constrains this  |
| `?sort=parked_at`      | Sort ascending (oldest first); prefix `-` for descending        |
| `?sort=created_at`     | Sort by record creation time                                    |
| `?page=…&per_page=…`   | Standard Laravel pagination (default 15)                        |

> Default sort is `-parked_at` (newest first). The client only needs `?sort=` to override.

**Response 200**:
```json
{
  "data": [
    {
      "id": 12,
      "car_id": 1,
      "name": "Mall basement, Level B2",
      "description": "Near the elevator bank",
      "latitude": "30.06263000",
      "longitude": "31.24967000",
      "parked_at": "2026-05-04T17:30:00.000000Z",
      "created_at": "2026-05-04T17:30:05.000000Z",
      "updated_at": "2026-05-04T17:30:05.000000Z"
    }
  ],
  "links":  { "...": "..." },
  "meta":   { "current_page": 1, "per_page": 15, "total": 1 }
}
```

---

## GET `/api/cars/{car}/parking-records/current`

Return the most recently recorded parking event for the car.

**Authorization**: Car owner OR `index-parking-record` permission *(reuses `viewAny` policy)*.

**Response 200**:
```json
{
  "data": {
    "id": 12,
    "car_id": 1,
    "name": "Mall basement, Level B2",
    "description": "Near the elevator bank",
    "latitude": "30.06263000",
    "longitude": "31.24967000",
    "parked_at": "2026-05-04T17:30:00.000000Z",
    "created_at": "2026-05-04T17:30:05.000000Z",
    "updated_at": "2026-05-04T17:30:05.000000Z"
  }
}
```

**Response 404** *(car has no parking history)*:
```json
{ "message": "No parking history found." }
```

> The `current` segment is registered BEFORE the `{parkingRecord}` parameterised route so Laravel matches the literal first.

---

## POST `/api/cars/{car}/parking-records`

Record a new parking event.

**Authorization**: Car owner OR `create-parking-record` permission *(handled in `StoreParkingRecordRequest::authorize()`)*.

**Request body** *(any of the three shapes is valid)*:

GPS only:
```json
{ "latitude": 30.062630, "longitude": 31.249670, "parked_at": "2026-05-04T17:30:00Z" }
```

Descriptive only:
```json
{ "name": "Mall basement, Level B2", "description": "Near the elevator bank", "parked_at": "2026-05-04T17:30:00Z" }
```

Hybrid:
```json
{
  "name": "Mall basement, Level B2",
  "latitude": 30.062630,
  "longitude": 31.249670,
  "parked_at": "2026-05-04T17:30:00Z"
}
```

**Validation**:

| Field         | Rules                                                                                  |
|---------------|----------------------------------------------------------------------------------------|
| `name`        | nullable, string, max:255, **required_without_all:latitude,longitude**                 |
| `description` | nullable, string, max:1000                                                             |
| `latitude`    | nullable, numeric, between:-90,90, **required_with:longitude**                         |
| `longitude`   | nullable, numeric, between:-180,180, **required_with:latitude**                        |
| `parked_at`   | required, date, before_or_equal:now                                                    |

**Response 201**:
```json
{
  "data": {
    "id": 13,
    "car_id": 1,
    "name": "Mall basement, Level B2",
    "description": null,
    "latitude": "30.06263000",
    "longitude": "31.24967000",
    "parked_at": "2026-05-04T17:30:00.000000Z",
    "created_at": "2026-05-04T17:30:05.000000Z",
    "updated_at": "2026-05-04T17:30:05.000000Z"
  },
  "message": "Parking location recorded successfully."
}
```

**Response 422** examples:
- Empty submission (`{}` or `{"parked_at": "..."}` only):
  ```json
  { "message": "The name field is required when latitude / longitude are not present.", "errors": { "name": ["..."] } }
  ```
- Partial coordinates (`{"latitude": 30, "parked_at": "..."}` with no longitude):
  ```json
  { "errors": { "longitude": ["The longitude field is required when latitude is present."] } }
  ```
- Future timestamp:
  ```json
  { "errors": { "parked_at": ["The parked at field must be a date before or equal to now."] } }
  ```

---

## DELETE `/api/cars/{car}/parking-records/{parkingRecord}`

Delete a parking record.

**Authorization**: Car owner OR `destroy-parking-record` permission.

**Validation**: `parkingRecord.car_id` must match the route's `{car}` — otherwise 404 (mirrors `FillUpController::destroy`).

**Response 200**:
```json
{ "data": [], "message": "Parking record deleted successfully." }
```

**Response 404** *(parking record belongs to a different car)*:
```json
{ "message": "Not Found" }
```

---

## Error Response Shapes

All errors use the `BaseController::error()` envelope where applicable, or Laravel's default `abort(...)` JSON for `404` / route-model-binding failures. No bare `response()->json(...)` is used in controller actions (constitution § III).
