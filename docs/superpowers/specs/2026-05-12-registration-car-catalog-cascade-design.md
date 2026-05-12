# Registration Car Catalog Cascade — Design Spec

**Date:** 2026-05-12
**Status:** Approved
**Owner:** Naderadelp

## Goal

During `POST /api/auth/register`, the signing-up user must pick their car from data that already exists in the database. The frontend needs a three-step cascade — **brand → model name → year** — so the user only ever selects from valid combinations, and the final pick resolves to a single `car_model_id` that the existing register endpoint already accepts.

## Background

- `Brand` has `name` (unique).
- `CarModel` has `brand_id`, `name`, `model_year` (integer, nullable). The same `name` repeats across multiple `model_year` rows under one brand (e.g. "Corolla 2020", "Corolla 2021" are two `CarModel` rows).
- `Car` belongs to `brand` + `car_model` (no year column of its own).
- `POST /api/auth/register` already accepts `brand_id` and `car_model_id` and creates the User + Car in one transaction. **This endpoint will not change.**
- Two public lookup routes already exist (annotated in `routes/api.php:31` as "used during registration"):
  - `GET /api/brands`
  - `GET /api/brands/{brand}/car-models`

## Non-goals

- No DB schema changes.
- No changes to `POST /api/auth/register` or `RegisterUserRequest`.
- No fix to the unrelated brand/model mismatch validation bug (out of scope for this work).
- No changes to admin-only brand/car-model CRUD routes.
- No new `CarModelRepository` — `CarModelController` does its queries inline today; we keep that pattern.

## Endpoints

### Step 1 — `GET /api/brands` (unchanged)

Existing endpoint, kept as-is. Public, paginated.

### Step 2 — `GET /api/brands/{brand}/car-model-names` (new)

Returns the distinct car-model names available under a brand.

- **Auth:** public (no `auth:sanctum`).
- **Query:** `SELECT DISTINCT name FROM car_models WHERE brand_id = ? AND model_year IS NOT NULL ORDER BY name ASC`.
- **Pagination:** none. List is small; the frontend needs all names at once.
- **Response shape:**
  ```json
  { "data": [ { "name": "Camry" }, { "name": "Corolla" }, { "name": "Yaris" } ] }
  ```

The `model_year IS NOT NULL` filter ensures a name only appears here if step 3 can produce at least one usable year for it.

### Step 3 — `GET /api/brands/{brand}/car-models?name={name}` (extended)

Existing endpoint. New behavior is **only** triggered when the `name` query string is present and non-empty.

- **Without `?name=`** — unchanged: paginated, ordered by `name ASC`. Existing clients keep working.
- **With `?name=Corolla`:**
  - Filter: `WHERE brand_id = ? AND name = ? AND model_year IS NOT NULL`.
  - Sort: `model_year DESC` (newest year first).
  - **No pagination.** Returns all matching rows under a `data` key (no `meta`).
  - Response shape (uses existing `CarModelResource`):
    ```json
    {
      "data": [
        { "id": 17, "brand_id": 2, "name": "Corolla", "model_year": 2024 },
        { "id": 12, "brand_id": 2, "name": "Corolla", "model_year": 2023 },
        { "id": 8,  "brand_id": 2, "name": "Corolla", "model_year": 2022 }
      ]
    }
    ```

The frontend uses each row's `id` as the `car_model_id` it submits to `/auth/register`.

**Empty-string filter (`?name=`)** is treated as "no filter" — falls back to default paginated behavior, so a malformed client request never accidentally returns an unpaginated 1000-row list.

## Edge cases

| Case | Response |
|------|----------|
| Brand has zero models → step 2 | `{ "data": [] }`, HTTP 200 |
| Brand ID does not exist | HTTP 404 (Laravel route-model binding) |
| `?name=DoesNotExist` on step 3 | `{ "data": [] }`, HTTP 200 |
| `?name=` (empty string) on step 3 | Falls back to paginated default |
| All matching models have `model_year` = NULL | Name does not appear in step 2; step 3 with that name returns empty `data` |

## Tests

Feature tests under `tests/Feature/`. SQLite in-memory (already configured in `phpunit.xml`). Uses `RefreshDatabase`.

Required factories (new): `BrandFactory`, `CarModelFactory`.

**`CarModelNamesTest`:**
- returns distinct names for the brand, sorted ASC
- duplicate name across multiple `model_year` rows collapses to one entry
- excludes rows where `model_year` is NULL
- empty array when brand has no models
- 404 when brand id doesn't exist
- models from other brands don't leak in
- route is publicly accessible (no auth header sent)

**`CarModelIndexFilterTest`:**
- `?name=Corolla` returns only Corolla rows, sorted by `model_year DESC`
- response with `name` filter has `data` only (no `meta` pagination block)
- `?name=` (empty) behaves identically to no filter (paginated, name ASC, `meta` present) — regression guard
- no filter at all → paginated, name ASC, `meta` present — regression guard
- `?name=DoesNotExist` returns empty `data`, HTTP 200, no `meta`
- `whereNotNull('model_year')` is honored under `?name=` (rows with null years excluded)
- models from other brands are excluded
- route is publicly accessible

## File map

- **New:**
  - `app/Http/Resources/CarModelNameResource.php` — `{ name: string }`
  - `database/factories/BrandFactory.php`
  - `database/factories/CarModelFactory.php`
  - `tests/Feature/CarModelNamesTest.php`
  - `tests/Feature/CarModelIndexFilterTest.php`
- **Modified:**
  - `routes/api.php` — register one new public GET route
  - `app/Http/Controllers/CarModelController.php` — extend `index()`, add `names()`
  - `app/Models/Brand.php` — `use HasFactory;` (factory support)
  - `app/Models/CarModel.php` — `use HasFactory;` (factory support)
