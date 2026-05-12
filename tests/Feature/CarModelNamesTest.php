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
