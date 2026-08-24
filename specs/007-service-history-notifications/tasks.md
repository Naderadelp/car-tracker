# Tasks: Service History + Smart Notifications

**Input**: Design documents from `specs/007-service-history-notifications/`
**Prerequisites**: plan.md ✅ spec.md ✅ research.md ✅ data-model.md ✅ contracts/ ✅ quickstart.md ✅

**Tests**: Not requested — no test tasks generated.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no shared dependencies on incomplete tasks)
- **[Story]**: Which user story this task belongs to (US1–US5)
- File paths are relative to the project root

---

## Phase 1: Setup

**Purpose**: Install the new package, create all migration files, and run migrations.

- [X] T001 Install `kreait/laravel-firebase` via Composer and publish config: `composer require kreait/laravel-firebase` then `php artisan vendor:publish --provider="Kreait\Laravel\Firebase\ServiceProvider"` — add `FIREBASE_CREDENTIALS` to `.env.example`
- [X] T002 [P] Create migration `database/migrations/2026_05_25_000001_create_car_logs_table.php` — schema: `car_id` (FK nullable nullOnDelete → cars), `service_id` (FK nullable nullOnDelete → services), `odometer_at_service` int, `actual_cost` decimal(10,2), `performed_at` date, `timestamps()`; index on `car_id` and `service_id`
- [X] T003 [P] Create migration `database/migrations/2026_05_25_000002_create_device_tokens_table.php` — schema: `user_id` (FK nullable nullOnDelete → users), `token` string, `device` enum('android','ios'), `timestamps()`; `unique(['user_id', 'device'])`
- [X] T004 [P] Create migration `database/migrations/2026_05_25_000003_create_fuel_prices_table.php` — schema: `type` enum('92','95','electric'), `price_per_unit` decimal(10,2), `effective_from` date, `timestamps()`; index on `['type', 'effective_from']`
- [X] T005 [P] Create migration `database/migrations/2026_05_25_000004_alter_fill_ups_add_fuel_fields.php` — `Schema::table('fill_ups', ...)`: add `fuel_type` enum('92','95','electric') nullable after `fill_date`; change `liters` decimal(8,2) to nullable; add `station_lat` decimal(10,8) nullable; add `station_lng` decimal(11,8) nullable
- [X] T006 Run `php artisan migrate` (or `migrate:fresh` for dev) to apply all four migrations

