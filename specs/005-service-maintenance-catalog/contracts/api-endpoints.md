# API Contracts: Service & Maintenance Catalog

**Auth**: All endpoints require `Authorization: Bearer {token}` (Sanctum).
**No URL versioning** — constitution § "API Routing Convention" forbids `/v1/`.

---

## Items (Master Parts Inventory)

### GET `/api/items`

List the master parts inventory.

**Authorization**: any authenticated user (`ItemPolicy::viewAny`).

**Query parameters** (Spatie QueryBuilder):
| Param                | Effect                                       |
|----------------------|----------------------------------------------|
| `?include=services`  | Embed each item's parent services            |
| `?filter[name]=oil`  | Partial match on `name`                      |
| `?sort=name`         | Sort ascending; prefix `-` for descending    |
| `?sort=price`        | Sort by price                                |
| `?page=1&per_page=…` | Standard pagination (default 15)             |

**Response 200**:
```json
{
  "data": [
    { "id": 1, "name": "Engine Oil", "price": "150.00", "created_at": "...", "updated_at": "..." }
  ],
  "links":  { "...": "..." },
  "meta":   { "current_page": 1, "per_page": 15, "total": 1 }
}
```

---

### POST `/api/items`

Create a new part.

**Authorization**: `create-item` permission (`StoreItemRequest::authorize()`).

**Request body**:
```json
{ "name": "Engine Oil", "price": 150.00 }
```

**Validation**:
| Field   | Rules                                          |
|---------|------------------------------------------------|
| `name`  | required, string, max:255, unique:items,name   |
| `price` | required, numeric, min:0                       |

**Response 201**:
```json
{ "data": { "id": 1, "name": "Engine Oil", "price": "150.00", ... }, "message": "Item created successfully." }
```

**Response 422** (duplicate name):
```json
{ "errors": { "name": ["The name has already been taken."] } }
```

---

### GET `/api/items/{item}`

Show a single part.

**Authorization**: any authenticated user (`ItemPolicy::view`).

**Response 200**:
```json
{ "data": { "id": 1, "name": "Engine Oil", "price": "150.00", ... } }
```

---

### PUT/PATCH `/api/items/{item}`

Update a part.

**Authorization**: `edit-item` permission (`UpdateItemRequest::authorize()`).

**Request body** *(any subset of fields)*:
```json
{ "name": "Engine Oil 5W-30", "price": 175.00 }
```

**Validation**:
| Field   | Rules                                                              |
|---------|--------------------------------------------------------------------|
| `name`  | sometimes, string, max:255, unique:items,name (ignore current id)  |
| `price` | sometimes, numeric, min:0                                          |

**Response 200**:
```json
{ "data": { "id": 1, "name": "Engine Oil 5W-30", "price": "175.00", ... } }
```

---

### DELETE `/api/items/{item}`

Delete a part. Cascade-removes any `service_items` pivot rows referencing this item.

**Authorization**: `destroy-item` permission.

**Response 200**:
```json
{ "data": [], "message": "Item deleted successfully." }
```

---

## Service Centers

> **Revised scope** — beyond the distance-ordered `index`, full CRUD is now exposed under the same brand-nested prefix.

### GET `/api/brands/{brand}/service-centers?lat={lat}&lng={lng}`

List service centers for a brand, sorted by distance from the supplied GPS position.

**Authorization**: any authenticated user (`ServiceCenterPolicy::viewAny`).

**Query parameters** (validated by `NearbyServiceCentersRequest`):
| Param  | Rules                                              |
|--------|----------------------------------------------------|
| `lat`  | required, numeric, between:-90,90                  |
| `lng`  | required, numeric, between:-180,180                |

> GPS is required — see Spec Assumptions ("nearby" headline value depends on distance ordering).

**Response 200**:
```json
{
  "data": [
    {
      "id": 12,
      "brand_id": 3,
      "name": "MG New Cairo",
      "address": "Ring Road, Fifth Settlement",
      "open_at":   "09:00",
      "close_at":  "21:00",
      "mobile":    "+201111234567",
      "lat":       "30.06263000",
      "lng":       "31.24967000",
      "is_open":     true,
      "distance_km": 4.21,
      "created_at": "...",
      "updated_at": "..."
    },
    { "...": "..." }
  ]
}
```

