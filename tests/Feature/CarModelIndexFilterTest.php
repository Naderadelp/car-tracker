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