**Checkpoint**: Four new tables exist and `fill_ups` has the new columns.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Firebase infrastructure, new models, DeviceToken and FuelPrice repositories, middleware, permissions — all must be complete before any user story can work.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [X] T007 [P] Create `app/Models/CarLog.php` — `use LogsActivity`; `$table = 'car_logs'`; `$fillable = ['car_id', 'service_id', 'odometer_at_service', 'actual_cost', 'performed_at']`; `casts()`: `odometer_at_service` → integer, `actual_cost` → `decimal:2`, `performed_at` → date; `getActivitylogOptions()` returning `LogOptions::defaults()->logOnly(['*'])`; add `car()` BelongsTo and `service()` BelongsTo relations
- [X] T008 [P] Create `app/Models/DeviceToken.php` — no `LogsActivity`; `$table = 'device_tokens'`; `$fillable = ['user_id', 'token', 'device']`; `user()` BelongsTo relation
- [X] T009 [P] Create `app/Models/FuelPrice.php` — `use LogsActivity`; `$table = 'fuel_prices'`; `$fillable = ['type', 'price_per_unit', 'effective_from']`; `casts()`: `price_per_unit` → `decimal:2`, `effective_from` → date; `getActivitylogOptions()` returning `LogOptions::defaults()->logOnly(['*'])`
- [X] T010 Modify `app/Models/FillUp.php` — add `'fuel_type', 'station_lat', 'station_lng'` to `$fillable`; add `'fuel_type' => 'string', 'station_lat' => 'decimal:8', 'station_lng' => 'decimal:8'` to `casts()`; `liters` cast stays `decimal:2` (column is now nullable in DB)
- [X] T011 Create `app/Classes/FireBaseNotification.php` — port from tai-be-superapp pattern: constructor calls `initialize()`; `initialize()` reads `config('firebase.credentials')` (the kreait package config key), creates a `Kreait\Firebase\Factory` with the service account, returns a `Kreait\Firebase\Contract\Messaging` instance (or null if credentials missing, with a `Log::warning`); `sendMessage(array $notification, array $data, array $deviceTokens): void` iterates tokens, builds a `CloudMessage::fromArray([...])`, calls `$this->messaging->send()`, catches per-token exceptions and logs warnings
- [X] T012 Create `app/Repositories/Contracts/DeviceTokenRepository.php` — extends `RepositoryInterface`; declares `public function upsertToken(int $userId, string $token, string $device): void`
- [X] T013 Create `app/Repositories/Eloquent/DeviceTokenRepositoryEloquent.php` — extends `EloquentRepository`, implements `DeviceTokenRepository`; `model()` returns `DeviceToken::class`; implement `upsertToken`: calls `app($this->model())->newQuery()->updateOrCreate(['user_id' => $userId, 'device' => $device], ['token' => $token])` then `$this->resetModel()`; no `scopeToUser()` needed (tokens accessed only via `upsertToken`, not via general query methods)
- [X] T014 Create `app/Repositories/Contracts/FuelPriceRepository.php` — extends `RepositoryInterface`; declares `public function currentForType(string $type): ?FuelPrice`
- [X] T015 Create `app/Repositories/Eloquent/FuelPriceRepositoryEloquent.php` — extends `EloquentRepository`, implements `FuelPriceRepository`; `model()` returns `FuelPrice::class`; `$allowedFiltersExact = ['type']`; `$allowedSorts = ['effective_from', 'type']`; `$allowedDefaultSorts = ['-effective_from']`; implement `currentForType`: uses `app($this->model())->newQuery()->where('type', $type)->orderByDesc('effective_from')->first()` then `$this->resetModel()`; no `scopeToUser()` (system-wide resource)
- [X] T016 Bind both new repositories in `app/Providers/RepositoryServiceProvider.php` — add `use` imports and `$this->app->bind(DeviceTokenRepository::class, DeviceTokenRepositoryEloquent::class)` + `$this->app->bind(FuelPriceRepository::class, FuelPriceRepositoryEloquent::class)`
- [X] T017 Create `app/Http/Middleware/FirebaseTokenStoreMiddleware.php` — constructor injects `DeviceTokenRepository $deviceTokenRepository`; `handle()` checks `auth()->check()` and headers `DEVICE-TOKEN` + `DEVICE-TYPE` exist; if both true, calls `$this->deviceTokenRepository->upsertToken(auth()->id(), $request->header('DEVICE-TOKEN'), $request->header('DEVICE-TYPE'))`; returns `$next($request)`
- [X] T018 Attach `FirebaseTokenStoreMiddleware` to the `auth:sanctum` middleware group in `routes/api.php` — change `Route::middleware('auth:sanctum')` to `Route::middleware(['auth:sanctum', \App\Http\Middleware\FirebaseTokenStoreMiddleware::class])`
- [X] T019 Update `database/seeders/RolePermissionsSeeder.php` — add `'car-log'` and `'fuel-price'` to the `$models` array; then run `php artisan sync:permissions`
- [X] T020 Update `app/Http/Resources/FillUpResource.php` — add `'fuel_type' => $this->fuel_type`, `'station_lat' => $this->station_lat`, `'station_lng' => $this->station_lng`, `'liters' => $this->liters` to `toArray()`

**Checkpoint**: Firebase class exists, DeviceToken tokens are stored on any authenticated request, FuelPrice model and repo are ready, permissions are seeded.

---

## Phase 3: User Story 1 — Log a Completed Service (Priority: P1) 🎯 MVP

**Goal**: Car owners can create, read, update, and delete service log entries for their cars. Creating a log immediately sends an FCM push notification confirming the entry.