**Response 422** (missing/invalid GPS):
```json
{ "errors": { "lat": ["The lat field is required."] } }
```

**Notes**:
- `is_open` is `true` when `open_at <= now <= close_at` (server local time).
- `distance_km` rounded to 2 decimals via `ServiceCenterResource`.
- Centers ordered by `distance_km` ascending.

---

### GET `/api/brands/{brand}/service-centers/{serviceCenter}`

Show a single service center for a brand.

**Authorization**: `ServiceCenterPolicy::view` *(any authenticated user)*. Returns 404 if `serviceCenter.brand_id !== brand.id`.

**Query parameters**: `?include=brand` to embed the parent.

**Response 200**:
```json
{
  "data": {
    "id": 12,
    "brand_id": 3,
    "name": "MG New Cairo",
    "address": "...",
    "open_at":  "09:00",
    "close_at": "21:00",
    "mobile":   "+201111234567",
    "lat":      "30.06263000",
    "lng":      "31.24967000",
    "is_open":  true,
    "brand":    { "id": 3, "name": "MG" },
    "created_at": "...",
    "updated_at": "..."
  }
}
```

---

### POST `/api/brands/{brand}/service-centers`

Create a service center under the given brand.

**Authorization**: `create-service-center` permission (admin / super-user) — handled by `StoreServiceCenterRequest::authorize()`.

**Request body**:
```json
{
  "name":     "Toyota Heliopolis",
  "address":  "Salah Salem St, Heliopolis",
  "open_at":  "08:30:00",
  "close_at": "20:00:00",
  "mobile":   "+201234567890",
  "lat":      30.10000,
  "lng":      31.34000
}
```

**Validation**:
| Field      | Rules                                                          |
|------------|----------------------------------------------------------------|
| `name`     | required, string, max:255                                      |
| `address`  | required, string, max:500                                      |
| `open_at`  | required, time (`H:i` or `H:i:s`)                              |
| `close_at` | required, time, after:`open_at`                                |
| `mobile`   | required, string, max:50                                       |
| `lat`      | required, numeric, between:-90,90                              |
| `lng`      | required, numeric, between:-180,180                            |

**Response 201**: full `ServiceCenterResource`.

---

### PUT/PATCH `/api/brands/{brand}/service-centers/{serviceCenter}`

Update fields on an existing service center.

**Authorization**: `edit-service-center` permission (handled by `UpdateServiceCenterRequest::authorize()`). Returns 404 if `serviceCenter.brand_id !== brand.id`.

**Validation**: same shape as Store, every field `sometimes` instead of `required`.

**Response 200**: full `ServiceCenterResource`.

---

### DELETE `/api/brands/{brand}/service-centers/{serviceCenter}`

Delete a service center.

**Authorization**: `destroy-service-center` permission. Returns 404 if `serviceCenter.brand_id !== brand.id`.

**Response 200**:
```json
{ "data": [], "message": "Service center deleted." }
```

---

## Maintenance Services (Catalogue + User-Owned) — *revised scope*

The `services` table holds both brand-defined catalogue rows (`user_id IS NULL`) and per-user/per-car custom rows (`user_id` and `car_id` set). Reads merge both sources via `ServiceRepository::scopeToUser()`. Writes split into two flows.

### GET `/api/services`

List services. The repository's `scopeToUser` automatically filters to `user_id IS NULL OR user_id = auth()->id()` for non-admins.

**Authorization**: `ServicePolicy::viewAny` *(any authenticated user; visibility is enforced at the repo)*.

**Query parameters** (Spatie):
- `?include=carModel`, `?include=carModel.brand`, `?include=car`, `?include=user`, `?include=items`
- `?filter[car_model_id]=`, `?filter[car_id]=`, `?filter[user_id]=`
- `?sort=km`, `?sort=price`, `?sort=created_at`

**Response 200**: paginated `ServiceResource` collection.

---

### GET `/api/services/{service}`

Show a single service.

**Authorization**: `ServicePolicy::view` — catalogue (`user_id IS NULL`) is open; user-owned is owner-only OR `show-service` permission.

