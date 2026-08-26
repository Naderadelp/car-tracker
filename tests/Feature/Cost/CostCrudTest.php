<?php

namespace Tests\Feature\Cost;

use App\Models\Car;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Gap B4 — FR-014 through FR-017.
 */
class CostCrudTest extends TestCase
{
    use RefreshDatabase;

    private Car $car;

    protected function setUp(): void
    {
        parent::setUp();

        $this->car = Car::factory()->create();
        Sanctum::actingAs(User::find($this->car->user_id));
    }

    private function record(array $overrides = []): int
    {
        return $this->postJson("/api/cars/{$this->car->id}/costs", array_merge([
            'spent_at'   => now()->toDateString(),
            'title'      => 'Annual insurance',
            'amount_egp' => 12500.00,
            'category'   => 'insurance',
        ], $overrides))->assertCreated()->json('data.id');
    }

    public function test_a_driver_can_record_a_cost(): void
    {
        $this->postJson("/api/cars/{$this->car->id}/costs", [
            'spent_at'   => now()->toDateString(),
            'title'      => 'Annual insurance',
            'amount_egp' => 12500.00,
            'category'   => 'insurance',
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Annual insurance')
            ->assertJsonPath('data.category', 'insurance')
            ->assertJsonPath('data.source', null)
            ->assertJsonPath('data.amount_overridden', false);
    }

    public function test_the_ledger_returns_a_total_and_a_share_per_category(): void
    {
        $this->record(['category' => 'insurance', 'amount_egp' => 12500.00]);
        $this->record(['category' => 'tyres',     'amount_egp' => 4000.00, 'title' => 'Front tyres']);
        $this->record(['category' => 'tyres',     'amount_egp' => 1000.00, 'title' => 'Balancing']);

        $totals = $this->getJson("/api/cars/{$this->car->id}/costs")->assertOk()->json('totals');

        $this->assertSame('17500.00', $totals['total_egp']);
        $this->assertSame('12500.00', $totals['by_category']['insurance']);
        $this->assertSame('5000.00',  $totals['by_category']['tyres']);
    }

    public function test_a_driver_can_correct_an_entry(): void
    {
        $id = $this->record();

        $this->putJson("/api/cars/{$this->car->id}/costs/{$id}", ['amount_egp' => 9000.00])
            ->assertOk()
            ->assertJsonPath('data.amount_egp', '9000.00');
    }

    /**
     * A manual entry has no source, so correcting it must not set the override
     * flag — that flag only means "the driver overruled a carried-across
     * figure".
     */
    public function test_correcting_a_manual_entry_does_not_mark_it_overridden(): void
    {
        $id = $this->record();

        $this->putJson("/api/cars/{$this->car->id}/costs/{$id}", ['amount_egp' => 9000.00])
            ->assertOk()
            ->assertJsonPath('data.amount_overridden', false);
    }

    public function test_a_driver_can_remove_an_entry(): void
    {
        $id = $this->record();

        $this->deleteJson("/api/cars/{$this->car->id}/costs/{$id}")->assertOk();

        $this->assertDatabaseMissing('costs', ['id' => $id]);
    }

    public function test_all_six_categories_the_app_uses_are_accepted(): void
    {
        foreach (['fuel', 'service', 'insurance', 'tyres', 'warranty', 'other'] as $category) {
            $this->postJson("/api/cars/{$this->car->id}/costs", [
                'spent_at'   => now()->toDateString(),
                'title'      => ucfirst($category),
                'amount_egp' => 100,
                'category'   => $category,
            ])->assertCreated();
        }
    }

    public function test_an_unknown_category_is_rejected(): void
    {
        $this->postJson("/api/cars/{$this->car->id}/costs", [
            'spent_at'   => now()->toDateString(),
            'title'      => 'Parking',
            'amount_egp' => 50,
            'category'   => 'parking',
        ])->assertStatus(422);
    }

    public function test_the_ledger_is_wrapped_with_data_meta_and_totals(): void
    {
        $this->record();

        $this->getJson("/api/cars/{$this->car->id}/costs")
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'meta'   => ['current_page', 'per_page', 'total', 'last_page'],
                'totals' => ['total_egp', 'by_category'],
            ]);
    }
}