**Independent Test**: POST `/api/cars/1/logs` with valid fields → verify 201 response + log appears in GET `/api/cars/1/logs` + FCM notification is queued.

### Implementation for User Story 1

- [X] T021 [P] [US1] Create `app/Repositories/Contracts/CarLogRepository.php` — extends `RepositoryInterface`; no custom methods needed
- [X] T022 [P] [US1] Create `app/Repositories/Eloquent/CarLogRepositoryEloquent.php` — extends `EloquentRepository`, implements `CarLogRepository`; `model()` returns `CarLog::class`; `$allowedIncludes = ['car', 'service']`; `$allowedFiltersExact = ['car_id', 'service_id']`; `$allowedSorts = ['performed_at', 'actual_cost', 'odometer_at_service']`; `$allowedDefaultSorts = ['-performed_at']`; override `scopeToUser()`: non-admin — `$this->model = $this->model->whereHas('car', fn($q) => $q->where('user_id', auth()->id()))`
- [X] T023 [P] [US1] Create `app/Policies/CarLogPolicy.php` — five methods: `viewAny(User $user, Car $car)`: `$user->id === $car->user_id || $user->hasPermissionTo('index-car-log')`; `create(User $user, Car $car)`: same ownership check with `create-car-log`; `view(User $user, CarLog $log)`: `$user->id === $log->car->user_id || $user->hasPermissionTo('show-car-log')`; `update(User $user, CarLog $log)`: ownership + `edit-car-log`; `delete(User $user, CarLog $log)`: ownership + `destroy-car-log`; no `before()` method
- [X] T024 [P] [US1] Create `app/Http/Requests/CarLog/StoreCarLogRequest.php` — `authorize()`: `$this->user()->can('create', [CarLog::class, $this->route('car')])`; `rules()`: `service_id` optional exists:services,id; `odometer_at_service` required integer min:0; `actual_cost` required numeric min:0; `performed_at` required date before_or_equal:today
- [X] T025 [P] [US1] Create `app/Http/Requests/CarLog/UpdateCarLogRequest.php` — `authorize()`: `$this->user()->can('update', $this->route('log'))`; same rules as store but all fields optional (`sometimes`)
- [X] T026 [P] [US1] Create `app/Http/Resources/CarLogResource.php` — returns: `id`, `car_id`, `service_id`, `odometer_at_service`, `actual_cost`, `performed_at` (toDateString), `created_at`; include `car` and `service` via `whenLoaded()`
- [X] T027 [US1] Bind `CarLogRepository` in `app/Providers/RepositoryServiceProvider.php` and register `CarLogPolicy` in `app/Providers/AppServiceProvider.php` via `Gate::policy(CarLog::class, CarLogPolicy::class)` — add all required `use` imports
- [X] T028 [US1] Create `app/Events/CarLogCreated.php` — public constructor property `public CarLog $carLog`; create `app/Listeners/SendServiceCompletedNotification.php` implementing `ShouldQueue` — `handle(CarLogCreated $event)`: loads `$event->carLog->car->user->deviceTokens`; if empty skip; builds notification `['title' => 'Service Logged', 'body' => "Service logged at {$km} km — cost {$cost} EGP"]` and data `['type' => 'service_completed', 'car_id' => (string)$car->id, 'log_id' => (string)$log->id]`; calls `(new FireBaseNotification)->sendMessage($notification, $data, $tokens)`; register both in `AppServiceProvider::boot()` via `Event::listen(CarLogCreated::class, SendServiceCompletedNotification::class)` and add required `use` imports
- [X] T029 [US1] Create `app/Http/Controllers/CarLogController.php` extending `BaseController` — constructor injects `CarLogRepository $carLogRepository`; implement `index(Request $request, Car $car)`: authorize `viewAny` with car, `->where('car_id', $car->id)->spatie()->paginate()`, return `$this->paginated($logs, CarLogResource::class)`; `store(StoreCarLogRequest $request, Car $car)`: DB transaction, `create([...$request->validated(), 'car_id' => $car->id])`, `event(new CarLogCreated($log))`, return 201; `show(Request $request, Car $car, CarLog $log)`: authorize `view`, abort_if car mismatch, return success; `update(UpdateCarLogRequest $request, Car $car, CarLog $log)`: abort_if car mismatch, DB transaction, update, return success; `destroy(Request $request, Car $car, CarLog $log)`: authorize `delete`, abort_if car mismatch, delete, return 200
- [X] T030 [US1] Add car-log routes to `routes/api.php` inside the `Route::prefix('cars/{car}')` group — five routes: `GET logs`, `POST logs`, `GET logs/{log}`, `PUT|PATCH logs/{log}`, `DELETE logs/{log}` all pointing to `CarLogController`; add `use` import for `CarLogController`