**Response 200**: full `ServiceResource`.

---

### POST `/api/services` *(catalogue create — admin path)*

**Authorization**: `create-service` permission (`StoreServiceRequest` catalogue branch).

**Request body**:
```json
{ "car_model_id": 4, "km": 40000, "price": 1250 }
```

**Validation**:
| Field          | Rules                                  |
|----------------|----------------------------------------|
| `car_model_id` | **required**, exists:car_models,id     |
| `km`           | required, integer, min:0               |
| `price`        | required, numeric, min:0               |

**Response 201**: `ServiceResource` with `user_id = null`, `car_id = null`.

---

### POST `/api/cars/{car}/services` *(user-service create — owner path)*

Create a personal upcoming-maintenance milestone for a car the requesting user owns.

**Authorization**: `StoreServiceRequest` user branch — `auth()->id() === car.user_id` AND `can('create', [Service::class, $car])`.

**Request body**:
```json
{ "km": 45000, "price": 600 }
```

**Validation**:
| Field          | Rules                                              |
|----------------|----------------------------------------------------|
| `km`           | required, integer, min:0                           |
| `price`        | required, numeric, min:0                           |
| `car_model_id` | nullable, exists:car_models,id *(usually omitted)* |

**Server-side autofill**: `car_id = $car->id`, `user_id = auth()->id()`.

**Response 201**: `ServiceResource` with `user_id`, `car_id` populated, `is_catalogue = false`.

**Response 403**: requesting user does not own the car.

---

### PUT/PATCH `/api/services/{service}`

Update a service.

**Authorization**: `UpdateServiceRequest::authorize()` → `ServicePolicy::update`:
- If `service.user_id !== null`: owner OR `edit-service` permission.
- If catalogue: `edit-service` permission.

**Validation**: same shape as Store, all rules `sometimes`.

**Response 200**: full `ServiceResource`.

---

### DELETE `/api/services/{service}`

Delete a service.

**Authorization**: `ServicePolicy::delete` — same shape as `update`.

**Response 200**:
```json
{ "data": [], "message": "Service deleted." }
```

---

## Upcoming Maintenance (Predictive Schedule)

### GET `/api/cars/{car}/upcoming-services`

Return the chronological list of upcoming maintenance milestones for a car.

**Authorization**: car owner OR `index-service` permission (`ServicePolicy::viewAny(User, Car)`).

**Logic** *(revised — merges catalogue + this user's own services)*:
1. `WHERE (car_model_id = $car->car_model_id AND user_id IS NULL AND car_id IS NULL)`
2.   `OR (car_id = $car->id AND user_id = $car->user_id)`
3. `AND km > $car->current_km`
4. `withCount('items')`
5. `ORDER BY km ASC`

**Response 200**:
```json
{
  "data": [
    {
      "id": 7,
      "car_model_id": 4,
      "km":           40000,
      "remaining_km": 5000,
      "price":        "1250.00",
      "items_count":  3,
      "created_at": "...",
      "updated_at": "..."
    },
    {
      "id": 8,
      "car_model_id": 4,
      "km":           60000,
      "remaining_km": 25000,
      "price":        "2500.00",
      "items_count":  5,
      "created_at": "...",
      "updated_at": "..."
    }
  ]
}
```

**Response 200** *(no upcoming milestones — empty list, not 404)*:
```json
{ "data": [] }
```

**Response 403** *(car belongs to another user)*:
```json
{ "message": "This action is unauthorized." }
```

**Notes**:
- `remaining_km = service.km - car.current_km` (always positive given the WHERE filter).
- `items_count` is the count of pivot rows (`service_items`) where `service_id = service.id`. Per-car pivot rows are NOT filtered out — `items_count` is catalogue-wide.
- `?include=carModel`, `?include=carModel.brand`, and `?include=items` are accepted via Spatie if the consumer wants the full breakdown in one round trip.

---

## Error Response Shapes

All errors use `BaseController::error()` envelope where applicable. Validation errors follow Laravel's default `errors` map. Authorization failures bubble up as `403`. No bare `response()->json(...)` calls in controller actions (constitution § III).
