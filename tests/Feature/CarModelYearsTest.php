<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CarModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CarModelYearsTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_distinct_years_sorted_descending(): void
    {
        $brand = Brand::factory()->create();
        CarModel::factory()->for($brand)->create(['name' => 'Corolla', 'model_year' => 2022]);
        CarModel::factory()->for($brand)->create(['name' => 'Corolla', 'model_year' => 2024]);
        CarModel::factory()->for($brand)->create(['name' => 'Corolla', 'model_year' => 2024]);
        CarModel::factory()->for($brand)->create(['name' => 'Corolla', 'model_year' => 2023]);

        $response = $this->getJson("/api/brands/{$brand->id}/car-model-years?name=Corolla");

        $response->assertOk()
            ->assertExactJson([
                'data' => [
                    ['year' => 2024],
                    ['year' => 2023],
                    ['year' => 2022],
                ],
            ]);
    }

    public function test_excludes_models_with_null_model_year(): void
    {
        $brand = Brand::factory()->create();
        CarModel::factory()->for($brand)->create(['name' => 'Corolla', 'model_year' => 2020]);
        CarModel::factory()->for($brand)->create(['name' => 'Corolla', 'model_year' => null]);

        $response = $this->getJson("/api/brands/{$brand->id}/car-model-years?name=Corolla");

        $response->assertOk()->assertExactJson(['data' => [['year' => 2020]]]);
    }

    public function test_returns_empty_array_when_no_models_match_name(): void
    {
        $brand = Brand::factory()->create();
        CarModel::factory()->for($brand)->create(['name' => 'Corolla', 'model_year' => 2020]);

        $response = $this->getJson("/api/brands/{$brand->id}/car-model-years?name=DoesNotExist");

        $response->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_returns_422_when_name_is_missing(): void
    {
        $brand = Brand::factory()->create();

        $response = $this->getJson("/api/brands/{$brand->id}/car-model-years");

        $response->assertStatus(422);
    }

    public function test_returns_422_when_name_is_empty(): void
    {
        $brand = Brand::factory()->create();

        $response = $this->getJson("/api/brands/{$brand->id}/car-model-years?name=");

        $response->assertStatus(422);
    }

    public function test_returns_404_when_brand_does_not_exist(): void
    {
        $response = $this->getJson('/api/brands/99999/car-model-years?name=Corolla');

        $response->assertNotFound();
    }

    public function test_does_not_leak_years_from_other_brands(): void
    {
        $brandA = Brand::factory()->create();
        $brandB = Brand::factory()->create();
        CarModel::factory()->for($brandA)->create(['name' => 'Corolla', 'model_year' => 2020]);
        CarModel::factory()->for($brandB)->create(['name' => 'Corolla', 'model_year' => 2021]);

        $response = $this->getJson("/api/brands/{$brandA->id}/car-model-years?name=Corolla");

        $response->assertOk()->assertExactJson(['data' => [['year' => 2020]]]);
    }

    public function test_does_not_leak_years_from_other_model_names(): void
    {
        $brand = Brand::factory()->create();
        CarModel::factory()->for($brand)->create(['name' => 'Corolla', 'model_year' => 2020]);
        CarModel::factory()->for($brand)->create(['name' => 'Camry',   'model_year' => 2024]);

        $response = $this->getJson("/api/brands/{$brand->id}/car-model-years?name=Corolla");

        $response->assertOk()->assertExactJson(['data' => [['year' => 2020]]]);
    }

    public function test_route_is_publicly_accessible(): void
    {
        $brand = Brand::factory()->create();

        $response = $this->getJson("/api/brands/{$brand->id}/car-model-years?name=Corolla");

        $this->assertNotEquals(401, $response->status());
        $this->assertNotEquals(403, $response->status());
    }
}