**Checkpoint**: User Story 1 is fully functional. CRUD on `/api/cars/{car}/logs` works, and FCM push is queued on store.

---

## Phase 4: User Story 2 — Quick Fill-Up from Gas Station Prompt (Priority: P2)

**Goal**: When the mobile app detects a gas station, it calls a check-in endpoint that sends an FCM push with a deep-link. The user taps it and fills a simplified form (amount + fuel type). The backend auto-captures date and odometer, and calculates liters from the current fuel price.

**Independent Test**: POST `/api/cars/1/gas-station-check-in` → verify FCM queued with `action=quick_fill_up`. POST `/api/cars/1/fill-ups/quick` with `amount_paid=500, fuel_type=95` (and a fuel price seeded) → verify 201 and `liters ≈ 36.36`.

### Implementation for User Story 2

- [X] T031 [US2] Create `app/Events/GasStationCheckIn.php` — public constructor property `public Car $car`; create `app/Listeners/SendGasStationReminderNotification.php` implementing `ShouldQueue` — `handle(GasStationCheckIn $event)`: loads car owner's device tokens; sends FCM with notification `['title' => 'Fill Up?', 'body' => "You're at a gas station — tap to log your fill-up"]` and data `['type' => 'quick_fill_up', 'car_id' => (string)$event->car->id]`; register in `AppServiceProvider::boot()` via `Event::listen(GasStationCheckIn::class, SendGasStationReminderNotification::class)`
- [X] T032 [US2] Create `app/Http/Controllers/GasStationCheckInController.php` (invokable) extending `BaseController` — constructor injects nothing; `__invoke(Request $request, Car $car)`: `$this->authorize('create', [FillUp::class, $car])` (car owner gate reuse); `event(new GasStationCheckIn($car))`; return `$this->success([], 200, 'Notification sent.')`; add route `Route::post('cars/{car}/gas-station-check-in', GasStationCheckInController::class)` inside the `auth:sanctum` group in `routes/api.php`; add `use` imports
- [X] T033 [US2] Create `app/Http/Requests/FillUp/QuickFillUpRequest.php` — `authorize()`: `$this->user()->can('create', [FillUp::class, $this->route('car')])`; `rules()`: `amount_paid` required numeric min:0.01; `fuel_type` required in:92,95,electric; `liters` nullable numeric min:0.01; `station_lat` nullable numeric between:-90,90; `station_lng` nullable numeric between:-180,180
- [X] T034 [US2] Add `quick(QuickFillUpRequest $request, Car $car): JsonResponse` action to `app/Http/Controllers/FillUpController.php` — inject `FuelPriceRepository $fuelPriceRepository` in constructor alongside existing `FillUpRepository`; method body: compute `$liters = $request->liters ?? (($price = $this->fuelPriceRepository->currentForType($request->fuel_type)) ? round($request->amount_paid / $price->price_per_unit, 2) : null)`; DB transaction: `$this->fillUpRepository->create(['car_id' => $car->id, 'liters' => $liters, 'odometer' => $car->current_km, 'cost_egp' => $request->amount_paid, 'fill_date' => now()->toDateString(), 'fuel_type' => $request->fuel_type, 'station_lat' => $request->station_lat, 'station_lng' => $request->station_lng])`; return 201; add route `Route::post('cars/{car}/fill-ups/quick', [FillUpController::class, 'quick'])` in `routes/api.php` BEFORE any `fill-ups/{fillUp}` wildcard route

