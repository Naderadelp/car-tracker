# Phase 0 Research: Close the CarLog Mobile API Gaps

**Feature**: `010-mobile-api-gaps` · **Date**: 2026-08-24

Everything below was read out of the codebase, not inferred from the gap report.
Several findings change the size or the shape of the work, in both directions.

---

## R1 — There is no car controller, no car routes, and no car policy

**Finding**: `routes/api.php` has no `cars` resource at all. A car is created
only inside `AuthController::register()`, and the only write path that touches it
afterwards is `updateProfile()`, which sets `tank_size` and nothing else.
`app/Policies/` contains 15 policies and `CarPolicy` is not among them, even
though `CarRepository` and `CarRepositoryEloquent` both exist.

**Decision**: US1 delivers a new `CarController` (`show` + `update`), a new
`CarPolicy`, a new `UpdateCarRequest`, a `Gate::policy()` registration, and two
routes. `tank_size` handling stays where it is — `PUT /auth/user` keeps working
so the existing mobile contract does not break — but the same value also becomes
settable through the car endpoint.

**Consequence — smaller than it looks**: `'car'` is already in
`RolePermissionsSeeder::$models`, so `edit-car`, `show-car` and the rest of the
CRUD permissions already exist. No seeder change and no `sync:permissions` run
is needed for US1.

**Alternative rejected**: extending `PUT /auth/user` to carry odometer, warranty
and colour. It keeps the route count down but puts car fields on a profile
endpoint, has no route-model binding to authorize against, and would leave the
project with no `CarPolicy` — pushing ownership checks back into the controller,
against Principle V.

---

## R2 — B3 is one column, not three

**Finding**: `fill_ups` already has `fuel_type` (enum `92` / `95` / `electric`,
nullable, added by `2026_05_25_000004`), `station_lat` and `station_lng`.
`FillUpController::store()` simply never accepts `fuel_type` — `quick()` does.
There is no `station_name` column anywhere.

`odometer` is `NOT NULL`, and **both** actions hardcode it:

```php
'odometer' => $car->current_km,
```

**Decision**: B3 needs exactly one migration (`station_name`), plus
`StoreFillUpRequest` accepting `odometer`, `station_name` and `fuel_type`, and
the controller preferring a supplied `odometer` over `$car->current_km`. The
column stays `NOT NULL` — the fallback happens in the controller, which is what
FR-010 describes.

---

## R3 — C1 and C2 are the same bug, and one fix closes both

**Finding**: `BaseController::success()` is

```php
return response()->json($data, $status);
```

`response()->json()` serialises a `JsonResource` through `jsonSerialize()`, which
returns the resolved array **without** the `data` wrapper that Laravel's normal
`toResponse()` path adds. So:

- `FillUpController::store()` → `success(new FillUpResource(...))` → a flat object.
- `FillUpController::quick()` → hand-wraps `['message' => ..., 'data' => ...]` to compensate → a different shape for the same resource. **That is C1.**
- `UpcomingServiceController::index()` → `success(ServiceResource::collection(...))` → a bare array, while `paginated()` explicitly builds `['data' => ..., 'meta' => ...]`. **That is C2.**

**Decision**: fix `success()` once so it always wraps in `data` and stops
hand-wrapping at the call sites. C1 and C2 both fall out of it.

**Consequence**: this changes the response body of **every** endpoint that calls
`success()`, not just the two the gap report names. The full test suite is the
gate, and `docs/mobile-api.md` must be re-issued in the same change.

---

## R4 — `success()` silently discards its `$message` argument

**Finding**:

```php
protected function success(mixed $data, int $status = 200, string $message = ''): JsonResponse
{
    return response()->json($data, $status);   // $message is never used
}
```

Every one of the ~14 call sites that passes a message — `'Fill-up recorded
successfully.'`, `'Role deleted successfully.'`, `'Verification code sent.'` —
sends nothing. `forgotPassword()` returns a literal `[]`.

**Decision**: honour `$message` when it is non-empty as part of the R3 fix. This
is the reason several endpoints in the gap report appear to return empty bodies,
and it is a one-line correction to a method every controller depends on.

---

## R5 — C3's type flip is `array` vs `object` in the JSON encoder

**Finding**: `BaseController::error()` declares `array $errors = []`. An empty
PHP array encodes as `[]`; Laravel's own `ValidationException` renders
`{"errors": {"field": ["..."]}}` — an object. One field, two JSON types.
`bootstrap/app.php` has an empty `withExceptions()` closure, so nothing
normalises this today.

**Decision**: make `errors` always an object — cast in `error()`, and register a
render callback in `withExceptions()` so framework-thrown exceptions match. The
empty case becomes `{}` rather than `[]`.

---

## R6 — C4 is already half-built

**Finding**: `AuthController::updateProfile()` already returns
`['user' => ..., 'car' => ...]`. `me()` returns `['user' => ...]` only.

**Decision**: make `me()` symmetric with `updateProfile()`. No `?include=car`
machinery, no query-builder work — the cheapest item in the feature.

---

## R7 — The forgot-password leak is a validation rule

