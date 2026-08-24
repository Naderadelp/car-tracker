# Quickstart: Service History + Smart Notifications

**Feature**: 007-service-history-notifications | **Date**: 2026-05-25

## Prerequisites

1. `composer require kreait/laravel-firebase`
2. Set `FIREBASE_CREDENTIALS=/path/to/service-account.json` in `.env`
3. `php artisan migrate`
4. `php artisan sync:permissions`
5. Queue worker running: `php artisan queue:listen`
6. Scheduler running (for expiry checks): `php artisan schedule:work` (dev) or cron in prod

---

## Smoke Tests

### 1. Log a completed service

```bash
# As car owner (user with token)
curl -X POST http://localhost/api/cars/1/logs \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "odometer_at_service": 50100,
    "actual_cost": 750.00,
    "performed_at": "2026-05-20"
  }'
# Expected: 201, log resource, FCM push queued
```

```bash
# List car logs
curl http://localhost/api/cars/1/logs \
  -H "Authorization: Bearer {token}"
# Expected: 200, paginated list with the new log
```

---

### 2. Gas station check-in + quick fill-up

```bash
# Step 1: App detects gas station → check-in
curl -X POST http://localhost/api/cars/1/gas-station-check-in \
  -H "Authorization: Bearer {token}"
# Expected: 200, FCM push queued with action=quick_fill_up

# Step 2: Admin sets fuel price first
curl -X POST http://localhost/api/fuel-prices \
  -H "Authorization: Bearer {admin_token}" \
  -H "Content-Type: application/json" \
  -d '{"type": "95", "price_per_unit": 13.75, "effective_from": "2026-05-01"}'
# Expected: 201

# Step 3: User submits quick fill-up (liters auto-calculated: 500/13.75 = 36.36)
curl -X POST http://localhost/api/cars/1/fill-ups/quick \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "amount_paid": 500,
    "fuel_type": "95",
    "station_lat": 30.0594,
    "station_lng": 31.2218
  }'
# Expected: 201, fill-up with liters=36.36, fill_date=today, odometer=car.current_km
```

---

### 3. FCM device token registration

```bash
# Any authenticated request with these headers auto-registers the token
curl http://localhost/api/auth/user \
  -H "Authorization: Bearer {token}" \
  -H "DEVICE-TOKEN: fcm_token_here" \
  -H "DEVICE-TYPE: android"
# Expected: 200 (normal user response), token upserted to device_tokens table
```

---

### 4. Trigger upcoming maintenance notification manually (dev test)

```bash
# Advance the car's odometer via a trip (e.g., car has service milestone at current_km+400)
curl -X POST http://localhost/api/cars/1/trips \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"start_lat": 30.0, "start_lng": 31.0, "end_lat": 30.01, "end_lng": 31.01}'
# Expected: 201, TripObserver fires OdometerAdvanced, listener checks milestones
```

---

### 5. Run scheduled expiry checks manually

```bash
php artisan app:check-document-expiry
# Expected: FCM pushes queued for documents expiring within 30 days

php artisan app:check-warranty-expiry
# Expected: FCM pushes queued for cars with warranty expiring by date or km
```

---

### 6. Verify fuel price management

```bash
# List prices (any authenticated user)
curl http://localhost/api/fuel-prices \
  -H "Authorization: Bearer {token}"
# Expected: 200, array of price records

# Regular user cannot create prices
curl -X POST http://localhost/api/fuel-prices \
  -H "Authorization: Bearer {regular_user_token}" \
  -H "Content-Type: application/json" \
  -d '{"type": "92", "price_per_unit": 11.0, "effective_from": "2026-05-01"}'
# Expected: 403
```
