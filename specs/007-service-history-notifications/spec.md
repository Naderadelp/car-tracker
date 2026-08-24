# Feature Specification: Service History + Smart Notifications

**Feature Branch**: `007-service-history-notifications`
**Created**: 2026-05-25
**Status**: Draft

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Log a Completed Service (Priority: P1)

A car owner performs a service (e.g., oil change) and logs it in the app. They enter the actual cost paid and the odometer reading at the time of service. The system records the event and immediately sends a push notification confirming the log.

**Why this priority**: Service logging is the core new capability of this feature. Without it, the car's maintenance history is empty and the gap between "upcoming services" and "done" is unbridgeable.

**Independent Test**: Submit a valid car log entry (odometer + cost), verify it appears in the car's log list, and verify a push notification is received on the user's device.

**Acceptance Scenarios**:

1. **Given** I own a car with an upcoming oil change at 50,000 km, **When** I log a service with `odometer_at_service = 50100` and `actual_cost = 750.00`, **Then** the record is saved and I immediately receive a push notification: "Service logged at 50,100 km — cost 750.00 EGP".
2. **Given** I submit a car log without linking a catalog service, **Then** the record is saved with `service_id = null` and the notification is still sent.
3. **Given** I am not the owner of the car, **When** I attempt to create a log for that car, **Then** the request is rejected with a 403 error.

---

### User Story 2 — Quick Fill-Up from Gas Station Prompt (Priority: P2)

A car owner arrives at a gas station. Their mobile app detects they've been at the station for more than 2 minutes and triggers a backend check-in. The owner receives a push notification. They tap it and land on a simplified fill-up form requiring only amount paid and fuel type. The system auto-captures the date and current odometer. Liters are auto-calculated from the fuel price configured by the admin.

**Why this priority**: This is the highest-impact UX improvement — reducing fill-up logging from a multi-field form to two taps. It directly increases fill-up data completeness, which powers fuel consumption analytics.

**Independent Test**: Call the gas-station check-in endpoint and verify a push notification is sent. Then call the quick fill-up endpoint with `amount_paid = 500`, `fuel_type = 92` (no liters), and verify a fill-up record is saved with `liters` auto-calculated from the current fuel price for type 92.

**Acceptance Scenarios**:

1. **Given** the app detects me at a gas station for 2+ minutes, **When** the app calls the check-in endpoint, **Then** I receive a push notification: "You're at a gas station — tap to log fill-up" with a deep-link to the quick fill-up form.
2. **Given** I submit a quick fill-up with `amount_paid = 500`, `fuel_type = 95` and no `liters`, **And** the current price for 95-octane is 13.75 EGP/liter, **Then** the system saves `liters = 36.36` (rounded to 2 decimal places) alongside the fill-up record.
3. **Given** no fuel price exists for the selected type, **Then** the fill-up is saved with `liters = null` rather than failing.
4. **Given** I submit a quick fill-up from a gas station, **Then** `fill_date` is automatically set to today and `odometer` is automatically set to the car's current odometer value — I do not need to enter these manually.

---

### User Story 3 — Upcoming Maintenance Push Notification (Priority: P3)

When a car's odometer advances (via a fill-up or trip), the system checks whether the car is within 500 km of any scheduled service milestone. If so, it sends a push notification alerting the owner.

**Why this priority**: Proactive maintenance reminders are the highest-value notification type. They prevent missed services without requiring the user to remember to check.

**Independent Test**: Set a car's current odometer to 49,600 km with a service milestone at 50,000 km. Record a fill-up that advances the odometer. Verify a push notification is sent: "Upcoming service in 400 km".

**Acceptance Scenarios**:

1. **Given** a car has a service milestone at 50,000 km and its current odometer is at 49,700 km, **When** a fill-up is logged, **Then** a push notification is sent: "Upcoming service in 300 km".
2. **Given** the car is more than 500 km away from the nearest milestone, **When** a fill-up is logged, **Then** no notification is sent.
3. **Given** the car has already passed a milestone, **Then** that milestone does not trigger a notification.

---

