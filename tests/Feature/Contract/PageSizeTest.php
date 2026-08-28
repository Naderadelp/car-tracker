<?php

namespace Tests\Feature\Contract;

use App\Models\Car;
use App\Models\Cost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * `?per_page=` on paginated collections.
 *
 * Page size used to be fixed at 15, so a client that wanted a whole list had to
 * walk it a page at a time on every screen open — the reason the gap report
 * raised it alongside the missing aggregates.
 */
class PageSizeTest extends TestCase
{
    use RefreshDatabase;

    private function ledgerOf(int $rows): Car
    {
        $car = Car::factory()->create();

        for ($i = 0; $i < $rows; $i++) {
            Cost::create([
                'car_id'     => $car->id,
                'user_id'    => $car->user_id,
                'spent_at'   => now()->subDays($i)->toDateString(),
                'title'      => "Entry {$i}",
                'amount_egp' => 100.00,
                'category'   => 'other',
            ]);
        }

        Sanctum::actingAs(User::find($car->user_id));

        return $car;
    }

    public function test_page_size_defaults_to_fifteen(): void
    {
        $car = $this->ledgerOf(20);

        $response = $this->getJson("/api/cars/{$car->id}/costs")->assertOk();

        $this->assertCount(15, $response->json('data'));
        $this->assertSame(15, $response->json('meta.per_page'));
    }

    public function test_a_client_can_ask_for_a_larger_page(): void
    {
        $car = $this->ledgerOf(20);

        $response = $this->getJson("/api/cars/{$car->id}/costs?per_page=50")->assertOk();

        $this->assertCount(20, $response->json('data'));
        $this->assertSame(50, $response->json('meta.per_page'));
    }

    /**
     * Clamped, not rejected. A page size is a hint, and answering 422 would
     * fail a request that has a perfectly good answer.
     */
    public function test_an_oversized_page_is_clamped_to_the_ceiling(): void
    {
        $car = $this->ledgerOf(3);

        $response = $this->getJson("/api/cars/{$car->id}/costs?per_page=5000")->assertOk();

        $this->assertSame(100, $response->json('meta.per_page'));
    }

    public function test_a_nonsense_page_size_falls_back_to_the_default(): void
    {
        $car = $this->ledgerOf(3);

        foreach (['abc', '0', '-4', ''] as $value) {
            $response = $this->getJson("/api/cars/{$car->id}/costs?per_page={$value}")->assertOk();

            $this->assertSame(
                $value === '0' || $value === '-4' ? 1 : 15,
                $response->json('meta.per_page'),
                "per_page={$value}",
            );
        }
    }

    public function test_it_applies_to_other_collections_too(): void
    {
        $car = $this->ledgerOf(1);

        $this->getJson("/api/cars/{$car->id}/issues?per_page=42")
             ->assertOk()
             ->assertJsonPath('meta.per_page', 42);
    }
}
