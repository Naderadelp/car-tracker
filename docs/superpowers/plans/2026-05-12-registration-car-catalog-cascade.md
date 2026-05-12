# Registration Car Catalog Cascade — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Surface the brand/model-name/year cascade during signup via two API changes: one new public endpoint for distinct model names and a `?name=` filter on the existing `car-models` index — so the frontend can drive three dropdowns that resolve to a single `car_model_id`.

**Architecture:** All work lives inside the existing `CarModelController` (no new repository — matches the controller's existing inline-query pattern). A new public `GET /api/brands/{brand}/car-model-names` returns distinct names. The existing `GET /api/brands/{brand}/car-models` gains an optional `?name=` filter that switches it from paginated/name-ASC mode to unpaginated/`model_year`-DESC mode. The signup endpoint itself does not change.

**Tech Stack:** PHP 8.4, Laravel 13, PHPUnit (SQLite in-memory for tests), spatie/laravel-permission (unused for these public routes).

**Spec:** [`docs/superpowers/specs/2026-05-12-registration-car-catalog-cascade-design.md`](../specs/2026-05-12-registration-car-catalog-cascade-design.md)

---

## Task 1: Scaffold factories and enable `HasFactory` on Brand & CarModel

The project has only `UserFactory.php` so far. Tests need to spin up `Brand` + `CarModel` rows. This task adds the two factories and the `HasFactory` trait on both models. No business logic — purely test infrastructure.

**Files:**
- Create: `database/factories/BrandFactory.php`
- Create: `database/factories/CarModelFactory.php`
- Modify: `app/Models/Brand.php`
- Modify: `app/Models/CarModel.php`

- [ ] **Step 1: Create `BrandFactory`**

Create `database/factories/BrandFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Brand>
 */
class BrandFactory extends Factory
{
    protected $model = Brand::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
        ];
    }
}
```

- [ ] **Step 2: Create `CarModelFactory`**

Create `database/factories/CarModelFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\CarModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CarModel>
 */
class CarModelFactory extends Factory
{
    protected $model = CarModel::class;

    public function definition(): array
    {
        return [
            'brand_id'   => Brand::factory(),
            'name'       => fake()->word(),
            'model_year' => fake()->numberBetween(2000, 2025),
        ];
    }
}
```

- [ ] **Step 3: Add `HasFactory` trait to `Brand`**

Modify `app/Models/Brand.php`. Add the `HasFactory` import and `use HasFactory;` inside the class:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function carModels(): HasMany
    {
        return $this->hasMany(CarModel::class);
    }

    public function serviceCenters(): HasMany
    {
        return $this->hasMany(ServiceCenter::class);
    }
}
```

- [ ] **Step 4: Add `HasFactory` trait to `CarModel`**

Modify `app/Models/CarModel.php`. Add the import and `use HasFactory;`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CarModel extends Model
{
    use HasFactory;

    protected $fillable = ['brand_id', 'name', 'model_year'];

    protected function casts(): array
    {
        return [
            'model_year' => 'integer',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function cars(): HasMany
    {
        return $this->hasMany(Car::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }
}
```

- [ ] **Step 5: Smoke-test the factories**

Run: `php artisan tinker --execute="App\Models\Brand::factory()->create(); App\Models\CarModel::factory()->create(); echo 'ok';"`

Expected: prints `ok` with no errors.

