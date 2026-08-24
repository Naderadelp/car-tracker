# Phase 1 Contracts: Endpoints

**Feature**: `010-mobile-api-gaps` · **Date**: 2026-08-24

No URL versioning anywhere — constitution, non-negotiable. Everything below sits
directly under the `api` prefix. All authenticated routes use `auth:sanctum` plus
`FirebaseTokenStoreMiddleware`, as the existing group does.

---

## Response envelope (Phase A — applies to every endpoint)

**Single resource**

```json
{ "data": { "id": 1, "...": "..." } }
```

**With a message**

```json
{ "data": { "...": "..." }, "message": "Fill-up recorded successfully." }
```

**Collection (paginated)**

```json
{ "data": [ { "...": "..." } ], "meta": { "current_page": 1, "per_page": 15, "total": 42, "last_page": 3 } }
```

**Error — `errors` is always an object, never an array**

```json
{ "message": "The given data was invalid.", "errors": { "liters": ["The liters field is required."] } }
```

```json
{ "message": "Something went wrong.", "errors": {} }
```

The empty case is `{}`. Today it is `[]`, which is the entire content of gap C3.

---

## New endpoints

### Car (US1)

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/cars/{car}` | Read the driver's car |
| `PUT` | `/cars/{car}` | Correct mileage, warranty, colour, tank size, purchase details |

`PUT` body — every field optional:

```json
{
  "current_km": 47200,
  "color": "silver",
  "has_warranty": true,
  "warranty_limit_km": 100000,
  "warranty_expiry_date": "2028-06-01",
  "tank_size": 52.0,
  "purchase_price_egp": 850000.00,
  "purchased_at": "2024-03-15"
}
```

A downward `current_km` is **accepted** (decision D3). Records already filed keep
the figures they were filed with.

### Costs (US4)

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/cars/{car}/costs` | The unified ledger, paginated, with category totals |
| `POST` | `/cars/{car}/costs` | Record a manual cost |
| `GET` | `/cars/{car}/costs/{cost}` | One entry |
| `PUT` | `/cars/{car}/costs/{cost}` | Correct an entry — on a carried-across row this sets `amount_overridden` |
| `DELETE` | `/cars/{car}/costs/{cost}` | Remove an entry |

`POST` body:

```json
{ "spent_at": "2026-08-14", "title": "Annual insurance", "amount_egp": 12500.00, "category": "insurance" }
```

Item shape — `source` is what makes decision D2 legible to the client:

```json
{
  "id": 91,
  "spent_at": "2026-08-14",
  "title": "Annual insurance",
  "amount_egp": "12500.00",
  "category": "insurance",
  "source": null,
  "amount_overridden": false
}
```

A carried-across row instead carries:

```json
{ "source": { "type": "fill_up", "id": 412 }, "amount_overridden": false }
```

The index response adds a `totals` block alongside `meta`:

```json
{ "total_egp": "48210.00", "by_category": { "fuel": "31200.00", "service": "9800.00", "insurance": "12500.00" } }
```

### Issues (US5)

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/cars/{car}/issues` | Fault log, filterable by `resolved` and `severity` |
| `POST` | `/cars/{car}/issues` | Record a fault |
| `GET` | `/cars/{car}/issues/{issue}` | One fault |
| `PUT` | `/cars/{car}/issues/{issue}` | Correct or resolve |
| `DELETE` | `/cars/{car}/issues/{issue}` | Remove |
| `POST` | `/cars/{car}/issues/{issue}/photo` | Attach or replace the photo |
| `GET` | `/cars/{car}/issues/{issue}/photo` | **`StreamedResponse`**, not JSON — private disk |

`severity` is `low` | `medium` | `high`. `resolved_at` null means unresolved;
`high` + unresolved is what FR-021 surfaces on the attention list.

### Insights (US9)

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/cars/{car}/statistics?period=month&compare=previous` | Spend by source, distance, fill-up count, average fuel price, cost per km, weekly buckets |
| `GET` | `/cars/{car}/valuation` | Purchase figure, derived present value, change |

The valuation response must carry its own honesty:

```json
{
  "purchase_price_egp": "850000.00",
  "purchased_at": "2024-03-15",
  "estimated_value_egp": "690000.00",
  "depreciation_egp": "160000.00",
  "depreciation_percent": 18.8,
  "basis": "estimate",
  "note": "Derived from purchase price, age and mileage. Not a market appraisal."
}
```

`basis` and `note` are required by decision D1 — no external market data is used,
and the response must not imply otherwise.

### Account (US3)

| Method | Path | Purpose |
|---|---|---|
| `DELETE` | `/auth/user` | Delete the signed-in driver's account |

---

## Changed endpoints

| Endpoint | Change | Gap |
|---|---|---|
| `GET /auth/user` | now returns `{ user, car }`, matching `PUT /auth/user` | C4 |
| `POST /auth/forgot-password` | identical response for known and unknown addresses | S2 |
| `POST /cars/{car}/fill-ups` | accepts `odometer`, `station_name`, `fuel_type`, all optional | B3 |
| `POST /cars/{car}/fill-ups/quick` | envelope now matches `store()` | C1 |
| `GET /cars/{car}/fill-ups` | each record carries `km_per_liter` | A2 |
| `POST /cars/{car}/documents` | `document_file` optional; past `expiry_date` accepted | B2 |
| `GET /cars/{car}/documents` | **stops throwing on Postgres** — `ISNULL()` was MySQL-only | R11 |
| `GET /cars/{car}/upcoming-services` | wrapped envelope; `items[]` included; `?include_past=1` returns the whole schedule | C2, F1, F2 |
| `POST /cars/{car}/services` | accepts `items[] = [{name, price}]` | F3 |
| `POST`/`PUT /cars/{car}/logs` | accept `title`, `workshop`, `category`, `notes` | F4 |
| `POST /cars/{car}/trips` | accepts `started_at`, `ended_at`, `duration_seconds`, `max_speed_kmh` | F5 |
| `POST /cars/{car}/parking-records` | accepts `address` | F7 |
| `PUT /cars/{car}/parking-records/{parkingRecord}` | **new** — the resource was create/delete only | F7 |
| `GET /services`, `GET /brands/{brand}/service-centers` | carry `name_ar`, `address_ar` | F6 |
| `POST /auth/register` | accepts `color` | F8 |

## Breaking changes

Phase A changes the envelope on **every** endpoint, and the fill-up and
upcoming-services shapes change beyond that. The mobile client is unreleased and
is the only consumer, which is why the spec records this as an acceptable
one-time break. After release it would not be.
