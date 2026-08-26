<?php

namespace Tests\Feature\Service;

use App\Models\Car;
use App\Models\Item;
use App\Models\Service;
use App\Models\ServiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Gaps F1 and F2 — the Services tab.
 *
 * upcomingForCar() filtered to `km > current_km`, so everything already passed
 * was unreachable, and returned `withCount('items')`, so the checklist that
 * *is* the tab needed a second paginated request.
 */
class ServiceScheduleTest extends TestCase
{
    use RefreshDatabase;

    private Car $car;

    protected function setUp(): void
    {
        parent::setUp();

        $this->car = Car::factory()->create(['current_km' => 50_000]);
        Sanctum::actingAs(User::find($this->car->user_id));
    }

    private function interval(int $km): Service
    {
        return Service::create([
            'car_model_id' => $this->car->car_model_id,
            'km'           => $km,
            'price'        => 1500,
        ]);
    }

    public function test_only_upcoming_intervals_are_returned_by_default(): void
    {
        $this->interval(30_000);
        $this->interval(60_000);

        $data = $this->getJson("/api/cars/{$this->car->id}/upcoming-services")
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $data);
        $this->assertSame(60_000, $data[0]['km']);
    }

    /** Gap F2 */
    public function test_the_whole_schedule_is_returned_on_request(): void
    {
        $this->interval(30_000);
        $this->interval(60_000);
        $this->interval(90_000);

        $data = $this->getJson("/api/cars/{$this->car->id}/upcoming-services?include_past=1")
            ->assertOk()
            ->json('data');

        $this->assertCount(3, $data);
        $this->assertSame([30_000, 60_000, 90_000], array_column($data, 'km'));
    }

    public function test_passed_and_upcoming_intervals_are_distinguishable(): void
    {
        $this->interval(30_000);
        $this->interval(60_000);

        $data = $this->getJson("/api/cars/{$this->car->id}/upcoming-services?include_past=1")
            ->assertOk()
            ->json('data');

        $this->assertSame('passed',   $data[0]['schedule_status']);
        $this->assertSame('upcoming', $data[1]['schedule_status']);
    }

    /** Gap F1 — the checklist arrives with the schedule, not behind it. */
    public function test_each_interval_carries_its_checklist(): void
    {
        $service = $this->interval(60_000);
        $item    = Item::create(['name' => 'Oil filter', 'price' => 350]);

        ServiceItem::create([
            'service_id' => $service->id,
            'item_id'    => $item->id,
            'car_id'     => $this->car->id,
        ]);

        $data = $this->getJson("/api/cars/{$this->car->id}/upcoming-services")
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $data[0]['items']);
        $this->assertSame('Oil filter', $data[0]['items'][0]['name']);
        $this->assertSame('350.00', $data[0]['items'][0]['price']);
    }

    public function test_the_collection_is_wrapped_like_every_other(): void
    {
        $this->interval(60_000);

        // Gap C2 — this used to return a bare array while every other
        // collection was wrapped, and it is the payload behind the busiest tab.
        $this->getJson("/api/cars/{$this->car->id}/upcoming-services")
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_an_empty_schedule_returns_an_empty_list(): void
    {
        $this->getJson("/api/cars/{$this->car->id}/upcoming-services")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