**Finding**: `ForgotPasswordRequest` carries `exists:users,email`, so an unknown
address returns 422 with a validation error while a known one returns 200. That
is the enumeration oracle.

**Decision**: drop the `exists` rule and keep `required|email`. `OtpService::send()`
must then return quietly for an address with no account rather than throwing,
and the response body must be byte-identical in both cases (which depends on the
R4 fix, since today both would be `[]` for a different reason).

---

## R8 — Driver-added service lines have nowhere to put a price

**Finding**: the `service_items` pivot holds `service_id`, `item_id`, `car_id`
and timestamps — **no price**. Prices live on `items`, and `items.name` is
`unique`. So a driver adding "Cabin filter, 450" either collides with a global
catalogue row or creates one, and either way cannot carry their own price.

**Decision**: add `name` and `price` overrides to `service_items`, both nullable,
plus a nullable `item_id` (already nullable). A row with an `item_id` and no
overrides behaves exactly as today; a row with overrides and no `item_id` is a
driver's own line. The resource resolves `name`/`price` as override-then-catalogue.

**Alternative rejected**: a separate `custom_service_items` table. It avoids
touching a working pivot, but then the checklist for one interval has to be read
from two tables and merged in the right order on every request, for no gain.

**Alternative rejected**: letting drivers create `Item` rows. `items.name` is
globally unique and the catalogue is admin-managed; one driver's "Oil filter"
would collide with another's and pollute a shared table.

---

## R9 — F1 and F2 are two lines in one method

**Finding**: `ServiceRepositoryEloquent::upcomingForCar()` ends with

```php
->where('km', '>', $car->current_km)
->withCount('items')
```

`withCount('items')` is exactly the `items_count` the gap report complains about,
and `km > current_km` is exactly why passed intervals are unreachable.

**Decision**: replace `withCount('items')` with `with('items')` (keeping the count
alongside for anything that reads it), and make the `km` filter conditional on a
request flag so the whole schedule can be returned with each interval marked
passed / current / upcoming. The existing route keeps its default behaviour;
callers opt in.

---

## R10 — The unified cost ledger needs real rows, not a read-time union

**Finding**: decision D2 in the spec requires that a carried-across entry be
**overwritable by the driver** (FR-045) and that source edits propagate **unless**
it has been overwritten (FR-046). A view or a read-time union of `fill_ups` and
`car_logs` cannot store an override, so it cannot satisfy FR-045.

**Decision**: `costs` is a real table. Rows carry `source_type` / `source_id`
(null for manual entries) and an `amount_overridden` flag. `FillUp` and `CarLog`
observers create, update and delete the matching row — updates skip any row whose
`amount_overridden` is true. `TripObserver` is the precedent for this pattern in
this codebase.

**Consequence**: US4 is the largest story in the feature, materially larger than
the manual-only ledger the gap report describes. Sizing must reflect that.

**Alternative rejected**: computing the ledger at read time. Simpler, no
observers, no drift — but it cannot hold an override, which is the thing the user
explicitly asked for.

---

## R11 — A live production bug sits inside this feature's path

**Finding**: `DocumentRepositoryEloquent::spatie()` runs

```php
$this->model = $this->model->orderByRaw('ISNULL(expiry_date) ASC, expiry_date ASC');
```

`ISNULL()` is MySQL-only. Verified empirically to throw on sqlite
(`SQLSTATE[HY000]: General error: 1 near "ISNULL": syntax error`) and it will
throw the same way on Postgres, which is what production runs. `GET
/cars/{car}/documents` is broken in production right now.

**Decision**: fix it as the first task of US2, since US2 already changes document
behaviour. The portable form is
`orderByRaw('expiry_date IS NULL ASC, expiry_date ASC')`, which is valid on
sqlite, MySQL and Postgres alike.

---

## R12 — Where the odometer is actually written

**Finding**: `TripObserver::created()` does

```php
$car->current_km += (int) round($trip->total_distance_km);
$car->save();
event(new OdometerAdvanced($car, $car->current_km));
```

So trips already move the odometer, and an `OdometerAdvanced` event already
exists and is listened for.

**Decision**: a manual correction (D3) sets `current_km` absolutely and fires the
same `OdometerAdvanced` event, so anything already reacting to odometer movement
keeps working. Trips continue to increment on top of whatever the current value
is. No recalculation of historical records — per D3, figures already filed stand.

---

## Summary of size changes against the gap report

| Item | Gap report implies | Actually |
|---|---|---|
| B3 fill-up fields | three fields to add | one column; `fuel_type` already exists |
| C1 + C2 | two separate fixes | one fix in `success()` |
| C4 | wire up `?include=car` | copy two lines from `updateProfile()` |
| F1 + F2 | two changes | two lines in one method |
| B1 | one endpoint | endpoint **plus** a missing policy and controller |
| B4 | a CRUD resource | a CRUD resource **plus** two observers and override tracking |
| F3 | accept `items[]` | needs a pivot migration first — nowhere to store a price |
| — | not mentioned | `success()` discards every message it is given |
| — | not mentioned | documents are **already broken in production** (R11) |
