<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Car;
use App\Models\CarModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Car>
 */
class CarFactory extends Factory
{
    protected $model = Car::class;

    public function definition(): array
    {
        return [
            'user_id'              => User::factory(),
            'brand_id'             => Brand::factory(),
            'car_model_id'         => CarModel::factory(),
            'current_km'           => fake()->numberBetween(0, 250_000),
            'tank_size'            => fake()->randomFloat(2, 30, 80),
            'has_warranty'         => false,
            'warranty_limit_km'    => null,
            'warranty_expiry_date' => null,
        ];
    }

    public function withWarranty(): static
    {
        return $this->state(fn (array $attributes): array => [
            'has_warranty'         => true,
            'warranty_limit_km'    => 100_000,
            'warranty_expiry_date' => now()->addYears(2)->toDateString(),
        ]);
    }
}