(If the local DB doesn't have the migrations applied, run `php artisan migrate` first. This is a smoke check only — actual usage is in PHPUnit with `RefreshDatabase` and in-memory SQLite.)

- [ ] **Step 6: Commit**

```bash
git add database/factories/BrandFactory.php database/factories/CarModelFactory.php app/Models/Brand.php app/Models/CarModel.php
git commit -m "test: add Brand and CarModel factories with HasFactory traits"
```

---

## Task 2: Failing test for `GET /api/brands/{brand}/car-model-names`

Write the test first; it should fail because the route doesn't exist yet.

**Files:**
- Create: `tests/Feature/CarModelNamesTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/CarModelNamesTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CarModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CarModelNamesTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_distinct_names_sorted_ascending(): void
    {
        $brand = Brand::factory()->create();
        CarModel::factory()->for($brand)->create(['name' => 'Corolla', 'model_year' => 2020]);
        CarModel::factory()->for($brand)->create(['name' => 'Corolla', 'model_year' => 2021]);
        CarModel::factory()->for($brand)->create(['name' => 'Camry',   'model_year' => 2022]);
        CarModel::factory()->for($brand)->create(['name' => 'Yaris',   'model_year' => 2019]);

        $response = $this->getJson("/api/brands/{$brand->id}/car-model-names");

        $response->assertOk()
            ->assertExactJson([
                'data' => [
                    ['name' => 'Camry'],
                    ['name' => 'Corolla'],
                    ['name' => 'Yaris'],
                ],
            ]);
    }

    public function test_excludes_models_with_null_model_year(): void
    {
        $brand = Brand::factory()->create();
        CarModel::factory()->for($brand)->create(['name' => 'HasYear',  'model_year' => 2020]);
        CarModel::factory()->for($brand)->create(['name' => 'NoYear',   'model_year' => null]);

        $response = $this->getJson("/api/brands/{$brand->id}/car-model-names");

        $response->assertOk()
            ->assertExactJson(['data' => [['name' => 'HasYear']]]);
    }

    public function test_returns_empty_array_when_brand_has_no_models(): void
    {
        $brand = Brand::factory()->create();

        $response = $this->getJson("/api/brands/{$brand->id}/car-model-names");

        $response->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_returns_404_when_brand_does_not_exist(): void
    {
        $response = $this->getJson('/api/brands/99999/car-model-names');

        $response->assertNotFound();
    }

    public function test_does_not_leak_models_from_other_brands(): void
    {
        $brandA = Brand::factory()->create();
        $brandB = Brand::factory()->create();
        CarModel::factory()->for($brandA)->create(['name' => 'OnlyA', 'model_year' => 2020]);
        CarModel::factory()->for($brandB)->create(['name' => 'OnlyB', 'model_year' => 2021]);

        $response = $this->getJson("/api/brands/{$brandA->id}/car-model-names");

        $response->assertOk()->assertExactJson(['data' => [['name' => 'OnlyA']]]);
    }

    public function test_route_is_publicly_accessible(): void
    {
        $brand = Brand::factory()->create();

        $response = $this->getJson("/api/brands/{$brand->id}/car-model-names");

        $this->assertNotEquals(401, $response->status());
        $this->assertNotEquals(403, $response->status());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CarModelNamesTest`

Expected: All six tests FAIL. The exact failure mode is `404 Not Found` (route doesn't exist) or similar — that's the signal we have a route to add.

- [ ] **Step 3: Commit the failing test**

```bash
git add tests/Feature/CarModelNamesTest.php
git commit -m "test: add failing tests for car-model-names endpoint"
```

---

## Task 3: Implement `GET /api/brands/{brand}/car-model-names`

Add the route and the controller method. Tests should turn green.

**Files:**
- Modify: `routes/api.php`
- Modify: `app/Http/Controllers/CarModelController.php`

- [ ] **Step 1: Register the public route**

Modify `routes/api.php`. The existing public lookup section is around line 31-33:

```php
// Public lookup routes (used during registration)
Route::get('brands', [BrandController::class, 'index']);
Route::get('brands/{brand}/car-models', [CarModelController::class, 'index']);
```

Add a third line so the section becomes:

```php
// Public lookup routes (used during registration)
Route::get('brands', [BrandController::class, 'index']);
Route::get('brands/{brand}/car-models', [CarModelController::class, 'index']);
Route::get('brands/{brand}/car-model-names', [CarModelController::class, 'names']);
```

- [ ] **Step 2: Add the `names()` method on `CarModelController`**

Modify `app/Http/Controllers/CarModelController.php`. Add the new method after `index()` and before `store()`:

```php
public function names(Brand $brand): JsonResponse
{
    $names = $brand->carModels()
        ->whereNotNull('model_year')
        ->orderBy('name')
        ->distinct()
        ->pluck('name')
        ->map(fn (string $name) => ['name' => $name])
        ->values();

    return $this->success(['data' => $names]);
}
```

The `pluck('name')` with `distinct()` returns a `Collection<string>`; mapping each to `['name' => $value]` matches the response shape locked in the spec. `values()` re-indexes the collection to a JSON array (not an object) — important so the serialized output is `[{...}]` not `{"0": {...}}`.

- [ ] **Step 3: Run the names tests — expect green**

Run: `php artisan test --filter=CarModelNamesTest`

Expected: All six tests PASS.

- [ ] **Step 4: Commit**

```bash
git add routes/api.php app/Http/Controllers/CarModelController.php
git commit -m "feat: add public GET /api/brands/{brand}/car-model-names endpoint"
```

---

## Task 4: Failing tests for the `?name=` filter on `GET /api/brands/{brand}/car-models`

Add a focused test file for the new filter behavior. The "no-filter" tests inside this file double as a regression guard for existing clients.

**Files:**
- Create: `tests/Feature/CarModelIndexFilterTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/CarModelIndexFilterTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CarModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CarModelIndexFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_name_filter_returns_only_matching_rows_sorted_by_year_desc(): void
    {
        $brand = Brand::factory()->create();
        CarModel::factory()->for($brand)->create(['name' => 'Corolla', 'model_year' => 2022]);
        CarModel::factory()->for($brand)->create(['name' => 'Corolla', 'model_year' => 2024]);
        CarModel::factory()->for($brand)->create(['name' => 'Corolla', 'model_year' => 2023]);
        CarModel::factory()->for($brand)->create(['name' => 'Camry',   'model_year' => 2024]);

        $response = $this->getJson("/api/brands/{$brand->id}/car-models?name=Corolla");

        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(3, $data);
        $this->assertSame([2024, 2023, 2022], array_column($data, 'model_year'));
        foreach ($data as $row) {
            $this->assertSame('Corolla', $row['name']);
        }
    }

    public function test_name_filter_response_has_no_pagination_meta(): void
    {
        $brand = Brand::factory()->create();
        CarModel::factory()->for($brand)->create(['name' => 'Corolla', 'model_year' => 2020]);

        $response = $this->getJson("/api/brands/{$brand->id}/car-models?name=Corolla");

        $response->assertOk()->assertJsonMissingPath('meta');
    }

    public function test_empty_name_filter_falls_back_to_paginated_default(): void
    {
        $brand = Brand::factory()->create();
        CarModel::factory()->for($brand)->create(['name' => 'Corolla', 'model_year' => 2020]);

        $response = $this->getJson("/api/brands/{$brand->id}/car-models?name=");

        $response->assertOk()->assertJsonStructure(['data', 'meta' => ['current_page', 'per_page', 'total', 'last_page']]);
    }

    public function test_no_filter_returns_paginated_default(): void
    {
        $brand = Brand::factory()->create();
        CarModel::factory()->for($brand)->create(['name' => 'Corolla', 'model_year' => 2020]);

        $response = $this->getJson("/api/brands/{$brand->id}/car-models");

        $response->assertOk()->assertJsonStructure(['data', 'meta' => ['current_page', 'per_page', 'total', 'last_page']]);
    }

    public function test_name_filter_with_no_match_returns_empty_data(): void
    {
        $brand = Brand::factory()->create();
        CarModel::factory()->for($brand)->create(['name' => 'Corolla', 'model_year' => 2020]);

        $response = $this->getJson("/api/brands/{$brand->id}/car-models?name=DoesNotExist");

        $response->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_name_filter_excludes_rows_with_null_model_year(): void
    {
        $brand = Brand::factory()->create();
        CarModel::factory()->for($brand)->create(['name' => 'Corolla', 'model_year' => 2020]);
        CarModel::factory()->for($brand)->create(['name' => 'Corolla', 'model_year' => null]);

        $response = $this->getJson("/api/brands/{$brand->id}/car-models?name=Corolla");

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame(2020, $data[0]['model_year']);
    }

    public function test_name_filter_excludes_models_from_other_brands(): void
    {
        $brandA = Brand::factory()->create();
        $brandB = Brand::factory()->create();
        CarModel::factory()->for($brandA)->create(['name' => 'Corolla', 'model_year' => 2020]);
        CarModel::factory()->for($brandB)->create(['name' => 'Corolla', 'model_year' => 2021]);

        $response = $this->getJson("/api/brands/{$brandA->id}/car-models?name=Corolla");

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame(2020, $data[0]['model_year']);
    }

    public function test_route_is_publicly_accessible(): void
    {
        $brand = Brand::factory()->create();

        $response = $this->getJson("/api/brands/{$brand->id}/car-models?name=Corolla");

        $this->assertNotEquals(401, $response->status());
        $this->assertNotEquals(403, $response->status());
    }
}
```

- [ ] **Step 2: Run the tests — expect failures on filter-specific cases**

Run: `php artisan test --filter=CarModelIndexFilterTest`

Expected behavior:
- `test_no_filter_returns_paginated_default` should already **PASS** (current code already does this).
- `test_route_is_publicly_accessible` should already **PASS**.
- All other tests should **FAIL** because the current `index()` ignores `?name=`.

If `test_no_filter_returns_paginated_default` fails, stop and investigate — the no-filter path should not have changed.

- [ ] **Step 3: Commit the failing tests**

```bash
git add tests/Feature/CarModelIndexFilterTest.php
git commit -m "test: add failing tests for car-models ?name= filter"
```

---

## Task 5: Implement the `?name=` filter on `CarModelController::index`

Extend `index()` so that when a non-empty `name` query string is present, the response switches to unpaginated, `model_year DESC`, name-filtered, non-null-year mode.

**Files:**
- Modify: `app/Http/Controllers/CarModelController.php`

- [ ] **Step 1: Update the `index()` method**

Modify `app/Http/Controllers/CarModelController.php`. Replace the existing `index()` method with:

```php
public function index(Brand $brand, \Illuminate\Http\Request $request): JsonResponse
{
    $name = $request->query('name');

    if (is_string($name) && $name !== '') {
        $models = $brand->carModels()
            ->where('name', $name)
            ->whereNotNull('model_year')
            ->orderByDesc('model_year')
            ->get();

        return $this->success(['data' => CarModelResource::collection($models)]);
    }

    $models = $brand->carModels()->orderBy('name')->paginate();

    return $this->paginated($models, CarModelResource::class);
}
```

Notes:
- Use `$request->query('name')` (reads from query string only — never the body).
- The `is_string($name) && $name !== ''` guard makes the empty string and array-style inputs (`?name[]=foo`) fall through to the paginated default — a hostile or malformed client never accidentally hits the unpaginated path.
- Returns `['data' => ...]` without `meta`, exactly matching the spec.

- [ ] **Step 2: Run the filter tests — expect green**

Run: `php artisan test --filter=CarModelIndexFilterTest`

Expected: All eight tests PASS.

- [ ] **Step 3: Run the names tests too — sanity check no regression**

Run: `php artisan test --filter=CarModelNamesTest`

Expected: All six tests still PASS.

- [ ] **Step 4: Run the full feature test suite**

Run: `php artisan test --testsuite=Feature`

Expected: All tests PASS (the only feature tests should be `ExampleTest`, `CarModelNamesTest`, `CarModelIndexFilterTest`).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/CarModelController.php
git commit -m "feat: support ?name= filter on car-models index, unpaginated and year-desc"
```

---

## Task 6: Manual smoke test against running server (optional but recommended)

Verify the new endpoints behave correctly against a real local server with seeded data.

- [ ] **Step 1: Start the dev server**

Run (in a separate terminal): `php artisan serve`

Expected: Server listening on `http://127.0.0.1:8000`.

- [ ] **Step 2: Seed at least one brand with multiple model-year rows**

If a seeder doesn't already exist, run via tinker:

```bash
php artisan tinker --execute="
\$b = App\Models\Brand::firstOrCreate(['name' => 'Toyota']);
App\Models\CarModel::firstOrCreate(['brand_id' => \$b->id, 'name' => 'Corolla', 'model_year' => 2022]);
App\Models\CarModel::firstOrCreate(['brand_id' => \$b->id, 'name' => 'Corolla', 'model_year' => 2023]);
App\Models\CarModel::firstOrCreate(['brand_id' => \$b->id, 'name' => 'Corolla', 'model_year' => 2024]);
App\Models\CarModel::firstOrCreate(['brand_id' => \$b->id, 'name' => 'Camry',   'model_year' => 2024]);
echo \$b->id;
"
```

Note the brand id printed.

- [ ] **Step 3: Hit the new names endpoint**

Run (replace `{brand_id}`): `curl -s http://127.0.0.1:8000/api/brands/{brand_id}/car-model-names | jq`

Expected: `{ "data": [ {"name": "Camry"}, {"name": "Corolla"} ] }`

- [ ] **Step 4: Hit the filtered car-models endpoint**

Run: `curl -s "http://127.0.0.1:8000/api/brands/{brand_id}/car-models?name=Corolla" | jq`

Expected: three Corolla rows, ordered 2024 → 2023 → 2022, **no `meta` key**.

- [ ] **Step 5: Hit the unfiltered car-models endpoint**

Run: `curl -s "http://127.0.0.1:8000/api/brands/{brand_id}/car-models" | jq`

Expected: paginated response with `data` and `meta` — regression check for existing clients.

- [ ] **Step 6: No commit needed** — this task is verification only.

---

## Self-Review (already performed; recorded here for transparency)

1. **Spec coverage:**
   - Step 1 (`GET /api/brands`) — unchanged, no task needed. ✓
   - Step 2 (`car-model-names`) — Tasks 2 + 3. ✓
   - Step 3 (`?name=` filter) — Tasks 4 + 5. ✓
   - Ordering rules (`name ASC` on names, `model_year DESC` on filtered list) — covered in Task 3 step 2 and Task 5 step 1. ✓
   - `whereNotNull('model_year')` on both code paths — covered in Task 3 step 2 and Task 5 step 1. ✓
   - All edge cases from spec table — covered in Tasks 2 and 4 test files. ✓
   - No DB schema / register endpoint / admin-route changes — none of the tasks touch those files. ✓

2. **Placeholder scan:** no TBDs / TODOs / "add error handling" / "similar to" references. Each step has runnable code or commands.

3. **Type / signature consistency:**
   - `names(Brand $brand)` defined in Task 3 — used nowhere else.
   - `index(Brand $brand, Request $request)` — new signature in Task 5; the route declaration in `routes/api.php` doesn't bind `$request` explicitly (Laravel injects it via the container), so adding it is backward-compatible.
   - `CarModelResource::collection(...)` — same class already used by the existing endpoint; same shape.
   - Response key `data` — used identically in both the new endpoint (Task 3) and the filtered index (Task 5).
