<?php

namespace Tests\Feature\Document;

use App\Models\Car;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Regression cover for the ordering in DocumentRepositoryEloquent::spatie().
 *
 * It used to call orderByRaw('ISNULL(expiry_date) ASC, ...'). ISNULL() is
 * MySQL-only, so this endpoint threw on both PostgreSQL (production) and
 * sqlite (this suite). There was no test, so nothing caught it — the endpoint
 * was broken in production for weeks.
 */
class DocumentIndexTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsOwnerOf(Car $car): User
    {
        $owner = $car->user_id ? User::find($car->user_id) : User::factory()->create();
        Sanctum::actingAs($owner);

        return $owner;
    }

    public function test_index_returns_ok_and_does_not_throw_on_the_raw_ordering(): void
    {
        $car = Car::factory()->create();
        $this->actingAsOwnerOf($car);

        Document::factory()->for($car)->create(['user_id' => $car->user_id]);

        $this->getJson("/api/cars/{$car->id}/documents")->assertOk();
    }

    public function test_documents_without_an_expiry_date_are_listed_last(): void
    {
        $car = Car::factory()->create();
        $this->actingAsOwnerOf($car);

        Document::factory()->for($car)->neverExpires()->create([
            'user_id' => $car->user_id,
            'type'    => 'registration',
        ]);
        Document::factory()->for($car)->create([
            'user_id'     => $car->user_id,
            'type'        => 'insurance_policy',
            'expiry_date' => now()->addYears(3)->toDateString(),
        ]);
        Document::factory()->for($car)->create([
            'user_id'     => $car->user_id,
            'type'        => 'vehicle_license',
            'expiry_date' => now()->addMonth()->toDateString(),
        ]);

        $response = $this->getJson("/api/cars/{$car->id}/documents")->assertOk();

        $types = array_column($response->json('data'), 'type');

        $this->assertSame(
            ['vehicle_license', 'insurance_policy', 'registration'],
            $types,
            'Soonest expiring first, never-expiring last.'
        );
    }

    public function test_an_expired_document_is_still_listed(): void
    {
        $car = Car::factory()->create();
        $this->actingAsOwnerOf($car);

        Document::factory()->for($car)->expired()->create(['user_id' => $car->user_id]);

        $response = $this->getJson("/api/cars/{$car->id}/documents")->assertOk();

        $this->assertCount(1, $response->json('data'));
    }

    public function test_index_is_wrapped_with_data_and_meta(): void
    {
        $car = Car::factory()->create();
        $this->actingAsOwnerOf($car);

        $this->getJson("/api/cars/{$car->id}/documents")
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);
    }
}