**Checkpoint**: Gas station check-in fires FCM. Quick fill-up creates a fill-up with auto-captured date/odometer and calculated liters.

---

## Phase 5: User Story 3 — Upcoming Maintenance Push Notification (Priority: P3)

**Goal**: When a car's odometer advances via a trip, the system checks if any service milestone is within 500 km. If so, the car owner receives a push notification.

**Independent Test**: Set a service milestone at `current_km + 400`. Create a trip that advances the odometer. Verify FCM is queued with body "Upcoming service in 400 km".

### Implementation for User Story 3

- [X] T035 [US3] Create `app/Events/OdometerAdvanced.php` — public constructor properties `public Car $car` and `public int $newKm`; create `app/Listeners/CheckUpcomingServicesNotification.php` implementing `ShouldQueue` — `handle(OdometerAdvanced $event)`: queries `Service::where('car_model_id', $event->car->car_model_id)->whereNull('user_id')->whereNull('car_id')->where('km', '>', $event->newKm)->where('km', '<=', $event->newKm + 500)->get()`; for each matched service, loads car owner device tokens and sends FCM with notification `['title' => 'Upcoming Service', 'body' => "Upcoming service in {$remaining} km"]` and data `['type' => 'upcoming_service', 'car_id' => (string)$event->car->id, 'km_remaining' => (string)$remaining]`; register in `AppServiceProvider::boot()` via `Event::listen(OdometerAdvanced::class, CheckUpcomingServicesNotification::class)`
- [X] T036 [US3] Modify `app/Observers/TripObserver.php` `created()` method — after `$car->save()`, add `event(new \App\Events\OdometerAdvanced($car, $car->current_km))`; add the `use` import at the top of the file

**Checkpoint**: Creating a trip now triggers an upcoming-service notification when a milestone is within 500 km.

---

## Phase 6: User Story 4 — Document & Warranty Expiry Reminders (Priority: P4)

**Goal**: A daily scheduled job sends push notifications for documents expiring within 30 days and for cars with warranties nearing expiry (by date or by odometer).

**Independent Test**: Run `php artisan app:check-document-expiry` and `php artisan app:check-warranty-expiry` manually. Verify FCM pushes are queued for matching records.

### Implementation for User Story 4

- [X] T037 [US4] Create `app/Console/Commands/CheckDocumentExpiry.php` — signature: `app:check-document-expiry`; `handle()`: queries `Document::with('car.user.deviceTokens')->whereNotNull('expiry_date')->whereBetween('expiry_date', [today(), today()->addDays(30)])->get()`; for each document, if `$document->car->user->deviceTokens` is not empty, sends FCM: notification `['title' => 'Document Expiring', 'body' => "Your {$document->type} expires in {$days} days"]`, data `['type' => 'document_expiry', 'document_id' => (string)$document->id, 'days_remaining' => (string)$days]`; register in `routes/console.php`: add `use Illuminate\Support\Facades\Schedule;` and `Schedule::command('app:check-document-expiry')->daily()`
- [X] T038 [US4] Create `app/Console/Commands/CheckWarrantyExpiry.php` — signature: `app:check-warranty-expiry`; `handle()`: queries cars where `warranty_expiry_date BETWEEN today AND today+30 days` OR `(warranty_limit_km IS NOT NULL AND warranty_limit_km - current_km BETWEEN 0 AND 500)` using `Car::with('user.deviceTokens')->where(fn($q) => $q->whereBetween('warranty_expiry_date', [...]) ->orWhereRaw('warranty_limit_km IS NOT NULL AND (warranty_limit_km - current_km) BETWEEN 0 AND 500'))->get()`; for each car with tokens, sends FCM: notification `['title' => 'Warranty Expiring', 'body' => 'Your car warranty expires soon']`, data `['type' => 'warranty_expiry', 'car_id' => (string)$car->id]`; register `Schedule::command('app:check-warranty-expiry')->daily()` in `routes/console.php`

