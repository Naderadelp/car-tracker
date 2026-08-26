<?php

namespace Tests\Feature\Cost;

use App\Models\Car;
use App\Models\CarLog;
use App\Models\Cost;
use App\Models\FillUp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Decision D2 — the unified ledger.
 *
 * Fuel and maintenance spending carries across automatically, the driver may
 * overwrite a carried-across amount, and once they have, a later edit of the
 * source must not undo their correction.
 */
class CostCarryAcrossTest extends TestCase
{
    use RefreshDatabase;

    private Car $car;

    protected function setUp(): void
    {
        parent::setUp();

        $this->car = Car::factory()->create(['current_km' => 40_000]);
        Sanctum::actingAs(User::find($this->car->user_id));
    }

    private function fileFillUp(float $cost = 1200.00): FillUp
    {
        return FillUp::create([
            'car_id'       => $this->car->id,
            'liters'       => 40,
            'odometer'     => 40_000,
            'cost_egp'     => $cost,
            'fill_date'    => now()->toDateString(),
            'station_name' => 'Wataniya Ring Road',
        ]);
    }

    public function test_a_fill_up_appears_in_the_ledger_without_being_entered_twice(): void
    {
        $fillUp = $this->fileFillUp();

        $this->assertDatabaseHas('costs', [
            'source_type' => Cost::SOURCE_FILL_UP,
            'source_id'   => $fillUp->id,
            'category'    => 'fuel',
            'amount_egp'  => 1200.00,
        ]);
    }

    public function test_the_carried_across_entry_names_the_station(): void
    {
        $this->fileFillUp();

        $this->assertSame(
            'Fuel — Wataniya Ring Road',
            Cost::where('source_type', Cost::SOURCE_FILL_UP)->value('title')
        );
    }

    public function test_a_maintenance_entry_carries_across_as_service_spending(): void
    {
        $log = CarLog::create([
            'car_id'              => $this->car->id,
            'odometer_at_service' => 40_000,
            'actual_cost'         => 3500.00,
            'performed_at'        => now()->toDateString(),
        ]);

        $this->assertDatabaseHas('costs', [
            'source_type' => Cost::SOURCE_CAR_LOG,
            'source_id'   => $log->id,
            'category'    => 'service',
            'amount_egp'  => 3500.00,
        ]);
    }

    public function test_editing_the_source_updates_the_ledger_entry(): void
    {
        $fillUp = $this->fileFillUp();

        $fillUp->update(['cost_egp' => 1500.00]);

        $this->assertDatabaseHas('costs', [
            'source_id'  => $fillUp->id,
            'amount_egp' => 1500.00,
        ]);
    }

    /** FR-045 */
    public function test_overriding_a_carried_across_amount_marks_it_overridden(): void
    {
        $fillUp = $this->fileFillUp();
        $cost   = Cost::where('source_id', $fillUp->id)->first();

        $this->putJson("/api/cars/{$this->car->id}/costs/{$cost->id}", ['amount_egp' => 1350.00])
            ->assertOk()
            ->assertJsonPath('data.amount_overridden', true);

        $this->assertSame('1350.00', Cost::find($cost->id)->amount_egp);
    }

    /** FR-046 — the driver's correction survives a later source edit. */
    public function test_editing_the_source_does_not_undo_a_drivers_override(): void
    {
        $fillUp = $this->fileFillUp();
        $cost   = Cost::where('source_id', $fillUp->id)->first();

        $this->putJson("/api/cars/{$this->car->id}/costs/{$cost->id}", ['amount_egp' => 1350.00])
            ->assertOk();

        $fillUp->update(['cost_egp' => 9999.00]);

        $this->assertSame(
            '1350.00',
            Cost::find($cost->id)->amount_egp,
            "The driver's figure is the authority once they have corrected it."
        );
    }

    public function test_deleting_the_source_removes_its_ledger_entry(): void
    {
        $fillUp = $this->fileFillUp();

        $fillUp->delete();

        $this->assertDatabaseMissing('costs', [
            'source_type' => Cost::SOURCE_FILL_UP,
            'source_id'   => $fillUp->id,
        ]);
    }

    /**
     * The edge case named in the spec: an overridden figure is deliberate data
     * and outlives the record it came from, becoming an ordinary manual entry
     * rather than an orphan pointing at nothing.
     */
    public function test_deleting_the_source_of_an_overridden_entry_keeps_it_as_a_manual_entry(): void
    {
        $fillUp = $this->fileFillUp();
        $cost   = Cost::where('source_id', $fillUp->id)->first();

        $this->putJson("/api/cars/{$this->car->id}/costs/{$cost->id}", ['amount_egp' => 1350.00])
            ->assertOk();

        $fillUp->delete();

        $survivor = Cost::find($cost->id);

        $this->assertNotNull($survivor, 'The overridden row must survive.');
        $this->assertNull($survivor->source_type);
        $this->assertNull($survivor->source_id);
        $this->assertSame('1350.00', $survivor->amount_egp);
    }

    /**
     * The database backstop. Two ledger rows for one source record must be
     * impossible by construction, not by observer discipline.
     */
    public function test_a_source_record_can_never_produce_two_ledger_rows(): void
    {
        $fillUp = $this->fileFillUp();

        $this->expectException(\Illuminate\Database\QueryException::class);

        Cost::create([
            'car_id'      => $this->car->id,
            'user_id'     => $this->car->user_id,
            'spent_at'    => now()->toDateString(),
            'title'       => 'Duplicate',
            'amount_egp'  => 1,
            'category'    => 'fuel',
            'source_type' => Cost::SOURCE_FILL_UP,
            'source_id'   => $fillUp->id,
        ]);
    }

    /**
     * D2 accepted that a driver can double-count themselves; the system makes
     * it visible and fixable rather than impossible.
     */
    public function test_a_manual_duplicate_can_be_deleted_afterwards(): void
    {
        $this->fileFillUp();

        $manualId = $this->postJson("/api/cars/{$this->car->id}/costs", [
            'spent_at'   => now()->toDateString(),
            'title'      => 'Fuel (typed in by mistake)',
            'amount_egp' => 1200.00,
            'category'   => 'fuel',
        ])->assertCreated()->json('data.id');

        $this->assertSame('2400.00', $this->totals()['total_egp']);

        $this->deleteJson("/api/cars/{$this->car->id}/costs/{$manualId}")->assertOk();

        $this->assertSame('1200.00', $this->totals()['total_egp']);
    }

    private function totals(): array
    {
        return $this->getJson("/api/cars/{$this->car->id}/costs")->json('totals');
    }
}
