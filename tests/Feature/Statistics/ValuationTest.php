<?php

namespace Tests\Feature\Statistics;

use App\Models\Car;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Gap A3 under decision D1 — the value is derived from purchase price, age and
 * mileage, with no external market-data provider.
 */
class ValuationTest extends TestCase
{
    use RefreshDatabase;

    private function car(array $attributes = []): Car
    {
        $car = Car::factory()->create($attributes);
        Sanctum::actingAs(User::find($car->user_id));

        return $car;
    }

    public function test_a_car_with_purchase_details_returns_an_estimated_value(): void
    {
        $car = $this->car([
            'purchase_price_egp' => 850000,
            'purchased_at'       => now()->subYears(2)->toDateString(),
            'current_km'         => 40_000,
        ]);

        $response = $this->getJson("/api/cars/{$car->id}/valuation")->assertOk();

        $this->assertSame('850000.00', $response->json('data.purchase_price_egp'));
        $this->assertNotNull($response->json('data.estimated_value_egp'));
        $this->assertLessThan(850000, (float) $response->json('data.estimated_value_egp'));
    }

    /**
     * The response must not let the client present this as a market appraisal —
     * that is the whole point of decision D1.
     */
    public function test_the_response_declares_itself_an_estimate(): void
    {
        $car = $this->car([
            'purchase_price_egp' => 850000,
            'purchased_at'       => now()->subYear()->toDateString(),
        ]);

        $this->getJson("/api/cars/{$car->id}/valuation")
            ->assertOk()
            ->assertJsonPath('data.basis', 'estimate')
            ->assertJsonPath('data.note', 'Derived from purchase price, age and mileage. Not a market appraisal.');
    }

    public function test_a_car_with_no_purchase_details_says_so_rather_than_guessing(): void
    {
        $car = $this->car(['purchase_price_egp' => null, 'purchased_at' => null]);

        $this->getJson("/api/cars/{$car->id}/valuation")
            ->assertOk()
            ->assertJsonPath('data.basis', 'unavailable')
            ->assertJsonPath('data.estimated_value_egp', null);
    }

    /**
     * Depreciation is pro-rated continuously rather than stepped at each
     * anniversary, so a car bought this morning is already a few hours old and
     * worth a hair under what was paid. What matters is that the driver is not
     * shown a visible loss on day one: the percentage they see rounds to zero.
     */
    public function test_a_brand_new_car_shows_no_visible_depreciation(): void
    {
        $car = $this->car([
            'purchase_price_egp' => 850000,
            'purchased_at'       => now()->toDateString(),
            'current_km'         => 0,
        ]);

        $response = $this->getJson("/api/cars/{$car->id}/valuation")->assertOk();

        $this->assertEquals(0.0, $response->json('data.depreciation_percent'));
        $this->assertEqualsWithDelta(
            850000,
            (float) $response->json('data.estimated_value_egp'),
            1000,
        );
    }

    public function test_higher_mileage_reduces_the_estimate(): void
    {
        $lowKm = $this->car([
            'purchase_price_egp' => 850000,
            'purchased_at'       => now()->subYears(3)->toDateString(),
            'current_km'         => 30_000,
        ]);
        $lowValue = (float) $this->getJson("/api/cars/{$lowKm->id}/valuation")->json('data.estimated_value_egp');

        $highKm = $this->car([
            'purchase_price_egp' => 850000,
            'purchased_at'       => now()->subYears(3)->toDateString(),
            'current_km'         => 250_000,
        ]);
        $highValue = (float) $this->getJson("/api/cars/{$highKm->id}/valuation")->json('data.estimated_value_egp');

        $this->assertLessThan($lowValue, $highValue);
    }

    /**
     * Without a floor, a very old or very high-mileage car would eventually be
     * valued at zero or below, which is never true of a running car.
     */
    public function test_the_estimate_never_falls_below_the_residual_floor(): void
    {
        $car = $this->car([
            'purchase_price_egp' => 850000,
            'purchased_at'       => now()->subYears(25)->toDateString(),
            'current_km'         => 900_000,
        ]);

        $value = (float) $this->getJson("/api/cars/{$car->id}/valuation")->json('data.estimated_value_egp');

        $this->assertGreaterThanOrEqual(850000 * 0.15, $value);
    }
}
