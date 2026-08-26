<?php

namespace Tests\Feature\Document;

use App\Models\Car;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Gap B2 — the add-document sheet collects a type and an expiry date and
 * nothing else, and the service refused both halves of that: a file was
 * required, and a past expiry date was rejected by `after:today`.
 */
class DocumentStoreTest extends TestCase
{
    use RefreshDatabase;

    private function ownedCar(): Car
    {
        $car = Car::factory()->create();
        Sanctum::actingAs(User::find($car->user_id));

        return $car;
    }

    public function test_a_document_saves_with_no_file_attached(): void
    {
        $car = $this->ownedCar();

        $this->postJson("/api/cars/{$car->id}/documents", [
            'type'        => 'vehicle_license',
            'expiry_date' => now()->addYear()->toDateString(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'vehicle_license')
            ->assertJsonPath('data.has_file', false);
    }

    public function test_a_document_that_expired_last_month_is_accepted_and_reported_expired(): void
    {
        $car = $this->ownedCar();

        $this->postJson("/api/cars/{$car->id}/documents", [
            'type'        => 'driver_license',
            'expiry_date' => now()->subMonth()->toDateString(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'expired');
    }

    public function test_a_future_expiry_date_is_reported_valid(): void
    {
        $car = $this->ownedCar();

        $this->postJson("/api/cars/{$car->id}/documents", [
            'type'        => 'insurance_policy',
            'expiry_date' => now()->addMonths(3)->toDateString(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'valid');
    }

    public function test_a_document_with_no_expiry_date_is_distinguished_from_a_valid_one(): void
    {
        $car = $this->ownedCar();

        $this->postJson("/api/cars/{$car->id}/documents", [
            'type' => 'registration',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'no_expiry');
    }

    /**
     * FR-008 — the driver adds the scan later, from the detail view.
     */
    public function test_a_file_can_be_attached_to_a_document_created_without_one(): void
    {
        Storage::fake('local');

        $car = $this->ownedCar();

        $documentId = $this->postJson("/api/cars/{$car->id}/documents", [
            'type' => 'vehicle_license',
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/cars/{$car->id}/documents/{$documentId}", [])
            ->assertOk();

        // Multipart, because a file cannot ride on a JSON body.
        $this->put(
            "/api/cars/{$car->id}/documents/{$documentId}",
            ['document_file' => UploadedFile::fake()->create('licence.pdf', 100, 'application/pdf')],
            ['Accept' => 'application/json']
        )->assertOk();

        $this->assertTrue(
            Document::find($documentId)->getMedia('vehicle_documents')->isNotEmpty()
        );
    }

    /**
     * Correcting a past date to a future one has to move the record back out of
     * the expired state — the spec names this as an edge case. It works because
     * `status` is derived on read rather than stored.
     */
    public function test_correcting_an_expired_date_returns_the_document_to_valid(): void
    {
        $car = $this->ownedCar();

        $documentId = $this->postJson("/api/cars/{$car->id}/documents", [
            'type'        => 'driver_license',
            'expiry_date' => now()->subMonth()->toDateString(),
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/cars/{$car->id}/documents/{$documentId}", [
            'expiry_date' => now()->addYear()->toDateString(),
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'valid');
    }

    public function test_an_oversized_file_is_still_rejected(): void
    {
        $car = $this->ownedCar();

        $this->post(
            "/api/cars/{$car->id}/documents",
            [
                'type'          => 'vehicle_license',
                'document_file' => UploadedFile::fake()->create('huge.pdf', 6000, 'application/pdf'),
            ],
            ['Accept' => 'application/json']
        )->assertStatus(422);
    }

    public function test_an_unknown_document_type_is_rejected(): void
    {
        $car = $this->ownedCar();

        $this->postJson("/api/cars/{$car->id}/documents", ['type' => 'not_a_real_type'])
            ->assertStatus(422);
    }
}
