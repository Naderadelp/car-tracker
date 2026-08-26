<?php

namespace Tests\Feature\Contract;

use App\Models\Brand;
use App\Models\Car;
use App\Models\CarModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Gaps C1, C2 and C4.
 *
 * BaseController::success() used to hand its payload straight to
 * response()->json(). A JsonResource serialises through jsonSerialize() on that
 * path, which skips the `data` wrapper Laravel's own toResponse() adds — so a
 * single resource came back flat, a resource collection came back as a bare
 * array, and paginated() wrapped by hand. Three shapes for one API.
 *
 * These tests pin the envelope so the next controller anyone writes cannot
 * quietly reintroduce a fourth.
 */
class ResponseEnvelopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_paginated_collections_are_wrapped_in_data_and_meta(): void
    {
        Brand::factory()->count(3)->create();

        $this->getJson('/api/brands')
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);
    }

    public function test_unpaginated_collections_are_wrapped_in_data(): void
    {
        $brand = Brand::factory()->create();
        CarModel::factory()->for($brand)->create(['name' => 'Corolla', 'model_year' => 2020]);

        $response = $this->getJson("/api/brands/{$brand->id}/car-model-names")->assertOk();

        $this->assertArrayHasKey('data', $response->json());
        $this->assertIsArray($response->json('data'));
    }

    public function test_a_single_resource_is_wrapped_in_data(): void
    {
        $car = Car::factory()->create();
        Sanctum::actingAs(User::find($car->user_id));

        $response = $this->getJson('/api/auth/user')->assertOk();

        $this->assertArrayHasKey('data', $response->json());
        $this->assertArrayHasKey('user', $response->json('data'));
    }

    /**
     * Gap C4 — a cold app launch used to need two requests to recover the
     * driver and their car. GET /auth/user now mirrors PUT /auth/user.
     */
    public function test_reading_the_signed_in_driver_includes_their_car(): void
    {
        $car = Car::factory()->create();
        Sanctum::actingAs(User::find($car->user_id));

        $this->getJson('/api/auth/user')
            ->assertOk()
            ->assertJsonPath('data.car.id', $car->id);
    }

    public function test_a_driver_with_no_car_gets_a_null_car_rather_than_a_missing_key(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/auth/user')->assertOk();

        $this->assertArrayHasKey('car', $response->json('data'));
        $this->assertNull($response->json('data.car'));
    }

    public function test_a_message_is_carried_in_the_body_when_one_is_given(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // success() used to discard its $message argument entirely, so every
        // endpoint that passed one sent nothing.
        $this->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out successfully.');
    }
}
