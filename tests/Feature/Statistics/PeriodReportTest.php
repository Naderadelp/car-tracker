<?php

namespace Tests\Feature\Statistics;

use App\Models\Car;
use App\Models\CarLog;
use App\Models\Cost;
use App\Models\FillUp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Gap A1 — the monthly report.
 *
 * `GET /home` gave a fixed 7-day window and `fill-ups.statistics` was all-time
 * and fuel-only, so the app had to page through the entire history on every
 * screen open.
 */
class PeriodReportTest extends TestCase
{
    use RefreshDatabase;

    private Car $car;

    protected function setUp(): void
    {
        parent::setUp();

        $this->car = Car::factory()->create(['current_km' => 50_000]);
        Sanctum::actingAs(User::find($this->car->user_id));
    }

    private function fuel(float $cost, float $liters, ?string $on = null): void
    {
        FillUp::create([
            'car_id'    => $this->car->id,
            'liters'    => $liters,
            'odometer'  => 50_000,
            'cost_egp'  => $cost,
            'fill_date' => $on ?? now()->toDateString(),
        ]);
    }

    private function report(string $query = '?period=month'): array
    {
        return $this->getJson("/api/cars/{$this->car->id}/statistics{$query}")
            ->assertOk()
            ->json('data');
    }

    public function test_the_report_returns_spend_split_by_source(): void
    {
        $this->fuel(1200, 40);

        CarLog::create([
            'car_id'              => $this->car->id,
            'odometer_at_service' => 50_000,
            'actual_cost'         => 3500,
            'performed_at'        => now()->toDateString(),
        ]);

        Cost::create([
            'car_id'     => $this->car->id,
            'user_id'    => $this->car->user_id,
            'spent_at'   => now()->toDateString(),
            'title'      => 'Insurance',
            'amount_egp' => 12500,
            'category'   => 'insurance',
        ]);

        $spend = $this->report()['current']['spend'];

        $this->assertSame('1200.00',  $spend['fuel']);
        $this->assertSame('3500.00',  $spend['service']);
        $this->assertSame('12500.00', $spend['other']);
        $this->assertSame('17200.00', $spend['total']);
    }

    /**
     * Decision D2 puts fuel and maintenance into the same ledger table, so a
     * naive SUM over `costs` on top of the two source tables would count every
     * one of them twice.
     */
    public function test_carried_across_ledger_entries_are_not_counted_twice(): void
    {
        $this->fuel(1200, 40);

        $spend = $this->report()['current']['spend'];

        $this->assertSame('1200.00', $spend['fuel']);
        $this->assertSame('0.00',    $spend['other']);
        $this->assertSame('1200.00', $spend['total']);
    }

    public function test_the_report_returns_fill_up_count_and_average_fuel_price(): void
    {
        $this->fuel(1200, 40);
        $this->fuel(900, 30);

        $current = $this->report()['current'];

        $this->assertSame(2, $current['fill_up_count']);
        $this->assertSame('30.00', $current['average_fuel_price_per_liter']);
    }

    /**
     * "No fuel bought" is not "fuel was free" — zero would be a wrong number
     * rather than a missing one.
     */
    public function test_average_fuel_price_is_null_when_no_fuel_was_bought(): void
    {
        $this->assertNull($this->report()['current']['average_fuel_price_per_liter']);
    }

    public function test_cost_per_km_is_null_when_no_distance_was_travelled(): void
    {
        $this->fuel(1200, 40);

        $this->assertNull($this->report()['current']['cost_per_km']);
    }

    /** FR-032 */
    public function test_the_report_includes_weekly_buckets(): void
    {
        $this->fuel(1200, 40);

        $weekly = $this->report()['weekly'];

        $this->assertNotEmpty($weekly);
        $this->assertArrayHasKey('from', $weekly[0]);
        $this->assertArrayHasKey('fuel', $weekly[0]);
        $this->assertArrayHasKey('service', $weekly[0]);
        $this->assertArrayHasKey('distance_km', $weekly[0]);
    }

    public function test_the_previous_period_is_returned_on_request(): void
    {
        $this->car->forceFill(['created_at' => now()->subMonths(6)])->save();

        $this->fuel(900, 30, now()->subMonth()->startOfMonth()->addDays(2)->toDateString());
        $this->fuel(1200, 40);

        $report = $this->report('?period=month&compare=previous');

        $this->assertSame('1200.00', $report['current']['spend']['fuel']);
        $this->assertSame('900.00',  $report['previous']['spend']['fuel']);
    }

    /**
     * A driver in their first month has no previous period. Returning a row of
     * zeroes would read as "you spent nothing last month" rather than "there
     * was no last month".
     */
    public function test_the_first_month_has_no_previous_period_to_compare(): void
    {
        $this->car->forceFill(['created_at' => now()])->save();

        $report = $this->report('?period=month&compare=previous');

        $this->assertArrayHasKey('previous', $report);
        $this->assertNull($report['previous']);
    }

    public function test_the_report_is_wrapped_in_data(): void
    {
        $this->getJson("/api/cars/{$this->car->id}/statistics")
            ->assertOk()
            ->assertJsonStructure(['data' => ['period', 'current', 'weekly']]);
    }
}
