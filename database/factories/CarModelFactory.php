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
