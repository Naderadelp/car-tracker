<?php

namespace Tests\Feature\Contract;

use App\Models\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gap C3 — the `errors` field used to flip JSON type.
 *
 * A validation failure produced an object (`{"email": ["..."]}`); a controller
 * error produced an empty array (`[]`), because that is how PHP encodes an
 * empty array. No single typed error model on the client could decode both
 * without a union.
 *
 * Assertions here read the raw response body rather than the decoded array,
 * because json_decode turns `{}` and `[]` into the same PHP value — decoding
 * would hide the exact bug being tested.
 */
class ErrorShapeTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_empty_errors_field_serialises_as_an_object_not_an_array(): void
    {
        $brand = Brand::factory()->create();

        // Hits BaseController::error() directly — the `name` query parameter
        // is required by CarModelController::years().
        $response = $this->getJson("/api/brands/{$brand->id}/car-model-years");

        $response->assertStatus(422);

        $this->assertStringContainsString('"errors":{}', $response->getContent());
        $this->assertStringNotContainsString('"errors":[]', $response->getContent());
    }

    public function test_a_validation_failure_carries_errors_as_an_object(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);

        $this->assertStringContainsString('"errors":{', $response->getContent());
    }

    public function test_a_not_found_response_uses_the_same_envelope(): void
    {
        $response = $this->getJson('/api/brands/99999/car-models');

        $response->assertNotFound()
            ->assertJsonStructure(['message', 'errors']);

        $this->assertStringContainsString('"errors":{}', $response->getContent());
    }

    public function test_an_unauthenticated_request_uses_the_same_envelope(): void
    {
        $response = $this->getJson('/api/auth/user');

        $response->assertUnauthorized()
            ->assertJsonStructure(['message', 'errors']);

        $this->assertStringContainsString('"errors":{}', $response->getContent());
    }

    public function test_every_failure_mode_exposes_the_same_two_keys(): void
    {
        $brand = Brand::factory()->create();

        $responses = [
            'controller error'  => $this->getJson("/api/brands/{$brand->id}/car-model-years"),
            'validation'        => $this->postJson('/api/auth/login', []),
            'not found'         => $this->getJson('/api/brands/99999/car-models'),
            'unauthenticated'   => $this->getJson('/api/auth/user'),
        ];

        foreach ($responses as $label => $response) {
            $decoded = $response->json();

            $this->assertSame(
                ['errors', 'message'],
                collect(array_keys($decoded))->sort()->values()->all(),
                "One error model must decode the '{$label}' case."
            );
        }
    }
}
