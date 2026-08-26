<?php

namespace Database\Factories;

use App\Models\Car;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        return [
            'user_id'     => User::factory(),
            'car_id'      => Car::factory(),
            'type'        => fake()->randomElement(Document::TYPES),
            'expiry_date' => now()->addMonths(6)->toDateString(),
        ];
    }

    /**
     * A document with no expiry date at all — these sort last.
     */
    public function neverExpires(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expiry_date' => null,
        ]);
    }

    /**
     * An already-expired document. Valid to store per FR-007: an expired
     * licence is precisely the record a driver most needs to keep.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expiry_date' => now()->subMonth()->toDateString(),
        ]);
    }
}