### User Story 4 — Document & Warranty Expiry Reminders (Priority: P4)

The system sends daily push notifications for documents that expire within 30 days and for car warranties that are close to expiry (within 30 days by date, or within 500 km of the warranty odometer limit).

**Why this priority**: Expiry reminders are safety-net notifications. Users rely on them to avoid driving with expired documents (a legal risk) or an expired warranty (a financial risk).

**Independent Test**: Set a document's `expiry_date` to 15 days from now, run the daily scheduler manually, and verify a push notification is sent: "Your Insurance expires in 15 days".

**Acceptance Scenarios**:

1. **Given** a car has an insurance document expiring in 20 days, **When** the daily scheduler runs, **Then** the car owner receives a push: "Your Insurance expires in 20 days".
2. **Given** a car's `warranty_expiry_date` is 10 days away, **When** the daily scheduler runs, **Then** the owner receives a push: "Your car warranty expires soon".
3. **Given** a car's `warranty_limit_km` is 450 km ahead of its current odometer, **When** the daily scheduler runs, **Then** the owner receives a push: "Your car warranty expires soon".
4. **Given** a document expires in more than 30 days, **Then** no notification is sent that day.

---

### User Story 5 — Admin Manages Fuel Prices (Priority: P5)

An admin sets and updates the current price per unit for each fuel type (92-octane, 95-octane, electric). These prices are used to auto-calculate liters when a quick fill-up is submitted without an explicit liter value.

**Why this priority**: Fuel prices are a prerequisite for liters auto-calculation. Without correct prices, the fill-up data quality degrades. Admin management ensures prices stay current without a code change.

**Independent Test**: Create a fuel price via the API, then submit a quick fill-up without liters. Verify the fill-up record's `liters` matches `amount_paid / price_per_unit`.

**Acceptance Scenarios**:

1. **Given** I am an admin, **When** I POST `{type: "92", price_per_unit: 12.25, effective_from: "2026-05-01"}`, **Then** the price is saved and future fill-ups use it for auto-calculation.
2. **Given** I am a regular user, **When** I attempt to create or update a fuel price, **Then** the request is rejected with 403.
3. **Given** two prices exist for type "95" with different `effective_from` dates, **When** a fill-up is submitted, **Then** the most recent price (latest `effective_from`) is used.

---

### Edge Cases

- What happens if no fuel price exists for the submitted `fuel_type`? → `liters` saved as `null`; fill-up succeeds.
- What happens if a user has no registered device tokens? → FCM send is skipped silently; the operation still succeeds.
- What happens if Firebase credentials are missing or invalid? → Notifications silently fail with a logged warning; all API operations remain unaffected.
- What happens if the car's `current_km` is null when a quick fill-up is submitted? → `odometer` is saved as `null`.
- What happens if an admin deletes a `service` that is referenced by a `car_log`? → `car_logs.service_id` is set to `null` (nullOnDelete); the log record is preserved.

---

## Requirements *(mandatory)*

### Functional Requirements

**Service History (Car Logs)**

- **FR-001**: System MUST allow a car owner to create a service log entry for their car with `odometer_at_service`, `actual_cost`, and `performed_at` fields.
- **FR-002**: System MUST allow a service log to optionally reference a catalog service via `service_id`.
- **FR-003**: System MUST allow a car owner to view, update, and delete their own car's service logs.
- **FR-004**: System MUST send a push notification to the car owner immediately upon creating a service log.

**Firebase & Device Tokens**

- **FR-005**: System MUST capture and store FCM device tokens automatically from any authenticated request that includes `DEVICE-TOKEN` and `DEVICE-TYPE` headers.
- **FR-006**: System MUST replace the existing token for a given user+device combination when a new token is provided (upsert by `user_id + device`).
- **FR-007**: System MUST support Android and iOS device types.

**Gas Station Check-In & Quick Fill-Up**