**Checkpoint**: Running both commands manually sends correct FCM notifications. Daily schedule is registered.

---

## Phase 7: User Story 5 — Admin Manages Fuel Prices (Priority: P5)

**Goal**: Admins can create and update fuel prices per type. All authenticated users can list current prices. The correct active price (latest `effective_from`) is used by the quick fill-up liters calculation.

**Independent Test**: POST `/api/fuel-prices` as admin → 201. GET `/api/fuel-prices` as any auth user → 200 with price list. POST as regular user → 403. POST quick fill-up without liters → fill-up saved with liters = amount / newest price.

### Implementation for User Story 5

- [X] T039 [P] [US5] Create `app/Policies/FuelPricePolicy.php` — `viewAny(User $user): bool` returns `true` (any authenticated user); `create(User $user): bool` returns `$user->hasPermissionTo('create-fuel-price')`; `update(User $user, FuelPrice $price): bool` returns `$user->hasPermissionTo('edit-fuel-price')`; no `before()` method; register in `app/Providers/AppServiceProvider.php` via `Gate::policy(FuelPrice::class, FuelPricePolicy::class)`
- [X] T040 [P] [US5] Create `app/Http/Requests/FuelPrice/StoreFuelPriceRequest.php` — `authorize()`: `$this->user()->can('create', FuelPrice::class)`; `rules()`: `type` required in:92,95,electric; `price_per_unit` required numeric min:0.01; `effective_from` required date; and `app/Http/Requests/FuelPrice/UpdateFuelPriceRequest.php` — `authorize()`: `$this->user()->can('update', $this->route('fuelPrice'))`; same rules all `sometimes`
- [X] T041 [P] [US5] Create `app/Http/Resources/FuelPriceResource.php` — returns: `id`, `type`, `price_per_unit`, `effective_from` (toDateString), `created_at`
- [X] T042 [US5] Create `app/Http/Controllers/FuelPriceController.php` extending `BaseController` — constructor injects `FuelPriceRepository $fuelPriceRepository`; `index(Request $request)`: authorize `viewAny`, `->spatie()->paginate()`, return `$this->paginated($prices, FuelPriceResource::class)`; `store(StoreFuelPriceRequest $request)`: DB transaction, create, return 201; `update(UpdateFuelPriceRequest $request, FuelPrice $fuelPrice)`: DB transaction, update, return success; add fuel-price routes to `routes/api.php`: `GET fuel-prices`, `POST fuel-prices`, `PUT|PATCH fuel-prices/{fuelPrice}`; add `use` imports for controller and model

**Checkpoint**: Admin can manage fuel prices. Regular user gets 403 on write attempts. Quick fill-up liters calculation uses the latest seeded price.

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: Final verification, route ordering safety, and smoke test run.

- [X] T043 Verify route ordering in `routes/api.php` — confirm `Route::post('cars/{car}/fill-ups/quick', ...)` appears BEFORE any `Route::*('cars/{car}/fill-ups/{fillUp}', ...)` wildcard to prevent "quick" being captured as a `{fillUp}` ID
- [X] T044 Run all 6 smoke tests from `specs/007-service-history-notifications/quickstart.md` — verify: (1) car log CRUD + FCM queued; (2) gas station check-in + quick fill-up with auto-calculated liters; (3) device token auto-registration via DEVICE-TOKEN header; (4) OdometerAdvanced triggered by trip creation; (5) scheduled expiry commands execute without errors; (6) fuel price admin CRUD with 403 for regular users

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately
- **Foundational (Phase 2)**: Depends on Phase 1 (package + migrations must exist) — BLOCKS all user stories
- **US1 (Phase 3)**: Depends on Phase 2 — independent of US2–US5
- **US2 (Phase 4)**: Depends on Phase 2 (FuelPriceRepository ready) — independent of US1, US3–US5
- **US3 (Phase 5)**: Depends on Phase 2 — independent of US1, US2, US4, US5
- **US4 (Phase 6)**: Depends on Phase 2 — independent of US1, US2, US3, US5
- **US5 (Phase 7)**: Depends on Phase 2 (FuelPriceRepository + model already bound) — independent of US1–US4
- **Polish (Phase 8)**: Depends on all phases complete

