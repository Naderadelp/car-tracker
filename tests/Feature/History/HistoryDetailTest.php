<?php

namespace Tests\Feature\History;

use App\Models\Car;
use App\Models\ParkingRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Gaps F4, F5 and F7 — three history screens that collected data and then threw
 * it away. Every day this went unfixed produced history that can never be
 * reconstructed.
 */
class HistoryDetailTest extends TestCase
{
    use RefreshDatabase;

    private Car $car;

    protected function setUp(): void
    {
        parent::setUp();

        $this->car = Car::factory()->create(['current_km' => 50_000]);
        Sanctum::actingAs(User::find($this->car->user_id));
    }

    /**
     * Gap F4 — ad-hoc work is the case that broke: with service_id null there
     * was no description anywhere, so the row was a bare number.
     */
    public function test_ad_hoc_work_records_what_was_done_and_who_did_it(): void
    {
        $this->postJson("/api/cars/{$this->car->id}/logs", [
            'service_id'          => null,
            'title'               => 'Brake pads',
            'workshop'            => 'El Nasr Auto',
            'category'            => 'service',
            'notes'               => 'Fronts only; rears still have life.',
            'odometer_at_service' => 50_000,
            'actual_cost'         => 2400,
            'performed_at'        => now()->toDateString(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Brake pads')
            ->assertJsonPath('data.workshop', 'El Nasr Auto')
            ->assertJsonPath('data.category', 'service')
            ->assertJsonPath('data.notes', 'Fronts only; rears still have life.');
    }

    public function test_a_maintenance_entry_still_saves_without_the_new_detail(): void
    {
        $this->postJson("/api/cars/{$this->car->id}/logs", [
            'odometer_at_service' => 50_000,
            'actual_cost'         => 2400,
            'performed_at'        => now()->toDateString(),
        ])->assertCreated();
    }

    /** Gap F5 */
    public function test_a_trip_keeps_its_timings_and_top_speed(): void
    {
        $startedAt = now()->subHour();
        $endedAt   = now()->subMinutes(20);

        $this->postJson("/api/cars/{$this->car->id}/trips", [
            'coordinates' => [
                ['lat' => 30.0444, 'lng' => 31.2357],
                ['lat' => 30.0561, 'lng' => 31.2394],
            ],
            'started_at'       => $startedAt->toISOString(),
            'ended_at'         => $endedAt->toISOString(),
            'duration_seconds' => 2400,
            'max_speed_kmh'    => 96.5,
        ])
            ->assertCreated()
            ->assertJsonPath('data.duration_seconds', 2400)
            ->assertJsonPath('data.max_speed_kmh', '96.50');
    }

    public function test_a_trip_still_saves_with_coordinates_alone(): void
    {
        $this->postJson("/api/cars/{$this->car->id}/trips", [
            'coordinates' => [
                ['lat' => 30.0444, 'lng' => 31.2357],
                ['lat' => 30.0561, 'lng' => 31.2394],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.duration_seconds', null);
    }

    public function test_a_trip_ending_before_it_started_is_rejected(): void
    {
        $this->postJson("/api/cars/{$this->car->id}/trips", [
            'coordinates' => [
                ['lat' => 30.0444, 'lng' => 31.2357],
                ['lat' => 30.0561, 'lng' => 31.2394],
            ],
            'started_at' => now()->toISOString(),
            'ended_at'   => now()->subHour()->toISOString(),
        ])->assertStatus(422);
    }

    /** Gap F7 — label, address and note are three distinct strings. */
    public function test_a_parking_record_keeps_its_address(): void
    {
        $this->postJson("/api/cars/{$this->car->id}/parking-records", [
            'name'        => 'Office garage',
            'address'     => '12 Gameat El Dowal El Arabeya, Mohandessin, Giza',
            'description' => 'Level B2, near the lift',
            'latitude'    => 30.0561,
            'longitude'   => 31.2394,
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Office garage')
            ->assertJsonPath('data.address', '12 Gameat El Dowal El Arabeya, Mohandessin, Giza')
            ->assertJsonPath('data.description', 'Level B2, near the lift');
    }

    /** Gap F7 — the resource was create/delete only. */
    public function test_a_parking_record_can_be_corrected_in_place(): void
    {
        $id = $this->postJson("/api/cars/{$this->car->id}/parking-records", [
            'name'    => 'Ofice garage',
            'address' => 'Wrong address',
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/cars/{$this->car->id}/parking-records/{$id}", [
            'name'    => 'Office garage',
            'address' => '12 Gameat El Dowal El Arabeya, Mohandessin, Giza',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Office garage')
            ->assertJsonPath('data.address', '12 Gameat El Dowal El Arabeya, Mohandessin, Giza');
    }

    /**
     * Seeded because ParkingRecordPolicy falls back to
     * hasPermissionTo('edit-parking-record') for a non-owner, and spatie
     * *throws* PermissionDoesNotExist when the row is missing — a 500, not a
     * 403. That is the real failure mode on any environment where
     * `sync:permissions` has not been run.
     */
    public function test_a_driver_cannot_correct_another_drivers_parking_record(): void
    {
        $this->seed(\Database\Seeders\RolePermissionsSeeder::class);

        $theirCar = Car::factory()->create();
        $record   = ParkingRecord::create([
            'car_id'    => $theirCar->id,
            'name'      => 'Their spot',
            'parked_at' => now(),
        ]);

        $this->putJson("/api/cars/{$theirCar->id}/parking-records/{$record->id}", [
            'name' => 'Hijacked',
        ])->assertForbidden();

        $this->assertSame('Their spot', $record->fresh()->name);
    }
}
