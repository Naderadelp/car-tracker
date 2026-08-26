<?php

namespace Tests\Feature\FillUp;

use App\Models\Car;
use App\Models\FillUp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Gap A2 — the fuel chart plots km/L per fill-up. Only one all-time average
 * existed, so the per-record series had to be reconstructed client-side from
 * the whole history.
 */
class FillUpEfficiencyTest extends TestCase
{
    use RefreshDatabase;

    private Car $car;

    protected function setUp(): void
    {
        parent::setUp();

        $this->car = Car::factory()->create(['current_km' => 0, 'tank_size' => 50]);
        Sanctum::actingAs(User::find($this->car->user_id));
    }

    private function fill(int $odometer, float $liters): FillUp
    {
        return FillUp::create([
            'car_id'    => $this->car->id,
            'liters'    => $liters,
            'odometer'  => $odometer,
            'cost_egp'  => $liters * 30,
            'fill_date' => now()->toDateString(),
        ]);
    }

    private function records(): array
    {
        return $this->getJson("/api/cars/{$this->car->id}/fill-ups")->assertOk()->json('data');
    }

    public function test_each_fill_up_carries_its_own_efficiency(): void
    {
        $this->fill(10_000, 40);
        $this->fill(10_400, 40);   // 400 km on 40 L = 10 km/L

        $records = collect($this->records())->keyBy('odometer');

        // assertEquals rather than assertSame: json_encode writes 10.0 as
        // `10`, which decodes to an int.
        $this->assertEquals(10.0, $records[10_400]['km_per_liter']);
    }

    /**
     * The first fill-up has no distance preceding it, so its efficiency is
     * undefined. Reporting zero would plot a false point at the origin.
     */
    public function test_the_first_fill_up_has_no_efficiency_figure(): void
    {
        $this->fill(10_000, 40);

        $this->assertNull($this->records()[0]['km_per_liter']);
    }

    public function test_a_single_fill_up_leaves_the_figure_undefined_rather_than_wrong(): void
    {
        $this->fill(10_000, 40);

        $records = $this->records();

        $this->assertCount(1, $records);
        $this->assertNull($records[0]['km_per_liter']);
    }

    /**
     * Decision D3 allows a downward odometer correction, which can leave a
     * negative distance between two records. A negative km/L is worse than no
     * figure at all.
     */
    public function test_a_backwards_odometer_yields_no_figure_rather_than_a_negative_one(): void
    {
        $this->fill(10_000, 40);
        $this->fill(9_000, 40);

        foreach ($this->records() as $record) {
            $this->assertNotNull(
                $record['km_per_liter'] === null || $record['km_per_liter'] > 0 ? true : null,
                'Efficiency must never be reported as negative.'
            );
        }
    }

    public function test_the_series_is_returned_alongside_the_all_time_statistics(): void
    {
        $this->fill(10_000, 40);
        $this->fill(10_400, 40);

        $this->getJson("/api/cars/{$this->car->id}/fill-ups")
            ->assertOk()
            ->assertJsonStructure([
                'data'  => [['km_per_liter']],
                'meta',
                'statistics' => ['average_consumption'],
            ]);
    }
}