### User Story Dependencies

- **US1 (P1)**: No cross-story dependency
- **US2 (P2)**: FuelPrice model and `FuelPriceRepositoryEloquent::currentForType()` must exist (done in Phase 2)
- **US3 (P3)**: No cross-story dependency beyond `TripObserver` and `Service` model (both pre-existing)
- **US4 (P4)**: No cross-story dependency beyond `Document` and `Car` models (pre-existing)
- **US5 (P5)**: No cross-story dependency; FuelPrice repo already created in Phase 2

### Within Each User Story

- Repository → Policy → Form Requests → Resource → Event+Listener → Controller → Routes
- Provider wiring (bind + register) after repository and policy are created

### Parallel Opportunities

- **Phase 1**: T002–T005 (4 migration files) all run in parallel
- **Phase 2**: T007–T009 (models) run in parallel; T012–T015 (repositories) run in parallel after models
- **Phase 3**: T021–T026 (repo, policy, requests, resource) all run in parallel
- **Phase 7**: T039–T041 (policy, requests, resource) all run in parallel

---

## Parallel Example: Phase 2 Foundation

```text
Parallel batch 1 — Models (T007–T009):
  Task T007: Create app/Models/CarLog.php
  Task T008: Create app/Models/DeviceToken.php
  Task T009: Create app/Models/FuelPrice.php

Parallel batch 2 — Repositories (T012–T015, after models exist):
  Task T012: Create DeviceTokenRepository contract + impl
  Task T013: Create FuelPriceRepository contract + impl
  (CarLogRepository deferred to Phase 3)
```

## Parallel Example: User Story 1 (Phase 3)

```text
Parallel batch — All of T021–T026 can be dispatched simultaneously:
  Task T021: CarLogRepository contract
  Task T022: CarLogRepositoryEloquent impl
  Task T023: CarLogPolicy
  Task T024: StoreCarLogRequest
  Task T025: UpdateCarLogRequest
  Task T026: CarLogResource
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational (**CRITICAL — blocks all stories**)
3. Complete Phase 3: User Story 1
4. **STOP and VALIDATE**: CRUD on `/api/cars/{car}/logs` + FCM push confirmation
5. Deploy/demo if ready

### Incremental Delivery

1. Phase 1 + Phase 2 → Foundation ready
2. Phase 3 → Car log CRUD + service completion push (MVP)
3. Phase 4 → Gas station flow + quick fill-up
4. Phase 5 → Odometer-based upcoming-service alerts
5. Phase 6 → Daily document/warranty expiry reminders
6. Phase 7 → Admin fuel price management
7. Phase 8 → Polish + full smoke test

### Parallel Team Strategy

With multiple developers after Phase 2 is complete:
- Dev A: US1 (Car Logs)
- Dev B: US2 (Gas Station + Quick Fill-Up)
- Dev C: US3 + US4 (Notification triggers)
- Dev D: US5 (Fuel Price Admin)

---

## Notes

- **Single commit rule**: Do NOT commit after each task. The user commits once at the end.
- `[P]` tasks touch different files — safe to dispatch in parallel via sub-agents.
- Each user story phase ends at a `Checkpoint` that describes the independently testable state.
- The `fill-ups/quick` route ordering (T043) is critical — verify before smoke testing.
- `php artisan sync:permissions` (T019) must run before any authorization tests.
- Queue worker must be running for FCM listeners to fire: `php artisan queue:listen`.
