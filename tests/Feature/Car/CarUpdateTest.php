<?php

namespace Tests\Feature\Car;

use App\Events\OdometerAdvanced;
use App\Models\Car;
use App\Models\FillUp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Gap B1 (plus F8, F9) — until this feature a car was written once, at
 * registration, and could never be corrected.
 */
class CarUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function ownedCar(array $attributes = []): Car
    {
        $car = Car::factory()->create($attributes);
        Sanctum::actingAs(User::find($car->user_id));

        return $car;
    }

    public function test_a_driver_can_correct_their_mileage(): void
    {
        $car = $this->ownedCar(['current_km' => 45_000]);

        $this->putJson("/api/cars/{$car->id}", ['current_km' => 47_200])
            ->assertOk()
            ->assertJsonPath('data.current_km', 47_200);

        $this->assertSame(47_200, $car->fresh()->current_km);
    }

    public function test_the_next_fill_up_uses_the_corrected_mileage(): void
    {
        $car = $this->ownedCar(['current_km' => 45_000]);

        $this->putJson("/api/cars/{$car->id}", ['current_km' => 47_200])->assertOk();

        $this->postJson("/api/cars/{$car->id}/fill-ups", [
            'liters'    => 40,
            'cost_egp'  => 1200,
            'fill_date' => now()->toDateString(),
        ])->assertCreated();

        $this->assertSame(47_200, FillUp::where('car_id', $car->id)->value('odometer'));
    }

    /**
     * Decision D3. A typo, or a replaced instrument cluster, both legitimately
     * read lower. Refusing would leave a driver stuck with a fat-fingered
     * reading and no way out from inside the app.
     */
    public function test_a_downward_mileage_correction_is_accepted(): void
    {
        $car = $this->ownedCar(['current_km' => 470_000]);

        $this->putJson("/api/cars/{$car->id}", ['current_km' => 47_000])
            ->assertOk()
            ->assertJsonPath('data.current_km', 47_000);

        $this->assertSame(47_000, $car->fresh()->current_km);
    }

    /**
     * D3's other half: records already filed keep the figures they were filed
     * with. Nothing recalculates history.
     */
    public function test_a_correction_does_not_rewrite_records_already_filed(): void
    {
        $car = $this->ownedCar(['current_km' => 50_000]);

        $this->postJson("/api/cars/{$car->id}/fill-ups", [
            'liters'    => 40,
            'cost_egp'  => 1200,
            'fill_date' => now()->toDateString(),
        ])->assertCreated();

        $this->putJson("/api/cars/{$car->id}", ['current_km' => 20_000])->assertOk();

        $this->assertSame(
            50_000,
            FillUp::where('car_id', $car->id)->value('odometer'),
            'The already-filed fill-up keeps the reading it was filed with.'
        );
    }

    public function test_advancing_the_odometer_fires_the_event_that_checks_due_services(): void
    {
        Event::fake([OdometerAdvanced::class]);

        $car = $this->ownedCar(['current_km' => 45_000]);

        $this->putJson("/api/cars/{$car->id}", ['current_km' => 47_200])->assertOk();

        Event::assertDispatched(OdometerAdvanced::class);
    }

    /**
     * A downward correction has not passed any service threshold. Firing there
     * would push a "service due" notification for distance never travelled.
     */
    public function test_a_downward_correction_does_not_fire_the_odometer_event(): void
    {
        Event::fake([OdometerAdvanced::class]);

        $car = $this->ownedCar(['current_km' => 470_000]);

        $this->putJson("/api/cars/{$car->id}", ['current_km' => 47_000])->assertOk();

        Event::assertNotDispatched(OdometerAdvanced::class);
    }

    public function test_a_driver_can_update_an_extended_warranty(): void
    {
        $car = $this->ownedCar(['has_warranty' => false]);

        $this->putJson("/api/cars/{$car->id}", [
            'has_warranty'         => true,
            'warranty_limit_km'    => 150_000,
            'warranty_expiry_date' => '2029-06-01',
        ])
            ->assertOk()
            ->assertJsonPath('data.has_warranty', true)
            ->assertJsonPath('data.warranty_limit_km', 150_000)
            ->assertJsonPath('data.warranty_expiry_date', '2029-06-01');
    }

    public function test_a_driver_can_set_their_cars_colour(): void
    {
        $car = $this->ownedCar();

        $this->putJson("/api/cars/{$car->id}", ['color' => 'silver'])
            ->assertOk()
            ->assertJsonPath('data.color', 'silver');
    }

    public function test_a_driver_can_record_what_the_car_cost_and_when(): void
    {
        $car = $this->ownedCar();

        $this->putJson("/api/cars/{$car->id}", [
            'purchase_price_egp' => 850000.00,
            'purchased_at'       => '2024-03-15',
        ])
            ->assertOk()
            ->assertJsonPath('data.purchased_at', '2024-03-15');
    }

    public function test_a_future_purchase_date_is_rejected(): void
    {
        $car = $this->ownedCar();

        $this->putJson("/api/cars/{$car->id}", [
            'purchased_at' => now()->addYear()->toDateString(),
        ])->assertStatus(422);
    }

    public function test_reading_a_car_returns_it_wrapped_in_data(): void
    {
        $car = $this->ownedCar(['current_km' => 12_345]);

        $this->getJson("/api/cars/{$car->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $car->id)
            ->assertJsonPath('data.current_km', 12_345);
    }
}
