# Research: Service History + Smart Notifications

**Feature**: 007-service-history-notifications | **Date**: 2026-05-25

---

## Decision 1 — `kreait/laravel-firebase` version

**Decision**: Use `kreait/laravel-firebase ^7.x`

**Rationale**: v7 explicitly targets Laravel 11+ / PHP 8.2+. Compatible with the project's Laravel 13 / PHP 8.4 stack. The superapp uses v6.x (Laravel 9 era); v7 is the upgrade path for this project's stack.

**Install**: `composer require kreait/laravel-firebase`

**Config key**: `FIREBASE_CREDENTIALS` env var → path to service-account JSON file, consumed by the kreait package's own `config/firebase.php` as `firebase.credentials`.

---

## Decision 2 — FK delete behavior for new tables (`nullOnDelete` vs `cascadeOnDelete`)

**Decision**: All FKs on new tables use **nullable + nullOnDelete** — matching the pattern established in specs 003–005.

**Rationale**: Deletes on parent records (users, cars, services) must never destroy child records. The project enforces this across the board. The superapp's `cascadeOnDelete` on `device_tokens.user_id` is not adopted.

---

## Decision 3 — `OdometerAdvanced` event dispatch point

**Decision**: Dispatched **only from `TripObserver::created()`**, after `$car->save()`.

**Rationale**: `FillUpController::store` snapshots `car.current_km` but does NOT advance it — it merely records the current reading. Only `TripObserver` actually increments `current_km`. Dispatching from both places would double-fire the notification check.

---

## Decision 4 — Repository pattern for `DeviceToken`

**Decision**: Create a `DeviceTokenRepository` contract + eloquent impl with a custom `upsertToken(int $userId, string $token, string $device): void` domain method.

**Rationale**: The constitution states all DB access must go through a repository. The middleware calls `$this->deviceTokenRepository->upsertToken(...)` rather than touching Eloquent directly. The overhead is minimal (one interface, one implementation).

---

## Decision 5 — Event + Listener registration strategy

**Decision**: Register via `Event::listen()` calls in `AppServiceProvider::boot()`.

**Rationale**: Consistent with how `TripObserver` is wired. Explicit registration is clearer than auto-discovery in a codebase that uses explicit provider wiring throughout.

---

## Decision 6 — `FirebaseTokenStoreMiddleware` attachment point

**Decision**: Append to the authenticated route group in `routes/api.php` by adding `FirebaseTokenStoreMiddleware::class` to the `auth:sanctum` middleware array.

**Rationale**: The middleware only needs to run for authenticated requests. Adding it as a second element in the `->middleware([...])` call is the simplest Laravel 13 approach without touching `bootstrap/app.php`.

---

## Decision 7 — `FuelPrice` CRUD scope

**Decision**: `index`, `store`, `update` only — no `destroy`.

**Rationale**: Fuel prices are a historical log; wrong entries are corrected by posting a new entry with the correct `price_per_unit` and today's `effective_from`. The system always reads the latest entry per type. Deletion is unnecessary and would create gaps in the audit trail.

---

## Decision 8 — `GasStationCheckInController` shape

**Decision**: Single-action invokable controller `GasStationCheckInController`.

**Rationale**: The endpoint has exactly one action (fire event + return 200). An invokable controller removes the method-level indirection. Precedent: `UpcomingServiceController` is similarly thin.

---

## Decision 9 — Scheduled command registration

**Decision**: Use `Schedule::command('app:check-document-expiry')->daily()` inside `routes/console.php`.

**Rationale**: Laravel 13 uses `routes/console.php` for both one-off Artisan commands and scheduled tasks (replacing the old `app/Console/Kernel.php`). Commands are created as `app/Console/Commands/` classes with `protected $signature = 'app:check-document-expiry'` (and similarly for warranty).

---

## Decision 10 — `liters` nullable change on `fill_ups` and backward compat

**Decision**: `liters` column altered to nullable in a new migration (`alter_fill_ups_add_fuel_fields_table.php`).

**Rationale**: The project uses `migrate:fresh` for development (greenfield strategy). In production, an ALTER TABLE statement makes `liters` nullable. Existing fill-up records keep their existing `liters` values; new records from the `quick` endpoint may have null liters (when no fuel price is configured).

---

## Decision 11 — `CarLog` table name (`car_logs`)

**Decision**: Table name `car_logs`, model `CarLog`.

**Rationale**: Requested by the user during brainstorming to replace the initially proposed `service_logs` name. The name is intentionally generic to accommodate future non-service log types.

---

## Decision 12 — `FuelPriceRepository::currentForType()` query strategy

**Decision**: `app($this->model())->newQuery()->where('type', $type)->orderByDesc('effective_from')->first()`.

**Rationale**: No unique constraint on `type` (history is preserved). The most recent `effective_from` date is the active price. Using `newQuery()` bypasses `$this->model` (the mutable Spatie builder) per the mutation pattern.

---

## Decision 13 — `CheckDocumentExpiry` notification target

**Decision**: Load each document with its related `user` (via `car.user`). Push to the car owner's device tokens.

**Rationale**: Documents belong to cars, and cars belong to users. The FCM notification goes to the car owner, not necessarily the user who uploaded the document (which could be an admin). `document->car->user_id` is the correct target.

---

## Decision 14 — No `DeviceToken` policy or user-facing CRUD

**Decision**: `DeviceToken` has no policy, no form requests, no resource, and no public CRUD endpoints.

**Rationale**: Token registration is entirely middleware-driven (transparent to the user). Exposing a token-management API would add complexity with no user-facing value. Tokens are replaced silently per Decision 4.