- **FR-008**: System MUST provide a gas-station check-in endpoint that, when called, sends a push notification to the car owner with a deep-link for quick fill-up.
- **FR-009**: System MUST provide a quick fill-up endpoint that accepts `amount_paid`, `fuel_type`, optional `liters`, optional `station_lat`, and optional `station_lng`.
- **FR-010**: System MUST auto-capture `fill_date = today` and `odometer = car.current_km` on quick fill-up submission.
- **FR-011**: When `liters` is absent, system MUST calculate it as `amount_paid / price_per_unit` using the most recent active fuel price for the submitted `fuel_type`. If no price exists, `liters` MUST be saved as `null`.
- **FR-012**: System MUST store `fuel_type`, `station_lat`, and `station_lng` on all fill-up records (existing fill-ups have these fields as `null`).

**Fuel Prices**

- **FR-013**: System MUST allow admins to create and update fuel prices per type (`92`, `95`, `electric`) with an `effective_from` date.
- **FR-014**: System MUST allow any authenticated user to read current fuel prices.
- **FR-015**: System MUST use the most recent `effective_from` price for a given type when auto-calculating liters.

**Notifications — Real-Time**

- **FR-016**: System MUST fire a queued notification when a service log is created (CarLogCreated event).
- **FR-017**: System MUST fire a queued notification when the gas-station check-in endpoint is called (GasStationCheckIn event).
- **FR-018**: When a car's odometer advances via fill-up or trip, system MUST check if any service milestone is within 500 km ahead and, if so, send a push notification (OdometerAdvanced event).

**Notifications — Scheduled**

- **FR-019**: System MUST run a daily job that sends push notifications for documents whose `expiry_date` falls within the next 30 days.
- **FR-020**: System MUST run a daily job that sends push notifications for cars whose `warranty_expiry_date` falls within the next 30 days OR whose `warranty_limit_km - current_km ≤ 500`.

### Key Entities

- **CarLog**: A completed maintenance event for a car. Attributes: `car_id`, `service_id` (optional), `odometer_at_service`, `actual_cost`, `performed_at`.
- **DeviceToken**: An FCM push token registered to a user. Attributes: `user_id`, `token`, `device` (android/ios). One token per user per device type.
- **FuelPrice**: An admin-defined price per fuel unit for a given type. Attributes: `type` (92/95/electric), `price_per_unit`, `effective_from`. Latest entry per type is the active price.
- **FillUp (extended)**: Existing entity gains `fuel_type`, optional `liters`, `station_lat`, `station_lng`.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A car owner can log a completed service in under 30 seconds.
- **SC-002**: A car owner at a gas station receives a fill-up prompt within 10 seconds of the app calling the check-in endpoint.
- **SC-003**: Quick fill-up takes two user inputs (amount + fuel type) — no date, no odometer entry required.
- **SC-004**: 100% of scheduled notification jobs complete daily without manual intervention.
- **SC-005**: Push notifications for all real-time events arrive on the user's device within 15 seconds of the triggering action.
- **SC-006**: Liters are correctly auto-calculated from amount and fuel price on all quick fill-ups where a fuel price exists (zero calculation errors).
- **SC-007**: All API operations (log, fill-up, check-in) succeed even when Firebase is unavailable — notification failure never blocks the primary action.

---

## Assumptions

- The mobile app (not the backend) is responsible for detecting that the user is at a gas station for 2+ minutes before calling the check-in endpoint.
- Egyptian fuel prices are government-regulated and change infrequently; admin-managed prices per type are sufficient without per-station granularity.
- The `fuel_type` field on fill-ups is optional for existing records (null = unknown/pre-feature); it is required only on the new quick fill-up endpoint.
- Each user has at most one device of each type (android/ios) at any given time. A new token for the same device type replaces the previous one.
- The scheduler runs via Laravel's built-in task scheduler (`routes/console.php` with `->daily()`); a system cron (`* * * * * php artisan schedule:run`) is assumed to be configured on the server.
- The `kreait/laravel-firebase` package is used for FCM, configured via a service account JSON file referenced by the `FIREBASE_CREDENTIALS` environment variable.
- A user who has no device tokens registered simply receives no push notifications — this is not an error state.
- All push notification failures are logged at `warning` level but do not affect the API response or transaction outcome.
