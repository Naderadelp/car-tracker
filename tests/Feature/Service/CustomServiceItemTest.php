<?php

namespace Tests\Feature\Service;

use App\Models\Car;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Gap F3 — drivers add their own lines to an interval, a label and a price
 * each, and those extras persist.
 *
 * The pivot had no price column and `items.name` is globally unique on an
 * admin-managed catalogue, so before this there was nowhere to put them.
 */
class CustomServiceItemTest extends TestCase
{
    use RefreshDatabase;

    private Car $car;

    protected function setUp(): void
    {
        parent::setUp();

        $this->car = Car::factory()->create(['current_km' => 50_000]);
        Sanctum::actingAs(User::find($this->car->user_id));
    }

    public function test_a_driver_can_add_their_own_line_with_its_own_price(): void
    {
        $response = $this->postJson("/api/cars/{$this->car->id}/services", [
            'km'    => 70_000,
            'price' => 2000,
            'items' => [
                ['name' => 'Cabin filter', 'price' => 450],
            ],
        ])->assertCreated();

        $this->assertSame('Cabin filter', $response->json('data.items.0.name'));
        $this->assertSame('450.00', $response->json('data.items.0.price'));
        $this->assertTrue($response->json('data.items.0.is_custom'));
    }

    /**
     * The catalogue is admin-managed and its names are globally unique — one
     * driver's "Cabin filter" must not become everyone's.
     */
    public function test_a_custom_line_does_not_create_a_catalogue_entry(): void
    {
        $this->postJson("/api/cars/{$this->car->id}/services", [
            'km'    => 70_000,
            'price' => 2000,
            'items' => [['name' => 'Cabin filter', 'price' => 450]],
        ])->assertCreated();

        $this->assertDatabaseMissing('items', ['name' => 'Cabin filter']);
    }

    public function test_a_custom_line_persists_across_reads(): void
    {
        $serviceId = $this->postJson("/api/cars/{$this->car->id}/services", [
            'km'    => 70_000,
            'price' => 2000,
            'items' => [['name' => 'Cabin filter', 'price' => 450]],
        ])->assertCreated()->json('data.id');

        $data = $this->getJson("/api/cars/{$this->car->id}/upcoming-services")
            ->assertOk()
            ->json('data');

        $interval = collect($data)->firstWhere('id', $serviceId);

        $this->assertSame('Cabin filter', $interval['items'][0]['name']);
    }

    public function test_a_catalogue_line_can_be_linked_by_id(): void
    {
        $item = Item::create(['name' => 'Oil filter', 'price' => 350]);

        $response = $this->postJson("/api/cars/{$this->car->id}/services", [
            'km'    => 70_000,
            'price' => 2000,
            'items' => [['item_id' => $item->id]],
        ])->assertCreated();

        $this->assertSame('Oil filter', $response->json('data.items.0.name'));
        $this->assertSame('350.00', $response->json('data.items.0.price'));
        $this->assertFalse($response->json('data.items.0.is_custom'));
    }

    /**
     * Override-then-catalogue: a driver who paid a different price for the same
     * part records what they actually paid.
     */
    public function test_a_price_override_beats_the_catalogue_price(): void
    {
        $item = Item::create(['name' => 'Oil filter', 'price' => 350]);

        $response = $this->postJson("/api/cars/{$this->car->id}/services", [
            'km'    => 70_000,
            'price' => 2000,
            'items' => [['item_id' => $item->id, 'price' => 500]],
        ])->assertCreated();

        $this->assertSame('Oil filter', $response->json('data.items.0.name'));
        $this->assertSame('500.00', $response->json('data.items.0.price'));
    }

    public function test_a_line_with_neither_a_name_nor_an_item_is_rejected(): void
    {
        $this->postJson("/api/cars/{$this->car->id}/services", [
            'km'    => 70_000,
            'price' => 2000,
            'items' => [['price' => 450]],
        ])->assertStatus(422);
    }

    public function test_an_interval_can_be_created_with_no_lines_at_all(): void
    {
        $this->postJson("/api/cars/{$this->car->id}/services", [
            'km'    => 70_000,
            'price' => 2000,
        ])->assertCreated()->assertJsonCount(0, 'data.items');
    }
}
