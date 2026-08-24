# API Contracts: Service History + Smart Notifications

**Feature**: 007-service-history-notifications | **Date**: 2026-05-25

All endpoints require `Authorization: Bearer {sanctum_token}` unless noted.

---

## Car Logs

### `GET /api/cars/{car}/logs`
List all service logs for a car (owner or admin).

**Query params**: `?sort=-performed_at`, `?filter[service_id]=1`

**Response 200**:
```json
{
  "data": [
    {
      "id": 1,
      "car_id": 3,
      "service_id": 2,
      "odometer_at_service": 50100,
      "actual_cost": "750.00",
      "performed_at": "2026-05-20",
      "created_at": "2026-05-20T10:00:00.000000Z"
    }
  ],
  "meta": { "current_page": 1, "last_page": 1, "total": 1 },
  "links": { ... }
}
```

---

### `POST /api/cars/{car}/logs`
Create a service log for a car.

**Request body**:
```json
{
  "service_id": 2,
  "odometer_at_service": 50100,
  "actual_cost": 750.00,
  "performed_at": "2026-05-20"
}
```
`service_id` is optional. `odometer_at_service`, `actual_cost`, `performed_at` are required.

**Response 201**:
```json
{
  "message": "Service log created.",
  "data": { ...log resource... }
}
```

**Side effect**: FCM push notification sent to car owner.

---

### `GET /api/cars/{car}/logs/{log}`
Show a single service log.

**Response 200**: Single log resource.

---

### `PUT|PATCH /api/cars/{car}/logs/{log}`
Update a service log (owner or admin).

**Request body**: Any subset of `service_id`, `odometer_at_service`, `actual_cost`, `performed_at`.

**Response 200**: Updated log resource.

---

### `DELETE /api/cars/{car}/logs/{log}`
Delete a service log (owner or admin).

**Response 200**: `{ "message": "Service log deleted." }`

---

## Gas Station Check-In

### `POST /api/cars/{car}/gas-station-check-in`
Called by the mobile app after detecting ≥2 minutes at a gas station.

**No request body.**

**Response 200**:
```json
{ "message": "Notification sent." }
```

**Side effect**: FCM push sent to car owner with data `{ "action": "quick_fill_up", "car_id": "3" }`.

---

## Quick Fill-Up

### `POST /api/cars/{car}/fill-ups/quick`
Record a fill-up from the gas station prompt (simplified form).

**Request body**:
```json
{
  "amount_paid": 500.00,
  "fuel_type": "95",
  "liters": null,
  "station_lat": 30.0594,
  "station_lng": 31.2218
}
```
`amount_paid` and `fuel_type` are required. `liters`, `station_lat`, `station_lng` are optional.

`fuel_type`: `"92"` | `"95"` | `"electric"`

**Auto-captured**: `fill_date = today`, `odometer = car.current_km`. If `liters` is null, the system calculates it from the active fuel price for the submitted type (null if no price configured).

**Response 201**:
```json
{
  "message": "Fill-up recorded successfully.",
  "data": { ...fill-up resource... }
}
```

---

## Fuel Prices

### `GET /api/fuel-prices`
List all fuel price records (all authenticated users).

**Response 200**:
```json
{
  "data": [
    { "id": 1, "type": "92", "price_per_unit": "12.25", "effective_from": "2026-05-01" },
    { "id": 2, "type": "95", "price_per_unit": "13.75", "effective_from": "2026-05-01" },
    { "id": 3, "type": "electric", "price_per_unit": "2.50", "effective_from": "2026-05-01" }
  ]
}
```

---

### `POST /api/fuel-prices`
Create a new fuel price entry (admin only).

**Request body**:
```json
{
  "type": "95",
  "price_per_unit": 14.00,
  "effective_from": "2026-06-01"
}
```

**Response 201**:
```json
{ "message": "Fuel price created.", "data": { ...price resource... } }
```

---

### `PUT|PATCH /api/fuel-prices/{fuelPrice}`
Update a fuel price entry (admin only).

**Request body**: Any subset of `type`, `price_per_unit`, `effective_from`.

**Response 200**: Updated price resource.

---

## FCM Notification Payloads

### Service Completed
```json
{
  "notification": { "title": "Service Logged", "body": "Service logged at 50,100 km — cost 750.00 EGP" },
  "data": { "type": "service_completed", "car_id": "3", "log_id": "1" }
}
```

### Gas Station Reminder
```json
{
  "notification": { "title": "Fill Up?", "body": "You're at a gas station — tap to log your fill-up" },
  "data": { "type": "quick_fill_up", "car_id": "3" }
}
```

### Upcoming Maintenance
```json
{
  "notification": { "title": "Upcoming Service", "body": "Upcoming service in 400 km" },
  "data": { "type": "upcoming_service", "car_id": "3", "km_remaining": "400" }
}
```

### Document Expiry
```json
{
  "notification": { "title": "Document Expiring", "body": "Your Insurance expires in 15 days" },
  "data": { "type": "document_expiry", "document_id": "7", "days_remaining": "15" }
}
```

### Warranty Expiry
```json
{
  "notification": { "title": "Warranty Expiring", "body": "Your car warranty expires soon" },
  "data": { "type": "warranty_expiry", "car_id": "3" }
}
```
