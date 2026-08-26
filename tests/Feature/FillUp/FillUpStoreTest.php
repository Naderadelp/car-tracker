<?php

namespace Tests\Feature\FillUp;

use App\Models\Car;
use App\Models\FillUp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Gap B3 — the fuel form collects a station name, a pump reading and a fuel
 * grade. The service accepted none of them, and hardcoded the odometer to
 * cars.current_km, so a driver who filled up before logging their mileage got
 * the wrong reading on the record permanently.
 */
class FillUpStoreTest extends TestCase
{
    use RefreshDatabase;

    private function ownedCar(array $attributes = []): Car
    {
        $car = Car::factory()->create($attributes);
        Sanctum::actingAs(User::find($car->user_id));

        return $car;
    }

    public function test_station_name_pump_reading_and_grade_are_all_stored(): void
    {
        $car = $this->ownedCar(['current_km' => 40_000]);

        $this->postJson("/api/cars/{$car->id}/fill-ups", [
            'liters'       => 42.5,
            'cost_egp'     => 1275.00,
            'fill_date'    => now()->toDateString(),
            'odometer'     => 41_230,
            'station_name' => 'Wataniya Ring Road',
            'fuel_type'    => '95',
        ])
            ->assertCreated()
            ->assertJsonPath('data.odometer', 41_230)
            ->assertJsonPath('data.station_name', 'Wataniya Ring Road')
            ->assertJsonPath('data.fuel_type', '95');
    }

    /**
     * FR-010 — the fallback the app relies on when the driver does not read the
     * pump.
     */
    public function test_a_fill_up_without_a_pump_reading_falls_back_to_current_mileage(): void
    {
        $car = $this->ownedCar(['current_km' => 40_000]);

        $this->postJson("/api/cars/{$car->id}/fill-ups", [
            'liters'    => 40,
            'cost_egp'  => 1200,
            'fill_date' => now()->toDateString(),
        ])->assertCreated();

        $this->assertSame(40_000, FillUp::where('car_id', $car->id)->value('odometer'));
    }

    public function test_the_pump_reading_wins_over_the_cars_current_mileage(): void
    {
        $car = $this->ownedCar(['current_km' => 40_000]);

        $this->postJson("/api/cars/{$car->id}/fill-ups", [
            'liters'    => 40,
            'cost_egp'  => 1200,
            'fill_date' => now()->toDateString(),
            'odometer'  => 40_850,
        ])->assertCreated();

        $this->assertSame(40_850, FillUp::where('car_id', $car->id)->value('odometer'));
    }

    public function test_an_unknown_fuel_grade_is_rejected(): void
    {
        $car = $this->ownedCar();

        $this->postJson("/api/cars/{$car->id}/fill-ups", [
            'liters'    => 40,
            'cost_egp'  => 1200,
            'fill_date' => now()->toDateString(),
            'fuel_type' => 'diesel',
        ])->assertStatus(422);
    }

    /**
     * Gap C1 — the two routes produce the same resource, so one decoder must
     * handle both. quick() used to hand-wrap {message, data}.
     */
    public function test_both_fill_up_routes_return_the_same_shape(): void
    {
        $car = $this->ownedCar(['current_km' => 40_000]);

        $store = $this->postJson("/api/cars/{$car->id}/fill-ups", [
            'liters'    => 40,
            'cost_egp'  => 1200,
            'fill_date' => now()->toDateString(),
        ])->assertCreated();

        $quick = $this->postJson("/api/cars/{$car->id}/fill-ups/quick", [
            'fuel_type'   => '92',
            'amount_paid' => 900,
        ])->assertCreated();

        $this->assertSame(
            array_keys($store->json('data')),
            array_keys($quick->json('data')),
            'One decoder must handle a fill-up from either route.'
        );
    }
}
